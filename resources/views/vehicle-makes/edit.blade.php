@extends('layouts.app')
@section('title', 'Edit Vehicle Make')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Makes', 'url' => route('vehicle-makes.index')], ['label' => 'Edit Vehicle Make']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $vehicleMake->vehicle_make }}</h2><p>Update this manufacturer and its availability.</p></div></section>
    <form method="POST" action="{{ route('vehicle-makes.update', $vehicleMake) }}">@csrf @method('PUT') @include('vehicle-makes.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
