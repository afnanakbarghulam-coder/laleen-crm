<?php

namespace Tests\Unit\NovaAI;

use App\Models\Sale;
use App\Models\Staff;
use App\NovaAI\Models\NovaBusinessFact;
use App\NovaAI\Services\NovaBusinessFactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MigratesNovaMemory;
use Tests\TestCase;

/**
 * Correction A: NovaBusinessFactService::relevantFacts() must not dump
 * every active fact into every request. This is the realistic "many
 * unrelated memories" scenario the correction asked for - a dozen
 * plausible facts spanning most of the category enum, most of them
 * irrelevant to the actual question asked.
 */
class NovaBusinessFactRelevanceTest extends TestCase
{
    use RefreshDatabase;
    use MigratesNovaMemory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateNovaMemory();
    }

    private function service(): NovaBusinessFactService
    {
        return new NovaBusinessFactService();
    }

    private function seedTwelveUnrelatedFacts(): void
    {
        $facts = [
            ['discount_policy', 'Never discount combo packages below 10 percent off.', 'policy'],
            ['daily_booking_target', 'Daily booking target is 40 appointments across both branches.', 'target'],
            ['cleaning_responsibility', 'Closing staff are responsible for cleaning the styling stations each night.', 'operations'],
            ['hair_color_offer', 'Hair color services are 15 percent off every Wednesday.', 'pricing'],
            ['staff_upsell_rule', 'Stylists must offer a retail product upsell on every visit.', 'staffing'],
            ['opening_hours', 'The salon opens at 9am and closes at 9pm daily.', 'policy'],
            ['owner_preference', 'The owner prefers WhatsApp updates over phone calls.', 'preference'],
            ['marketing_rule', 'All social media posts must be approved by the owner before publishing.', 'marketing'],
            ['package_policy', 'Combo packages expire 60 days after purchase.', 'policy'],
            ['payroll_rule', 'Payroll is processed on the 1st of every month.', 'staffing'],
            ['branch_target', 'Al Wakrah branch has a monthly revenue target of 30000 QAR.', 'target'],
            ['wifi_password_rotation', 'The salon WiFi password is changed every quarter.', 'other'],
        ];

        foreach ($facts as [$key, $value, $category]) {
            $this->service()->recordFacts([['category' => $category, 'normalized_key' => $key, 'value' => $value]], 1, $value);
        }
    }

    public function test_relevant_discount_and_package_facts_surface_for_a_discount_question(): void
    {
        $this->seedTwelveUnrelatedFacts();

        $relevant = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);

        $values = array_column($relevant, 'value');
        $this->assertContains('Never discount combo packages below 10 percent off.', $values);
        $this->assertContains('Combo packages expire 60 days after purchase.', $values);
    }

    public function test_unrelated_payroll_and_cleaning_facts_do_not_flood_the_context(): void
    {
        $this->seedTwelveUnrelatedFacts();

        $relevant = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);

        $values = array_column($relevant, 'value');
        $this->assertNotContains('Payroll is processed on the 1st of every month.', $values);
        $this->assertNotContains('Closing staff are responsible for cleaning the styling stations each night.', $values);
        $this->assertNotContains('Al Wakrah branch has a monthly revenue target of 30000 QAR.', $values);
        $this->assertNotContains('Stylists must offer a retail product upsell on every visit.', $values);

        // Not all 12 facts flood in - only the genuinely relevant ones, plus
        // whichever broadly-applicable policy/preference facts carry a
        // baseline (opening hours, owner preference) - never the full set.
        $this->assertLessThan(12, count($relevant));
    }

    public function test_the_most_relevant_fact_ranks_above_a_merely_globally_applicable_one(): void
    {
        $this->seedTwelveUnrelatedFacts();

        $relevant = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);

        $this->assertNotEmpty($relevant);
        $this->assertSame('Never discount combo packages below 10 percent off.', $relevant[0]['value']);
    }

    public function test_hard_fact_count_cap_of_twelve(): void
    {
        // 20 facts that ALL score identically via keyword overlap with the
        // question, to isolate the cap itself from relevance selection.
        for ($i = 1; $i <= 20; $i++) {
            $this->service()->recordFacts([
                ['category' => 'other', 'normalized_key' => "widget_rule_{$i}", 'value' => "Widget rule number {$i} about combo pricing."],
            ], 1, 'msg');
        }

        $relevant = $this->service()->relevantFacts('Tell me about combo pricing.', 1);

        $this->assertLessThanOrEqual(12, count($relevant));
        $this->assertCount(12, $relevant);
    }

    public function test_character_budget_is_enforced(): void
    {
        // Each fact's value is close to the 500-char extractor cap - eight
        // of them already exceeds a 2000-char budget, so the budget (not
        // just the 12-item cap) must be doing real work here.
        for ($i = 1; $i <= 10; $i++) {
            $longValue = "Combo pricing rule number {$i}: " . str_repeat('x', 470);
            $this->service()->recordFacts([
                ['category' => 'other', 'normalized_key' => "long_combo_rule_{$i}", 'value' => $longValue],
            ], 1, 'msg');
        }

        $relevant = $this->service()->relevantFacts('Tell me about combo pricing.', 1);

        $totalChars = array_sum(array_map(fn ($f) => mb_strlen($f['value']), $relevant));
        $this->assertLessThanOrEqual(2000, $totalChars);
        $this->assertLessThan(10, count($relevant));
    }

    public function test_relevance_beats_recency(): void
    {
        // The OLDER fact is exactly on-topic; the NEWER fact shares no
        // keywords with the question and isn't a global category - recency
        // alone must not let it outrank the relevant older fact.
        $this->service()->recordFacts([
            ['category' => 'other', 'normalized_key' => 'combo_pricing_floor', 'value' => 'Combo pricing must never go below cost.'],
        ], 1, 'msg-old');

        Carbon::setTestNow(now()->addDay());
        $this->service()->recordFacts([
            ['category' => 'other', 'normalized_key' => 'staff_parking_rule', 'value' => 'Staff must park in the rear lot only.'],
        ], 1, 'msg-new');
        Carbon::setTestNow();

        $relevant = $this->service()->relevantFacts('What is our combo pricing policy?', 1);

        $this->assertSame('Combo pricing must never go below cost.', $relevant[0]['value']);
        $this->assertNotContains('Staff must park in the rear lot only.', array_column($relevant, 'value'));
    }

    public function test_owner_isolation_two_admins_get_the_same_relevant_facts(): void
    {
        $this->seedTwelveUnrelatedFacts();

        $forOwnerOne = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);
        $forOwnerTwo = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 2);

        // Business facts are business-wide, not per-admin - the same
        // question from a different authenticated owner must retrieve the
        // same relevant set (the owner_user_id parameter never filters
        // facts, only exists for interface symmetry - see the service
        // docblock).
        $this->assertSame($forOwnerOne, $forOwnerTwo);
    }

    public function test_superseded_facts_are_excluded_even_when_highly_relevant(): void
    {
        $this->service()->recordFacts([
            ['category' => 'policy', 'normalized_key' => 'discount_policy', 'value' => 'Never discount combo packages below 10 percent off.'],
        ], 1, 'msg1');
        $this->service()->recordFacts([
            ['category' => 'policy', 'normalized_key' => 'discount_policy', 'value' => 'Never discount combo packages below 15 percent off.'],
        ], 1, 'msg2');

        $relevant = $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);

        $values = array_column($relevant, 'value');
        $this->assertContains('Never discount combo packages below 15 percent off.', $values);
        $this->assertNotContains('Never discount combo packages below 10 percent off.', $values);
    }

    public function test_no_crm_writes_from_relevance_scoring(): void
    {
        $this->seedTwelveUnrelatedFacts();

        $staff = Staff::create(['name' => 'Relevance RO Staff', 'branch' => 'old_airport', 'base_salary' => 2000.0]);
        $sale = Sale::create(['branch' => 'old_airport', 'total_amount' => 100.0, 'created_at' => now(), 'updated_at' => now()]);

        $countsBefore = [Staff::count(), Sale::count()];
        $staffBefore = Staff::find($staff->id)->getAttributes();

        $this->service()->relevantFacts('Can I give a client 20% discount on a combo?', 1);
        $this->service()->relevantFacts('What is our combo pricing policy?', 2);

        $this->assertSame($countsBefore, [Staff::count(), Sale::count()]);
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes());
        $this->assertSame(12, NovaBusinessFact::count());
    }
}
