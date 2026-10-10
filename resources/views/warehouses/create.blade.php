@extends('layouts.app')
@section('title', 'Add Warehouse')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Warehouses', 'url' => route('warehouses.index')], ['label' => 'Add Warehouse']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Warehouse</h2><p>Register a warehouse and its precise operating location.</p></div></section>
    <form method="POST" action="{{ route('warehouses.store') }}">@csrf @include('warehouses.partials.form', ['submitLabel' => 'Create Warehouse'])</form>
@endsection
