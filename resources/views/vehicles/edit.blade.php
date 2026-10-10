@extends('layouts.app')
@section('title', 'Edit Vehicle')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Vehicles', 'url' => route('vehicles.index')], ['label' => 'Edit Vehicle']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Fleet Operations</p><h2>Edit {{ $vehicle->plate_number }}</h2><p>Update vehicle information and specifications.</p></div></section><form method="POST" action="{{ route('vehicles.update', $vehicle) }}">@csrf @method('PUT') @include('vehicles.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
