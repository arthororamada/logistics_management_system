@extends('layouts.app')
@section('title', 'Add Vehicle Group')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Groups', 'url' => route('vehicle-groups.index')], ['label' => 'Add Vehicle Group']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Configuration</p><h2>Add Vehicle Group</h2><p>Create a grouping option for fleet vehicles.</p></div></section>
    <form method="POST" action="{{ route('vehicle-groups.store') }}">@csrf @include('vehicle-groups.partials.form', ['submitLabel' => 'Create Vehicle Group'])</form>
@endsection
