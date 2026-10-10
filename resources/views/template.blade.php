@extends('layouts.app')

@section('title', 'Sample Page')

@php
    $breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Sample Page']];
@endphp

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Template reference</p><h2>Sample Page</h2><p>This page demonstrates the reusable application template and core interface styles.</p></div>
        <div class="heading-actions"><x-button variant="primary" icon="plus">Primary Action</x-button><x-button variant="outline">Secondary Action</x-button></div>
    </section>

    <div class="template-stack">
        <x-card title="Example Card" subtitle="Use cards to group related information and actions.">
            <p class="example-copy">This is an example reusable content card. Its header, spacing, border, and typography remain consistent across the application.</p>
        </x-card>

        <x-card title="Example Table" subtitle="A responsive table pattern for future record pages.">
            <x-slot:actions><x-button variant="ghost">Export</x-button></x-slot:actions>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Status</th><th class="align-right">Action</th></tr></thead>
                    <tbody>
                        <tr><td class="record-id">001</td><td><strong>Example Record</strong><small>Created Aug 28, 2026</small></td><td><x-status-badge status="active" /></td><td class="align-right"><button class="table-action" type="button">View</button></td></tr>
                        <tr><td class="record-id">002</td><td><strong>Example Record</strong><small>Created Aug 30, 2026</small></td><td><x-status-badge status="pending" /></td><td class="align-right"><button class="table-action" type="button">View</button></td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
