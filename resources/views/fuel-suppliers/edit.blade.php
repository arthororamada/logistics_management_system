@extends('layouts.app')
@section('title', 'Edit Fuel Supplier')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Fuel Suppliers', 'url' => route('fuel-suppliers.index')], ['label' => 'Edit Fuel Supplier']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $fuelSupplier->fuel_supplier_name }}</h2><p>Update supplier pricing and availability.</p></div></section><form method="POST" action="{{ route('fuel-suppliers.update', $fuelSupplier) }}">@csrf @method('PUT') @include('fuel-suppliers.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
