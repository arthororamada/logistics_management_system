@extends('layouts.app')
@section('title', 'Edit Vehicle Type')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Types', 'url' => route('vehicle-types.index')], ['label' => 'Edit Vehicle Type']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $vehicleType->vehicle_type }}</h2><p>Update this vehicle classification and its availability.</p></div></section>
    <form method="POST" action="{{ route('vehicle-types.update', $vehicleType) }}">@csrf @method('PUT') @include('vehicle-types.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
