@extends('layouts.app')
@section('title', 'Trip Details')
@section('content')
<section class="page-heading trip-page-heading">
    <div><p class="eyebrow">Fleet Operations</p><h2>{{ $trip->trip_id }}</h2><p>{{ $trip->current_status }} &middot; Saved version {{ $trip->version }}</p></div>
    <div class="heading-actions">
        <a class="button button-outline" href="{{ route('trips.index') }}">Back to Trips</a>
        @if(in_array($trip->current_status, ['Dispatched','Completed']))
            <a class="button button-primary trip-action trip-action-print" href="{{ route('trips.print',$trip) }}" target="_blank" rel="noopener">Print Trip</a>
        @endif
        @if($trip->current_status==='Dispatched' && auth()->user()->hasPermission('trips.complete'))
            <form method="POST" action="{{ route('trips.complete',$trip) }}" data-trip-action data-trip-operation="complete" data-confirm="Complete this trip? This cannot be undone through the standard interface.">@csrf<button class="button button-primary trip-action trip-action-complete">Complete Trip</button></form>
        @endif
        @if($trip->current_status==='Open' && !auth()->user()->hasPermission('trips.update') && auth()->user()->hasPermission('trips.dispatch'))
            <form method="POST" action="{{ route('trips.dispatch',$trip) }}" data-trip-action data-trip-operation="dispatch" data-confirm="Dispatch this truck using saved details?">@csrf<button class="button button-primary trip-action trip-action-dispatch">Dispatch Truck</button></form>
        @endif
    </div>
</section>
<div class="alert alert-error" data-trip-errors hidden role="alert"></div>
@if($errors->any())<div class="alert alert-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="trip-workspace {{ $trip->current_status==='Open' && auth()->user()->hasPermission('trips.update') ? '' : 'trip-readonly' }}">
@if($trip->current_status==='Open' && auth()->user()->hasPermission('trips.update'))
<form method="POST" action="{{ route('trips.update',$trip) }}" id="trip-form" data-trip-editor data-availability-url="{{ route('trips.availability',$trip) }}" data-dispatch-url="{{ route('trips.dispatch',$trip) }}">
    @csrf @method('PUT')<input type="hidden" name="version" value="{{ $trip->version }}">
    <x-card title="General Trip Information" data-trip-section="general" :collapsible="true" :collapsed="true" id="trip-section-general">
        <div class="trip-form-grid">
            <div class="form-group"><label>Trip ID</label><input class="form-input" value="{{ $trip->trip_id }}" readonly></div>
            @foreach(['trip_no'=>'Trip number','drops_number'=>'Number of drops'] as $field=>$label)
            <div class="form-group"><label for="{{ $field }}">{{ $label }}</label><input class="form-input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$trip->$field) }}" @if($field==='drops_number') type="number" min="0" max="10000" required @else maxlength="150" @endif></div>
            @endforeach
            <div class="form-group"><label for="trip_type">Trip type</label><select data-trip-searchable class="form-select" id="trip_type" name="trip_type"><option value="">Select</option>@foreach($tripTypes as $type)<option value="{{ $type->trip_type }}" @selected(old('trip_type',$trip->trip_type)===$type->trip_type)>{{ $type->trip_type }}</option>@endforeach</select></div>

        <div class="form-group"><label for="payment_type">Payment Type</label><select data-trip-searchable class="form-select" id="payment_type" name="payment_type"><option value="">Select payment type</option>@foreach($payments as $payment)<option value="{{ $payment->payment_type }}" @selected(old('payment_type',$trip->payment_type)===$payment->payment_type)>{{ $payment->payment_type }}</option>@endforeach</select></div>
    
        <div class="form-group"><label for="tariff_id">Trip Rate</label><select data-trip-searchable class="form-select" id="tariff_id" name="tariff_id" data-trip-rate><option value="">Select matching Trip Rate</option>@foreach($tripRates as $rate)<option value="{{ $rate->id }}" data-rate="{{ json_encode($rate->only(['trip_number','trip_type','payment_type'])) }}" data-personnel-rates="{{ json_encode($rate->personnelRatesForDesignations($designations)) }}" @selected(old('tariff_id',$trip->tariff_id)==$rate->id)>#{{ $rate->id }} &middot; {{ $rate->trip_number }} &middot; {{ $rate->client_code }} &middot; {{ $rate->vehicle_group }} &middot; {{ $rate->trip_type }} &middot; {{ $rate->payment_type }}</option>@endforeach</select></div>
            </div>
    </x-card>
    <x-card title="Vehicle" data-trip-section="vehicle" :collapsible="true" :collapsed="true" id="trip-section-vehicle">
        <div class="form-group"><label for="vehicle_id">Van / vehicle</label><select data-trip-searchable class="form-select" name="vehicle_id" id="vehicle_id" data-trip-vehicle><option value="">Select vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" data-vehicle="{{ json_encode($vehicle->only(['body_number','vehicle_group','plate_number','fuel_capacity','fuel_type','fuel_usage_per_kilometer','cargo_capacity','engine_number','chassi_number'])) }}" @disabled($assignmentAvailability['vehicles'][$vehicle->id] !== null) data-eligibility-reason="{{ $assignmentAvailability['vehicles'][$vehicle->id] }}" @selected(old('vehicle_id',$trip->vehicle_id)==$vehicle->id)>{{ $vehicle->body_number }} &middot; {{ $vehicle->plate_number }}{{ $assignmentAvailability['vehicles'][$vehicle->id] ? ' ? '.$assignmentAvailability['vehicles'][$vehicle->id] : '' }}</option>@endforeach</select></div>
        <p>Only Running vehicles and Active personnel available outside dispatched trips can be selected. Availability is checked again before saving.</p>
        <div class="trip-form-grid" data-vehicle-fields>
            @foreach(['body_number'=>'Van number','vehicle_group'=>'Vehicle group','plate_number'=>'Plate number','fuel_capacity'=>'Fuel capacity (L)','fuel_type'=>'Fuel type','fuel_usage_per_kilometer'=>'Fuel usage (L/km)','cargo_capacity'=>'Cargo capacity','engine_number'=>'Engine number','chassi_number'=>'Chassis number'] as $field=>$label)
            <div class="form-group"><label>{{ $label }}</label><input class="form-input" data-vehicle-field="{{ $field }}" readonly></div>
            @endforeach
        </div>
    </x-card>
    @foreach(['segments'=>'Route','personnels'=>'Personnel','costs'=>'Additional Trip Costs'] as $collection=>$title)
    <x-card :title="$title" :data-trip-section="$collection" :collapsible="true" :collapsed="true" :id="'trip-section-'.$collection">
        <div data-trip-collection="{{ $collection }}">
            @php($relation = ['personnels'=>'personnels','segments'=>'segments','costs'=>'costs'][$collection])
            @foreach(old($collection,$collection==='costs' ? $costRows : $trip->$relation->toArray()) as $row)
                @include('trips.row',['collection'=>$collection,'row'=>$row,'index'=>$loop->index])
            @endforeach
        </div>
        <button type="button" class="button button-outline" data-trip-add="{{ $collection }}">Add {{ $title }}</button>
        <template id="trip-template-{{ $collection }}">@include('trips.row',['collection'=>$collection,'row'=>[],'index'=>'__INDEX__'])</template>
        @if($collection==='segments')<p>Saved total distance: {{ number_format($trip->total_distance,2) }} km.</p>@endif
    </x-card>
    @endforeach
    <x-card title="Disbursement Information (Cash Released / GCash)" data-trip-section="disbursements" :collapsible="true" :collapsed="true" id="trip-section-disbursements">
        <div class="form-group"><label for="gcash_number">GCash number</label><input class="form-input" id="gcash_number" name="gcash_number" value="{{ old('gcash_number',$trip->gcash_number) }}" maxlength="150"></div>
        @php($savedDisbursements = $trip->personnels->map(fn($p) => ['employee_id'=>$p->employee_id,'cash_released'=>$p->cash_released])->values()->all())
        <div data-trip-disbursements data-saved-releases="{{ json_encode($savedDisbursements) }}">
            @foreach(old('disbursements',$savedDisbursements) as $release)
                @include('trips.disbursement-row',['release'=>$release,'index'=>$loop->index])
            @endforeach
        </div>
        <template id="trip-disbursement-template">@include('trips.disbursement-row',['release'=>[],'index'=>'__INDEX__'])</template>
    </x-card>
    <div class="form-actions"><button class="button button-primary" type="submit">Save Trip</button>@if(auth()->user()->hasPermission('trips.dispatch'))<button class="button button-primary trip-action trip-action-dispatch" type="submit" name="intent" value="dispatch">Save &amp; Dispatch Truck</button>@endif</div>
</form>
@endif
<div class="trip-preview"><p class="trip-preview-label">Saved trip document</p>@include('trips.document')</div>
</div>
@endsection
