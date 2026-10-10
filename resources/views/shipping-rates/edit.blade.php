@extends('layouts.app')
@section('title', 'Edit Shipping Rate')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Tariffs'], ['label' => 'Shipping Rates', 'url' => route('shipping-rates.index')], ['label' => 'Edit Shipping Rate']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Tariffs</p><h2>Edit Shipping Rate</h2><p>Update this rate and its availability.</p></div></section><form method="POST" action="{{ route('shipping-rates.update', $shippingRate) }}">@csrf @method('PUT') @include('shipping-rates.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
