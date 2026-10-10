@extends('layouts.app')
@section('title', 'Edit Role')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Roles', 'url' => route('roles.index')], ['label' => 'Edit Role']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Access control</p><h2>Edit {{ $role->name }}</h2><p>Update role details and its assigned permissions.</p></div></section>
    <form method="POST" action="{{ route('roles.update', $role) }}">@csrf @method('PUT') @include('roles.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
