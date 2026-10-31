<?php

use App\Models\Appointment;
use App\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        if (!Schema::hasColumn('leads', 'categories')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('categories')->nullable()->after('category');
            });
        }

        $leads = Lead::with('appointment')->get();

        foreach ($leads as $lead) {
            $cats = is_array($lead->categories) ? $lead->categories : [];
            if ($lead->category) {
                $cats[] = $lead->category;
            }

            // Repair open leads whose linked appointment (or cancelled appointment history)
            // had its 'cancel' / 'no_show' category overwritten by a manual merge
            $isOpen = !in_array($lead->needful_done, Lead::CLOSED_STATUSES, true);
            if ($isOpen) {
                if ($lead->appointment) {
                    if ($lead->appointment->status === 'cancelled') {
                        $cats[] = 'cancel';
                    } elseif ($lead->appointment->status === 'no_show') {
                        $cats[] = 'no_show';
                    }
                } elseif ($lead->phone || $lead->customer_id) {
                    [$countryCode, $localNumber] = Lead::splitPhone($lead->phone);
                    $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);
                    $phoneCandidates = array_values(array_unique(array_filter([$normalizedPhone, $lead->phone])));

                    $recentMissedAppt = Appointment::whereIn('status', ['cancelled', 'no_show'])
                        ->where(function ($q) use ($lead, $phoneCandidates) {
                            if ($lead->customer_id) {
                                $q->where('customer_id', $lead->customer_id);
                            }
                            if (!empty($phoneCandidates)) {
                                $q->orWhereIn('phone', $phoneCandidates);
                            }
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($recentMissedAppt) {
                        $cats[] = $recentMissedAppt->status === 'cancelled' ? 'cancel' : 'no_show';
                    }
                }
            }

            $normalizedCats = Lead::normalizeCategories($cats);
            $primaryCategory = Lead::resolvePrimaryCategory($normalizedCats);

            $lead->update([
                'categories' => !empty($normalizedCats) ? $normalizedCats : null,
                'category' => $primaryCategory,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'categories')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('categories');
            });
        }
    }
};
