<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\StaffContractCategory;
use App\Models\User;
use App\Support\StaffContractPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffContractController extends Controller
{
    public const UNLOCK_TTL_SECONDS = 600; // 10 minutes
    public const MAX_PIN_ATTEMPTS = 5;

    /**
     * Admin: Save a reusable custom Contract Category / Designation.
     */
    public function storeCategory(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'default_currency' => ['required', 'string', Rule::in(StaffContract::CURRENCIES)],
            'default_branch' => ['nullable', 'string', Rule::in(array_keys(StaffContract::BRANCHES))],
            'clauses' => ['nullable', 'string', 'max:30000'],
        ]);

        $cleanLabel = trim($validated['label']);
        $currency = strtoupper($validated['default_currency']);
        $defaultBranch = $validated['default_branch'] ?? ($currency === 'PKR' ? 'remote' : 'old_airport');
        $clauses = trim((string) ($validated['clauses'] ?? ''));
        if ($clauses === '') {
            $clauses = StaffContract::defaultCustomClauses($cleanLabel, $currency);
        }

        $existing = StaffContractCategory::whereRaw('LOWER(label) = ?', [strtolower($cleanLabel)])->first();
        if ($existing) {
            $existing->update([
                'default_currency' => $currency,
                'default_branch' => $defaultBranch,
                'clauses' => $clauses,
            ]);
            $category = $existing;
        } else {
            $category = StaffContractCategory::create([
                'slug' => StaffContractCategory::generateUniqueSlug($cleanLabel),
                'label' => $cleanLabel,
                'default_currency' => $currency,
                'default_branch' => $defaultBranch,
                'clauses' => $clauses,
                'created_by' => $request->user()->id,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'slug' => $category->slug,
                'template' => $category->toTemplateArray(),
            ], 201);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "Contract category \"{$category->label}\" saved.");
    }

    /**
     * Admin: Create a new staff contract for either a Salon Staff record ("staff:{id}")
     * or a CRM Staff Access account ("user:{id}").
     */
    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        // Normalize single Compensation input into base_salary for backward DB compatibility
        if ($request->filled('compensation') && !$request->filled('base_salary')) {
            $request->merge(['base_salary' => $request->input('compensation')]);
        }

        $member = $this->resolveTargetMember($request);
        $userProfile = $member instanceof User ? StaffContract::resolveUserContractProfile($member) : null;

        if (!$request->filled('template_key') && $userProfile) {
            $request->merge(['template_key' => $userProfile['template_key']]);
        }

        $allTemplates = StaffContract::allTemplates();
        $allowedTemplateKeys = array_merge(
            array_keys($allTemplates),
            [StaffContract::NEW_CATEGORY_SENTINEL]
        );

        $validated = $request->validate([
            'team_member' => ['nullable', 'string', 'max:60'],
            'staff_id' => ['nullable'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'template_key' => ['required', 'string', Rule::in($allowedTemplateKeys)],
            'custom_category_name' => ['nullable', 'string', 'max:120'],
            'custom_category_currency' => ['nullable', 'string', Rule::in(StaffContract::CURRENCIES)],
            'role_title' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', Rule::in(array_keys(StaffContract::BRANCHES))],
            'currency' => ['nullable', 'string', Rule::in(StaffContract::CURRENCIES)],
            'compensation' => ['nullable', 'numeric', 'min:0'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'clauses_body' => ['nullable', 'string', 'max:30000'],
            'status' => ['nullable', 'string', Rule::in(array_keys(StaffContract::STATUSES))],
            'pin' => ['nullable', 'string', 'regex:/^\d{4}$/'],
        ]);

        $templateKey = $validated['template_key'];

        if ($templateKey === StaffContract::NEW_CATEGORY_SENTINEL) {
            $newCategoryName = trim((string) ($validated['custom_category_name'] ?? ''));
            if ($newCategoryName === '') {
                throw ValidationException::withMessages([
                    'custom_category_name' => 'Please enter a Category / Designation Name when creating a new category.',
                ]);
            }

            $newCurrency = strtoupper((string) (
                $validated['custom_category_currency']
                ?? $validated['currency']
                ?? 'QAR'
            ));
            $newDefaultBranch = $validated['branch'] ?? ($newCurrency === 'PKR' ? 'remote' : 'old_airport');
            $submittedClauses = trim((string) ($validated['clauses_body'] ?? ''));
            $finalCategoryClauses = $submittedClauses !== ''
                ? $submittedClauses
                : StaffContract::defaultCustomClauses($newCategoryName, $newCurrency);

            $existingCategory = StaffContractCategory::whereRaw('LOWER(label) = ?', [strtolower($newCategoryName)])->first();
            if ($existingCategory) {
                $existingCategory->update([
                    'default_currency' => $newCurrency,
                    'default_branch' => $newDefaultBranch,
                    'clauses' => $finalCategoryClauses,
                ]);
                $savedCategory = $existingCategory;
            } else {
                $savedCategory = StaffContractCategory::create([
                    'slug' => StaffContractCategory::generateUniqueSlug($newCategoryName),
                    'label' => $newCategoryName,
                    'default_currency' => $newCurrency,
                    'default_branch' => $newDefaultBranch,
                    'clauses' => $finalCategoryClauses,
                    'created_by' => $request->user()->id,
                ]);
            }

            $templateKey = $savedCategory->slug;
            $template = $savedCategory->toTemplateArray();
        } else {
            $template = $allTemplates[$templateKey];
        }

        $currency = strtoupper((string) (
            $validated['currency']
            ?? ($userProfile && $templateKey === $userProfile['template_key'] ? $userProfile['currency'] : null)
            ?? $template['default_currency']
        ));
        if (!in_array($currency, $template['allowed_currencies'], true)) {
            throw ValidationException::withMessages([
                'currency' => sprintf(
                    'The %s template only supports %s currency.',
                    $template['label'],
                    implode(' / ', $template['allowed_currencies'])
                ),
            ]);
        }

        if ($validated['branch'] ?? null) {
            $branch = $validated['branch'];
        } elseif ($templateKey === 'remote_chat_creative' || $template['default_branch'] === 'remote') {
            $branch = 'remote';
        } elseif ($member instanceof Staff && array_key_exists((string) $member->branch, StaffContract::BRANCHES)) {
            $branch = (string) $member->branch;
        } elseif ($userProfile && array_key_exists($userProfile['branch'], StaffContract::BRANCHES)) {
            $branch = $userProfile['branch'];
        } else {
            $branch = $template['default_branch'];
        }

        $roleTitle = trim((string) ($validated['role_title'] ?? ''));
        if ($roleTitle === '') {
            if ($userProfile && $templateKey === $userProfile['template_key'] && isset(StaffContract::TEMPLATES[$templateKey])) {
                $roleTitle = $userProfile['role_title'];
            } else {
                $roleTitle = $template['default_role'];
            }
        }

        $clausesBody = trim((string) ($validated['clauses_body'] ?? ''));
        if ($clausesBody === '') {
            $clausesBody = $template['clauses'];
        }

        $status = $validated['status'] ?? StaffContract::STATUS_PENDING_SIGNATURE;
        $monthlyCompensation = (float) ($validated['compensation'] ?? $validated['base_salary']);

        if ($member instanceof Staff) {
            $staffId = $member->id;
            $userId = $member->user_id ?: null;
        } else {
            $userId = $member->id;
            $linkedStaffId = Staff::where('user_id', $member->id)->value('id');
            $staffId = $linkedStaffId ? (int) $linkedStaffId : null;
        }

        $contract = DB::transaction(function () use (
            $request,
            $member,
            $staffId,
            $userId,
            $validated,
            $templateKey,
            $roleTitle,
            $branch,
            $currency,
            $monthlyCompensation,
            $clausesBody,
            $status
        ) {
            if (!empty($validated['pin'])) {
                $this->syncPinAcrossMember($member, $validated['pin']);
            }

            return StaffContract::create([
                'staff_id' => $staffId,
                'user_id' => $userId,
                'template_key' => $templateKey,
                'role_title' => $roleTitle,
                'branch' => $branch,
                'currency' => $currency,
                'base_salary' => $monthlyCompensation,
                'housing_allowance' => $validated['housing_allowance'] ?? 0,
                'transport_allowance' => $validated['transport_allowance'] ?? 0,
                'other_allowance' => $validated['other_allowance'] ?? 0,
                'start_date' => $validated['start_date'] ?? now('Asia/Qatar')->toDateString(),
                'expiry_date' => $validated['expiry_date'] ?? null,
                'clauses_body' => $clausesBody,
                'status' => $status,
                'secure_token' => StaffContract::generateUniqueToken(),
                'signed_at' => $status === StaffContract::STATUS_ACTIVE ? now() : null,
                'signed_name' => $status === StaffContract::STATUS_ACTIVE ? $member->name : null,
                'signature_type' => $status === StaffContract::STATUS_ACTIVE ? 'typed' : null,
                'created_by' => $request->user()->id,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'secure_token' => $contract->secure_token,
                'portal_url' => route('contracts.portal.show', $contract->secure_token),
            ], 201);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "Contract {$contract->contract_number} created for {$member->name}.");
    }

    /**
     * Admin: Transition a non-locked contract between Draft and Pending Signature.
     */
    public function updateStatus(Request $request, StaffContract $contract)
    {
        $this->ensureAdmin($request);

        if ($contract->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Active signed contracts are permanently locked and cannot be altered.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                StaffContract::STATUS_DRAFT,
                StaffContract::STATUS_PENDING_SIGNATURE,
            ])],
        ]);

        $contract->update(['status' => $validated['status']]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => $contract->status,
                'status_label' => $contract->statusLabel(),
            ]);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "Contract {$contract->contract_number} marked as {$contract->statusLabel()}.");
    }

    /**
     * Admin: Rotate / regenerate a staff contract's unguessable private link token.
     */
    public function regenerateToken(Request $request, StaffContract $contract)
    {
        $this->ensureAdmin($request);

        $contract->loadMissing(['staff', 'user']);

        $contract->update([
            'secure_token' => StaffContract::generateUniqueToken(),
        ]);

        $request->session()->forget($this->sessionKey($contract));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'secure_token' => $contract->secure_token,
                'portal_url' => route('contracts.portal.show', $contract->secure_token),
            ]);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "Private contract link regenerated for {$contract->memberName()}.");
    }

    /**
     * Admin: Set or reset a Salon Staff member's 4-digit contract portal PIN.
     */
    public function updatePin(Request $request, Staff $staff)
    {
        $this->ensureAdmin($request);

        return $this->applyMemberPinUpdate($request, $staff);
    }

    /**
     * Admin: Set or reset a Staff Access User's 4-digit contract portal PIN.
     */
    public function updateUserPin(Request $request, User $user)
    {
        $this->ensureAdmin($request);

        return $this->applyMemberPinUpdate($request, $user);
    }

    /**
     * Admin: Set or reset 4-digit contract portal PIN using composite team_member ("staff:{id}" or "user:{id}").
     */
    public function updateMemberPin(Request $request)
    {
        $this->ensureAdmin($request);

        $member = $this->resolveTargetMember($request);

        return $this->applyMemberPinUpdate($request, $member);
    }

    /**
     * Admin: Delete an unsigned (Draft / Pending Signature) contract.
     */
    public function destroy(Request $request, StaffContract $contract)
    {
        $this->ensureAdmin($request);

        if ($contract->isLocked()) {
            throw ValidationException::withMessages([
                'contract' => 'Signed Active contracts are permanently locked and cannot be deleted.',
            ]);
        }

        $number = $contract->contract_number;
        $contract->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "Contract {$number} removed.");
    }

    /**
     * Public / Secure Staff Portal: Show Outside Shell (locked) or Full Terms (unlocked / Admin).
     */
    public function showPortal(Request $request, string $token)
    {
        $contract = $this->findContractByToken($token);
        $this->authorizePortalVisitor($request, $contract);

        $logoUrl = StaffContract::logoPublicUrl();
        $memberName = $contract->memberName();

        if (!$this->isUnlocked($request, $contract)) {
            $throttleKey = $this->throttleKey($request, $contract);
            $isThrottled = RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS);
            $retryAfterSeconds = $isThrottled ? RateLimiter::availableIn($throttleKey) : 0;

            return response()
                ->view('contracts.portal-locked', [
                    'staffName' => $memberName,
                    'roleTitle' => $contract->designationLabel(),
                    'branchLabel' => $contract->branchLabel(),
                    'statusLabel' => $contract->statusLabel(),
                    'statusKey' => $contract->status,
                    'unlockUrl' => route('contracts.portal.unlock', $contract->secure_token),
                    'logoUrl' => $logoUrl,
                    'isThrottled' => $isThrottled,
                    'retryAfterSeconds' => $retryAfterSeconds,
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        $isAdminViewer = (bool) ($request->user() && $request->user()->isAdmin());
        $unlockedAt = (int) $request->session()->get($this->sessionKey($contract), now()->timestamp);
        $remainingSeconds = $isAdminViewer
            ? self::UNLOCK_TTL_SECONDS
            : max(0, self::UNLOCK_TTL_SECONDS - (now()->timestamp - $unlockedAt));

        return response()
            ->view('contracts.portal-unlocked', [
                'contract' => $contract,
                'staffName' => $memberName,
                'isAdminViewer' => $isAdminViewer,
                'remainingSeconds' => $remainingSeconds,
                'signUrl' => route('contracts.portal.sign', $contract->secure_token),
                'pdfUrl' => route('contracts.portal.pdf', $contract->secure_token),
                'logoUrl' => $logoUrl,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * Public / Secure Staff Portal: Authenticate 4-digit PIN (throttled after 5 failed attempts).
     */
    public function unlockPortal(Request $request, string $token)
    {
        $contract = $this->findContractByToken($token);
        $this->authorizePortalVisitor($request, $contract);

        $throttleKey = $this->throttleKey($request, $contract);
        $memberName = $contract->memberName();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PIN_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);
            $msg = "Too many failed PIN attempts. Please wait {$retryAfter} seconds before trying again.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg, 'retry_after' => $retryAfter], 429);
            }

            return response()
                ->view('contracts.portal-locked', [
                    'staffName' => $memberName,
                    'roleTitle' => $contract->designationLabel(),
                    'branchLabel' => $contract->branchLabel(),
                    'statusLabel' => $contract->statusLabel(),
                    'statusKey' => $contract->status,
                    'unlockUrl' => route('contracts.portal.unlock', $contract->secure_token),
                    'logoUrl' => StaffContract::logoPublicUrl(),
                    'isThrottled' => true,
                    'retryAfterSeconds' => $retryAfter,
                    'pinError' => $msg,
                ], 429)
                ->header('Retry-After', (string) $retryAfter)
                ->header('Cache-Control', 'no-store, private');
        }

        $pin = trim((string) $request->input('pin', ''));

        if (!$contract->verifyMemberPin($pin)) {
            RateLimiter::hit($throttleKey, self::UNLOCK_TTL_SECONDS);
            $attemptsUsed = RateLimiter::attempts($throttleKey);
            $remainingAttempts = max(0, self::MAX_PIN_ATTEMPTS - $attemptsUsed);

            $errorMsg = $remainingAttempts > 0
                ? "Incorrect 4-digit PIN ({$remainingAttempts} attempt" . ($remainingAttempts === 1 ? '' : 's') . " remaining)."
                : 'Too many failed PIN attempts. Portal access is now temporarily throttled.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $errorMsg,
                    'errors' => ['pin' => [$errorMsg]],
                ], 422);
            }

            return redirect()
                ->route('contracts.portal.show', $contract->secure_token)
                ->withErrors(['pin' => $errorMsg]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put($this->sessionKey($contract), now()->timestamp);

        if ($request->expectsJson()) {
            return response()->json([
                'unlocked' => true,
                'expires_in_seconds' => self::UNLOCK_TTL_SECONDS,
            ]);
        }

        return redirect()
            ->route('contracts.portal.show', $contract->secure_token)
            ->with('success', 'Contract unlocked for 10 minutes.');
    }

    /**
     * Public / Secure Staff Portal: Sign & permanently lock a Pending Signature contract as Active.
     */
    public function signPortal(Request $request, string $token)
    {
        $contract = $this->findContractByToken($token);
        $this->authorizePortalVisitor($request, $contract);

        if (!$this->isUnlocked($request, $contract)) {
            abort(403, 'Unlock this contract with your 4-digit PIN before signing.');
        }

        if ($contract->status !== StaffContract::STATUS_PENDING_SIGNATURE) {
            throw ValidationException::withMessages([
                'contract' => $contract->isLocked()
                    ? 'This contract is already signed and permanently locked.'
                    : 'Draft contracts cannot be signed until issued for signature.',
            ]);
        }

        $validated = $request->validate([
            'signed_name' => ['nullable', 'string', 'max:160'],
            'signature_data' => ['nullable', 'string', 'max:500000'],
            'signature_type' => ['nullable', 'string', Rule::in(['drawn', 'typed'])],
        ]);

        $signedNameInput = trim((string) ($validated['signed_name'] ?? ''));
        $signatureDataInput = trim((string) ($validated['signature_data'] ?? ''));

        if ($signedNameInput === '' && $signatureDataInput === '') {
            throw ValidationException::withMessages([
                'signature' => 'Please draw your signature or type your full name to sign and accept.',
            ]);
        }

        $isDrawn = $signatureDataInput !== '' && (
            str_starts_with($signatureDataInput, 'data:image/')
            || str_starts_with($signatureDataInput, '<svg')
            || ($validated['signature_type'] ?? null) === 'drawn'
        );

        $signatureType = $isDrawn ? 'drawn' : 'typed';
        $finalSignedName = $signedNameInput !== ''
            ? $signedNameInput
            : $contract->memberName();

        $finalSignatureData = $signatureDataInput !== ''
            ? $signatureDataInput
            : $finalSignedName;

        $updated = DB::transaction(function () use ($contract, $finalSignedName, $finalSignatureData, $signatureType, $request) {
            return StaffContract::whereKey($contract->id)
                ->where('status', StaffContract::STATUS_PENDING_SIGNATURE)
                ->update([
                    'status' => StaffContract::STATUS_ACTIVE,
                    'signed_name' => $finalSignedName,
                    'signature_data' => $finalSignatureData,
                    'signature_type' => $signatureType,
                    'signed_at' => now(),
                    'signed_ip' => $request->ip(),
                ]);
        });

        if (!$updated) {
            throw ValidationException::withMessages([
                'contract' => 'This contract is already signed and permanently locked.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => StaffContract::STATUS_ACTIVE,
            ]);
        }

        return redirect()
            ->route('contracts.portal.show', $contract->secure_token)
            ->with('success', 'Contract signed and locked as Active. You may download your PDF copy anytime.');
    }

    /**
     * Public / Secure Staff Portal & Admin: Download clean Laleen-branded PDF copy.
     */
    public function downloadPdf(Request $request, string $token, StaffContractPdfGenerator $pdfGenerator)
    {
        $contract = $this->findContractByToken($token);
        $this->authorizePortalVisitor($request, $contract);

        if (!$this->isUnlocked($request, $contract)) {
            abort(403, 'Unlock this contract with your 4-digit PIN before downloading the PDF.');
        }

        $pdfBinary = $pdfGenerator->render($contract);
        $slugName = Str::slug($contract->memberName() ?: 'staff');
        $filename = "Laleen-Contract-{$contract->contract_number}-{$slugName}.pdf";

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($pdfBinary),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Resolve target person (Staff or User) from composite team_member ("staff:{id}" / "user:{id}")
     * or explicit staff_id / user_id fields.
     */
    private function resolveTargetMember(Request $request): Staff|User
    {
        $composite = trim((string) ($request->input('team_member') ?? ''));
        if ($composite === '' && is_string($request->input('staff_id')) && str_contains($request->input('staff_id'), ':')) {
            $composite = trim($request->input('staff_id'));
        }

        if ($composite !== '') {
            if (preg_match('/^staff:(\d+)$/i', $composite, $m)) {
                $staff = Staff::find((int) $m[1]);
                if ($staff) {
                    return $staff;
                }
            } elseif (preg_match('/^user:(\d+)$/i', $composite, $m)) {
                $user = User::find((int) $m[1]);
                if ($user) {
                    return $user;
                }
            }

            throw ValidationException::withMessages([
                'team_member' => 'Selected team member was not found.',
            ]);
        }

        $rawStaffId = $request->input('staff_id');
        if ($rawStaffId !== null && $rawStaffId !== '' && is_numeric($rawStaffId)) {
            $staff = Staff::find((int) $rawStaffId);
            if ($staff) {
                return $staff;
            }

            throw ValidationException::withMessages([
                'staff_id' => 'Selected salon staff member was not found.',
            ]);
        }

        $rawUserId = $request->input('user_id');
        if ($rawUserId !== null && $rawUserId !== '' && is_numeric($rawUserId)) {
            $user = User::find((int) $rawUserId);
            if ($user) {
                return $user;
            }

            throw ValidationException::withMessages([
                'user_id' => 'Selected Staff Access account was not found.',
            ]);
        }

        throw ValidationException::withMessages([
            'team_member' => 'Please select a team member.',
        ]);
    }

    private function syncPinAcrossMember(Staff|User $member, string $pin): void
    {
        $member->setContractPin($pin);

        if ($member instanceof Staff && $member->user_id) {
            $linkedUser = $member->user ?? User::find($member->user_id);
            $linkedUser?->setContractPin($pin);
        } elseif ($member instanceof User) {
            $linkedStaff = Staff::where('user_id', $member->id)->first();
            $linkedStaff?->setContractPin($pin);
        }
    }

    private function applyMemberPinUpdate(Request $request, Staff|User $member)
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ], [
            'pin.regex' => 'The contract PIN must be an exact 4-digit number (e.g. 2456).',
        ]);

        $this->syncPinAcrossMember($member, $validated['pin']);

        $contractIds = collect();
        if ($member instanceof Staff) {
            $contractIds = $contractIds->merge(StaffContract::where('staff_id', $member->id)->pluck('id'));
            if ($member->user_id) {
                $contractIds = $contractIds->merge(StaffContract::where('user_id', $member->user_id)->pluck('id'));
            }
        } else {
            $contractIds = $contractIds->merge(StaffContract::where('user_id', $member->id)->pluck('id'));
            $linkedStaffId = Staff::where('user_id', $member->id)->value('id');
            if ($linkedStaffId) {
                $contractIds = $contractIds->merge(StaffContract::where('staff_id', $linkedStaffId)->pluck('id'));
            }
        }

        foreach ($contractIds->unique() as $contractId) {
            $request->session()->forget("contract_pin_unlocked.{$contractId}");
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "4-digit PIN updated for {$member->name}.",
            ]);
        }

        return redirect()
            ->route('staffs.index', ['tab' => 'contracts'])
            ->with('success', "4-digit contract PIN updated for {$member->name}.");
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only administrators can manage staff contracts.');
        }
    }

    private function findContractByToken(string $token): StaffContract
    {
        if (strlen($token) < 32) {
            abort(404);
        }

        return StaffContract::with(['staff', 'user'])
            ->where('secure_token', $token)
            ->firstOrFail();
    }

    /**
     * Admins view all contracts freely; logged-in non-admin CRM users can only access
     * a token belonging to their own user_id or linked Staff profile. Unauthenticated
     * team members using their private link proceed to the 4-digit PIN gate.
     */
    private function authorizePortalVisitor(Request $request, StaffContract $contract): void
    {
        $user = $request->user();
        if (!$user) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $ownsViaUser = $contract->user_id && (int) $contract->user_id === (int) $user->id;
        $viewerStaffId = Staff::where('user_id', $user->id)->value('id');
        $ownsViaStaff = $viewerStaffId && $contract->staff_id && (int) $viewerStaffId === (int) $contract->staff_id;

        if (!$ownsViaUser && !$ownsViaStaff) {
            abort(403, 'You may only access your own secure contract link.');
        }
    }

    private function isUnlocked(Request $request, StaffContract $contract): bool
    {
        $user = $request->user();
        if ($user && $user->isAdmin()) {
            return true;
        }

        $key = $this->sessionKey($contract);
        $unlockedAt = $request->session()->get($key);

        if (!is_numeric($unlockedAt)) {
            return false;
        }

        $elapsed = now()->timestamp - (int) $unlockedAt;
        if ($elapsed < 0 || $elapsed >= self::UNLOCK_TTL_SECONDS) {
            $request->session()->forget($key);
            return false;
        }

        return true;
    }

    private function sessionKey(StaffContract $contract): string
    {
        return "contract_pin_unlocked.{$contract->id}";
    }

    private function throttleKey(Request $request, StaffContract $contract): string
    {
        return 'contract-pin:' . $contract->id . ':' . ($request->ip() ?: 'unknown');
    }
}
