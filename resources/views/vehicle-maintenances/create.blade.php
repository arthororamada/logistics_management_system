@extends('layouts.app')
@section('title', 'Add Vehicle Maintenance')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Maintenance', 'url' => route('vehicle-maintenances.index')], ['label' => 'Add Policy']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Vehicle Maintenance Policy</h2><p>Create a distance- or date-based maintenance rule.</p></div></section>
    <form method="POST" action="{{ route('vehicle-maintenances.store') }}">@csrf @include('vehicle-maintenances.partials.form', ['submitLabel' => 'Create Policy'])</form>
@endsection
