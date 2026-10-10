@extends('layouts.app')
@section('title', 'Edit Route')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Routes', 'url' => route('routes.index')], ['label' => 'Edit Route']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit Route</h2><p>Update this route point and its availability.</p></div></section>
    <form method="POST" action="{{ route('routes.update', $routeRecord) }}">@csrf @method('PUT') @include('routes.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
