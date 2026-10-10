@extends('layouts.app')
@section('title', 'Add Shipping Line')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Shipping Lines', 'url' => route('shipping-lines.index')], ['label' => 'Add Shipping Line']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Shipping Line</h2><p>Register a shipping line and its primary contact.</p></div></section><form method="POST" action="{{ route('shipping-lines.store') }}">@csrf @include('shipping-lines.partials.form', ['submitLabel' => 'Create Shipping Line'])</form>@endsection
