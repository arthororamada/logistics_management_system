@extends('layouts.app')
@section('title', 'Edit Vehicle Maintenance')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Maintenance', 'url' => route('vehicle-maintenances.index')], ['label' => 'Edit Policy']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $vehicleMaintenance->maintenance_code }}</h2><p>Update this maintenance policy and its trigger thresholds.</p></div></section>
    <form method="POST" action="{{ route('vehicle-maintenances.update', $vehicleMaintenance) }}">@csrf @method('PUT') @include('vehicle-maintenances.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
