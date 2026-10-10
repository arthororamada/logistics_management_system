@extends('layouts.app')
@section('title', 'Add Shipping Rate')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Tariffs'], ['label' => 'Shipping Rates', 'url' => route('shipping-rates.index')], ['label' => 'Add Shipping Rate']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Tariffs</p><h2>Add Shipping Rate</h2><p>Register a rate for a shipping line, port pair, and vehicle type.</p></div></section><form method="POST" action="{{ route('shipping-rates.store') }}">@csrf @include('shipping-rates.partials.form', ['submitLabel' => 'Create Shipping Rate'])</form>@endsection
