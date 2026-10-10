@extends('layouts.app')
@section('title', 'Edit Other Cost')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Trip Maintenance'], ['label' => 'Other Costs', 'url' => route('other-costs.index')], ['label' => 'Edit Other Cost']])
@section('content')<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Edit {{ $otherCost->cost_name }}</h2><p>Update this cost and its release settings.</p></div></section><form method="POST" action="{{ route('other-costs.update', $otherCost) }}">@csrf @method('PUT') @include('other-costs.partials.form', ['submitLabel' => 'Save Changes'])</form>@endsection
