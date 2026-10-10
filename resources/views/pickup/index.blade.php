@extends('layouts.app')
@section('title', 'Pickup')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Pickup']])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Pickup</h2><p>Create a pickup manifest with multiple waybill numbers.</p></div>
    </section>

    @if(auth()->user()->hasPermission('pickup.create'))
        <form method="POST" action="{{ route('pickup.waybills.add') }}">
            @csrf
            <x-card title="New Pickup" subtitle="Type or paste waybill numbers to validate and add to your document manifest.">
                <div class="form-stack">
                    <div class="form-group">
                        <label for="waybill_numbers">Waybill numbers <span class="required">*</span></label>
                        <textarea id="waybill_numbers" name="waybill_numbers" class="textarea-control @error('waybill_numbers') has-error @enderror" rows="10" maxlength="100000" required aria-describedby="waybill-hint @error('waybill_numbers') waybill-error @enderror" @error('waybill_numbers') aria-invalid="true" @enderror placeholder="Enter waybill numbers here...">{{ old('waybill_numbers') }}</textarea>
                        <p class="field-hint" id="waybill-hint">Separate numbers with new lines, commas, semicolons, or spaces. Up to 1,000 numbers per pickup. Duplicate entries are saved once; leading zeros are preserved.</p>
                        @if($errors->has('waybill_numbers'))<div class="form-error" id="waybill-error" role="alert">@foreach($errors->get('waybill_numbers') as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                    </div>
                </div>
                <div class="form-actions"><x-button type="submit" variant="primary" icon="plus">Add Waybill Number/s</x-button></div>
            </x-card>
        </form>
        <x-card title="Added Waybill Numbers" :subtitle="count($draftNumbers).' waybill number(s) ready for the document manifest.'">
            <div class="table-wrap">
                <table class="access-table">
                    <thead><tr><th>Waybill Number</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($draftNumbers as $number)
                            <tr><td>{{ $number }}</td><td>
                                <form method="POST" action="{{ route('pickup.waybills.remove') }}">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="waybill_number" value="{{ $number }}">
                                    <x-button type="submit" variant="secondary" aria-label="Remove waybill {{ $number }}">Remove</x-button>
                                </form>
                            </td></tr>
                        @empty
                            <tr><td colspan="2" class="empty-table">Add valid waybill numbers to start your document manifest.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('pickup.store') }}" class="form-stack">
                @csrf
                <div class="form-group">
                    <label for="driver_id">Driver <span class="required">*</span></label>
                    <select id="driver_id" name="driver_id" class="form-select @error('driver_id') has-error @enderror" required aria-describedby="driver-hint @error('driver_id') driver-error @enderror" @error('driver_id') aria-invalid="true" @enderror>
                        <option value="">Select a driver</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) old('driver_id') === (string) $driver->id)>{{ $driver->last_name }}, {{ $driver->first_name }} {{ $driver->middle_name }} ({{ $driver->employee_id }})</option>
                        @endforeach
                    </select>
                    <p class="field-hint" id="driver-hint">Select an active driver before creating the document manifest.</p>
                    @if($drivers->isEmpty())<p class="form-error">No active drivers are available. Add or activate a driver in Personnel.</p>@endif
                    @error('driver_id')<p class="form-error" id="driver-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-actions"><x-button type="submit" variant="primary" :disabled="count($draftNumbers) === 0 || $drivers->isEmpty()">Create Document Manifest</x-button></div>
            </form>
        </x-card>
    @endif

    <x-card title="Saved Pickups" subtitle="Previously saved pickup manifests.">
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Pickup</th><th>Waybill Numbers</th><th>Total</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse($pickups as $pickup)
                        <tr>
                            <td><a href="{{ route('pickup.show', $pickup) }}"><strong>#{{ $pickup->id }}</strong></a></td>
                            <td><details><summary>View waybill numbers</summary>@foreach($pickup->waybill_numbers as $number)<div>{{ $number }}</div>@endforeach</details></td>
                            <td>{{ count($pickup->waybill_numbers) }}</td>
                            <td><span class="date-label">{{ $pickup->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-table">No pickups saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pickups->hasPages())<div class="pagination-links">{{ $pickups->links() }}</div>@endif
    </x-card>
@endsection
