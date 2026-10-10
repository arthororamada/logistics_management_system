@extends('layouts.app')
@section('title', 'Add User')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Users', 'url' => route('users.index')], ['label' => 'Add User']])
@section('content')
    <section class="page-heading"><div><p class="eyebrow">User management</p><h2>Add User</h2><p>Create a username-based account and assign its application access.</p></div></section>
    <form method="POST" action="{{ route('users.store') }}">@csrf @include('users.partials.form', ['submitLabel' => 'Create User'])</form>
@endsection
