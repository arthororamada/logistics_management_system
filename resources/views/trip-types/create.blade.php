@extends('layouts.app')
@section('title', 'Add Trip Type')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Trip Types', 'url' => route('trip-types.index')], ['label' => 'Add Trip Type']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Trip Type</h2><p>Create a classification for fleet trips.</p></div></section><form method="POST" action="{{ route('trip-types.store') }}">@csrf @include('trip-types.partials.form', ['submitLabel' => 'Create Trip Type'])</form>@endsection
