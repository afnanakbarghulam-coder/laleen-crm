<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StaffContract extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_SIGNATURE = 'pending_signature';
    public const STATUS_ACTIVE = 'active';
    public const NEW_CATEGORY_SENTINEL = '__new_category__';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_SIGNATURE => 'Pending Signature',
        self::STATUS_ACTIVE => 'Active',
    ];

    public const CURRENCIES = ['QAR', 'PKR'];

    public const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
        'both' => 'Both Branches',
        'remote' => 'Remote',
    ];

    public const TEMPLATES = [
        'salon_beautician' => [
            'label' => 'Salon Beautician',
            'default_role' => 'Salon Beautician',
            'default_currency' => 'QAR',
            'allowed_currencies' => ['QAR'],
            'default_branch' => 'old_airport',
            'summary' => 'QAR monthly compensation + salon hygiene & floor clauses',
            'clauses' => <<<'EOT'
1. ROLE & SALON FLOOR STANDARDS
The Employee serves as a Salon Beautician at Laleen Beauty Salon (Qatar) and shall deliver hair, nail, skincare, or beauty treatments strictly in accordance with Laleen's service protocols, consultation checklists, and punctual chair readiness.

2. MONTHLY COMPENSATION (QAR)
Agreed Monthly Compensation is paid in Qatari Riyals (QAR) as specified in the Compensation Summary of this agreement. Approved overtime and unpaid leave adjustments follow Laleen CRM payroll policy.

3. HYGIENE, SANITIZATION & UNIFORM DISCIPLINE
Tools, workstations, towels, and treatment rooms must be sanitized before and after every client in full compliance with Qatar Ministry of Public Health standards. Full Laleen uniform and grooming presentation are mandatory throughout shift hours.

4. CLIENT CONFIDENTIALITY & NON-SOLICITATION
Client phone numbers, booking records, pricing, and treatment histories remain the exclusive confidential property of Laleen Beauty Salon. Off-system bookings, private home-service solicitation, or sharing client records is strictly prohibited.

5. ATTENDANCE, LEAVE & NOTICE PERIOD
Weekly off-days and shift blocks follow the published CRM Scheduled Shifts roster. Annual leave, sick leave documentation, and termination notice periods are governed by Qatar Labour Law and signed salon policy.
EOT,
        ],
        'branch_manager' => [
            'label' => 'Branch Manager',
            'default_role' => 'Branch Manager',
            'default_currency' => 'QAR',
            'allowed_currencies' => ['QAR'],
            'default_branch' => 'old_airport',
            'summary' => 'QAR monthly compensation + branch KPI targets, <= 48h SLA & floor supervisor clauses',
            'clauses' => <<<'EOT'
1. BRANCH LEADERSHIP & SUPERVISORY ACCOUNTABILITY
The Employee serves as Branch Manager responsible for daily salon floor operations, front-desk POS accuracy, opening/closing cash reconciliation, inventory stewardship, and team scheduling discipline at the assigned Laleen branch.

2. MONTHLY COMPENSATION (QAR)
Agreed Monthly Compensation is denominated in Qatari Riyals (QAR) as stated in the Compensation Summary of this agreement and remitted monthly in accordance with Laleen payroll governance.

3. BRANCH TARGETS, MONDAY EVALUATIONS & MYSTERY SHOPPER KPI
The Branch Manager is accountable for monthly branch revenue and chair-side upsell targets, completing 100% of weekly Salon Staff Evaluations inside the Monday-Tuesday window, and maintaining a Branch Mystery Shopper score of >= 85%.

4. <= 48-HOUR CLIENT COMPLAINT SLA
Every walk-in, WhatsApp, or review complaint must be logged in Laleen CRM the same day and contacted, resolved, and documented within <= 48 hours, with zero unauthorized off-system refunds or unlogged complimentary redos.

5. CONFIDENTIALITY, GOVERNANCE & HANDOVER
All financial reports, staff wages, client databases, and operational playbooks are strictly confidential. A formal 60-day written notice and full branch operational handover apply upon separation unless otherwise stipulated by Qatar law.
EOT,
        ],
        'remote_chat_creative' => [
            'label' => 'Remote Chat / Creative',
            'default_role' => 'Remote Chat & Creative Specialist',
            'default_currency' => 'PKR',
            'allowed_currencies' => ['PKR', 'QAR'],
            'default_branch' => 'remote',
            'summary' => 'PKR / QAR monthly compensation + remote response SLA & confidentiality clauses',
            'clauses' => <<<'EOT'
1. REMOTE ENGAGEMENT & SHIFT SIGN-IN
The Contractor / Team Member performs remote digital operations (WhatsApp/Instagram/TikTok lead conversion, booking coordination, or creative asset production) aligned with Qatar (AST, UTC+3) shift schedules and daily CRM Shift Sign-In logs.

2. MONTHLY COMPENSATION & SETTLEMENT (PKR / QAR)
Agreed Monthly Compensation is denominated in the contract currency (PKR or QAR) as specified in the Compensation Summary and remitted monthly following verification of shift attendance and deliverable logs.

3. REMOTE RESPONSE SLA & DAILY CONVERSION STANDARDS
Chat specialists shall maintain prompt response times across active social/WhatsApp queues, uphold >= 85% Chat Quality audit standards, and pursue the daily confirmed booking target (13 bookings/day) with accurate CRM pipeline updates. Creative specialists shall deliver approved campaigns and reels within agreed 48h/72h Ops Tracker SLAs.

4. INTELLECTUAL PROPERTY & BRAND ASSETS
All ad creatives, scripts, raw footage edits, captions, and CRM workflows produced during engagement are work-made-for-hire and remain 100% owned by Laleen Beauty Salon & Laleen Rituals.

5. DATA SECURITY & CLIENT PRIVACY
Access to Laleen CRM, Meta Business Suite, TikTok, and WhatsApp channels is strictly personal. Sharing credentials, exporting customer lists, or retaining client data on unauthorized devices constitutes grounds for immediate termination.
EOT,
        ],
    ];

    protected $fillable = [
        'contract_number',
        'staff_id',
        'template_key',
        'role_title',
        'branch',
        'currency',
        'base_salary',
        'housing_allowance',
        'transport_allowance',
        'other_allowance',
        'start_date',
        'expiry_date',
        'clauses_body',
        'status',
        'secure_token',
        'signed_name',
        'signature_data',
        'signature_type',
        'signed_at',
        'signed_ip',
        'created_by',
    ];

    protected $hidden = [
        'secure_token',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'other_allowance' => 'decimal:2',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'signed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contract) {
            if (empty($contract->secure_token)) {
                $contract->secure_token = self::generateUniqueToken();
            }

            if (empty($contract->contract_number)) {
                $contract->contract_number = self::generateContractNumber();
            }

            if (empty($contract->clauses_body) && isset(self::TEMPLATES[$contract->template_key])) {
                $contract->clauses_body = self::TEMPLATES[$contract->template_key]['clauses'];
            }

            if (empty($contract->role_title) && isset(self::TEMPLATES[$contract->template_key])) {
                $contract->role_title = self::TEMPLATES[$contract->template_key]['default_role'];
            }
        });
    }

    public static function defaultCustomClauses(string $designation = 'Team Member', string $currency = 'QAR'): string
    {
        $cleanRole = trim($designation) !== '' ? trim($designation) : 'Team Member';
        $cleanCurr = in_array(strtoupper($currency), self::CURRENCIES, true) ? strtoupper($currency) : 'QAR';

        return <<<EOT
1. DESIGNATION & OPERATIONAL DUTIES
The Team Member serves as {$cleanRole} for Laleen Beauty Salon and shall perform all assigned operational, service, or digital responsibilities in accordance with Laleen's quality protocols and shift schedules.

2. MONTHLY COMPENSATION ({$cleanCurr})
Agreed Monthly Compensation is denominated in {$cleanCurr} as stated in the Compensation Summary of this agreement and settled monthly in accordance with Laleen CRM attendance and payroll policy.

3. QUALITY, PROFESSIONAL CONDUCT & SLA DISCIPLINE
The Team Member shall uphold Laleen's luxury presentation, hygiene, punctuality, and service standards across every client and team interaction, logging required shift and task updates accurately in Laleen CRM.

4. CONFIDENTIALITY & BRAND PROTECTION
Client records, phone numbers, pricing, creative assets, and internal CRM data remain the exclusive confidential property of Laleen Beauty Salon. Unauthorized sharing, off-system solicitation, or data export is strictly prohibited.

5. ATTENDANCE, LEAVE & SEPARATION NOTICE
Shift schedules, approved leave, and notice periods follow published Laleen CRM rosters, signed salon policy, and applicable labour regulations.
EOT;
    }

    /**
     * Return built-in templates merged with Admin-created custom categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allTemplates(): array
    {
        $templates = self::TEMPLATES;

        try {
            $custom = StaffContractCategory::orderBy('label')->get();
            foreach ($custom as $cat) {
                $templates[$cat->slug] = $cat->toTemplateArray();
            }
        } catch (\Throwable) {
            // Table not migrated yet or unavailable; fall back to built-in templates
        }

        return $templates;
    }

    public static function logoPublicUrl(): string
    {
        return asset('images/laleen-contract-logo.png');
    }

    public static function logoDataUri(): string
    {
        $path = public_path('images/laleen-contract-logo.png');
        if (is_file($path) && is_readable($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                return 'data:image/png;base64,' . base64_encode($raw);
            }
        }

        return '';
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('secure_token', $token)->exists());

        return $token;
    }

    public static function generateContractNumber(): string
    {
        $year = now('Asia/Qatar')->format('Y');
        $prefix = "CNT-{$year}-";

        $latest = self::where('contract_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('contract_number');

        $nextSeq = 1;
        if ($latest && preg_match('/^CNT-\d{4}-(\d+)$/', $latest, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        do {
            $candidate = sprintf('%s%03d', $prefix, $nextSeq);
            $exists = self::where('contract_number', $candidate)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function designationLabel(): string
    {
        $role = trim((string) $this->role_title);
        if ($role !== '') {
            return $role;
        }

        return $this->templateLabel();
    }

    public function templateLabel(): string
    {
        if (isset(self::TEMPLATES[$this->template_key])) {
            return self::TEMPLATES[$this->template_key]['label'];
        }

        if (!empty($this->role_title)) {
            return $this->role_title;
        }

        return Str::headline((string) $this->template_key);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline((string) $this->status);
    }

    public function branchLabel(): string
    {
        return self::BRANCHES[$this->branch] ?? Str::headline(str_replace('_', ' ', (string) $this->branch));
    }

    public function totalAllowances(): float
    {
        return round(
            (float) $this->housing_allowance
            + (float) $this->transport_allowance
            + (float) $this->other_allowance,
            2
        );
    }

    public function totalMonthlyPackage(): float
    {
        return round((float) $this->base_salary + $this->totalAllowances(), 2);
    }

    public function monthlyCompensation(): float
    {
        return $this->totalMonthlyPackage();
    }

    /**
     * Parse raw clauses_body into structured numbered editorial sections.
     *
     * @return list<array{index: string, title: string, body: string}>
     */
    public function parsedClauses(): array
    {
        $raw = trim((string) $this->clauses_body);
        if ($raw === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $sections = [];
        $currentTitle = '';
        $currentBody = [];
        $seq = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $m)) {
                if ($currentTitle !== '' || !empty($currentBody)) {
                    $seq++;
                    $sections[] = [
                        'index' => str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
                        'title' => $currentTitle !== '' ? $currentTitle : "CLAUSE {$seq}",
                        'body' => trim(implode("\n", $currentBody)),
                    ];
                }
                $currentTitle = trim($m[2]);
                $currentBody = [];
                continue;
            }

            if ($trimmed === '' && empty($currentBody)) {
                continue;
            }

            $currentBody[] = $trimmed;
        }

        if ($currentTitle !== '' || !empty($currentBody)) {
            $seq++;
            $sections[] = [
                'index' => str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
                'title' => $currentTitle !== '' ? $currentTitle : "TERMS & CONDITIONS",
                'body' => trim(implode("\n", $currentBody)),
            ];
        }

        return $sections;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
