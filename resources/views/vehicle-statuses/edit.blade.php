@extends('layouts.app')
@section('title', 'Edit Vehicle Status')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Statuses', 'url' => route('vehicle-statuses.index')], ['label' => 'Edit Vehicle Status']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $vehicleStatus->vehicle_status }}</h2><p>Update this vehicle status and its availability.</p></div></section><form method="POST" action="{{ route('vehicle-statuses.update', $vehicleStatus) }}">@csrf @method('PUT') @include('vehicle-statuses.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
