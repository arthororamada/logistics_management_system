@extends('layouts.app')
@section('title', 'Add Fuel Supplier')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Fuel Suppliers', 'url' => route('fuel-suppliers.index')], ['label' => 'Add Fuel Supplier']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Fuel Supplier</h2><p>Register a supplier and its current fuel price.</p></div></section><form method="POST" action="{{ route('fuel-suppliers.store') }}">@csrf @include('fuel-suppliers.partials.form', ['submitLabel' => 'Create Fuel Supplier'])</form>@endsection
