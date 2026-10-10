@extends('layouts.app')
@section('title', 'Add Vehicle Type')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Types', 'url' => route('vehicle-types.index')], ['label' => 'Add Vehicle Type']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Vehicle Type</h2><p>Create a classification for fleet vehicles.</p></div></section>
    <form method="POST" action="{{ route('vehicle-types.store') }}">@csrf @include('vehicle-types.partials.form', ['submitLabel' => 'Create Vehicle Type'])</form>
@endsection
