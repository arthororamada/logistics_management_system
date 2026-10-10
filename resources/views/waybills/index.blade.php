@extends('layouts.app')
@section('title', 'Waybills')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill'], ['label' => 'List']])
@section('content')
<section class="page-heading"><div><p class="eyebrow">Waybill</p><h2>Waybill List</h2><p>View packages and their connected items.</p></div><div class="heading-actions">@if(auth()->user()->hasPermission('waybills.upload'))<a class="button button-outline" href="{{ route('waybills.upload') }}">Mass Upload</a>@endif @if(auth()->user()->hasPermission('waybills.create'))<x-button href="{{ route('waybills.create') }}" variant="primary" icon="plus">Create Waybill</x-button>@endif</div></section>
<x-card title="Waybill Directory" subtitle="Search by waybill, order, package, or buyer.">
    <form method="GET" class="filter-bar"><label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $search }}" placeholder="Search waybills"></label><button class="button button-outline" type="submit">Filter</button>@if($search)<a class="clear-filter" href="{{ route('waybills.index') }}">Clear</a>@endif</form>
    <div class="table-wrap"><table class="access-table"><thead><tr><th>Waybill</th><th>Client</th><th>Seller</th><th>Buyer</th><th>Package</th><th>Items</th><th>Status</th><th>Pickup</th></tr></thead><tbody>
    @forelse($packages as $package)<tr><td><strong>{{ $package->waybill_number }}</strong><small>{{ $package->order_number ?: 'No order number' }}</small></td><td>{{ $package->client?->client_name }}</td><td>{{ $package->seller?->client_name ?: '—' }}</td><td><strong>{{ $package->buyer_name }}</strong><small>{{ $package->buyer_mobile }}</small></td><td>{{ $package->package_id }}<small>{{ $package->package_sku }}</small></td><td>{{ $package->items->where('is_deleted', false)->count() }}</td><td><x-status-badge :status="$package->package_status" /></td><td><span class="date-label">{{ $package->pickup_date?->format('M d, Y H:i') ?: '—' }}</span></td></tr>
    @empty<tr><td colspan="8" class="empty-table">No waybills found.</td></tr>@endforelse
    </tbody></table></div>
    @if($packages->hasPages())<div class="pagination-links">{{ $packages->links() }}</div>@endif
</x-card>
@endsection
