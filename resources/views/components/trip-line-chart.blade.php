@props(['points', 'label'])

@php
    $step = max(1, (int) ceil(max(array_column($points, 'total')) / 4));
    $maximum = $step * 4;
    $coordinates = [];
    foreach ($points as $index => $point) {
        $coordinates[] = [48 + $index * 552 / max(1, count($points) - 1), 190 - $point['total'] * 160 / $maximum];
    }
    $polyline = implode(' ', array_map(fn ($coordinate) => implode(',', $coordinate), $coordinates));
    $total = array_sum(array_column($points, 'total'));
@endphp

<p class="analytics-chart-total"><strong>{{ number_format($total) }}</strong> completed trips</p>
<div class="attendance-chart analytics-line-chart">
    <svg viewBox="0 0 640 235" role="img" aria-label="{{ $label }}: {{ $total }} completed trips">
        <g class="chart-grid">
            @for($tick = 0; $tick <= 4; $tick++)
                <line x1="48" y1="{{ 190 - $tick * 40 }}" x2="600" y2="{{ 190 - $tick * 40 }}" />
            @endfor
        </g>
        <g class="chart-labels">
            @for($tick = 0; $tick <= 4; $tick++)
                <text x="35" y="{{ 194 - $tick * 40 }}" text-anchor="end">{{ $tick * $step }}</text>
            @endfor
        </g>
        <polygon class="chart-area" points="48,190 {{ $polyline }} 600,190" />
        <polyline class="chart-line" points="{{ $polyline }}" />
        <g class="chart-points">
            @foreach($points as $index => $point)
                <circle cx="{{ $coordinates[$index][0] }}" cy="{{ $coordinates[$index][1] }}" r="4" tabindex="0">
                    <title>{{ $point['date'] }}: {{ $point['total'] }} completed trips</title>
                </circle>
            @endforeach
        </g>
        <g class="chart-days">
            @foreach($points as $index => $point)
                @if(count($points) <= 12 || $index % 5 === 0 || $loop->last)
                    <text x="{{ $coordinates[$index][0] }}" y="220">{{ $point['label'] }}</text>
                @endif
            @endforeach
        </g>
    </svg>
</div>
@if($total === 0)<p class="analytics-note">No completed trips in this period.</p>@endif
<details class="analytics-chart-data">
    <summary>View trip counts</summary>
    <table class="analytics-data-table">
        <caption>{{ $label }}</caption>
        <thead><tr><th scope="col">Period</th><th scope="col">Completed trips</th></tr></thead>
        <tbody>@foreach($points as $point)<tr><th scope="row">{{ $point['date'] }}</th><td>{{ $point['total'] }}</td></tr>@endforeach</tbody>
    </table>
</details>
