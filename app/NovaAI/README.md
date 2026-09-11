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
