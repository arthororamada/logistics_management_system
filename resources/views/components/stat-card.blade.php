@props(['title', 'value', 'description', 'trend' => null, 'icon' => 'dashboard', 'tone' => 'blue', 'color' => null])

<article class="stat-card" @if($color) style="--stat-color: {{ $color }}" @endif>
    <div class="stat-card-top">
        <span class="stat-title">{{ $title }}</span>
        <span class="stat-icon stat-icon-{{ $tone }}" @if($color) style="color: {{ $color }}" @endif><x-icon :name="$icon" :size="21" /></span>
    </div>
    <strong class="stat-value">{{ $value }}</strong>
    <div class="stat-meta">
        @if($trend)<span class="stat-trend"><x-icon name="trend" :size="14" />{{ $trend }}</span>@endif
        <span>{{ $description }}</span>
    </div>
</article>
