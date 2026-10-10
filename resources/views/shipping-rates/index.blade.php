@extends('layouts.app')
@section('title', 'Shipping Rates')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Tariffs'], ['label' => 'Shipping Rates']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Tariffs</p><h2>Shipping Rates</h2><p>Maintain shipping rates by line, port pair, and vehicle type.</p></div>@if(auth()->user()->hasPermission('shipping-rates.create'))<x-button href="{{ route('shipping-rates.create') }}" variant="primary" icon="plus">Add Shipping Rate</x-button>@endif</section>
    <x-card title="Shipping Rate Directory" subtitle="Server-side search, sorting, and pagination are enabled for large rate datasets.">
        <div class="filter-bar route-filter-bar"><label for="shipping-rate-status">Status</label><select id="shipping-rate-status" aria-label="Filter by status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div class="table-wrap"><table id="shipping-rates-table" class="access-table routes-table" style="width:100%"><thead><tr><th>Shipping Line</th><th>Port Origin</th><th>Port Destination</th><th>Vehicle Type</th><th>Shipping Rate</th><th>Status</th><th class="align-right">Actions</th></tr></thead><tbody></tbody></table></div>
    </x-card>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css"><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const element = document.querySelector('#shipping-rates-table'); const status = document.querySelector('#shipping-rate-status'); const url = @json(route('shipping-rates.index'));
            if (typeof window.DataTable === 'function') {
                const table = new window.DataTable(element, { processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100], order: [[0, 'asc'], [1, 'asc']], ajax: { url, data: (request) => { request.status = status?.value ?? ''; } }, columns: [{data:'shipping_line_name'}, {data:'port_origin'}, {data:'port_destination'}, {data:'vehicle_type'}, {data:'shippine_rate', className:'coordinate-label'}, {data:'status', orderable:false, searchable:false}, {data:'actions', orderable:false, searchable:false, className:'align-right'}] });
                status?.addEventListener('change', () => table.ajax.reload()); return;
            }
            const load = async () => { const params = new URLSearchParams({draw:'1', start:'0', length:'100', status:status?.value ?? ''}); const response = await fetch(`${url}?${params}`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}); if (!response.ok) throw new Error(`Request failed (${response.status}).`); const payload = await response.json(); const body = element.querySelector('tbody'); body.innerHTML = payload.data.length ? payload.data.map((row) => `<tr><td>${row.shipping_line_name}</td><td>${row.port_origin}</td><td>${row.port_destination}</td><td>${row.vehicle_type}</td><td class="coordinate-label">${row.shippine_rate}</td><td>${row.status}</td><td class="align-right">${row.actions}</td></tr>`).join('') : '<tr><td colspan="7" class="empty-table">No shipping rates found.</td></tr>'; }; const handleDirectoryError = () => window.AppNotifications.error('Unable to load shipping rates. Please try again.'); load().catch(() => { handleDirectoryError(); element.querySelector('tbody').innerHTML = '<tr><td colspan="7" class="empty-table">Unable to load shipping rates.</td></tr>'; }); status?.addEventListener('change', () => load().catch(handleDirectoryError));
        });
    </script>
@endsection
