@extends('layouts.app')
@section('title', 'Add Route')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Routes', 'url' => route('routes.index')], ['label' => 'Add Route']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Route</h2><p>Register a barangay route point and its geographic coordinates.</p></div></section>
    <form method="POST" action="{{ route('routes.store') }}">@csrf @include('routes.partials.form', ['submitLabel' => 'Create Route'])</form>
@endsection
