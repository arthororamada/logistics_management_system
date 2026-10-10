@extends('layouts.app')
@section('title', 'Edit Shipping Line')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Shipping Lines', 'url' => route('shipping-lines.index')], ['label' => 'Edit Shipping Line']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $shippingLine->shipping_line_name }}</h2><p>Update shipping line details and availability.</p></div></section><form method="POST" action="{{ route('shipping-lines.update', $shippingLine) }}">@csrf @method('PUT') @include('shipping-lines.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
