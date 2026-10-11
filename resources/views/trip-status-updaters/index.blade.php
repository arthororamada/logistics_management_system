@extends('layouts.app')
@section('title', 'Trip Status Updater')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Trip Maintenance</p><h2>Trip Status Updater</h2></div></section>
<div data-trip-status-updater>
    <div class="alert" data-status-message role="status" hidden></div>
    <x-card title="Trip Search">
        <form class="trip-filter" method="GET" action="{{ route('trip-status-updaters.search') }}" data-status-search>
            <div class="form-group"><label for="status-trip-search">Trip ID</label><input class="form-input" id="status-trip-search" name="trip_id" maxlength="50" required autocomplete="off"></div>
            <button class="button button-primary" type="submit">Search</button>
        </form>
        <div class="table-wrap"><table class="access-table">
            <thead><tr><th>Action</th><th>Trip ID</th><th>Date Created</th><th>Van Number</th><th>Status</th></tr></thead>
            <tbody data-status-results data-can-update="{{ auth()->user()->hasPermission('trip-status-updaters.update') ? '1' : '0' }}"><tr><td colspan="5" class="empty-table">No trip selected.</td></tr></tbody>
        </table></div>
    </x-card>
    @if(auth()->user()->hasPermission('trip-status-updaters.update'))
    <div data-status-editor hidden>
        <x-card title="Update Trip Status">
            <form method="POST" action="{{ route('trip-status-updaters.update') }}" data-status-update>
                @csrf @method('PUT')
                <div class="trip-form-grid">
                    <div class="form-group"><label for="status-trip-id">Trip ID</label><input class="form-input" id="status-trip-id" data-status-trip-id disabled></div>
                    <div class="form-group"><label for="status-current">Current Status</label><input class="form-input" id="status-current" data-status-current disabled></div>
                    <div class="form-group"><label for="status-new">New Status</label><select class="form-select" id="status-new" name="new_status" required><option value="">Select new status</option><option value="Open">Open</option><option value="Cancelled">Cancelled</option></select></div>
                    <div class="form-group"><label for="status-reason">Change Status Reason</label><textarea class="form-input" id="status-reason" name="reason" maxlength="2000" required rows="3"></textarea></div>
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Update</button><button class="button button-outline" type="button" data-status-cancel>Cancel</button></div>
            </form>
        </x-card>
    </div>
    @endif
</div>
@endsection
