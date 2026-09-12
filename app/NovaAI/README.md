# Nova AI — developer notes

## NOVA READ-ONLY INTEGRATION RULE

Existing CRM modules are sources of truth.

Nova development must not modify existing CRM controllers, operational
workflows, database schema, existing business calculations, or frontend
behavior unless that change is separately and explicitly approved.

**Nova may:**
- read existing models/tables
- consume already-reusable existing services without changing them
- create Nova-specific context/adaptor/calculation classes
- calculate read-only derived intelligence
- test parity against existing CRM logic

**Nova must not, by default:**
- refactor existing controllers for Nova
- change existing CRM calculation semantics
- write/update/delete CRM business records
- add migrations for Nova intelligence
- alter CRM operational workflows

This rule must guide future Nova work.

## Why this exists

Stage 3C extracted `FinanceController::branchBreakdown()`'s formula into
`App\Support\BranchFinancialSummaryService` and made `FinanceController`
delegate to it, so both consumers shared one implementation. That traded
CRM-module independence for de-duplication - a real cost the owner
determined outweighs the benefit for Nova work specifically. A later
safety pass restored `FinanceController::branchBreakdown()` to its
original, independent implementation. `BranchFinancialSummaryService`
still exists, but now purely as Nova's own read-only copy of the same
formula, never called by `FinanceController` or any other CRM module.

## What this means in practice

- `App\Support\BranchFinancialSummaryService` is a **Nova-side**
  calculation class. It queries `Sale` and `Expense` directly (read-only)
  and independently reproduces the CRM's existing revenue/expense/profit
  formula - it does not share code with `FinanceController`, and
  `FinanceController` must never be made to call into it or anything
  else under `App\NovaAI` or Nova-specific `App\Support` classes.
- Because the two implementations are intentionally independent, they can
  drift. `tests/Unit/BranchFinancialParityTest.php` is the guard against
  that: it asserts, for identical fixture data, that
  `BranchFinancialSummaryService` and `FinanceController::branchBreakdown()`
  produce the same revenue, expenses, and profit. That test also asserts
  `FinanceController.php`'s source never re-introduces a reference to
  `BranchFinancialSummaryService` - if it starts failing, the two
  calculations have diverged (fix the Nova copy to match, don't touch the
  CRM one) or something re-coupled the two classes (undo that coupling).
- `margin_percent` is Nova-specific: `FinanceController::branchBreakdown()`
  never returned a margin (each of its two callers computes its own
  display margin independently), so that field is not part of the parity
  contract and is not expected to exist on the CRM side.
- If a future Nova stage genuinely needs a CRM controller or existing
  business calculation to change, that is a separate, explicit
  conversation with the owner - not something to do silently as part of
  "adding Nova intelligence."

## Stage 3D — StaffSalesAnalytics (direct reuse, not duplication)

Unlike `BranchFinancialSummaryService`, `App\Support\StaffSalesAnalytics`
is consumed **directly** by Nova (`NovaAIService::staffTargetSection()`),
not duplicated. That's a deliberate, different choice from Stage 3C: this
class already lives in the `App\Support` layer with no controller
coupling and is already called freely by multiple existing consumers
(`Kpi\StaffSalesController`, `UserController::dashboard()`) - it is
exactly the kind of "already-reusable existing service" the read-only
rule says Nova may call without modification. `StaffSalesAnalytics` is
never edited for Nova's convenience; if its API were ever awkward for
Nova, the fix would be a Nova-side adapter, not a change to this class.

## Stage 3E — CustomerRetentionAnalytics (Nova-side aggregation)

`App\NovaAI\Support\CustomerRetentionAnalytics` reads `Appointment`/`Sale`
directly and additionally consumes the CRM's existing, unmodified
`App\Support\ClientMaintenancePlanner` (for rebooking/maintenance
urgency) - a hybrid of the two patterns above. It was built Nova-side
because the relevant CRM logic (`CustomerController::index()`/`show()`)
is controller-embedded and, on inspection, deliberately looser than what
Nova needs: those methods count *any* appointment - including cancelled,
no-show, and future-scheduled ones - toward "last visit"/LTV with no
status or date filter at all. Nova's own "qualifying visit" definition
(status not cancelled/no_show, not in the future) is intentionally more
precise, mirroring `ClientMaintenancePlanner`'s own filtering convention
rather than `CustomerController`'s looser one - see that class's docblock
for the exact reasoning. `tests/Unit/CustomerRetentionReadOnlyTest.php`
and `tests/Unit/NovaAI/CustomerRetentionSectionTest.php`'s maintenance
tests are the parity/isolation guards for this one.

## Stage 3F — BookingAgentPerformanceAnalytics (hybrid, and a discovered constraint)

`App\NovaAI\Support\BookingAgentPerformanceAnalytics` is the same hybrid
pattern as Stage 3E: shift-level bookings/target/achievement come from a
fresh, unsaved `App\Models\KpiAgentTargetReport` instance's own
`shiftStats()`/`combined()` (exactly how `UserController::dashboard()`
already uses it - `::create()`/`->save()` are never called, so no row is
ever written to `kpi_agent_target_reports`). The per-agent breakdown is
Nova-specific: that class's real attribution logic
(`rawShiftBookings()`) is `private`, so this mirrors - never calls - the
same rule (same agent account must both create the `Appointment` record
and be checked in, via `AgentShiftLog`, for a matching window that day).

Important discovered constraint, reported rather than silently
papered over: **there is no per-agent target anywhere in the CRM** -
`KpiAgentTargetReport` only ever computes an aggregate shift-level
(morning/evening) target, never one scoped to an individual agent. Nova's
per-agent rows show real, individually-attributed booking counts (and
real logged shift hours), but they are never compared against an
individual target, because the CRM has no such thing to compare against
- both the code (`bookingAgentPerformanceSection()`'s docblock) and the
system prompt say this explicitly, so Nova never implies otherwise.

Also: booking agents (`User`, role `agent`) and salon staff (`Staff`,
Stage 3D) are enforced as separate populations throughout - nothing in
this class or its Nova consumer ever joins or compares the two.

## Stage 3G — MarketingLeadAnalytics (weakest-evidence domain, by design)

`App\NovaAI\Support\MarketingLeadAnalytics` reads a fresh, unsaved
`App\Models\KpiAdsConversionReport` for ad-inquiry figures (exactly how
`UserController::dashboard()` already uses it) and `App\Models\Lead`
directly for follow-up figures, mirroring (not calling, since it's
`private`) `LeadController::overdueLeadsQuery()`'s exact where-clauses.

This stage deliberately prioritizes evidence-quality labeling over
metric breadth. Two facts drive every wording choice in the section and
the prompt caveat: (1) `AdLeadEntry::isBooked()` means a branch was
manually typed into a dropdown - there is no FK from `AdLeadEntry` to
`Customer`, `Appointment`, or `Sale` anywhere in the schema, so "booked"
is a manual marker, never a completed-appointment or sale fact; (2)
`ticket_amount` is self-reported and never reconciled against `Sale` -
it is always called "reported booking value," never "revenue" or
"sales." There is no advertising-spend field anywhere in this CRM, so
CAC/CPL/ROAS are structurally impossible, not merely unimplemented - the
section and prompt both say so explicitly rather than omitting the topic
silently. `Lead.needful_done` is kept strictly separate from "converted"
throughout, per the model's own docblock.

Note on period choice: `UserController::dashboard()` uses month-to-date
for `KpiAdsConversionReport` (matching every other Nova section), while
the standalone Ads Analytics screen defaults to the full span of logged
data when unfiltered. Nova mirrors the dashboard's MTD convention for
consistency with her other sections, not the standalone screen's
different default - both are real, existing conventions; this was a
deliberate choice among them, not an invented third one.

## Stage 3H — PayrollPerformanceAnalytics (direct reuse, plus Nova-only bucketing)

`App\NovaAI\Support\PayrollPerformanceAnalytics` consumes
`App\Support\StaffPayrollCalculator` **directly** - like Stage 3D's
`StaffSalesAnalytics`, not duplicated - since `rowFor()`/`payrollFor()`
are already public and population-agnostic, and this is exactly how
`StaffController::index()`'s Payroll tab already uses it (same
`Staff::active()` roster, same `new StaffPayrollCalculator($from, $to)`
construction). Nothing about the payroll formula itself
(`base_salary + overtime_pay - deductions`) is touched or reimplemented;
this class only adds branch bucketing that keeps a `'both'`-branch staff
member out of both single-branch totals (a separate bucket instead),
since no CRM-defined allocation rule exists to split their pay 50/50 or
otherwise - inventing one would have been a real rule invention, so it
wasn't done.

Two discovered constraints drove the design, both reported rather than
smoothed over:
1. **Period**: `StaffController::index()`'s own Payroll tab defaults to
   `now()->startOfMonth()` -> `now()->endOfMonth()` - the **full**
   calendar month, including days that haven't happened yet - unlike
   every other Nova section's "start of month to now." Mirrored exactly,
   since that's the CRM's own established payroll period, not invented.
2. **No proven Expense relationship**: `Expense::create()` is only ever
   called from `FinanceController::storeExpense()`, a plain manual admin
   form with no reference to payroll anywhere. There is no code-level
   link proving recorded expenses do or don't already include payroll -
   `tests/Unit/PayrollReadOnlyTest.php::test_payroll_code_never_writes_into_expense`
   is the regression guard for this claim staying true. Both the section
   and the system prompt state this as unestablished, and neither ever
   subtracts payroll from `branchFinancialSection()`'s net profit -
   doing so would invent a "true profit" formula this CRM doesn't
   support.

Commission (the estimated figure in `staffPerformanceSection()`,
Stage 1) and payroll (this stage) remain two entirely separate,
unrelated calculations throughout - never added together, never used to
validate each other.

## Stage 4 — Conversation memory (isolated database)

Nova now has short-term conversational continuity: `nova_conversations`
and `nova_messages` on a dedicated `nova_memory` connection
(`config/database.php`), backed by `storage/app/nova/nova-memory.sqlite`
in every real environment - a completely separate SQLite file from the
CRM's own `database/database.sqlite`. Existing CRM production files
modified: **none**. The only files touched to make this isolated database
possible are Nova's own (`App\NovaAI\Models\NovaConversation`/
`NovaMessage`, `App\NovaAI\Services\NovaConversationService`,
`App\NovaAI\Providers\NovaMemoryServiceProvider`,
`database/migrations/nova-memory/*`) plus the two limited, explicitly
pre-approved infrastructure registrations this required:
`config/database.php` (one new `'nova_memory'` connection block - every
pre-existing connection block is untouched) and `bootstrap/providers.php`
(one new provider registration line, alongside the three already there).

**Why a whole second connection/database, not just new tables on the
CRM's own connection**: so a Nova memory write is structurally incapable
of touching operational data - not "policy," but something a bug can't
violate even by accident. `tests/Unit/NovaConversationMemoryReadOnlyTest.php`
is the regression guard.

**A real discovered constraint, worth recording**: Laravel's
`RefreshDatabase` test trait only caches/restores an in-memory SQLite
connection's PDO *across test methods* for whichever connection names a
given test class lists in `$connectionsToTransact` (default: just the
app's default connection) - and the underlying `migrate:fresh` that
creates the schema in the first place only runs once, on whichever test
class happens to execute first in the whole suite. Since 100+ pre-existing
Nova tests never mention `nova_memory`, relying on that shared mechanism
meant nova_memory's schema would exist for whichever test happened to run
first and be silently blank (`no such table`) for every other test class,
depending on execution order - a real bug that surfaced in the very first
full-suite run and would otherwise have been an intermittent,
order-dependent test flake. The fix: nova_memory's two migrations live in
their own subfolder (`database/migrations/nova-memory/`, never in the
default `database/migrations/` directory the CRM's own migrations share)
and are migrated explicitly, fresh, per test method, via
`tests/Concerns/MigratesNovaMemory.php` - completely independent of
`RefreshDatabase`'s shared, order-sensitive caching. In every real
(non-test) environment, `NovaMemoryServiceProvider` registers that same
subfolder with Laravel's migrator via `loadMigrationsFrom()` (skipped
under the test suite specifically to avoid colliding with the per-test
migration above), so a single ordinary `php artisan migrate` continues to
provision both the CRM's own pending migrations and Nova's, in one
familiar command, exactly as a future deploy will expect.

**Design choices, and why**: (1) Recent conversation is sent to Gemini as
*native* prior `contents` turns (`role: 'user'`/`'model'`, Gemini's own
naming) ahead of the current, always-freshly-built business-snapshot
turn - not as a text block appended into the snapshot - so a stored
historical turn can never be mistaken for current business truth; the
snapshot is rebuilt from scratch on every single request regardless of
what conversation history is attached. (2) `NovaAIService::ask()`'s
existing signature/return type is completely unchanged (it only gained
optional parameters), so all pre-Stage-4 tests and callers keep working
untouched; `NovaAIService::askWithMeta()` is new and is what
`NovaConversationService`-aware callers use, since it also reports
whether a reply was a genuine Gemini answer or an infrastructure fallback
message (missing key, 429, failed request, empty response, exception) -
only a genuine answer is ever persisted as assistant memory, though the
admin's own message is always persisted regardless. (3) An unknown,
malformed, or another-owner's `conversation_id` is all treated
identically by `NovaConversationService::resolveConversation()` - silently
start a fresh conversation - so the ask endpoint never reveals whether a
given id belongs to someone else. (4) The recent-context window
(`NovaConversationService::RECENT_MESSAGE_LIMIT` = 12 messages,
`RECENT_CONTEXT_CHAR_BUDGET` = 6000 characters) always keeps the newest
messages and drops the oldest first, and always keeps at least the single
newest message even if it alone exceeds the character budget - a message
is never truncated mid-way, only ever included or excluded whole.

## Stage 5 — Structured business memory (durable facts)

`nova_business_facts` (also on the isolated `nova_memory` connection - see
Stage 4 above) gives Nova durable memory of standing policies, targets,
prices, constraints, and preferences the owner has explicitly stated -
"we close on Fridays now," "never discount packages below 10%." Existing
CRM production files modified: **none**. Every file touched is Nova's own
(`App\NovaAI\Models\NovaBusinessFact`, `App\NovaAI\Services\NovaFactExtractor`,
`App\NovaAI\Services\NovaBusinessFactService`, one new migration in
`database/migrations/nova-memory/`) plus the controller wiring and
system-prompt updates.

**Why a second, independent Gemini call, rather than asking the main
answer-generating call to also flag facts**: so "never Nova inference,
never a live CRM value, never an unapproved recommendation" is
structurally true, not a prompting hope. `NovaFactExtractor::extract()`
receives *only* the admin's literal message text - never the business
snapshot, never conversation history, never Nova's own reply. A live
revenue figure or a recommendation Nova made cannot be captured as a
"stated fact" by a call that never sees either -
`tests/Unit/NovaAI/NovaBusinessFactMemoryIntegrationTest.php::test_extraction_request_never_receives_the_business_snapshot_or_novas_reply`
is the regression guard.

**Validation is never outsourced to the model alone**: the extraction
call uses Gemini's structured-output mode (`responseSchema`) to make
malformed JSON unlikely, but every returned item is still independently
re-checked in `NovaFactExtractor::validateFact()` against a small fixed
category enum and format/length limits before it can reach storage - a
schema is a strong hint, not a trust boundary.

**Dedup/supersession** is keyed on `normalized_key`
(`NovaBusinessFactService::recordFact()`): a new fact sharing an existing
active fact's key marks the old row `superseded` (kept for audit/history,
excluded from retrieval) and inserts the new one as the sole active fact
for that key. Facts with different keys simply coexist.

**Retrieval is deliberately dumb**: every active fact is returned,
newest-updated first, capped at 100 purely as a defensive ceiling on
prompt size (`NovaBusinessFactService::MAX_ACTIVE_FACTS`) - not a
relevance filter. No vector search, no keyword matching, no
question-awareness; that is explicitly out of scope until a later stage.

**Where facts are injected**: `NovaAIService::buildBusinessFactsSection()`
adds a clearly-labelled `REMEMBERED BUSINESS FACTS` block to the current
turn only, structurally separate from `buildSnapshot()`'s CRM-only text -
never merged into it, so the snapshot stays provably CRM-only and the
"CRM > business facts > conversation" priority rule
(`resources/prompts/nova-system.md`'s MEMORY PRIORITY ORDER section) has
something structurally distinct to refer to. When nothing has been
recorded yet, the section says so explicitly rather than being omitted.

**Failure handling matches Stage 4's conventions exactly**: a failed or
malformed extraction call returns an empty array and is logged, never
thrown; a nova_memory outage during storage/retrieval is caught and
logged in `NovaBusinessFactService`, never allowed to affect whether the
admin's actual question gets answered. The admin's own message is
extracted from regardless of whether the main answer call itself
succeeded that turn.

**A real testing nuance discovered here, worth recording**: once a second
Gemini call exists per request, `Http::fake()`'s "first registered
closure that returns non-null wins" matching order
(`Illuminate\Http\Client\PendingRequest`'s `->filter()->first()`) means
calling `Http::fake($closure)` a second time mid-test does not override
the first - both `NovaConversationMemoryIntegrationTest` and
`NovaBusinessFactMemoryIntegrationTest` had to register exactly one
closure per test that reads from mutable test-instance properties,
updated between requests, rather than re-registering the fake.

## Stage 6 — Decision & experiment memory

`nova_decisions` and `nova_experiments` (both on the isolated
`nova_memory` connection - see Stage 4) give Nova memory of durable
choices and time-bounded trials the owner has explicitly approved -
never a Nova recommendation the owner merely acknowledged. Existing CRM
production files modified: **none**. Every file touched is Nova's own
(`App\NovaAI\Models\NovaDecision`/`NovaExperiment`,
`App\NovaAI\Services\NovaDecisionExtractor`, `App\NovaAI\Services\
NovaDecisionService`, two new migrations in
`database/migrations/nova-memory/`) plus the controller wiring and
system-prompt updates.

**A third, independent Gemini call**, following Stage 5's exact
isolation discipline: `NovaDecisionExtractor::extract()` receives only the
admin's literal message plus a short reference list of currently active
decisions/experiments (id/type/title only) - never the business snapshot,
never conversation history, never Nova's own reply. Three Gemini calls
now happen per `/nova/ask` request (main answer, fact extraction,
decision extraction); this is a deliberate latency/cost tradeoff for
isolation and correctness, and a natural target for a later stage to
optimize (e.g. combining extractors, or moving them off the request path
entirely) - out of scope here.

**The action enum, and why it stays that narrow**: every extracted item
is one of exactly four actions - `create`, `complete`, `cancel`,
`reverse` - never an arbitrary SQL statement, class, table, or method
choice. A `complete`/`cancel`/`reverse` action must additionally name a
`target_id` that was actually present in the active-items list offered to
that specific call; `NovaDecisionExtractor::validateTransition()` checks
that membership before the action can reach `NovaDecisionService` at all,
so the model cannot invent or guess an id. The extractor's own
instructions also require the owner's reference to be unambiguous - "if
you are not confident which one the owner means, extract nothing" -
matching Stage 5's "when in doubt, extract nothing" precision-over-recall
choice.

**Null, never invented, for unspecified experiment parameters**:
`started_at`, `ends_at`, `target_metric`, `success_criteria`, and
`review_date` are all optional on an experiment. When the owner doesn't
state one, `NovaDecisionExtractor::validateCreate()` stores it as `null` -
it is never inferred, defaulted, or guessed by the extractor or by
`NovaDecisionService::create()`.

**Six-tier memory priority**, extending Stage 5's three-tier order
(`resources/prompts/nova-system.md`'s MEMORY PRIORITY ORDER section):
current business snapshot > remembered business facts > active decisions
> active experiments > recent conversation > older/historical memory.
Decisions and experiments are injected as their own sections
(`NovaAIService::buildDecisionsSection()`/`buildExperimentsSection()`),
structurally separate from the CRM snapshot and from each other, so this
ordering has something concrete to refer to. Only `status = 'active'`
rows are ever injected - completed/cancelled/reversed rows stay in the
database for audit but are not surfaced by default, matching Stage 5's
"only active facts" precedent.

**"State the discrepancy, don't pretend the CRM changed"**: a decision or
experiment records the owner's *intent*, never proof that the CRM was
actually updated to match it - Nova has no ability to execute anything.
The system prompt's ACTIVE DECISIONS AND EXPERIMENTS section states this
explicitly and requires Nova to say so plainly when the current business
snapshot doesn't yet reflect an on-record decision/experiment, rather
than assuming it does.

**Review-date awareness only, never automation**: `review_date` is a
plain stored field. `NovaAIService::reviewDateSuffix()` computes an
"(OVERDUE)" label fresh on every single request purely by comparing the
stored date to `now()` - there is no scheduler, queued job, cron entry,
or notification anywhere tied to it. Nova can mention an overdue review
only because the label is already sitting in front of her in that
request's prompt, never because anything pushed her to.

**CRM remains strictly read-only**: nothing in `NovaDecisionService`
references, queries, or writes any CRM model, table, or connection -
recording, completing, cancelling, or reversing a decision/experiment
only ever touches `nova_decisions`/`nova_experiments` on the isolated
`nova_memory` connection.
`tests/Unit/NovaConversationMemoryReadOnlyTest.php::test_a_full_decision_extraction_round_trip_makes_zero_writes_anywhere_on_the_crm_connection`
is the regression guard, alongside the equivalent Stage 4/5 guards in the
same file.

**Two real bugs found only by live-verifying against the real Gemini API**
(the mocked test suite couldn't have caught either, since it never
exercises Gemini's actual structured-output behavior):

1. A flat `responseSchema` with only `action` marked `required` let
   Gemini satisfy the schema with a bare `{"action":"create","type":
   "experiment"}` - technically valid JSON matching the schema, but
   silently missing `title`/`category` despite the system instructions
   asking for them. Fixed by switching to an `anyOf` discriminated union -
   one object shape for `create` (title/type/category all `required`) and
   one for `complete`/`cancel`/`reverse` (`target_id` `required`) - which
   makes the model commit to fully populating whichever branch it picks.
2. Without today's date anywhere in its context, the extractor resolved
   "starting today" against an arbitrary date from the model's training
   data (observed: `2023-10-24`) instead of the real current date.
   `NovaDecisionExtractor::extractorInstructions()` now states
   `now()->toDateString()` explicitly and instructs the model to resolve
   relative references against it - the same grounding
   `NovaAIService::buildPrompt()` already gives Nova's main answer via its
   `BUSINESS SNAPSHOT (as of ...)` header, just not something a narrow
   extraction call gets for free.

Both were caught by manually calling `NovaDecisionExtractor` against the
real API with a realistic message during live verification, not by any
mocked test - a reminder that a schema/prompt correctly *validating*
mocked JSON says nothing about what the real model actually produces
under that schema.

## Correction pass (post Stage 6) - relevant memory retrieval + contextual approval

Two precision corrections to Stages 5/6's already-approved design, not a
redesign: replacing blanket "every active X" retrieval with deterministic
relevance scoring, and giving the decision extractor the minimum
conversation context it needs to resolve "yes, do that". Existing CRM
production files modified: **none**.

**Correction A - relevant business memory retrieval.**
`NovaBusinessFactService::activeFacts()` (all active facts, newest first,
capped at 100) is replaced by `relevantFacts(string $question, int
$ownerUserId)`. Scoring is deterministic keyword overlap - no embeddings,
no vector store, no external service, no LLM call
(`App\NovaAI\Support\NovaRelevance`, shared with decisions/experiments
below): lowercase, strip punctuation, drop a small fixed stopword list,
then score each fact by `(normalized_key token overlap x 3) + (value
token overlap x 1) + (1 if category is policy/preference/constraint,
else 0)`. A fact scoring zero is excluded outright - not merely
down-ranked - which is what actually keeps unrelated facts from flooding
the context (see `NovaBusinessFactRelevanceTest`'s 12-fact scenario: a
combo-discount question retrieves the discount and package-expiry facts
plus the two "global" policy/preference facts, and excludes payroll,
cleaning, staffing, and branch-target facts entirely). Selection is
capped at 12 facts and a 2000-character budget
(`NovaRelevance::selectWithinBudget()` - relevance-ranked, so an
oversized lower-ranked item is skipped in favor of smaller ones that
still fit, rather than truncating the list outright). Relevance always
outranks recency; recency only breaks ties between equally-scored facts.
`$ownerUserId` is accepted (matching the correction's exact requested
input) but does not filter anything: business facts remain global/
business-wide, per Stage 5's original, unredesigned data model - every
admin asking the same question gets the same relevant facts
(`test_owner_isolation_two_admins_get_the_same_relevant_facts`).

**Decision/experiment retrieval got the same treatment**
(`NovaDecisionService::relevantDecisions()`/`relevantExperiments()`,
replacing `activeDecisions()`/`activeExperiments()`), with a deliberate
asymmetry: active decisions carry an unconditional flat baseline (score 2
before any overlap) and are only capped/ordered, never excluded for lack
of keyword overlap - a standing commitment is worth staying aware of
regardless of topic. Active experiments carry no baseline and are
excluded outright at zero overlap, mirroring facts' stricter behavior,
since an experiment is more situational. Both get a modest boost
(+1) when their `review_date` has passed. Both are capped at 12 items and
a 1500-character budget. `activeItemsForExtraction()` (the reference list
`NovaDecisionExtractor` uses to resolve complete/cancel/reverse by id) is
deliberately untouched by any of this - it must still list every active
item regardless of relevance, since the extractor needs to be able to
reference any of them.

**Correction B - contextual decision approval.**
`NovaDecisionExtractor::extract()` gained a third parameter,
`array $priorTurns = []` - at most the last two prior conversation turns
(already computed once per request as `NovaConversationService::
recentTurns()` for the main answer call; `array_slice($recentTurns, -2)`
in the controller, and re-capped defensively inside the extractor itself).
No new data source, no new Gemini call, no full conversation, no CRM
snapshot, no business facts - exactly the minimum needed to resolve "yes,
do that" against Nova's immediately preceding recommendation. Prior turns
are sent as native Gemini multi-turn `contents` (role `user`/`model`),
the exact same mechanism `NovaAIService` already uses for the main
answer's own conversation memory - never folded into the system
instruction - so they are structurally untrusted conversational data, not
new instructions, consistent with the project's established safety
pattern. With no prior turns (the default), `contents` is still exactly
one turn, so every pre-correction test and caller is unaffected.

**Approval discipline** is enforced entirely through
`NovaDecisionExtractor`'s own instructions (there is no code-level
heuristic separating "who said what" - the same trust model the rest of
Stage 5/6 already relies on for extraction semantics): a prior assistant
recommendation, however specific, is never by itself a decision - it only
identifies what a short reference in the CURRENT message points to.
Approval itself must be explicitly present in the current message. The
instructions include the exact worked examples from the correction
("Why?" -> no decision, "Maybe." -> no decision, "Let's think about it."
-> no decision, "Yes, let's do that." -> decision, "Okay, run that test."
-> experiment) and state the ambiguity rule ("if you cannot confidently
tell what a reference points to... extract nothing").

**"Yes, do that" flow, concretely** (`NovaDecisionApprovalFlowTest`):
turn 1 - owner asks "What would you recommend for Wakrah?", Nova answers
"I recommend running a 7-day Wakrah reactivation campaign and measuring
bookings," decision extraction (correctly) returns nothing since no
approval exists yet, `NovaDecision::count()` stays 0. Turn 2 (same
conversation) - owner says "Yes, do that." The controller passes the
prior user turn + prior assistant reply as `priorTurns`; decision
extraction now returns a `create` action; `NovaDecisionService::
applyActions()` persists it. A brand-new conversation (no shared history)
still surfaces "Wakrah reactivation campaign" in its ACTIVE DECISIONS
section, since retrieval is a `nova_memory` database query, not something
tied to conversation state.

**Gemini call count per request: still 3** (main answer, fact extraction,
decision extraction) - unchanged by this correction pass, as instructed.
The new minimal-context data for decision extraction is drawn from
already-fetched `$recentTurns`, not a new API call. Optimizing the call
count remains explicitly out of scope until a later stage.

**Files changed:** `app/NovaAI/Support/NovaRelevance.php` (new, shared
scoring utility), `app/NovaAI/Services/NovaBusinessFactService.php`,
`app/NovaAI/Services/NovaDecisionService.php`,
`app/NovaAI/Services/NovaDecisionExtractor.php`,
`app/NovaAI/Http/Controllers/NovaAIController.php`, plus test files. No
new migrations, no schema changes, no CRM production file.

**A real nuance found only in live verification, worth recording**: Nova's
already-established executive-advisor persona (Stage 2) answers with a
thorough diagnosis -> constraint -> recommendation -> metric structure,
often bundling two or three distinct action items into one answer rather
than a single crisp suggestion. A bare "Yes, do that." against such a
multi-part answer is *genuinely* ambiguous - which part does "that" mean?
- and the real model correctly (per its instructions) extracted nothing
for exactly that reason during live testing, confirmed by replaying the
exact real recommendation text directly through `NovaDecisionExtractor`
in isolation. This is the intended precision-over-recall behavior, not a
bug: it was proven working correctly, live, once the owner's message
named the specific action being approved ("Yes, do that - have the
booking agents reach out to the overdue leads."), which the model
resolved against the single matching item in Nova's prior multi-part
reply and correctly created a decision from. Nothing about the extractor
or its instructions was changed in response to this finding - it is
reported here because it is a real property of how the two already-
approved systems (Stage 2's persona, this correction's extractor)
interact, not something this correction pass needed to fix.
