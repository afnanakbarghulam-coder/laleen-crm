<style>
    .wa-tone.active {
        background: var(--luxe-accent);
        border-color: var(--luxe-accent);
        color: var(--luxe-accent-ink);
    }

    #waDrawer .offcanvas-title {
        font-size: 16px;
    }
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="waDrawer">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Follow-Up Script — <span id="waClientName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="text-muted small mb-3" id="waClientMeta"></div>

        <div class="btn-group w-100 mb-3" role="group">
            <button type="button" class="btn btn-outline-secondary wa-tone active" data-tone="friendly">Friendly</button>
            <button type="button" class="btn btn-outline-secondary wa-tone" data-tone="urgency">Limited Slots</button>
            <button type="button" class="btn btn-outline-secondary wa-tone" data-tone="loyalty">Loyalty Perk</button>
        </div>

        <textarea id="waScript" class="form-control mb-3" rows="8"></textarea>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary flex-grow-1" id="waCopyBtn">
                <i class="bx bx-copy"></i> Copy
            </button>
            <a href="#" class="btn btn-success flex-grow-1" id="waOpenBtn">
                <i class="bx bxl-whatsapp"></i> Open WhatsApp
            </a>
        </div>
        <div class="text-success small mt-2 d-none" id="waCopied">Copied to clipboard.</div>
    </div>
</div>

<script>
(function () {
    let current = null;

    // High-converting follow-up frameworks: warm check-in, gentle urgency,
    // and a loyalty-points nudge — kept short and personal for WhatsApp.
    const TEMPLATES = {
        friendly: (d) => `Hi ${d.name}! \u{1F44B} It's been ${d.daysSince} day${d.daysSince == 1 ? '' : 's'} since your ${d.service}${d.branch ? ' at ' + d.branch : ''}. Most clients love to refresh this every ${d.interval || 'few'} days — want me to hold a spot for you this week?`,
        urgency: (d) => `Hi ${d.name}, quick heads up — we've got a few slots opening up this week for ${d.service} and thought of you first since you're about due for your next visit. Want me to reserve one before they fill up?`,
        loyalty: (d) => `Hi ${d.name}! You're due for your ${d.service} soon, and you're sitting on ${d.loyalty} loyalty points \u{1F48E} — more than enough for a nice reward when you rebook. Should I pencil you in?`,
    };

    function render() {
        if (!current) return;
        const activeTone = document.querySelector('#waDrawer .wa-tone.active');
        const tone = activeTone ? activeTone.dataset.tone : 'friendly';
        document.getElementById('waScript').value = TEMPLATES[tone](current);
    }

    document.querySelectorAll('.wa-trigger').forEach((btn) => {
        btn.addEventListener('click', function () {
            current = {
                name: this.dataset.name || 'there',
                phone: this.dataset.phone || '',
                service: this.dataset.service || 'your treatment',
                lastVisit: this.dataset.lastVisit || '',
                daysSince: this.dataset.daysSince || '0',
                loyalty: this.dataset.loyalty || 0,
                branch: this.dataset.branch || '',
                interval: this.dataset.interval || '',
            };

            document.getElementById('waClientName').textContent = current.name;
            document.getElementById('waClientMeta').textContent =
                current.service + ' · last visit ' + current.lastVisit + ' · ' + current.phone;

            document.querySelectorAll('#waDrawer .wa-tone').forEach((t) => t.classList.remove('active'));
            document.querySelector('#waDrawer .wa-tone[data-tone="friendly"]').classList.add('active');
            render();

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('waDrawer')).show();
        });
    });

    document.querySelectorAll('#waDrawer .wa-tone').forEach((btn) => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#waDrawer .wa-tone').forEach((t) => t.classList.remove('active'));
            this.classList.add('active');
            render();
        });
    });

    document.getElementById('waCopyBtn').addEventListener('click', function () {
        navigator.clipboard.writeText(document.getElementById('waScript').value).then(() => {
            const badge = document.getElementById('waCopied');
            badge.classList.remove('d-none');
            setTimeout(() => badge.classList.add('d-none'), 1800);
        });
    });

    document.getElementById('waOpenBtn').addEventListener('click', function (e) {
        e.preventDefault();
        if (!current) return;
        let digits = (current.phone || '').replace(/\D/g, '');
        if (digits.length > 0 && digits.length <= 8) digits = '974' + digits;
        const text = encodeURIComponent(document.getElementById('waScript').value);
        window.open('https://wa.me/' + digits + '?text=' + text, '_blank');
    });
})();
</script>
