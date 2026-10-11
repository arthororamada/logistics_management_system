@extends('layouts.app')
@section('title', 'Trips')
@section('content')
<section class="page-heading trip-page-heading"><div><p class="eyebrow">Fleet Operations</p><h2>Trips</h2></div>
@if(auth()->user()->hasPermission('trips.create'))
<form method="POST" action="{{ route('trips.store') }}" data-trip-action>@csrf<input type="hidden" name="creation_key" value="{{ $creationKey }}"><button class="button button-primary">Add New Trip</button></form>
@endif</section>
<div class="alert alert-error" data-trip-errors hidden role="alert"></div>
<x-card title="Trip Directory" data-trip-directory>
<form method="GET" class="trip-filter">
<input class="form-input" name="search" value="{{ request('search') }}" placeholder="Trip, vehicle, driver or route" aria-label="Search trips">
<select data-trip-searchable class="form-select" name="current_status" aria-label="Status"><option value="">All statuses</option>@foreach(['Open','Dispatched','Completed'] as $status)<option @selected(request('current_status')===$status)>{{ $status }}</option>@endforeach</select>
<select data-trip-searchable class="form-select" name="trip_type" aria-label="Trip type"><option value="">All trip types</option>@foreach($tripTypes as $type)<option @selected(request('trip_type')===$type->trip_type)>{{ $type->trip_type }}</option>@endforeach</select>
<select data-trip-searchable class="form-select" name="vehicle_group" aria-label="Vehicle group"><option value="">All vehicle groups</option>@foreach($vehicleGroups as $group)<option @selected(request('vehicle_group')===$group)>{{ $group }}</option>@endforeach</select>
<select data-trip-searchable class="form-select" name="sort" aria-label="Sort by">@foreach(['created_at'=>'Created date','trip_id'=>'Trip ID','trip_no'=>'Trip number','total_distance'=>'Distance','total_cost'=>'Cost','current_status'=>'Status'] as $key=>$label)<option value="{{ $key }}" @selected(request('sort','created_at')===$key)>{{ $label }}</option>@endforeach</select>
<select data-trip-searchable class="form-select" name="direction" aria-label="Sort direction"><option value="desc" @selected(request('direction')!=='asc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select>
<select data-trip-searchable class="form-select" name="per_page" aria-label="Rows per page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected(request('per_page',15)==$size)>{{ $size }} per page</option>@endforeach</select>
<button class="button button-primary">Apply</button><a class="button button-outline" href="{{ route('trips.index') }}">Reset</a>
</form>
<div class="table-wrap"><table class="access-table"><thead><tr>@foreach(['ID','Trip ID','Trip number','Van','Plate','Driver','Origin','Destination','Type','Distance (km)','Cost','Status','Created','Actions'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($trips as $trip)
<tr><td>{{ $trip->id }}</td><td>{{ $trip->trip_id }}</td><td>{{ $trip->trip_no ?: 'Draft' }}</td><td>{{ $trip->van_number ?: '—' }}</td><td>{{ $trip->plate_number ?: '—' }}</td><td>@foreach($trip->personnels->where('designation','Driver') as $person){{ $employees->get($person->employee_id)?->first_name }} {{ $employees->get($person->employee_id)?->last_name }}@if(!$loop->last), @endif @endforeach</td><td>{{ $trip->trip_route_origin }}</td><td>{{ $trip->trip_route_destination }}</td><td>{{ $trip->trip_type }}</td><td>{{ number_format($trip->total_distance,2) }}</td><td>{{ number_format($trip->total_cost,2) }}</td><td><span class="status-badge trip-status trip-status-{{ strtolower($trip->current_status) }}">{{ $trip->current_status }}</span></td><td>{{ $trip->created_at->format('M d, Y H:i') }}</td><td>
<a class="table-action trip-action {{ $trip->current_status==='Open' && auth()->user()->hasPermission('trips.update') ? 'trip-action-edit' : 'trip-action-view' }}" href="{{ route('trips.show',$trip) }}">{{ $trip->current_status==='Open' && auth()->user()->hasPermission('trips.update') ? 'Edit Trip' : 'View Trip' }}</a>
@if($trip->current_status==='Open' && auth()->user()->hasPermission('trips.dispatch'))<form method="POST" action="{{ route('trips.dispatch',$trip) }}" data-trip-action data-trip-operation="dispatch" data-confirm>@csrf<button class="table-action trip-action trip-action-dispatch">Dispatch Truck</button></form>@endif
@if($trip->current_status==='Dispatched' && auth()->user()->hasPermission('trips.complete'))<form method="POST" action="{{ route('trips.complete',$trip) }}" data-trip-action data-trip-operation="complete" data-confirm>@csrf<button class="table-action trip-action trip-action-complete">Complete Trip</button></form>@endif
@if(in_array($trip->current_status,['Dispatched','Completed']))<a class="table-action trip-action trip-action-print" href="{{ route('trips.print',$trip) }}" target="_blank" rel="noopener">Print Trip</a>@endif
</td></tr>
@empty<tr><td colspan="14" class="empty-table">No trips found.</td></tr>@endforelse
</tbody></table></div>{{ $trips->links() }}
</x-card>
@endsection
