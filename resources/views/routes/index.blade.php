@extends('layouts.app')
@section('title', 'Routes')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Routes']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Routes</h2><p>Maintain barangay route points and coordinates used for trip planning.</p></div>@if(auth()->user()->hasPermission('routes.create'))<x-button href="{{ route('routes.create') }}" variant="primary" icon="plus">Add Route</x-button>@endif</section>
    <x-card title="Route Directory" subtitle="Server-side search, sorting, and pagination are enabled for large route datasets.">
        <div class="filter-bar route-filter-bar"><label for="route-status">Status</label><select id="route-status" aria-label="Filter by status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div class="table-wrap"><table id="routes-table" class="access-table routes-table" style="width:100%"><thead><tr><th>Province</th><th>City</th><th>Barangay</th><th>Longitude</th><th>Latitude</th><th>Status</th><th class="align-right">Actions</th></tr></thead><tbody></tbody></table></div>
    </x-card>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
    <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js" onerror="this.dataset.failed='true'"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableElement = document.querySelector('#routes-table');
            const status = document.querySelector('#route-status');
            const ajaxUrl = @json(route('routes.index'));
            const options = { processing: true, serverSide: true, pageLength: 25, lengthMenu: [10, 25, 50, 100], order: [[0, 'asc'], [1, 'asc'], [2, 'asc']], ajax: { url: ajaxUrl, data: (request) => { request.status = status?.value ?? ''; } }, columns: [
                { data: 'province' }, { data: 'city' }, { data: 'brgy' }, { data: 'longitued', className: 'coordinate-label' }, { data: 'latitude', className: 'coordinate-label' }, { data: 'status', orderable: false, searchable: false }, { data: 'actions', orderable: false, searchable: false, className: 'align-right' }
            ], language: { emptyTable: 'No routes found.', processing: 'Loading routes...' } };

            if (typeof window.DataTable === 'function') {
                const dataTable = new window.DataTable(tableElement, options);
                status?.addEventListener('change', () => dataTable.ajax.reload());
                return;
            }

            // Offline/CDN-blocked fallback keeps the server-side route directory usable.
            const loadFallback = async () => {
                const params = new URLSearchParams({ draw: '1', start: '0', length: '100', status: status?.value ?? '' });
                const response = await fetch(`${ajaxUrl}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error(`Request failed (${response.status}).`); const payload = await response.json();
                const body = tableElement.querySelector('tbody');
                body.innerHTML = payload.data.length ? payload.data.map((row) => `<tr><td>${row.province}</td><td>${row.city}</td><td>${row.brgy}</td><td class="coordinate-label">${row.longitued}</td><td class="coordinate-label">${row.latitude}</td><td>${row.status}</td><td class="align-right">${row.actions}</td></tr>`).join('') : '<tr><td colspan="7" class="empty-table">No routes found.</td></tr>';
            };
            const handleDirectoryError = () => window.AppNotifications.error('Unable to load routes. Please try again.'); loadFallback().catch(() => { handleDirectoryError(); tableElement.querySelector('tbody').innerHTML = '<tr><td colspan="7" class="empty-table">Unable to load routes.</td></tr>'; });
            status?.addEventListener('change', () => loadFallback().catch(handleDirectoryError));
        });
    </script>
@endsection
