@extends('layouts.app')
@section('title', 'Upload Waybills')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Waybill', 'url' => route('waybills.index')], ['label' => 'Upload']])
@section('content')
<section class="page-heading"><div><p class="eyebrow">Waybill</p><h2>Mass Upload</h2><p>Import packages and their connected items from CSV.</p></div><a href="{{ route('waybills.template') }}" class="button button-outline">Download CSV Template</a></section>
<x-card title="Upload Waybills" subtitle="Use one row per item. Repeat the package fields and waybill number for additional items in the same package." class="narrow-form-card"><form method="POST" action="{{ route('waybills.import') }}" enctype="multipart/form-data" class="form-stack">@csrf<div class="form-group"><label for="waybill-upload">CSV file <span class="required">*</span></label><input id="waybill-upload" name="file" type="file" accept=".csv,.txt" required>@error('file')<p class="form-error">{{ $message }}</p>@enderror</div><div class="form-actions"><a href="{{ route('waybills.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary" icon="upload">Upload Waybills</x-button></div></form></x-card>
@endsection
