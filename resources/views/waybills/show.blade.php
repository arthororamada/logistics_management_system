@extends('layouts.app')
@section('title', 'View Waybill')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill', 'url' => route('waybills.index')], ['label' => 'View '.$package->waybill_number]])

@section('content')
<section class="page-heading waybill-view-heading">
    <div><p class="eyebrow">Waybill / View</p><h2>{{ $package->waybill_number }}</h2><p>Complete shipment, consignee, merchant, and item information.</p></div>
    <div class="heading-actions waybill-view-actions">
        <button id="print-waybill" type="button" class="button button-primary"><x-icon name="reports" :size="16" /> Print Waybill</button>
        <a class="button button-outline" href="{{ route('waybills.index', request()->query()) }}">Back to List</a>
    </div>
</section>

<section class="waybill-overview" aria-label="Waybill summary">
    <div class="waybill-overview-primary"><span>Waybill Number</span><strong>{{ $package->waybill_number }}</strong><x-status-badge :status="$package->package_status" /></div>
    <div><span>Client</span><strong>{{ $package->client?->client_name ?: '—' }}</strong><small>{{ $package->client?->client_code ?: 'No client code' }}</small></div>
    <div><span>Package ID</span><strong>{{ $package->package_id }}</strong><small>{{ $package->order_number ?: 'No order number' }}</small></div>
    <div><span>Created At</span><strong>{{ $package->created_at?->format('M d, Y') ?: '—' }}</strong><small>{{ $package->created_at?->format('H:i:s') ?: '' }}</small></div>
    <div><span>Total Items</span><strong>{{ $package->items->count() }}</strong><small>{{ $package->is_priority ? 'Priority shipment' : 'Standard shipment' }}</small></div>
</section>

<div class="waybill-view-grid">
    <div class="form-stack">
        <x-card title="Package Reference" subtitle="Tracking and shipment classification">
            <dl class="waybill-detail-list">
                <div><dt>Tracking Number</dt><dd>{{ $package->waybill_number }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $package->package_status)) }}</dd></div>
                <div><dt>Package ID</dt><dd>{{ $package->package_id }}</dd></div>
                <div><dt>Order Number</dt><dd>{{ $package->order_number ?: '—' }}</dd></div>
                @foreach(range(1, 5) as $reference)
                    <div><dt>Reference {{ $reference }}</dt><dd>{{ $package->{"package_reference_{$reference}"} ?: '—' }}</dd></div>
                @endforeach
                <div><dt>Current Location</dt><dd>{{ $package->package_location ?: '—' }}</dd></div>
                <div><dt>Priority</dt><dd>{{ $package->is_priority ? 'Priority' : 'Standard' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Package Information" subtitle="Physical attributes and declared values">
            <div class="waybill-metric-grid">
                <div><span>Package Size</span><strong>{{ $package->package_size ?: '—' }}</strong></div>
                <div><span>Quantity</span><strong>{{ $package->package_quantity }}</strong></div>
                <div><span>Length</span><strong>{{ $package->package_length }}</strong></div>
                <div><span>Width</span><strong>{{ $package->package_width }}</strong></div>
                <div><span>Height</span><strong>{{ $package->package_height }}</strong></div>
                <div><span>Weight</span><strong>{{ $package->package_weight }}</strong></div>
                <div><span>Total Price</span><strong>{{ number_format((float) $package->total_price, 2) }}</strong></div>
                <div><span>Declared Value</span><strong>{{ number_format((float) $package->declared_value, 2) }}</strong></div>
            </div>
            <div class="waybill-description"><span>Description</span><p>{{ $package->package_description ?: '—' }}</p></div>
            <div class="waybill-description"><span>Remarks</span><p>{{ $package->package_remarks ?: '—' }}</p></div>
        </x-card>
    </div>

    <div class="form-stack">
        <x-card title="Consignee Information" subtitle="Delivery recipient and destination">
            <dl class="waybill-detail-list">
                <div><dt>Consignee Name</dt><dd>{{ $package->buyer_name }}</dd></div>
                <div><dt>Address</dt><dd>{{ $package->buyer_full_address }}</dd></div>
                <div><dt>Email</dt><dd>{{ $package->buyer_email ?: '—' }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $package->buyer_mobile }}</dd></div>
                <div><dt>City</dt><dd>{{ $package->buyer_city ?: '—' }}</dd></div>
                <div><dt>Province</dt><dd>{{ $package->buyer_province ?: '—' }}</dd></div>
                <div><dt>Barangay</dt><dd>{{ $package->buyer_barangay ?: '—' }}</dd></div>
                <div><dt>Building Type</dt><dd>{{ $package->buyer_building_type ?: '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Merchant Information" subtitle="Client saved as the shipment seller">
            <dl class="waybill-detail-list">
                <div><dt>Merchant Name</dt><dd>{{ $package->seller?->client_name ?: '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $package->seller?->full_address ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd>{{ $package->seller?->email ?: '—' }}</dd></div>
                <div><dt>Mobile</dt><dd>{{ $package->seller?->contact_number ?: '—' }}</dd></div>
                <div><dt>City</dt><dd>{{ $package->seller?->city ?: '—' }}</dd></div>
                <div><dt>Province</dt><dd>{{ $package->seller?->province ?: '—' }}</dd></div>
                <div><dt>Barangay</dt><dd>{{ $package->seller?->barangay ?: '—' }}</dd></div>
                <div><dt>Building Type</dt><dd>{{ $package->seller?->building_type ?: '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Payment and Delivery">
            <dl class="waybill-detail-list">
                <div><dt>Payment Type</dt><dd>{{ $package->payment_type ?: '—' }}</dd></div>
                <div><dt>Remittance Status</dt><dd>{{ $package->remittance_status ?: '—' }}</dd></div>
                <div><dt>Minimum Delivery Time</dt><dd>{{ $package->delivery_min_time?->format('M d, Y H:i') ?: '—' }}</dd></div>
                <div><dt>Maximum Delivery Time</dt><dd>{{ $package->delivery_max_time?->format('M d, Y H:i') ?: '—' }}</dd></div>
                <div><dt>Handover Date</dt><dd>{{ $package->handover_date?->format('M d, Y H:i') ?: '—' }}</dd></div>
            </dl>
        </x-card>
    </div>
</div>

<x-card title="Item List" subtitle="{{ $package->items->count() }} item(s) connected to this waybill." class="waybill-items-card">
    <div class="table-wrap">
        <table class="access-table waybill-items-table">
            <thead><tr><th>Reference</th><th>Description</th><th>SKU</th><th>Length</th><th>Width</th><th>Height</th><th>Weight</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse($package->items as $item)
                    <tr><td><strong>{{ $item->item_reference ?: '—' }}</strong></td><td>{{ $item->item_description }}</td><td>{{ $item->item_sku ?: '—' }}</td><td>{{ $item->item_length }}</td><td>{{ $item->item_width }}</td><td>{{ $item->item_height }}</td><td>{{ $item->item_weight }}</td><td>{{ $item->item_remarks ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="8" class="empty-table">No active items are connected to this waybill.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-card title="Record Activity" subtitle="Available audit information for this waybill." class="waybill-activity-card">
    <div class="table-wrap">
        <table class="access-table">
            <thead><tr><th>Date</th><th>Event</th><th>Status</th><th>Reference</th></tr></thead>
            <tbody>
                <tr><td>{{ $package->created_at?->format('M d, Y H:i:s') ?: '—' }}</td><td>Waybill created</td><td><x-status-badge :status="$package->package_status" /></td><td>{{ $package->waybill_number }}</td></tr>
                @if($package->updated_at && !$package->updated_at->equalTo($package->created_at))
                    <tr><td>{{ $package->updated_at->format('M d, Y H:i:s') }}</td><td>Waybill updated</td><td><x-status-badge :status="$package->package_status" /></td><td>{{ $package->waybill_number }}</td></tr>
                @endif
                @if($package->handover_date)
                    <tr><td>{{ $package->handover_date->format('M d, Y H:i:s') }}</td><td>Package handed over</td><td><x-status-badge :status="$package->package_status" /></td><td>{{ $package->package_reference_1 ?: $package->waybill_number }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</x-card>

<script>document.querySelector('#print-waybill')?.addEventListener('click', () => window.print());</script>
@endsection
