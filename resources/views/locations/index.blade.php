@extends('layouts.app')

@section('title', 'Locations')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'User Management'], ['label' => 'Locations']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">User Management</p><h2>Locations</h2><p>Maintain the locations available to users and logistics operations.</p></div>
        @if(auth()->user()->hasPermission('locations.create'))<x-button href="{{ route('locations.create') }}" variant="primary" icon="plus">Add Location</x-button>@endif
    </section>

    <x-card title="Location Directory" subtitle="Search and manage location records.">
        <form method="GET" action="{{ route('locations.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code or location name"></label>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('locations.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Location</th><th>Coordinates</th><th>Created By</th><th>Updated</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($locations as $location)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="location" :size="17" /></span><div><strong>{{ $location->location_name }}</strong><small>{{ $location->location_code }}</small></div></div></td>
                            <td><span class="coordinate-label">{{ $location->location_lat }}, {{ $location->location_long }}</span></td>
                            <td>{{ $location->createdBy?->name ?: '—' }}</td>
                            <td><span class="date-label">{{ $location->updated_at?->format('M d, Y H:i') ?: '—' }}</span>@if($location->updatedBy)<small>{{ $location->updatedBy->name }}</small>@endif</td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('locations.update'))<a href="{{ route('locations.edit', $location) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('locations.delete'))<form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Delete this location? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No locations match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($locations->hasPages())<div class="pagination-links">{{ $locations->links() }}</div>@endif
    </x-card>
@endsection
