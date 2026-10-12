@extends('layouts.app')
@section('title', 'Transfer Document Manifest #'.$transfer->id)
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Transfer', 'url' => route('transfer.index')], ['label' => '#'.$transfer->id]])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Transfer Document Manifest #{{ $transfer->id }}</h2><p>Review the manifest and its waybill numbers.</p></div>
        <x-button :href="route('transfer.index')" variant="secondary">Back to Transfers</x-button>
    </section>

    @if($errors->any())
        <div class="alert alert-error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
    @endif

    <x-card title="Document Manifest Details">
        <p><strong>Manifest:</strong> #{{ $transfer->id }}</p>
        <p><strong>Created by:</strong> {{ $creator ?? 'Unavailable' }}</p>
        <p><strong>Created:</strong> {{ $transfer->created_at?->format('M d, Y H:i') }}</p>
        <p><strong>Total waybill numbers:</strong> {{ count($transfer->waybill_numbers) }}</p>
        <p><strong>Dispatch:</strong> {{ $details->isEmpty() ? 'Awaiting dispatch' : 'Dispatched' }}</p>
    </x-card>

    <x-card title="Waybill Numbers and Packages">
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Waybill Number</th><th>Package ID</th><th>Buyer</th><th>Address</th><th>Package Status</th><th>Manifest Status</th></tr></thead>
                <tbody>
                    @foreach($transfer->waybill_numbers as $number)
                        @forelse($packages->get($number, collect()) as $package)
                            <tr>
                                <td>{{ $number }}</td><td>{{ $package->package_id }}</td><td>{{ $package->buyer_name }}</td>
                                <td>{{ $package->buyer_full_address }}</td><td>{{ $package->package_status }}</td>
                                <td>{{ $details->get($number)?->status ?? 'Awaiting dispatch' }}</td>
                            </tr>
                        @empty
                            <tr><td>{{ $number }}</td><td colspan="4">Package unavailable or deleted</td><td>{{ $details->get($number)?->status ?? 'Awaiting dispatch' }}</td></tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
