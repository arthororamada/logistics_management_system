@extends('layouts.app')
@section('title', 'Edit Trip Type')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Trip Types', 'url' => route('trip-types.index')], ['label' => 'Edit Trip Type']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $tripType->trip_type }}</h2><p>Update this trip classification and its availability.</p></div></section><form method="POST" action="{{ route('trip-types.update', $tripType) }}">@csrf @method('PUT') @include('trip-types.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
