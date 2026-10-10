@extends('layouts.app')
@section('title', 'Add Trip Payment')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Trip Payments', 'url' => route('trip-payments.index')], ['label' => 'Add Trip Payment']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Trip Payment</h2><p>Create a payment type for trip settlement.</p></div></section><form method="POST" action="{{ route('trip-payments.store') }}">@csrf @include('trip-payments.partials.form', ['submitLabel' => 'Create Trip Payment'])</form>@endsection
