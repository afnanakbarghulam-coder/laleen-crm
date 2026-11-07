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
            $this->dispatchViaMetaCloud($message, $phone);
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

            $response = Http::timeout(10)
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
     */
    private function dispatchViaMetaCloud(EcommerceWhatsappMessage $message, string $phone): void
    {
        $token = trim((string) config('services.whatsapp.token'));
        $phoneNumberId = trim((string) config('services.whatsapp.phone_number_id'));

        if ($token === '' || $phoneNumberId === '' || $phone === '') {
            Log::warning('EcommerceWhatsappTransportService: meta_cloud credentials or customer phone missing.');
            $message->delivery_status = 'failed';
            $message->save();

            return;
        }

        $endpoint = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";

        try {
            $response = Http::timeout(12)
                ->withToken($token)
                ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
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
}
