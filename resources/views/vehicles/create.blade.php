@extends('layouts.app')
@section('title', 'Add Vehicle')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Vehicles', 'url' => route('vehicles.index')], ['label' => 'Add Vehicle']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Fleet Operations</p><h2>Add Vehicle</h2><p>Register vehicle identity, specifications, and operational details.</p></div></section><form method="POST" action="{{ route('vehicles.store') }}">@csrf @include('vehicles.partials.form', ['submitLabel' => 'Create Vehicle'])</form>@endsection
