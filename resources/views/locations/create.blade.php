@extends('layouts.app')
@section('title', 'Add Location')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'User Management'], ['label' => 'Locations', 'url' => route('locations.index')], ['label' => 'Add Location']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">User Management</p><h2>Add Location</h2><p>Register a location and its geographic coordinates.</p></div></section>
    <form method="POST" action="{{ route('locations.store') }}">@csrf @include('locations.partials.form', ['submitLabel' => 'Create Location'])</form>
@endsection
