@extends('layouts.app')
@section('title', 'Edit Warehouse')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Warehouses', 'url' => route('warehouses.index')], ['label' => 'Edit Warehouse']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Edit {{ $warehouse->warehouse_description }}</h2><p>Update warehouse identity, location, and operating status.</p></div></section>
    <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">@csrf @method('PUT') @include('warehouses.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
