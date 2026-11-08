<?php

namespace App\Services\Ecommerce;

use App\Models\EcommerceAiSetting;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EcommerceWhatsappTransportService
{
    /**
     * Dispatch an outbound message via the configured WhatsApp driver
     * ('simulator', 'webhook', or 'meta_cloud') and update its delivery_status.
     */
    public function sendOutboundMessage(
        EcommerceWhatsappMessage $message,
        ?EcommerceAiSetting $settings = null
    ): EcommerceWhatsappMessage {
        $settings = $settings ?? EcommerceAiSetting::current();
        $message->message_body = EcommerceWhatsappMessage::toWhatsappNativeFormatting((string) $message->message_body);
        $thread = $message->relationLoaded('thread') ? $message->thread : $message->thread()->with('customer')->first();
        $customer = $thread?->relationLoaded('customer') ? $thread->customer : $thread?->customer()->first();

        $driver = strtolower((string) ($settings->whatsapp_driver ?: 'simulator'));
        $phone = EcommerceCustomer::normalizePhone($customer?->phone ?? '', $thread?->region === 'QA' ? '974' : '92');

        if ($driver === 'webhook') {
            $this->dispatchViaWebhook($message, $thread, $customer, $phone, $settings);
        } elseif ($driver === 'meta_cloud') {
            $this->sendViaMetaCloud($message, $phone, $thread);
        } else {
            // Simulator driver: mark as sent immediately for live in-CRM testing
            $message->delivery_status = 'sent';
            if (empty($message->external_message_id)) {
                $message->external_message_id = 'sim-' . uniqid();
            }
            $message->save();
        }

        if ($thread) {
            $thread->last_message_at = now();
            $thread->save();
        }

        return $message;
    }

    /**
     * Driver 2: n8n / Make Outbound Webhook Bridge
     */
    private function dispatchViaWebhook(
        EcommerceWhatsappMessage $message,
        ?EcommerceWhatsappThread $thread,
        ?EcommerceCustomer $customer,
        string $phone,
        EcommerceAiSetting $settings
    ): void {
        $url = trim((string) $settings->outbound_webhook_url);

        if ($url === '') {
            Log::warning('EcommerceWhatsappTransportService: webhook driver selected but outbound_webhook_url is empty; falling back to simulator status.');
            $message->delivery_status = 'sent';
            $message->external_message_id = 'webhook-sim-' . uniqid();
            $message->save();

            return;
        }

        try {
            $headers = [];
            $webhookToken = config('services.whatsapp.webhook_token');
            if (!empty($webhookToken)) {
                $headers['X-Ecommerce-Webhook-Token'] = (string) $webhookToken;
            }

            $response = Http::connectTimeout(5)->timeout(10)
                ->withHeaders($headers)
                ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                ->post($url, [
                    'thread_id' => $thread?->id,
                    'phone' => $phone,
                    'customer_name' => $customer?->name ?? 'Valued Customer',
                    'message_body' => $message->message_body,
                    'order_group_id' => $thread?->order_group_id,
                    'playbook' => $thread?->playbook,
                ]);

            if ($response->successful()) {
                $message->delivery_status = 'sent';
                $message->external_message_id = (string) ($response->json('message_id') ?? ('wh-' . uniqid()));
            } else {
                $message->delivery_status = 'failed';
                Log::warning('Ecommerce WhatsApp webhook delivery failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $message->delivery_status = 'failed';
            Log::warning('Ecommerce WhatsApp webhook exception.', [
                'error' => $e->getMessage(),
            ]);
        }

        $message->save();
    }

    /**
     * Driver 3: Official Meta WhatsApp Cloud API (v20.0)
     *
     * Checks if the thread has received an inbound customer message within the last 24 hours.
     * If yes, sends a standard `"type": "text"` payload; if outside the 24-hour window (initial Step 1 outreach)
     * and a Meta template name is configured, supports sending `"type": "template"` with automatic fallback to `"type": "text"`.
     */
    public function sendViaMetaCloud(
        EcommerceWhatsappMessage $message,
        string $phone,
        ?EcommerceWhatsappThread $thread = null
    ): void {
        $token = trim((string) config('services.whatsapp.token'));
        $phoneNumberId = trim((string) config('services.whatsapp.phone_number_id'));

        if ($token === '' || $phoneNumberId === '' || $phone === '') {
            Log::warning('EcommerceWhatsappTransportService: meta_cloud credentials or customer phone missing.');
            $message->delivery_status = 'failed';
            $message->save();

            return;
        }

        $thread = $thread ?? ($message->relationLoaded('thread') ? $message->thread : $message->thread()->with('customer')->first());
        $endpoint = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

        $hasInboundWithin24Hours = false;
        if ($thread) {
            if ($thread->relationLoaded('messages')) {
                $hasInboundWithin24Hours = $thread->messages->contains(function (EcommerceWhatsappMessage $m) {
                    return $m->direction === 'inbound'
                        && $m->created_at
                        && $m->created_at->greaterThanOrEqualTo(now()->subHours(24));
                });
            } else {
                $hasInboundWithin24Hours = $thread->messages()
                    ->where('direction', 'inbound')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->exists();
            }
        }

        $templateName = trim((string) (
            data_get($message->ai_metadata, 'meta_template_name')
            ?: config('services.whatsapp.template_name', '')
        ));
        $templateLang = trim((string) (
            data_get($message->ai_metadata, 'meta_template_language')
            ?: config('services.whatsapp.template_language', 'en_US')
        )) ?: 'en_US';

        try {
            // Outside 24-hour customer service window + template configured -> attempt template first
            if (!$hasInboundWithin24Hours && $templateName !== '') {
                $customerName = $thread?->customer?->name ?: 'Valued Customer';
                $templatePayload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $templateLang],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $customerName],
                                    ['type' => 'text', 'text' => mb_substr((string) $message->message_body, 0, 1024)],
                                ],
                            ],
                        ],
                    ],
                ];

                $templateResponse = Http::connectTimeout(5)->timeout(12)
                    ->withToken($token)
                    ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                    ->post($endpoint, $templatePayload);

                if ($templateResponse->successful()) {
                    $message->delivery_status = 'sent';
                    $message->external_message_id = (string) (data_get($templateResponse->json(), 'messages.0.id') ?: ('wamid.tpl.' . uniqid()));
                    $message->save();

                    return;
                }

                Log::warning('Meta Cloud WhatsApp template delivery failed; falling back to text payload.', [
                    'template' => $templateName,
                    'status' => $templateResponse->status(),
                    'body' => $templateResponse->body(),
                ]);
            }

            $response = Http::connectTimeout(5)->timeout(12)
                ->withToken($token)
                ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => str_contains((string) $message->message_body, 'http'),
                        'body' => $message->message_body,
                    ],
                ]);

            if ($response->successful()) {
                $message->delivery_status = 'sent';
                $message->external_message_id = (string) (data_get($response->json(), 'messages.0.id') ?: ('wamid.' . uniqid()));
            } else {
                $message->delivery_status = 'failed';
                Log::warning('Meta Cloud WhatsApp API delivery failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $message->delivery_status = 'failed';
            Log::warning('Meta Cloud WhatsApp API exception.', [
                'error' => $e->getMessage(),
            ]);
        }

        $message->save();
    }

    private function dispatchViaMetaCloud(EcommerceWhatsappMessage $message, string $phone): void
    {
        $this->sendViaMetaCloud($message, $phone);
    }
}
