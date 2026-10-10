@extends('layouts.app')
@section('title', 'Waybills')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill'], ['label' => 'List']])

@section('content')
<div class="form-stack waybill-list-stack">
    <x-card title="Waybill Advanced Search" subtitle="Use one or more fields below. No records load until you search.">
        <form method="GET" action="{{ route('waybills.index') }}" class="advanced-waybill-form">
            <div class="advanced-waybill-grid">
                <div class="form-group"><select id="creator_id" name="creator_id" aria-label="Creator"><option value="">Select creator</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(($filters['creator_id'] ?? null) == $creator->id)>{{ $creator->name }} ({{ $creator->username }})</option>@endforeach</select></div>
                <div class="form-group"><select id="client_id" name="client_id" aria-label="Client"><option value="">Select client</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->client_code }} - {{ $client->client_name }}</option>@endforeach</select></div>
                <div class="form-group"><select id="priority" name="priority" aria-label="Priority"><option value="">All priorities</option><option value="yes" @selected(($filters['priority'] ?? '') === 'yes')>Priority</option><option value="no" @selected(($filters['priority'] ?? '') === 'no')>Standard</option></select></div>

                <div class="form-group"><select id="package_status" name="package_status" aria-label="Status"><option value="">Select status</option><option value="for_pickup" @selected(($filters['package_status'] ?? '') === 'for_pickup')>For Pickup</option><option value="in_transit" @selected(($filters['package_status'] ?? '') === 'in_transit')>In Transit</option><option value="delivered" @selected(($filters['package_status'] ?? '') === 'delivered')>Delivered</option><option value="cancelled" @selected(($filters['package_status'] ?? '') === 'cancelled')>Cancelled</option></select></div>
                <div class="form-group"><input id="package_location" name="package_location" value="{{ $filters['package_location'] ?? '' }}" placeholder="Current location" aria-label="Current location"></div>
                <div class="form-group"><input id="payment_type" name="payment_type" value="{{ $filters['payment_type'] ?? '' }}" placeholder="Payment type" aria-label="Payment type"></div>

                <div class="form-group"><select id="date_reference" name="date_reference" aria-label="Date reference"><option value="created_at" @selected($dateReference === 'created_at')>Date Created</option><option value="updated_at" @selected($dateReference === 'updated_at')>Date Updated</option><option value="handover_date" @selected($dateReference === 'handover_date')>Handover Date</option></select></div>
                <div class="form-group advanced-date-range"><input id="date_range" type="text" name="date_range" value="{{ $filters['date_range'] ?? '' }}" placeholder="Start date                 ~                 End date" aria-label="Date range" autocomplete="off">@error('date_range')<p class="form-error">{{ $message }}</p>@enderror @error('date_from')<p class="form-error">{{ $message }}</p>@enderror @error('date_to')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>

            <div class="advanced-waybill-multivalue">
                <div class="form-group"><textarea id="tracking_numbers" name="tracking_numbers" rows="2" placeholder="Tracking number(s)" aria-label="Tracking numbers">{{ $filters['tracking_numbers'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="package_ids" name="package_ids" rows="2" placeholder="Package ID(s)" aria-label="Package IDs">{{ $filters['package_ids'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="references" name="references" rows="2" placeholder="Reference(s)" aria-label="References">{{ $filters['references'] ?? '' }}</textarea></div>
                <div class="form-group"><textarea id="order_numbers" name="order_numbers" rows="2" placeholder="Order number(s)" aria-label="Order numbers">{{ $filters['order_numbers'] ?? '' }}</textarea></div>
            </div>

            <div class="advanced-search-actions"><button id="clear-waybill-filters" type="button" class="button button-outline">Clear</button><button type="submit" class="button button-primary"><x-icon name="search" :size="16" /> Search</button></div>
        </form>
    </x-card>

    <x-card title="Waybill Results" subtitle="Results appear only after applying a search filter.">
        <x-slot:actions><button id="export-waybill-results" type="button" class="button button-outline" @disabled($packages->isEmpty())><x-icon name="reports" :size="16" /> Export CSV</button></x-slot:actions>
        <div class="waybill-results-toolbar"><strong>Total: {{ number_format($packages->total()) }}</strong><span>{{ $hasFilters ? 'Filtered waybill results' : 'Ready for search' }}</span></div>
        <div class="table-wrap"><table id="waybill-results-table" class="access-table waybill-table"><thead><tr><th>Client</th><th>Created Date</th><th>Handover Date</th><th>Waybill Number</th><th>Consignee</th><th>City</th><th>Status</th><th>Reference</th><th>Priority</th></tr></thead><tbody>
            @forelse($packages as $package)
                <tr><td>{{ $package->client?->client_name ?: '-' }}</td><td><span class="date-label">{{ $package->created_at?->format('M d, Y H:i') ?: '-' }}</span></td><td><span class="date-label">{{ $package->handover_date?->format('M d, Y H:i') ?: '-' }}</span></td><td><strong>{{ $package->waybill_number }}</strong><small>{{ $package->package_id }}</small></td><td><strong>{{ $package->buyer_name }}</strong><small>{{ $package->buyer_mobile }}</small></td><td>{{ $package->buyer_city ?: '-' }}</td><td><x-status-badge :status="$package->package_status" /></td><td>{{ $package->package_reference_1 ?: '-' }}</td><td>@if($package->is_priority)<span class="status-badge status-active">Priority</span>@else<span class="date-label">Standard</span>@endif</td></tr>
            @empty
                <tr><td colspan="9" class="empty-table">{{ $hasFilters ? 'No waybills match the selected filters.' : 'No rows loaded. Use Advanced Search above.' }}</td></tr>
            @endforelse
        </tbody></table></div>
        @if($packages->hasPages())<div class="pagination-links">{{ $packages->links() }}</div>@endif
    </x-card>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangePicker = typeof window.flatpickr === 'function'
        ? window.flatpickr('#date_range', { mode: 'range', dateFormat: 'Y-m-d', conjunction: ' to ', allowInput: true })
        : null;

    const filterForm = document.querySelector('.advanced-waybill-form');
    document.querySelector('#clear-waybill-filters')?.addEventListener('click', () => {
        filterForm.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => { field.value = ''; });
        filterForm.querySelectorAll('select').forEach((field) => { field.selectedIndex = 0; });
        rangePicker?.clear();
    });

    document.querySelector('#export-waybill-results')?.addEventListener('click', () => {
        const rows = [...document.querySelectorAll('#waybill-results-table tr')].map((row) =>
            [...row.querySelectorAll('th, td')].map((cell) => `"${cell.innerText.trim().replaceAll('"', '""')}"`).join(',')
        );
        const blob = new Blob([`\uFEFF${rows.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = Object.assign(document.createElement('a'), { href: url, download: `waybill-results-${new Date().toISOString().slice(0, 10)}.csv` });
        link.click();
        URL.revokeObjectURL(url);
    });
});
</script>
@endsection
