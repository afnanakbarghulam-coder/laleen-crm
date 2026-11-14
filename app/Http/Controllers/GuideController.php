<?php

namespace App\Http\Controllers;

use App\Models\CrmIdea;
use App\Models\CrmIdeaVote;
use App\Models\CrmPresentation;
use App\Models\KpiStaffEvaluation;
use App\Models\OpsActionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class GuideController extends Controller
{
    public const PREVIEW_ROLES = [
        'all' => 'All',
        'agent' => 'Chat Agent',
        'manager' => 'Branch Manager',
        'marketing' => 'Marketing',
        'frontdesk' => 'Front-Desk',
        'ecommerce' => 'E-commerce',
    ];

    /**
     * Safely escape copy and convert [[Label]] tokens into sleek inline UI button chips.
     */
    public static function renderInlineChips(string $text): string
    {
        $escaped = e($text);

        return (string) preg_replace(
            '/\[\[(.+?)\]\]/',
            '<span class="g-ui-pill">$1</span>',
            $escaped
        );
    }

    public function presentations(Request $request)
    {
        return $this->index($request, 'presentations');
    }

    public function ideas(Request $request)
    {
        return $this->index($request, 'ideas');
    }

    public function index(Request $request, ?string $forcedSection = null)
    {
        /** @var User $realUser */
        $realUser = $request->user();
        $isRealAdmin = $realUser->isAdmin();

        $previewRole = null;
        $effectiveUser = $realUser;

        if ($isRealAdmin) {
            $requestedPreview = strtolower(trim((string) $request->query('preview_role', '')));
            if ($requestedPreview === 'branch_manager') {
                $requestedPreview = 'manager';
            } elseif ($requestedPreview === 'marketing_specialist') {
                $requestedPreview = 'marketing';
            }
            if ($requestedPreview === 'social_media_editor') {
                $previewRole = 'social_media_editor';
                $effectiveUser = $this->makePreviewPersona('social_media_editor');
            } elseif ($requestedPreview !== '' && array_key_exists($requestedPreview, self::PREVIEW_ROLES) && $requestedPreview !== 'all') {
                $previewRole = $requestedPreview;
                $effectiveUser = $this->makePreviewPersona($requestedPreview);
            }
        }

        $playbooks = $this->buildAllowedPlaybooks($effectiveUser);
        $defaultTab = $this->resolveDefaultTab($effectiveUser, $playbooks);

        $requestedTab = strtolower(trim((string) $request->query('tab', '')));
        $requestedSection = strtolower(trim((string) ($forcedSection ?? $request->query('section', ''))));

        if ($requestedSection === '' && in_array($requestedTab, ['presentations', 'ideas', 'guides'], true)) {
            $requestedSection = $requestedTab;
        }

        $activeSection = in_array($requestedSection, ['guides', 'presentations', 'ideas'], true)
            ? $requestedSection
            : 'guides';

        $validTabs = array_keys($playbooks);
        if (count($playbooks) > 1) {
            $validTabs[] = 'all';
        }

        $activeTab = in_array($requestedTab, $validTabs, true)
            ? $requestedTab
            : $defaultTab;

        $scopeBadge = $this->resolveScopeBadge($effectiveUser, $previewRole);

        $presentations = collect();
        if (Schema::hasTable('crm_presentations')) {
            $presentations = CrmPresentation::with('creator:id,name')
                ->orderByDesc('is_pinned')
                ->orderByDesc('id')
                ->get();
        }

        $ideas = collect();
        $votedIdeaIds = [];
        if (Schema::hasTable('crm_ideas')) {
            $ideas = CrmIdea::with(['author:id,name,role', 'comments.user:id,name,role', 'opsTask:id,title,due_at,is_completed,pending_with_name'])
                ->orderByRaw("CASE status WHEN 'New Idea' THEN 1 WHEN 'Approved' THEN 2 WHEN 'Converted to Ops' THEN 3 ELSE 4 END ASC")
                ->orderByDesc('upvotes_count')
                ->orderByDesc('id')
                ->get();

            if (Schema::hasTable('crm_idea_votes')) {
                $votedIdeaIds = CrmIdeaVote::where('user_id', $realUser->id)
                    ->pluck('idea_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }

        $isPreviewMode = $previewRole !== null;
        $canManagePresentations = CrmPresentationController::canManage($effectiveUser);
        $canAdminIdeas = $isRealAdmin && !$isPreviewMode;

        $opsAssigneeUsers = collect();
        if ($canAdminIdeas) {
            $opsAssigneeUsers = User::where('is_active', true)
                ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 WHEN 'agent' THEN 3 ELSE 4 END ASC")
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role_label' => User::roleLabel($u->role),
                ]);
        }

        return view('guide.index', [
            'playbooks' => $playbooks,
            'activeTab' => $activeTab,
            'defaultTab' => $defaultTab,
            'activeSection' => $activeSection,
            'scopeBadge' => $scopeBadge,
            'showAdminPreviewBar' => $isRealAdmin,
            'previewRoles' => $isRealAdmin ? self::PREVIEW_ROLES : [],
            'activePreviewRole' => $previewRole ?? 'all',
            'isPreviewMode' => $isPreviewMode,
            'presentations' => $presentations,
            'presentationCategories' => CrmPresentation::CATEGORIES,
            'presentationLayouts' => CrmPresentation::LAYOUTS,
            'canManagePresentations' => $canManagePresentations,
            'canDeletePresentations' => $canAdminIdeas,
            'ideas' => $ideas,
            'ideaCategories' => CrmIdea::CATEGORIES,
            'ideaStatuses' => CrmIdea::STATUSES,
            'votedIdeaIds' => $votedIdeaIds,
            'canAdminIdeas' => $canAdminIdeas,
            'opsAssigneeUsers' => $opsAssigneeUsers,
            'realUserId' => (int) $realUser->id,
        ]);
    }

    /**
     * Build an unsaved in-memory persona so Admins can preview exact role isolation.
     */
    protected function makePreviewPersona(string $previewKey): User
    {
        $persona = new User();

        $configs = [
            'agent' => [
                'name' => 'Areeba',
                'email' => 'agent-preview@laleen.local',
                'role' => 'agent',
                'is_active' => true,
                'permissions' => [
                    'leads' => 'edit',
                    'bookings' => 'edit',
                    'clients' => 'edit',
                    'services' => 'view',
                    'products' => 'view',
                    'kpis' => 'view',
                    'finance' => 'none',
                    'staff_management' => 'none',
                    'ecommerce' => 'none',
                ],
            ],
            'manager' => [
                'name' => 'Ahana',
                'email' => 'manager-preview@laleen.local',
                'role' => 'manager',
                'is_active' => true,
                'permissions' => [
                    'kpis' => 'edit',
                    'bookings' => 'edit',
                    'clients' => 'edit',
                    'services' => 'view',
                    'products' => 'view',
                    'staff_management' => 'view',
                    'leads' => 'none',
                    'finance' => 'none',
                    'ecommerce' => 'none',
                ],
            ],
            'marketing' => [
                'name' => 'Sana',
                'email' => 'marketing-preview@laleen.local',
                'role' => 'marketing_specialist',
                'is_active' => true,
                'permissions' => [
                    'kpis' => 'edit',
                    'clients' => 'view',
                    'leads' => 'none',
                    'bookings' => 'none',
                    'services' => 'none',
                    'products' => 'none',
                    'finance' => 'none',
                    'staff_management' => 'none',
                    'ecommerce' => 'none',
                ],
            ],
            'social_media_editor' => [
                'name' => 'Content Editor',
                'email' => 'editor-preview@laleen.local',
                'role' => 'social_media_editor',
                'is_active' => true,
                'permissions' => User::defaultPermissionsForRole('social_media_editor'),
            ],
            'frontdesk' => [
                'name' => 'Reception',
                'email' => 'frontdesk-preview@laleen.local',
                'role' => 'staff',
                'is_active' => true,
                'permissions' => [
                    'bookings' => 'edit',
                    'services' => 'view',
                    'products' => 'view',
                    'clients' => 'edit',
                    'finance' => 'edit',
                    'leads' => 'none',
                    'kpis' => 'none',
                    'staff_management' => 'none',
                    'ecommerce' => 'none',
                ],
            ],
            'ecommerce' => [
                'name' => 'Ecommerce',
                'email' => 'ecommerce-preview@laleen.local',
                'role' => 'user',
                'is_active' => true,
                'permissions' => [
                    'ecommerce' => 'edit',
                    'leads' => 'none',
                    'bookings' => 'none',
                    'services' => 'none',
                    'products' => 'none',
                    'clients' => 'none',
                    'kpis' => 'none',
                    'finance' => 'none',
                    'staff_management' => 'none',
                ],
            ],
        ];

        $persona->forceFill($configs[$previewKey] ?? $configs['agent']);

        return $persona;
    }

    /**
     * Resolve clean workspace badge for the top header.
     */
    protected function resolveScopeBadge(User $user, ?string $previewRole): array
    {
        if ($previewRole === 'social_media_editor') {
            return [
                'label' => 'Social Media Editor View',
                'tone' => 'amber',
            ];
        }

        if ($previewRole !== null && isset(self::PREVIEW_ROLES[$previewRole])) {
            return [
                'label' => self::PREVIEW_ROLES[$previewRole] . ' View',
                'tone' => 'amber',
            ];
        }

        if ($user->isSuperAdmin()) {
            return ['label' => 'Super-Admin', 'tone' => 'emerald'];
        }

        if ($user->isAdmin()) {
            return ['label' => 'Admin', 'tone' => 'emerald'];
        }

        $managedBranch = KpiStaffEvaluation::managedBranchFor($user);
        if ($managedBranch === 'old_airport') {
            return ['label' => 'Branch Manager · Old Airport', 'tone' => 'rose'];
        }
        if ($managedBranch === 'wakrah') {
            return ['label' => 'Branch Manager · Al Wakrah', 'tone' => 'rose'];
        }

        if (OpsActionItem::isArbab($user)) {
            return ['label' => 'Operations Lead', 'tone' => 'rose'];
        }

        $role = strtolower(trim((string) $user->role));
        if ($role === 'agent' || preg_match('/\bladen\b/i', (string) $user->name)) {
            return ['label' => 'Customer Service & Booking Desk', 'tone' => 'champagne'];
        }

        if ($role === 'marketing_specialist') {
            return ['label' => 'Marketing Specialist', 'tone' => 'champagne'];
        }

        if ($role === 'social_media_editor') {
            return ['label' => 'Social Media Editor', 'tone' => 'champagne'];
        }

        if (preg_match('/\b(ayan|sana)\b/i', (string) $user->name)) {
            return ['label' => 'Marketing & Content', 'tone' => 'champagne'];
        }

        if ($role === 'manager') {
            return ['label' => 'Branch Operations', 'tone' => 'rose'];
        }

        return ['label' => 'Team Member', 'tone' => 'champagne'];
    }

    /**
     * Auto-select the user's primary persona playbook tab.
     *
     * @param  array<string, array<string, mixed>>  $playbooks
     */
    protected function resolveDefaultTab(User $user, array $playbooks): string
    {
        if (empty($playbooks)) {
            return 'general';
        }

        $role = strtolower(trim((string) $user->role));
        $name = trim((string) $user->name);

        if ($user->isAdmin()) {
            return count($playbooks) > 1 ? 'all' : (string) array_key_first($playbooks);
        }

        if (($role === 'agent' || preg_match('/\bladen\b/i', $name)) && isset($playbooks['chat_cs'])) {
            return 'chat_cs';
        }

        if (
            (in_array($role, ['marketing_specialist', 'social_media_editor'], true) || preg_match('/\b(ayan|sana)\b/i', $name))
            && isset($playbooks['marketing_content'])
        ) {
            return 'marketing_content';
        }

        if (
            (KpiStaffEvaluation::managedBranchFor($user) !== null || OpsActionItem::isArbab($user) || $role === 'manager')
            && isset($playbooks['managers_hr'])
        ) {
            return 'managers_hr';
        }

        if (isset($playbooks['frontdesk_pos'])) {
            return 'frontdesk_pos';
        }

        return (string) array_key_first($playbooks);
    }

    protected function userCanView(User $user, string $module): bool
    {
        return $user->isAdmin() || $user->canView($module);
    }

    protected function userCanEdit(User $user, string $module): bool
    {
        return $user->isAdmin() || $user->canEdit($module);
    }

    /**
     * Resolve an authorized action link for a card (returns null when the user lacks route permission).
     */
    protected function authorizedAction(User $user, string $label, string $routeName, array $params = [], ?string $gateType = null, ?string $module = null): ?array
    {
        if (! Route::has($routeName)) {
            return null;
        }

        $allowed = match ($gateType) {
            'super_admin' => $user->isSuperAdmin(),
            'admin' => $user->isAdmin(),
            'module_edit' => $module ? $this->userCanEdit($user, $module) : false,
            'module_view' => $module ? $this->userCanView($user, $module) : false,
            default => true,
        };

        if (! $allowed) {
            return null;
        }

        return [
            'label' => $label,
            'url' => route($routeName, $params),
        ];
    }

    /**
     * Build strictly server-filtered, zero-repetition playbooks — zero HTML leakage.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function buildAllowedPlaybooks(User $user): array
    {
        $isAdmin = $user->isAdmin();
        $isSuperAdmin = $user->isSuperAdmin();
        $isArbab = OpsActionItem::isArbab($user);
        $managedBranch = KpiStaffEvaluation::managedBranchFor($user);
        $role = strtolower(trim((string) $user->role));
        $isLaden = (bool) preg_match('/\bladen\b/i', (string) $user->name);
        $isPureChatAgent = ($role === 'agent' && ! $isAdmin && ! $isLaden);

        $playbooks = [];

        // =====================================================================
        // PLAYBOOK 1: CHAT AGENTS & CS LEAD (chat_cs)
        // =====================================================================
        if ($isAdmin || $role === 'agent' || $this->userCanView($user, 'leads')) {
            $chatTargets = [
                ['value' => '13 / Day', 'label' => 'Bookings', 'icon' => 'bx-calendar-check'],
                ['value' => '≥ 85%', 'label' => 'Chat Quality', 'icon' => 'bx-message-check'],
                ['value' => '≥ 30%', 'label' => 'Team Lead Conv', 'icon' => 'bx-trending-up'],
            ];

            if (! $isPureChatAgent) {
                $chatTargets[] = ['value' => '≤ 48h', 'label' => 'Complaint SLA', 'icon' => 'bx-timer'];
            }

            $chatQualityCard = $isPureChatAgent
                ? [
                    'id' => 'cs-chat-qa-sla',
                    'icon' => 'bx-award',
                    'target' => 'Target: ≥ 85%',
                    'title' => 'Chat Quality Standards',
                    'flow' => [
                        ['title' => 'Reply Promptly', 'desc' => 'Greet warmly using consultative luxury salon tone.'],
                        ['title' => 'Confirm Branch', 'desc' => 'Verify exact location, date, and service before closing.'],
                        ['title' => 'Review Feedback', 'desc' => 'Click [[View Report]] on graded 15-point chat audits.'],
                    ],
                    'do_rule' => 'Escalate unhappy client chats to your Lead immediately',
                    'avoid_rule' => 'Avoid rushed, one-word, or unbranded replies',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Chat QA ↗', 'kpi.chat-eval.index', [], 'module_view', 'kpis'),
                    ])),
                ]
                : [
                    'id' => 'cs-chat-qa-sla',
                    'icon' => 'bx-award',
                    'target' => 'Target: ≥ 85% · ≤ 48h SLA',
                    'title' => 'Chat Quality & Complaint SLA',
                    'flow' => [
                        ['title' => 'Coach Chat Tone', 'desc' => 'Maintain warm hospitality and clear branch confirmation.'],
                        ['title' => 'Inspect QA Audits', 'desc' => 'Click [[View Report]] on 15-point chat scorecards.'],
                        ['title' => 'Resolve Under 48h', 'desc' => 'Close logged client complaints with documented action.'],
                    ],
                    'do_rule' => 'Open complaints under 48h remain inside grace window',
                    'avoid_rule' => 'Any complaint open past 48h breaches monthly SLA',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Chat QA ↗', 'kpi.chat-eval.index', [], 'module_view', 'kpis'),
                    ])),
                ];

            $playbooks['chat_cs'] = [
                'key' => 'chat_cs',
                'title' => $isPureChatAgent ? 'Chat & Leads' : 'Chat Agents & CS Lead',
                'short_title' => 'Chat & Leads',
                'icon' => 'bx-message-rounded-dots',
                'targets' => $chatTargets,
                'cards' => [
                    [
                        'id' => 'cs-shift-checkin',
                        'icon' => 'bx-time-five',
                        'target' => 'Shift Start',
                        'title' => 'Daily Shift Check-In',
                        'flow' => [
                            ['title' => 'Open Shift Tab', 'desc' => 'Go to [[Agent Shift Log]] inside Agents Target Reports.'],
                            ['title' => 'Log Sign-In', 'desc' => 'Click [[+ Log Sign-In]] and choose Morning or Evening.'],
                            ['title' => 'Watch Live Pace', 'desc' => 'Track your Today and MTD booking count on top.'],
                        ],
                        'do_rule' => 'Sign in using your own individual login',
                        'avoid_rule' => 'Need a time fix? Ask Admin (logs lock after save)',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open Shift Log ↗', 'kpi.agents.index', ['tab' => 'shifts'], 'module_view', 'kpis'),
                        ])),
                    ],
                    [
                        'id' => 'cs-leads-workflow',
                        'icon' => 'bx-conversation',
                        'target' => 'Target: ≥ 30% Conv',
                        'title' => 'Leads Priority & AI Closer',
                        'inline_strip' => 'lead_priority',
                        'flow' => [
                            ['title' => 'Work Urgent First', 'desc' => 'Filter [[🔴 Overdue Follow-ups]] in priority order above.'],
                            ['title' => 'Copy AI Script', 'desc' => 'Click [[✨ AI Script]] for EN / عربي objection replies.'],
                            ['title' => 'Set Next Callback', 'desc' => 'Mark [[💬 Contacted / In Progress]] with follow-up date.'],
                        ],
                        'do_rule' => 'Saving a Calendar visit auto-locks status to [[✅ Booked]]',
                        'avoid_rule' => 'Never leave active leads without a follow-up date',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open Leads ↗', 'leads.index', [], 'module_view', 'leads'),
                        ])),
                    ],
                    [
                        'id' => 'cs-booking-quota',
                        'icon' => 'bx-calendar-check',
                        'target' => 'Target: 13/day',
                        'title' => '13/Day Booking Quota',
                        'flow' => [
                            ['title' => 'Pick Stylist & Time', 'desc' => 'Choose branch, date, start time, and stylist on Calendar.'],
                            ['title' => 'Select Booking Agent', 'desc' => 'Pick your name inside the [[Booking Agent]] dropdown.'],
                            ['title' => 'Saved = +1 Credit', 'desc' => 'Saving onto the grid counts toward your daily target.'],
                        ],
                        'do_rule' => 'Kept even if client Cancels/No-Shows · Max +1 per unique client/day',
                        'avoid_rule' => 'Blank [[Booking Agent]] or [[Unscheduled]] queue = 0 until placed on grid',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open Calendar ↗', 'appointments.calendar', [], 'module_view', 'bookings'),
                        ])),
                    ],
                    $chatQualityCard,
                ],
            ];
        }

        // =====================================================================
        // PLAYBOOK 2: FRONT-DESK, CALENDAR, COMBOS & POS (frontdesk_pos)
        // =====================================================================
        if ($isAdmin || $this->userCanView($user, 'bookings') || $this->userCanView($user, 'services')) {
            $frontdeskCards = [];

            if ($isAdmin || $this->userCanView($user, 'bookings')) {
                $frontdeskCards[] = [
                    'id' => 'fd-calendar-mastery',
                    'icon' => 'bx-calendar-star',
                    'target' => 'Live Floor',
                    'title' => 'Calendar & Floor Control',
                    'flow' => [
                        ['title' => 'Pick Active Branch', 'desc' => 'Select Old Airport or Al Wakrah inside [[Filters]].'],
                        ['title' => 'Book Or Queue', 'desc' => 'Click a time slot, or choose [[Decide in Salon]] if unsure.'],
                        ['title' => 'Update Visit Status', 'desc' => 'Move Pending to Arrived, or mark [[Cancel]] / [[No show]].'],
                    ],
                    'do_rule' => 'Brief stylists whenever a red Allergy banner appears',
                    'avoid_rule' => 'Never delete cancelled visits (Cancel triggers lead recovery)',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Calendar ↗', 'appointments.calendar', [], 'module_view', 'bookings'),
                    ])),
                ];
            }

            $comboVisit1DoRule = 'Build distinct-service packages in [[Services]] → [[Combos]]';
            if ($user->isAdmin()) {
                $comboVisit1DoRule .= ' · Admin: [[Manage / Extend]] on profile';
            }

            $frontdeskCards[] = [
                'id' => 'fd-combos-visit1',
                'icon' => 'bx-package',
                'target' => '7-Day Validity',
                'title' => 'Combos · Visit 1 & Pending Slots',
                'flow' => [
                    ['title' => 'Add Combo Services', 'desc' => 'Click [[+ Add Combo]] and tick [[Do today]] for Visit 1; unchecked slots save as Pending.'],
                    ['title' => 'Pay 100% At Checkout', 'desc' => 'Click [[💳 Checkout]] → [[Complete Sale]] to unlock pending slots and start the 7-day clock.'],
                    ['title' => 'Check Client Profile', 'desc' => 'Open [[View Full Profile]] to track [[Combo Packages]], remaining slots, and expiry date.'],
                ],
                'do_rule' => $comboVisit1DoRule,
                'avoid_rule' => 'Pending slots stay locked until Visit 1 is paid in full',
                'actions' => array_values(array_filter([
                    $this->authorizedAction($user, 'Open Calendar ↗', 'appointments.calendar', [], 'module_view', 'bookings'),
                    $this->authorizedAction($user, 'Combos ↗', 'combos.index', [], 'module_view', 'services'),
                ])),
            ];

            $frontdeskCards[] = [
                'id' => 'fd-combos-visit2',
                'icon' => 'bx-gift',
                'target' => '0.00 QAR Checkout',
                'title' => 'Combos · Visit 2+ Redemption',
                'flow' => [
                    ['title' => 'Select Returning Client', 'desc' => 'Pick client by name or phone on the Calendar to load her active package badge.'],
                    ['title' => 'Redeem Pending Slots', 'desc' => 'Click [[Redeem Package]], tick today\'s remaining services, and assign any stylist per slot.'],
                    ['title' => 'Close At 0.00 QAR', 'desc' => 'Click [[💳 Checkout]] → [[Complete Sale]] ([[0.00 QAR]]) to mark Used and split staff credit ([[1/N]]).'],
                ],
                'do_rule' => 'Click [[Cancel]] on Visit 2 (never delete) to restore slots to her profile',
                'avoid_rule' => 'Unused pending slots auto-expire after Day 7 (Doha time)',
                'actions' => array_values(array_filter([
                    $this->authorizedAction($user, 'Open Calendar ↗', 'appointments.calendar', [], 'module_view', 'bookings'),
                ])),
            ];

            $frontdeskCards[] = [
                'id' => 'fd-upsells-retail',
                'icon' => 'bx-up-arrow-circle',
                'target' => 'Target: 1,700 QAR/mo',
                'title' => 'Chair-Side Upsells & Retail',
                'flow' => [
                    ['title' => 'Open Visit Drawer', 'desc' => 'Click the client\'s appointment block on the Calendar.'],
                    ['title' => 'Add Chair Upgrade', 'desc' => 'Click [[+ Add upsell]] and pick Service or Product.'],
                    ['title' => 'Credit Performing Staff', 'desc' => 'Select responsible stylist and save before checkout.'],
                ],
                'do_rule' => 'Checkout automatically deducts sold retail stock',
                'avoid_rule' => 'Replenish any retail SKU flagged [[Low Stock]] (≤ 3 units)',
                'actions' => array_values(array_filter([
                    $this->authorizedAction($user, 'Open Products ↗', 'products.index', [], 'module_view', 'products'),
                ])),
            ];

            // STRICT GATE: POS Checkout & Split Payments card only rendered when $isAdmin || $user->canView('finance')
            if ($isAdmin || $this->userCanView($user, 'finance')) {
                $frontdeskCards[] = [
                    'id' => 'fd-pos-checkout',
                    'icon' => 'bx-credit-card-front',
                    'target' => 'Exact Tender',
                    'title' => 'POS Checkout & Split Payments',
                    'flow' => [
                        ['title' => 'Open POS Ticket', 'desc' => 'Click [[💳 Checkout]] on drawer or [[New Sale]] for walk-ins.'],
                        ['title' => 'Apply Points Or Tip', 'desc' => 'Add optional discount, tip, or [[Use Max]] loyalty points.'],
                        ['title' => 'Match Split Tender', 'desc' => 'Balance Cash, Card, and Online, then click [[Complete Sale]].'],
                    ],
                    'do_rule' => 'Attach client profile to award 1 loyalty point per QAR',
                    'avoid_rule' => 'Split payments off by > 0.01 QAR are rejected',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Walk-In Sale ↗', 'sales.create', [], 'module_edit', 'finance'),
                    ])),
                ];
            }

            $playbooks['frontdesk_pos'] = [
                'key' => 'frontdesk_pos',
                'title' => 'Calendar, Combos & Reception',
                'short_title' => 'Calendar & Combos',
                'icon' => 'bx-calendar-star',
                'targets' => [
                    ['value' => 'Visit 1 Paid', 'label' => 'Starts 7-Day Clock', 'icon' => 'bx-time-five'],
                    ['value' => '0.00 QAR', 'label' => 'Visit 2 Checkout', 'icon' => 'bx-gift'],
                    ['value' => 'Equal Split', 'label' => 'Per-Slot Staff Share', 'icon' => 'bx-group'],
                ],
                'cards' => $frontdeskCards,
            ];
        }

        // =====================================================================
        // PLAYBOOK 3: CLIENTS, LOYALTY & RETENTION RADAR (clients_retention)
        // =====================================================================
        if ($isAdmin || $this->userCanView($user, 'clients')) {
            $clientCards = [
                [
                    'id' => 'cl-360-profile',
                    'icon' => 'bx-user-circle',
                    'target' => '1 Pt = 1 QAR',
                    'title' => 'Client 360 & Allergy Alerts',
                    'flow' => [
                        ['title' => 'Check 360 Summary', 'desc' => 'Review LTV, visit history, loyalty points, and pending slots.'],
                        ['title' => 'Save Allergy Alert', 'desc' => 'Record sensitivities via [[Save Alert]] to warn reception.'],
                        ['title' => 'Rebook Or Upgrade', 'desc' => 'Pitch recommended Combos or click [[📅 Rebook Favorite Visit]].'],
                    ],
                    'do_rule' => 'Check Beauty Planner dates for timely touch-up outreach',
                    'avoid_rule' => 'Clients with visit or sale history cannot be deleted',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Clients ↗', 'customers.index', [], 'module_view', 'clients'),
                    ])),
                ],
                [
                    'id' => 'cl-retention-radar',
                    'icon' => 'bx-radar',
                    'target' => '14d Cooldown',
                    'title' => 'Retention Radar Win-Backs',
                    'flow' => [
                        ['title' => 'Select Radar Tab', 'desc' => 'Open [[Due for Refill]], [[Packages Ending]], or [[Missing VIPs]].'],
                        ['title' => 'Copy AI Outreach', 'desc' => 'Click [[✨ AI Script]] for personalized EN / عربي messages.'],
                        ['title' => 'Push To Leads', 'desc' => 'Click [[Send Overdue to Leads]] to queue chat follow-ups.'],
                    ],
                    'do_rule' => '14-day cooldown automatically skips recently contacted VIPs',
                    'avoid_rule' => 'Never message overdue VIPs outside logged CRM workflows',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Due for Refill ↗', 'customers.index', ['segment' => 'due_for_refill'], 'module_view', 'clients'),
                    ])),
                ],
            ];

            // STRICT ADMIN GATE: Admin Package Override (Manage / Extend) card ONLY rendered for Admins
            if ($isAdmin) {
                $clientCards[] = [
                    'id' => 'cl-admin-package-override',
                    'icon' => 'bx-slider-alt',
                    'target' => 'Admin Only',
                    'title' => 'Admin Package Override',
                    'flow' => [
                        ['title' => 'Open Combo Card', 'desc' => 'Locate Combo Packages on the client\'s 360 profile.'],
                        ['title' => 'Extend Expiry Date', 'desc' => 'Click [[Manage / Extend]] and pick +7d, +14d, or custom date.'],
                        ['title' => 'Log Audit Reason', 'desc' => 'Enter mandatory reason (3+ chars) to reactivate slots.'],
                    ],
                    'do_rule' => 'Future expiry automatically restores expired packages to active',
                    'avoid_rule' => 'Never extend unpaid booking placeholder records',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Clients ↗', 'customers.index', [], 'module_view', 'clients'),
                    ])),
                ];
            }

            $playbooks['clients_retention'] = [
                'key' => 'clients_retention',
                'title' => 'Clients, Loyalty & Retention Radar',
                'short_title' => 'Clients & Retention',
                'icon' => 'bx-heart-circle',
                'targets' => [
                    ['value' => '1 Pt = 1 QAR', 'label' => 'Loyalty', 'icon' => 'bx-diamond'],
                    ['value' => '≤ 1 Slot / 21d', 'label' => 'Package Ending', 'icon' => 'bx-package'],
                    ['value' => '14 Days', 'label' => 'Outreach Cooldown', 'icon' => 'bx-shield-quarter'],
                ],
                'cards' => $clientCards,
            ];
        }

        // =====================================================================
        // PLAYBOOK 4: PAID ADS & ORGANIC CONTENT (marketing_content)
        // =====================================================================
        if ($isAdmin || in_array($role, ['marketing_specialist', 'social_media_editor'], true) || ($this->userCanView($user, 'kpis') && $role !== 'agent')) {
            $replySlaStep3 = $isAdmin
                ? ['title' => 'Admin Breach Log', 'desc' => 'Log verified >2h incidents on Sana\'s card with screenshot.']
                : ['title' => 'Keep Zero Late', 'desc' => 'Maintain 0 late replies across Sana and Ayan scorecards.'];

            $playbooks['marketing_content'] = [
                'key' => 'marketing_content',
                'title' => 'Paid Ads & Organic Content',
                'short_title' => 'Ads & Content',
                'icon' => 'bx-bullseye',
                'targets' => [
                    ['value' => '2,500 QAR', 'label' => 'Ad Rev / Branch', 'icon' => 'bx-bullseye'],
                    ['value' => '4 Reels + 7 Stories', 'label' => 'Weekly', 'icon' => 'bx-movie-play'],
                    ['value' => '0 Late', 'label' => '>2h Social SLA', 'icon' => 'bx-bolt-circle'],
                ],
                'cards' => [
                    [
                        'id' => 'mk-paid-ads',
                        'icon' => 'bx-bullseye',
                        'target' => 'Target: 2,500 QAR/Branch',
                        'title' => 'Paid Ads Attribution',
                        'inline_strip' => 'ads_separation',
                        'flow' => [
                            ['title' => 'Log Ad Inquiry', 'desc' => 'Click [[+ Add Entry]] with accurate 8-digit Qatar phone.'],
                            ['title' => 'Send Closing Script', 'desc' => 'Click [[🧠 Generate AI Closing Script]] to convert fast.'],
                            ['title' => 'Sync Paid POS', 'desc' => 'Same-month POS checkout syncs revenue and branch automatically.'],
                        ],
                        'do_rule' => 'Manage 70/20/10 creative tests inside Weekly Ads Plan',
                        'avoid_rule' => 'Unpaid scheduled bookings do not count toward ad revenue',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open Paid Ads ↗', 'kpi.ads.index', [], 'module_view', 'kpis'),
                        ])),
                    ],
                    [
                        'id' => 'mk-content-calendar',
                        'icon' => 'bx-movie-play',
                        'target' => 'Target: ≥ 90% Pace',
                        'title' => 'Weekly Content Calendar',
                        'flow' => [
                            ['title' => 'Assign Weekly Grid', 'desc' => 'Set branch, owner, and pillar (max 3 [[— No Reel Day]]).'],
                            ['title' => 'Paste Live Proof', 'desc' => 'Enter concept and paste [[🔗 Reel Asset / Live URL]] link.'],
                            ['title' => 'Mark Posted & Save', 'desc' => 'Switch [[Pending]] to [[Posted ✓]], then click [[Save Weekly Plan]].'],
                        ],
                        'do_rule' => 'MTD targets prorate strictly across in-month days until midnight',
                        'avoid_rule' => 'Empty concept or asset link blocks switching to [[Posted ✓]]',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open Content Calendar ↗', 'kpi.content-calendar.index', [], 'module_view', 'kpis'),
                        ])),
                    ],
                    [
                        'id' => 'mk-reply-sla',
                        'icon' => 'bx-bolt-circle',
                        'target' => 'Target: 0 Late',
                        'title' => 'Social Reply 2h SLA',
                        'flow' => [
                            ['title' => 'Reply Under 2h', 'desc' => 'Answer IG, TikTok, and FB comments and DMs promptly.'],
                            ['title' => 'Review SLA Banner', 'desc' => 'Click [[View Screenshot]] on red breach alerts for coaching.'],
                            $replySlaStep3,
                        ],
                        'do_rule' => 'Sweep social inboxes every 60–90 minutes during operating hours',
                        'avoid_rule' => 'Even 1 verified >2h late reply drops monthly SLA score to 0%',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Open KPI Hub ↗', 'kpi.hub', [], 'module_view', 'kpis'),
                        ])),
                    ],
                ],
            ];
        }

        // =====================================================================
        // PLAYBOOK 5: BRANCH MANAGERS, STAFF SALES & HR (managers_hr)
        // =====================================================================
        if ($isAdmin || $this->userCanView($user, 'staff_management') || $managedBranch !== null || $isArbab || $role === 'manager') {
            $mysteryStep3 = $isAdmin
                ? ['title' => 'Log Official Audit', 'desc' => 'Click [[+ New Branch Evaluation]] to score 30-point audit.']
                : ['title' => 'Coach Weak Areas', 'desc' => 'Run morning huddles on any section scoring below 85%.'];

            $complaintStep2Desc = $user->isAdmin()
                ? 'Log [[Corrective Action]], redo impact, [[Manager Feedback]], and Admin [[Deduction]] or [[Generate Notice]] terms.'
                : 'Log [[Corrective Action]], redo booking or revenue impact, and [[Manager Feedback]] coaching notes.';

            $managerCards = [
                [
                    'id' => 'mgr-monday-evals',
                    'icon' => 'bx-user-check',
                    'target' => 'Target: 100% Roster',
                    'title' => 'Monday Staff Evaluation Compliance',
                    'inline_strip' => 'manager_window',
                    'flow' => [
                        ['title' => 'Start Weekly Audit', 'desc' => 'Click [[+ New Evaluation]] on Monday or Tuesday.'],
                        ['title' => 'Score Full Roster', 'desc' => 'Grade every stylist 1–5, including leave or sick staff.'],
                        ['title' => 'Hit Fully Evaluated', 'desc' => 'Submit until your branch status shows [[Fully Evaluated]].'],
                    ],
                    'do_rule' => 'Document leave or sick context inside Leader Notes',
                    'avoid_rule' => 'Submissions lock Wednesday 00:00 Doha time (no backdating)',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Evaluations ↗', 'kpi.evaluations.index', [], 'module_view', 'kpis'),
                    ])),
                ],
                [
                    'id' => 'mgr-mystery-shopper',
                    'icon' => 'bx-store',
                    'target' => 'Target: ≥ 85%',
                    'title' => 'Branch Mystery Shopper Assessments',
                    'flow' => [
                        ['title' => 'Uphold Floor Standards', 'desc' => 'Maintain 5-star greeting, hygiene, consultation, and checkout.'],
                        ['title' => 'Inspect Audit Report', 'desc' => 'Filter your branch and click [[View Report]] for feedback.'],
                        $mysteryStep3,
                    ],
                    'do_rule' => 'At least 1 official Admin audit is required each month',
                    'avoid_rule' => '0 audits logged in a month scores 0% (no self-grading)',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Branch Audits ↗', 'kpi.branch-evaluation.index', [], 'module_view', 'kpis'),
                    ])),
                ],
                [
                    'id' => 'mgr-staff-sales',
                    'icon' => 'bx-line-chart',
                    'target' => 'Target: 1,700 QAR/Staff',
                    'title' => 'Staff Sales & Upsell Coaching',
                    'flow' => [
                        ['title' => 'Filter Your Branch', 'desc' => 'Select Old Airport or Al Wakrah on Staff Sales.'],
                        ['title' => 'Spot Zero Upsells', 'desc' => 'Check prorated targets and stylists sitting at 0 QAR.'],
                        ['title' => 'Coach Chair Upgrades', 'desc' => 'Brief express add-on scripts during morning huddles.'],
                    ],
                    'do_rule' => 'Compare branch leaders inside the Analytics tab',
                    'avoid_rule' => 'Reception must log upgrades via [[+ Add upsell]] for credit',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Staff Sales ↗', 'kpi.staff-sales.index', [], 'module_view', 'kpis'),
                    ])),
                ],
                [
                    'id' => 'mgr-complaints-intake',
                    'icon' => 'bx-message-alt-error',
                    'target' => 'Same-Day Log',
                    'title' => 'Complaints · Logging & Intake',
                    'flow' => [
                        ['title' => 'Open & Attach Client', 'desc' => 'Click [[+ Log Complaint]], select [[Old Airport]] or [[Al Wakrah]], and link the client profile.'],
                        ['title' => 'Tag Staff & Issue', 'desc' => 'Pick [[Staff Involved]], [[Service Received]], and [[Category]], then write factual [[What Happened]] notes.'],
                        ['title' => 'Save & Start SLA', 'desc' => 'Click [[Save Complaint]] to log as [[Pending HR]] and start the 48h clock.'],
                    ],
                    'do_rule' => 'Log every walk-in, WhatsApp, or review complaint the same day',
                    'avoid_rule' => 'Never promise free redos or refunds off-system without a logged ticket',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Complaints ↗', 'staffs.index', ['tab' => 'complaints'], 'module_view', 'staff_management'),
                    ])),
                ],
                [
                    'id' => 'mgr-complaints-resolution',
                    'icon' => 'bx-check-shield',
                    'target' => 'SLA: ≤ 48 Hours',
                    'title' => 'Complaints · 48h Resolution & Outcome',
                    'flow' => [
                        ['title' => 'Contact Within 48h', 'desc' => 'Reach out within 48 hours to investigate and confirm redo, credit, or refund recovery.'],
                        ['title' => 'Record Recovery & Impact', 'desc' => $complaintStep2Desc],
                        ['title' => 'Close & Feed HR Review', 'desc' => 'Mark [[Resolved]] via [[Save Resolutions]] once recovery completes; incidents feed Mon–Tue staff evaluations.'],
                    ],
                    'do_rule' => 'Review open branch complaints daily until zero tickets breach 48h',
                    'avoid_rule' => 'Never mark [[Resolved]] before the client confirms or completes her redo/refund',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Complaints ↗', 'staffs.index', ['tab' => 'complaints'], 'module_view', 'staff_management'),
                    ])),
                ],
            ];

            if ($isAdmin || $this->userCanView($user, 'staff_management')) {
                $managerCards[] = [
                    'id' => 'mgr-hr-payroll-shifts',
                    'icon' => 'bx-id-card',
                    'target' => 'Live Roster',
                    'title' => 'Roster, Workspace & Shifts',
                    'flow' => [
                        ['title' => 'Assign Staff Services', 'desc' => 'Enable bookable status and qualified services in [[Staff Workspace]].'],
                        ['title' => 'Block Leave Shifts', 'desc' => 'Log Leave or Sick inside [[Scheduled shifts]] immediately.'],
                        ['title' => 'Track Staff Overtime', 'desc' => 'Log approved extra hours via [[+ Log Overtime]] for payroll.'],
                    ],
                    'do_rule' => 'Review prorated salary and overtime inside [[Payroll & Overtime]]',
                    'avoid_rule' => 'Unassigned services show "No staff available" on Calendar',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Staff Directory ↗', 'staffs.index', [], 'module_view', 'staff_management'),
                    ])),
                ];
            }

            if ($isAdmin || $this->userCanView($user, 'staff_management') || $isArbab) {
                $managerCards[] = [
                    'id' => 'mgr-ops-tracker',
                    'icon' => 'bx-task',
                    'target' => 'On-Time SLA',
                    'title' => 'Ops & Action Tracker',
                    'flow' => [
                        ['title' => 'Filter My Tasks', 'desc' => 'Open [[⚡ Ops & Action Tracker]] and check Overdue items.'],
                        ['title' => 'Post Solution Notes', 'desc' => 'Add progress notes before the SLA timer expires.'],
                        ['title' => 'Verify Completion', 'desc' => 'Admin or Ops Lead marks verified tasks Completed.'],
                    ],
                    'do_rule' => 'Unfinished tasks carry over automatically into next week',
                    'avoid_rule' => 'Only Admin or Owner can delete tracker tasks',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Ops Tracker ↗', 'staff.ops-tracker.index', [], 'module_view', 'staff_management'),
                    ])),
                ];
            }

            $playbooks['managers_hr'] = [
                'key' => 'managers_hr',
                'title' => 'Branch Managers, Staff Sales & HR',
                'short_title' => 'Managers & HR',
                'icon' => 'bx-briefcase-alt-2',
                'targets' => [
                    ['value' => 'Mon – Tue', 'label' => 'Doha Eval Window', 'icon' => 'bx-calendar-event'],
                    ['value' => '100% Roster', 'label' => 'Incl. Leave / Sick', 'icon' => 'bx-user-check'],
                    ['value' => '≥ 85%', 'label' => 'Mystery Shopper', 'icon' => 'bx-award'],
                    ['value' => '1,700 QAR', 'label' => 'Staff Upsell', 'icon' => 'bx-up-arrow-circle'],
                    ['value' => '≤ 48h', 'label' => 'Complaint SLA', 'icon' => 'bx-timer'],
                ],
                'cards' => $managerCards,
            ];
        }

        // =====================================================================
        // PLAYBOOK 6: E-COMMERCE HUB (ecommerce)
        // =====================================================================
        if ($isAdmin || $this->userCanView($user, 'ecommerce')) {
            $playbooks['ecommerce'] = [
                'key' => 'ecommerce',
                'title' => 'E-commerce Hub (PK & QA)',
                'short_title' => 'E-commerce',
                'icon' => 'bx-store-alt',
                'targets' => [
                    ['value' => 'PKR & QAR', 'label' => 'Isolated Regions', 'icon' => 'bx-globe'],
                    ['value' => 'Raw → Batch', 'label' => '2-Tier Stock', 'icon' => 'bx-layer'],
                    ['value' => 'CAC / MER / AOV', 'label' => 'Live Pulse', 'icon' => 'bx-bar-chart-alt-2'],
                ],
                'cards' => [
                    [
                        'id' => 'ec-financials-inventory',
                        'icon' => 'bx-layer',
                        'target' => 'True BOM COGS',
                        'title' => 'Regions, BOM & 2-Tier Stock',
                        'flow' => [
                            ['title' => 'Select Region Pill', 'desc' => 'Toggle [[PK]] (PKR) or [[QA]] (QAR) workstation first.'],
                            ['title' => 'Set BOM Pricing', 'desc' => 'Attach raw-material component costs to every SKU.'],
                            ['title' => 'Mint Batch Stock', 'desc' => 'Convert Tier-1 raw stock into Tier-2 sellable units.'],
                        ],
                        'do_rule' => 'Log partner capital and expenses to keep Runway accurate',
                        'avoid_rule' => 'SKUs missing BOM pricing distort net profit margins',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Ecommerce Hub ↗', 'ecommerce.index', [], 'module_view', 'ecommerce'),
                        ])),
                    ],
                    [
                        'id' => 'ec-customers-sales-ai',
                        'icon' => 'bx-cart-alt',
                        'target' => 'COD & Refills',
                        'title' => 'Orders, Refills & AI Sales',
                        'flow' => [
                            ['title' => 'Confirm COD Fast', 'desc' => 'Verify unconfirmed COD orders within 48h before dispatch.'],
                            ['title' => 'Work Refill Queue', 'desc' => 'Trigger Day-7 cross-sells and Day-30 refill nudges.'],
                            ['title' => 'Handle Escalations', 'desc' => 'Step into AI WhatsApp threads flagged for human reply.'],
                        ],
                        'do_rule' => 'Shopify and manual orders unify automatically on customer profiles',
                        'avoid_rule' => 'Dispatching unconfirmed COD orders inflates return losses',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Customers & Sales ↗', 'ecommerce.customers.index', [], 'module_view', 'ecommerce'),
                        ])),
                    ],
                    [
                        'id' => 'ec-marketing-kpis',
                        'icon' => 'bx-bar-chart-alt-2',
                        'target' => '70/20/10 Ads',
                        'title' => 'Ecommerce Ads & Unit Economics',
                        'flow' => [
                            ['title' => 'Balance 70/20/10 Ads', 'desc' => 'Run 70% winners, 20% iterations, and 10% wildcards.'],
                            ['title' => 'Fill 7-Slot Grid', 'desc' => 'Schedule weekly Reels and Stories for product demand.'],
                            ['title' => 'Check MER & CAC', 'desc' => 'Audit Blended CAC, MER, and AOV snapshots daily.'],
                        ],
                        'do_rule' => 'Verify net margin after BOM COGS before scaling spend',
                        'avoid_rule' => 'Refresh winning ad creatives early when MER softens',
                        'actions' => array_values(array_filter([
                            $this->authorizedAction($user, 'Performance ↗', 'ecommerce.dashboard', [], 'module_view', 'ecommerce'),
                        ])),
                    ],
                ],
            ];
        }

        // =====================================================================
        // PLAYBOOK 7: ADMIN, BRANCH P&L & SYSTEM MAINTENANCE (admin_finance)
        // =====================================================================
        if ($isAdmin || $this->userCanView($user, 'finance')) {
            $adminFinanceCards = [
                [
                    'id' => 'af-branch-pnl',
                    'icon' => 'bx-wallet',
                    'target' => 'Cash Basis',
                    'title' => 'Branch P&L & Sales Ledger',
                    'flow' => [
                        ['title' => 'Filter Branch Dates', 'desc' => 'Pick Old Airport, Al Wakrah, or All inside [[Filters]].'],
                        ['title' => 'Audit Staff Ledger', 'desc' => 'Filter [[Sales Ledger]] by stylist for attributed sales.'],
                        ['title' => 'Log Branch Expense', 'desc' => 'Click [[+ Add Expense]] under branch or General overhead.'],
                    ],
                    'do_rule' => 'Visit 1 Combo cash splits equally per redeemed stylist slot',
                    'avoid_rule' => 'Never log Visit 2 package redemptions as manual cash sales',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Finance ↗', 'appointments.revenue.index', [], 'module_view', 'finance'),
                    ])),
                ],
            ];

            // STRICT ADMIN GATE: Command Center, Legal Tasks, Admin KPI Overrides & CLI Maintenance
            if ($isAdmin) {
                $adminFinanceCards[] = [
                    'id' => 'af-admin-governance',
                    'icon' => 'bx-crown',
                    'target' => '7 Scorecards',
                    'title' => 'Command Center & KPI Governance',
                    'flow' => [
                        ['title' => 'Query AI Telemetry', 'desc' => 'Use [[Command Center]] and track [[Legal Tasks]] renewals.'],
                        ['title' => 'Grade Monthly Audits', 'desc' => 'Score Chat QA and [[+ New Branch Evaluation]] reports.'],
                        ['title' => 'Audit SLA & Overrides', 'desc' => 'Log >2h social breaches and approve [[Manage / Extend]] edits.'],
                    ],
                    'do_rule' => 'Audit every salon branch at least once per month',
                    'avoid_rule' => 'Never share Admin logins with branch or chat staff',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Command Center ↗', 'command-center.index', [], 'admin'),
                    ])),
                ];

                $adminFinanceCards[] = [
                    'id' => 'af-cli-maintenance',
                    'icon' => 'bx-terminal',
                    'target' => '5/5 Health',
                    'title' => 'CLI Maintenance & Health Gates',
                    'flow' => [
                        ['title' => 'Expire Paid Combos', 'desc' => '[[packages:expire]] locks elapsed paid Combos via daily cron.'],
                        ['title' => 'Sync Ad Attribution', 'desc' => '[[kpi:sync-ad-leads]] normalizes phones and syncs POS sales.'],
                        ['title' => 'Run Deploy Gate', 'desc' => 'Execute [[php artisan test]] and [[crm:health-check]] first.'],
                    ],
                    'do_rule' => 'Audit ecommerce SKUs via [[ecommerce:audit-pricing]]',
                    'avoid_rule' => 'Read configuration strictly via config() inside app/',
                    'actions' => [],
                ];
            }

            // STRICT SUPER-ADMIN GATE: Staff Access ONLY rendered for afnanakbarghulam@gmail.com
            if ($isSuperAdmin) {
                $adminFinanceCards[] = [
                    'id' => 'af-superadmin-users',
                    'icon' => 'bx-key',
                    'target' => 'Super-Admin',
                    'title' => 'Staff Access & Permissions',
                    'flow' => [
                        ['title' => 'Create Team Login', 'desc' => 'Click [[+ Add Staff]] to provision a staff account.'],
                        ['title' => 'Set Module Matrix', 'desc' => 'Assign Role and none / view / edit permissions.'],
                        ['title' => 'Revoke Access Fast', 'desc' => 'Click [[Deactivate]] to end active sessions immediately.'],
                    ],
                    'do_rule' => 'Assign role = agent so Chat Agents appear in Booking Agent',
                    'avoid_rule' => 'Deactivate rather than delete accounts to preserve history',
                    'actions' => array_values(array_filter([
                        $this->authorizedAction($user, 'Open Staff Access ↗', 'users.index', [], 'super_admin'),
                    ])),
                ];
            }

            $adminFinanceTargets = $isAdmin
                ? [
                    ['value' => 'Cash Basis', 'label' => 'Branch P&L', 'icon' => 'bx-wallet'],
                    ['value' => 'Admin Only', 'label' => 'QA / SLA / Overrides', 'icon' => 'bx-lock-alt'],
                    ['value' => 'Daily Cron', 'label' => 'Auto Package Expiry', 'icon' => 'bx-cog'],
                ]
                : [
                    ['value' => 'Cash Basis', 'label' => 'Branch P&L', 'icon' => 'bx-wallet'],
                    ['value' => '0.01 QAR', 'label' => 'Split Tender Match', 'icon' => 'bx-check-double'],
                    ['value' => 'Equal Split', 'label' => 'Combo Staff Share', 'icon' => 'bx-group'],
                ];

            $playbooks['admin_finance'] = [
                'key' => 'admin_finance',
                'title' => $isAdmin ? 'Admin, Branch P&L & System Maintenance' : 'Finance & Expenses',
                'short_title' => $isAdmin ? 'Admin & P&L' : 'Finance',
                'icon' => 'bx-shield-quarter',
                'targets' => $adminFinanceTargets,
                'cards' => $adminFinanceCards,
            ];
        }

        return $playbooks;
    }
}
