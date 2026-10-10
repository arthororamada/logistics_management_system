@props(['status' => 'active'])
<span {{ $attributes->merge(['class' => 'status-badge status-'.$status]) }}>{{ ucfirst($status) }}</span>
