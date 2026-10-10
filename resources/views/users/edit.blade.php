@extends('layouts.app')
@section('title', 'Edit User')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Users', 'url' => route('users.index')], ['label' => 'Edit User']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">User management</p><h2>Edit {{ $user->name }}</h2><p>Update account details, credentials, status, and assigned roles.</p></div></section>
    <form method="POST" action="{{ route('users.update', $user) }}">@csrf @method('PUT') @include('users.partials.form', ['submitLabel' => 'Save Changes'])</form>
@endsection
