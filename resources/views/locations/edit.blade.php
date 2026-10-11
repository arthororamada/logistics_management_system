@extends('layouts.app')
@section('title', 'Edit Location')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'User Management'], ['label' => 'Locations', 'url' => route('locations.index')], ['label' => 'Edit Location']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">User Management</p><h2>Edit {{ $location->location_name }}</h2><p>Update the location identity and coordinates.</p></div></section>
    <form method="POST" action="{{ route('locations.update', $location) }}">@csrf @method('PUT') @include('locations.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
