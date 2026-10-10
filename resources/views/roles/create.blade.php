@extends('layouts.app')
@section('title', 'Create Role')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Roles', 'url' => route('roles.index')], ['label' => 'Create Role']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">Access control</p><h2>Create Role</h2><p>Create a role and select the permissions it should receive.</p></div></section>
    <form method="POST" action="{{ route('roles.store') }}">@csrf @include('roles.partials.form', ['submitLabel' => 'Create Role'])</form>
@endsection
