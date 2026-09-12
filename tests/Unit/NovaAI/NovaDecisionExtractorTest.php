<?php

namespace Tests\Unit\NovaAI;

use App\NovaAI\Services\NovaDecisionExtractor;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 6: NovaDecisionExtractor in isolation - no database, no
 * NovaAIService. Verifies the request shape (never a business snapshot,
 * never Nova's reply, never conversation history), the create/complete/
 * cancel/reverse validation pipeline, and fail-open behavior.
 */
class NovaDecisionExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
    }

    private function fakeExtraction(array $actions): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($actions)]]]]],
            ], 200),
        ]);
    }

    public function test_returns_empty_array_when_no_api_key_configured(): void
    {
        config(['services.gemini.key' => null]);

        $result = (new NovaDecisionExtractor())->extract('We decided to prioritize Wakrah hiring.');

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_returns_empty_array_for_blank_message(): void
    {
        $this->assertSame([], (new NovaDecisionExtractor())->extract('   '));
        Http::assertNothingSent();
    }

    public function test_valid_decision_create_is_returned(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring', 'category' => 'staffing'],
        ]);

        $result = (new NovaDecisionExtractor())->extract('We have decided to prioritize hiring at Wakrah this quarter.');

        $this->assertCount(1, $result);
        $this->assertSame('create', $result[0]['action']);
        $this->assertSame('decision', $result[0]['type']);
        $this->assertSame('Prioritize Wakrah hiring', $result[0]['title']);
        $this->assertSame('staffing', $result[0]['category']);
        $this->assertNull($result[0]['description']);
    }

    public function test_valid_experiment_create_carries_optional_fields_through(): void
    {
        $this->fakeExtraction([
            [
                'action' => 'create',
                'type' => 'experiment',
                'title' => 'Tuesday 10% discount trial',
                'category' => 'pricing',
                'started_at' => '2026-09-15',
                'ends_at' => '2026-09-29',
                'target_metric' => 'Tuesday bookings',
                'success_criteria' => 'At least 15% increase in Tuesday bookings',
            ],
        ]);

        $result = (new NovaDecisionExtractor())->extract(
            'Let\'s try a 10% discount on Tuesdays from Sept 15 to Sept 29 and see if Tuesday bookings go up by 15%.'
        );

        $this->assertCount(1, $result);
        $this->assertSame('experiment', $result[0]['type']);
        $this->assertSame('2026-09-15', $result[0]['started_at']);
        $this->assertSame('2026-09-29', $result[0]['ends_at']);
        $this->assertSame('Tuesday bookings', $result[0]['target_metric']);
        $this->assertSame('At least 15% increase in Tuesday bookings', $result[0]['success_criteria']);
    }

    public function test_experiment_create_with_unspecified_fields_yields_null_not_invented_values(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'experiment', 'title' => 'Weekend promo trial', 'category' => 'pricing'],
        ]);

        $result = (new NovaDecisionExtractor())->extract('Let\'s try a weekend promo and see how it goes.');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['started_at']);
        $this->assertNull($result[0]['ends_at']);
        $this->assertNull($result[0]['target_metric']);
        $this->assertNull($result[0]['success_criteria']);
        $this->assertNull($result[0]['review_date']);
    }

    public function test_create_with_invalid_type_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'not_a_type', 'title' => 'Something', 'category' => 'other'],
        ]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_create_with_invalid_category_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'decision', 'title' => 'Something', 'category' => 'not_a_category'],
        ]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_create_with_empty_title_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'decision', 'title' => '  ', 'category' => 'other'],
        ]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_create_with_overlong_title_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'decision', 'title' => str_repeat('x', 151), 'category' => 'other'],
        ]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_create_with_malformed_date_yields_null_date_not_a_dropped_fact(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'experiment', 'title' => 'Trial', 'category' => 'pricing', 'started_at' => 'not-a-date'],
        ]);

        $result = (new NovaDecisionExtractor())->extract('Some statement.');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['started_at']);
    }

    public function test_transition_with_valid_target_id_is_returned(): void
    {
        $this->fakeExtraction([
            ['action' => 'complete', 'target_id' => 5, 'result_summary' => 'Hit the target early.'],
        ]);

        $result = (new NovaDecisionExtractor())->extract(
            'The Wakrah hiring push is done.',
            [['id' => 5, 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring']]
        );

        $this->assertSame([
            ['action' => 'complete', 'target_id' => 5, 'result_summary' => 'Hit the target early.'],
        ], $result);
    }

    public function test_transition_referencing_an_id_not_in_the_active_list_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'cancel', 'target_id' => 999],
        ]);

        $result = (new NovaDecisionExtractor())->extract(
            'Cancel that.',
            [['id' => 5, 'type' => 'decision', 'title' => 'Prioritize Wakrah hiring']]
        );

        $this->assertSame([], $result);
    }

    public function test_transition_with_no_active_items_offered_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'reverse', 'target_id' => 5],
        ]);

        $result = (new NovaDecisionExtractor())->extract('Reverse that decision.', []);

        $this->assertSame([], $result);
    }

    public function test_action_outside_the_allowed_enum_is_dropped(): void
    {
        $this->fakeExtraction([
            ['action' => 'delete', 'target_id' => 5],
        ]);

        $result = (new NovaDecisionExtractor())->extract('Delete that.', [['id' => 5, 'type' => 'decision', 'title' => 'X']]);

        $this->assertSame([], $result);
    }

    public function test_non_json_response_yields_no_actions_without_throwing(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'not json']]]]],
            ], 200),
        ]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_failed_request_yields_no_actions_without_throwing(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $this->assertSame([], (new NovaDecisionExtractor())->extract('Some statement.'));
    }

    public function test_request_never_contains_a_business_snapshot_or_conversation_history(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract('We decided to prioritize Wakrah hiring.');

        Http::assertSent(function (HttpClientRequest $request) {
            $this->assertCount(1, $request['contents']);
            $this->assertSame('user', $request['contents'][0]['role']);
            $this->assertSame('We decided to prioritize Wakrah hiring.', $request['contents'][0]['parts'][0]['text']);
            $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $request['contents'][0]['parts'][0]['text']);

            return true;
        });
    }

    public function test_active_items_list_is_carried_in_the_system_instruction_not_the_message(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract(
            'Cancel the Tuesday discount trial.',
            [['id' => 7, 'type' => 'experiment', 'title' => 'Tuesday discount trial']]
        );

        Http::assertSent(function (HttpClientRequest $request) {
            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';
            $this->assertStringContainsString('id 7', $systemText);
            $this->assertStringContainsString('Tuesday discount trial', $systemText);
            $this->assertSame('Cancel the Tuesday discount trial.', $request['contents'][0]['parts'][0]['text']);

            return true;
        });
    }

    /*
     * Correction B: minimal prior-turn context for approval resolution.
     */

    public function test_prior_turns_are_sent_as_native_conversation_turns_before_the_current_message(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract(
            'Yes, do that.',
            [],
            [
                ['role' => 'user', 'content' => 'What would you recommend for Wakrah?'],
                ['role' => 'assistant', 'content' => 'I recommend running a 7-day Wakrah reactivation campaign.'],
            ]
        );

        Http::assertSent(function (HttpClientRequest $request) {
            $this->assertCount(3, $request['contents']);
            $this->assertSame('user', $request['contents'][0]['role']);
            $this->assertSame('What would you recommend for Wakrah?', $request['contents'][0]['parts'][0]['text']);
            $this->assertSame('model', $request['contents'][1]['role']);
            $this->assertSame('I recommend running a 7-day Wakrah reactivation campaign.', $request['contents'][1]['parts'][0]['text']);
            $this->assertSame('user', $request['contents'][2]['role']);
            $this->assertSame('Yes, do that.', $request['contents'][2]['parts'][0]['text']);

            return true;
        });
    }

    public function test_more_than_two_prior_turns_are_capped_to_the_last_two(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract(
            'Yes, do that.',
            [],
            [
                ['role' => 'user', 'content' => 'First ever message, several turns back.'],
                ['role' => 'assistant', 'content' => 'An old reply, several turns back.'],
                ['role' => 'user', 'content' => 'What would you recommend for Wakrah?'],
                ['role' => 'assistant', 'content' => 'I recommend running a 7-day Wakrah reactivation campaign.'],
            ]
        );

        Http::assertSent(function (HttpClientRequest $request) {
            // Only the last two prior turns, plus the current message = 3 total.
            $this->assertCount(3, $request['contents']);
            $this->assertStringNotContainsString('First ever message', json_encode($request['contents']));
            $this->assertStringNotContainsString('An old reply', json_encode($request['contents']));
            $this->assertSame('I recommend running a 7-day Wakrah reactivation campaign.', $request['contents'][1]['parts'][0]['text']);

            return true;
        });
    }

    public function test_with_no_prior_turns_contents_is_still_exactly_one_turn(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract('We decided to prioritize Wakrah hiring.', [], []);

        Http::assertSent(function (HttpClientRequest $request) {
            $this->assertCount(1, $request['contents']);

            return true;
        });
    }

    public function test_prior_turns_never_appear_in_the_system_instruction(): void
    {
        $this->fakeExtraction([]);

        (new NovaDecisionExtractor())->extract(
            'Yes, do that.',
            [],
            [
                ['role' => 'user', 'content' => 'What would you recommend for Wakrah?'],
                ['role' => 'assistant', 'content' => 'I recommend running a 7-day Wakrah reactivation campaign, secret-code-alpha.'],
            ]
        );

        Http::assertSent(function (HttpClientRequest $request) {
            $systemText = $request['systemInstruction']['parts'][0]['text'] ?? '';
            $this->assertStringNotContainsString('secret-code-alpha', $systemText);
            $this->assertStringNotContainsString('What would you recommend for Wakrah?', $systemText);

            return true;
        });
    }

    public function test_approval_yes_do_that_with_prior_recommendation_creates_a_decision(): void
    {
        $this->fakeExtraction([
            ['action' => 'create', 'type' => 'decision', 'title' => 'Wakrah reactivation campaign', 'category' => 'marketing'],
        ]);

        $result = (new NovaDecisionExtractor())->extract(
            'Yes, do that.',
            [],
            [
                ['role' => 'user', 'content' => 'What would you recommend for Wakrah?'],
                ['role' => 'assistant', 'content' => 'I recommend running a 7-day Wakrah reactivation campaign.'],
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame('create', $result[0]['action']);
    }

    public function test_a_prior_recommendation_alone_with_a_non_committal_reply_extracts_nothing(): void
    {
        // The model is instructed to require approval in the current
        // message - simulating its correct behavior for "Maybe." by having
        // the fake return an empty array, and confirming the extractor
        // faithfully passes that through rather than inventing anything.
        $this->fakeExtraction([]);

        $result = (new NovaDecisionExtractor())->extract(
            'Maybe.',
            [],
            [
                ['role' => 'user', 'content' => 'What would you recommend for Wakrah?'],
                ['role' => 'assistant', 'content' => 'I recommend running a 7-day Wakrah reactivation campaign.'],
            ]
        );

        $this->assertSame([], $result);
    }
}
