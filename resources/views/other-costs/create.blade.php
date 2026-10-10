@extends('layouts.app')
@section('title', 'Add Other Cost')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Other Costs', 'url' => route('other-costs.index')], ['label' => 'Add Other Cost']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Add Other Cost</h2><p>Register an additional cost used during trip operations.</p></div></section><form method="POST" action="{{ route('other-costs.store') }}">@csrf @include('other-costs.partials.form', ['submitLabel' => 'Create Other Cost'])</form>@endsection
