<?php

namespace Tests\Unit\NovaAI;

use App\NovaAI\Services\NovaFactExtractor;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 5: NovaFactExtractor in isolation - no database, no NovaAIService.
 * Verifies the request shape (never a business snapshot, never Nova's
 * reply - only the raw admin message), the JSON-parsing/validation
 * pipeline, and fail-open behavior.
 */
class NovaFactExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
    }

    private function fakeExtraction(array $facts): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($facts)]]]]],
            ], 200),
        ]);
    }

    public function test_returns_empty_array_when_no_api_key_configured(): void
    {
        config(['services.gemini.key' => null]);

        $result = (new NovaFactExtractor())->extract('We close on Fridays now.');

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_returns_empty_array_for_blank_message(): void
    {
        $result = (new NovaFactExtractor())->extract('   ');

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_valid_fact_is_returned(): void
    {
        $this->fakeExtraction([
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'The salon is closed on Fridays.'],
        ]);

        $result = (new NovaFactExtractor())->extract('We are closing on Fridays from now on.');

        $this->assertSame([
            ['category' => 'policy', 'normalized_key' => 'closing_day', 'value' => 'The salon is closed on Fridays.'],
        ], $result);
    }

    public function test_multiple_facts_in_one_message_are_all_returned(): void
    {
        $this->fakeExtraction([
            ['category' => 'target', 'normalized_key' => 'upsell_target', 'value' => 'Upsell target is 2500 QAR per stylist per month.'],
            ['category' => 'staffing', 'normalized_key' => 'wakrah_hiring_priority', 'value' => 'Wakrah is prioritized for hiring this quarter.'],
        ]);

        $result = (new NovaFactExtractor())->extract('Our upsell target is 2500 QAR per stylist, and prioritize Wakrah hiring this quarter.');

        $this->assertCount(2, $result);
    }

    public function test_empty_array_from_gemini_yields_no_facts(): void
    {
        $this->fakeExtraction([]);

        $result = (new NovaFactExtractor())->extract('What is our revenue today?');

        $this->assertSame([], $result);
    }

    public function test_fact_with_disallowed_category_is_dropped(): void
    {
        $this->fakeExtraction([
            ['category' => 'not_a_real_category', 'normalized_key' => 'some_key', 'value' => 'Some value.'],
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_fact_with_malformed_normalized_key_is_dropped(): void
    {
        $this->fakeExtraction([
            ['category' => 'policy', 'normalized_key' => 'Not A Valid Key!', 'value' => 'Some value.'],
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_fact_with_empty_value_is_dropped(): void
    {
        $this->fakeExtraction([
            ['category' => 'policy', 'normalized_key' => 'some_key', 'value' => '   '],
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_fact_with_overlong_value_is_dropped(): void
    {
        $this->fakeExtraction([
            ['category' => 'policy', 'normalized_key' => 'some_key', 'value' => str_repeat('x', 501)],
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_non_json_response_yields_no_facts_without_throwing(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'not valid json at all']]]]],
            ], 200),
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_failed_request_yields_no_facts_without_throwing(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $result = (new NovaFactExtractor())->extract('Some statement.');

        $this->assertSame([], $result);
    }

    public function test_request_never_contains_a_business_snapshot(): void
    {
        $this->fakeExtraction([]);

        (new NovaFactExtractor())->extract('We close on Fridays now.');

        Http::assertSent(function (HttpClientRequest $request) {
            $text = $request['contents'][0]['parts'][0]['text'] ?? '';
            $this->assertStringNotContainsString('BUSINESS SNAPSHOT', $text);
            $this->assertSame('We close on Fridays now.', $text);

            return true;
        });
    }

    public function test_request_sends_exactly_one_turn_with_the_raw_message_only(): void
    {
        $this->fakeExtraction([]);

        (new NovaFactExtractor())->extract('Never discount packages below 10%.');

        Http::assertSent(function (HttpClientRequest $request) {
            $this->assertCount(1, $request['contents']);
            $this->assertSame('user', $request['contents'][0]['role']);
            $this->assertSame('Never discount packages below 10%.', $request['contents'][0]['parts'][0]['text']);

            return true;
        });
    }

    public function test_request_uses_structured_json_output_mode(): void
    {
        $this->fakeExtraction([]);

        (new NovaFactExtractor())->extract('We close on Fridays now.');

        Http::assertSent(function (HttpClientRequest $request) {
            $this->assertSame('application/json', $request['generationConfig']['responseMimeType'] ?? null);

            return true;
        });
    }
}
