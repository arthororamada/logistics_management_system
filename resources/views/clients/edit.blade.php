@extends('layouts.app')
@section('title', 'Edit Client')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Clients', 'url' => route('clients.index')], ['label' => 'Edit Client']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $client->client_name }}</h2><p>Update client details and availability for trip planning.</p></div></section>
    <form method="POST" action="{{ route('clients.update', $client) }}">@csrf @method('PUT') @include('clients.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
