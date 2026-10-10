@props(['title' => null, 'subtitle' => null, 'collapsible' => false, 'collapsed' => false, 'id' => null])

@php
    $cardId = $id ?: 'card-'.Str::slug($title ?: 'content').'-'.substr(md5(($title ?? '').'|'.($subtitle ?? '')), 0, 8);
@endphp

<section {{ $attributes->class(['content-card', 'collapsible-card' => $collapsible, 'is-collapsed' => $collapsible && $collapsed]) }} @if($collapsible) data-collapsible-card @endif>
    @if($title || isset($actions))
        <header class="card-header">
            <div>
                @if($title)<h2>{{ $title }}</h2>@endif
                @if($subtitle)<p>{{ $subtitle }}</p>@endif
            </div>
            <div class="card-header-actions">
                @isset($actions)<div class="card-actions">{{ $actions }}</div>@endisset
                @if($collapsible)
                    <button type="button" class="card-collapse-toggle" data-card-toggle aria-expanded="{{ $collapsed ? 'false' : 'true' }}" aria-controls="{{ $cardId }}" aria-label="{{ $collapsed ? 'Expand' : 'Collapse' }} {{ $title }}">
                        <x-icon name="chevron-down" :size="18" />
                    </button>
                @endif
            </div>
        </header>
    @endif
    <div class="card-body" id="{{ $cardId }}" @if($collapsible && $collapsed) hidden @endif>{{ $slot }}</div>
</section>
