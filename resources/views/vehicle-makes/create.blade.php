@extends('layouts.app')
@section('title', 'Add Vehicle Make')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Makes', 'url' => route('vehicle-makes.index')], ['label' => 'Add Vehicle Make']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Vehicle Make</h2><p>Register a manufacturer for fleet vehicles.</p></div></section>
    <form method="POST" action="{{ route('vehicle-makes.store') }}">@csrf @include('vehicle-makes.partials.form', ['submitLabel' => 'Create Vehicle Make'])</form>
@endsection
