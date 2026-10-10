@extends('layouts.app')
@section('title', 'Edit Trip Payment')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Trip Payments', 'url' => route('trip-payments.index')], ['label' => 'Edit Trip Payment']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $tripPayment->payment_type }}</h2><p>Update this payment type and its availability.</p></div></section><form method="POST" action="{{ route('trip-payments.update', $tripPayment) }}">@csrf @method('PUT') @include('trip-payments.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
