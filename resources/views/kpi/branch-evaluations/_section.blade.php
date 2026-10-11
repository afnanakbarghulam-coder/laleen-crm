@php
    $pct = $max > 0 ? round($score / $max * 100, 1) : 0;
    $tier = $pct >= 90 ? 'green' : ($pct < 50 ? 'red' : 'amber');
    $badgeClass = match ($tier) {
        'green' => 'kpi-badge-green',
        'red' => 'kpi-badge-red',
        default => 'kpi-badge-amber',
    };
@endphp
<div class="be-section">
    <div class="be-section-header tier-{{ $tier }}">
        <div class="be-section-title">{{ $title }}</div>
        <div class="d-flex align-items-center gap-2">
            <span class="be-section-fraction">{{ $score }} / {{ $max }}</span>
            <span class="kpi-badge {{ $badgeClass }}">{{ rtrim(rtrim(number_format($pct, 1), '0'), '.') }}%</span>
        </div>
    </div>
    <div class="be-section-table">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th style="width:56px;">Q</th>
                        <th>Question</th>
                        <th style="width:220px;">Answer</th>
                        <th class="text-end" style="width:90px;">Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questions as $q)
                        <tr>
                            <td>{{ $q['num'] }}</td>
                            <td>{{ $q['question'] }}</td>
                            <td><span class="be-answer is-{{ $q['status'] }}">{{ $q['answer'] }}</span></td>
                            <td class="text-end">{{ $q['score'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
