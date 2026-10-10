@extends('layouts.app')
@section('title', 'Edit Vehicle Group')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Groups', 'url' => route('vehicle-groups.index')], ['label' => 'Edit Vehicle Group']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $vehicleGroup->vehicle_group_description }}</h2><p>Update this vehicle group and its availability.</p></div></section>
    <form method="POST" action="{{ route('vehicle-groups.update', $vehicleGroup) }}">@csrf @method('PUT') @include('vehicle-groups.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
