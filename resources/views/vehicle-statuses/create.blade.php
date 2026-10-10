@extends('layouts.app')
@section('title', 'Add Vehicle Status')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Statuses', 'url' => route('vehicle-statuses.index')], ['label' => 'Add Vehicle Status']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Vehicle Status</h2><p>Create an operational status for fleet vehicles.</p></div></section><form method="POST" action="{{ route('vehicle-statuses.store') }}">@csrf @include('vehicle-statuses.partials.form', ['submitLabel' => 'Create Vehicle Status'])</form>@endsection
