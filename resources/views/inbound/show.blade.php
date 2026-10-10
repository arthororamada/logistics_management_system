@extends('layouts.app')
@section('title', 'Inbound Document Manifest #'.$inbound->id)
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Inbound', 'url' => route('inbound.index')], ['label' => '#'.$inbound->id]])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Inbound Document Manifest #{{ $inbound->id }}</h2><p>Review the inbound manifest and its packages.</p></div>
        <x-button :href="route('inbound.index')" variant="secondary">Back to Inbound</x-button>
    </section>

    <x-card title="Document Manifest Details">
        <p><strong>Manifest:</strong> #{{ $inbound->id }}</p>
        <p><strong>Created by:</strong> {{ $creator ?? 'Unavailable' }}</p>
        <p><strong>Created:</strong> {{ $inbound->created_at?->format('M d, Y H:i') }}</p>
        <p><strong>Total waybill numbers:</strong> {{ count($inbound->waybill_numbers) }}</p>
    </x-card>

    <x-card title="Waybill Numbers and Packages">
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Waybill Number</th><th>Package ID</th><th>Buyer</th><th>Address</th><th>Package Status</th></tr></thead>
                <tbody>
                    @foreach($inbound->waybill_numbers as $number)
                        @forelse($packages->get($number, collect()) as $package)
                            <tr><td>{{ $number }}</td><td>{{ $package->package_id }}</td><td>{{ $package->buyer_name }}</td><td>{{ $package->buyer_full_address }}</td><td>{{ $package->package_status }}</td></tr>
                        @empty
                            <tr><td>{{ $number }}</td><td colspan="4">Package unavailable or deleted</td></tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
