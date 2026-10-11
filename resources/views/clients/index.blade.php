@extends('layouts.app')

@section('title', 'Clients')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Clients']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Trip Maintenance</p><h2>Clients</h2><p>Maintain client contacts and destinations used for trip planning.</p></div>
        @if(auth()->user()->hasPermission('clients.create'))<x-button href="{{ route('clients.create') }}" variant="primary" icon="plus">Add Client</x-button>@endif
    </section>

    <x-card title="Client Directory" subtitle="Search, filter, and manage client records.">
        <form method="GET" action="{{ route('clients.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search client, location, email, or contact"></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('clients.index') }}" class="clear-filter">Clear</a>@endif
        </form>
        <div class="table-wrap">
            <table class="access-table clients-table">
                <thead><tr><th>Client</th><th>Address</th><th>Focal Person</th><th>Contact Number</th><th>Status</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="user" :size="17" /></span><div><strong>{{ $client->client_name }}</strong><small class="warehouse-code">{{ $client->client_code }}</small></div></div></td>
                            <td><span class="warehouse-address">{{ $client->full_address }}</span><small>{{ implode(', ', array_filter([$client->barangay, $client->city, $client->province])) }}@if($client->building_type) · {{ $client->building_type }}@endif</small></td>
                            <td>{{ $client->focal_person }}</td>
                            <td><span class="coordinate-label">{{ $client->contact_number }}</span>@if($client->email)<small>{{ $client->email }}</small>@endif</td>
                            <td><x-status-badge :status="$client->is_active ? 'active' : 'inactive'" /></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('clients.update'))<a href="{{ route('clients.edit', $client) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('clients.delete'))<form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Delete this client? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-table">No clients match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($clients->hasPages())<div class="pagination-links">{{ $clients->links() }}</div>@endif
    </x-card>
@endsection
