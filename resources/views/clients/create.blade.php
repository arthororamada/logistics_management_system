@extends('layouts.app')
@section('title', 'Add Client')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Clients', 'url' => route('clients.index')], ['label' => 'Add Client']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Client</h2><p>Register a client and the contact information used for trip coordination.</p></div></section>
    <form method="POST" action="{{ route('clients.store') }}">@csrf @include('clients.partials.form', ['submitLabel' => 'Create Client'])</form>
@endsection
