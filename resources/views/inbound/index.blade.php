@extends('layouts.app')
@section('title', 'Inbound')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Inbound']])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Inbound</h2><p>Create an inbound manifest for packages accepted by the courier.</p></div>
    </section>

    @if(auth()->user()->hasPermission('inbound.create'))
        <form method="POST" action="{{ route('inbound.waybills.add') }}">
            @csrf
            <x-card title="New Inbound" subtitle="Enter waybill numbers to validate their package status.">
                <div class="form-stack">
                    <div class="form-group">
                        <label for="waybill_numbers">Waybill numbers <span class="required">*</span></label>
                        <textarea id="waybill_numbers" name="waybill_numbers" class="textarea-control @error('waybill_numbers') has-error @enderror" rows="10" maxlength="100000" required aria-describedby="waybill-hint @error('waybill_numbers') waybill-error @enderror" @error('waybill_numbers') aria-invalid="true" @enderror placeholder="Enter waybill numbers here...">{{ old('waybill_numbers') }}</textarea>
                        <p class="field-hint" id="waybill-hint">Each package must have status accepted_by_courier. Separate numbers with new lines, commas, semicolons, or spaces. Up to 1,000 numbers per manifest. Duplicates are added once; leading zeros are preserved.</p>
                        @if($errors->has('waybill_numbers'))<div class="form-error" id="waybill-error" role="alert">@foreach($errors->get('waybill_numbers') as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                    </div>
                </div>
                <div class="form-actions"><x-button type="submit" variant="primary" icon="plus">Add Waybill Number/s</x-button></div>
            </x-card>
        </form>

        <x-card title="Added Waybill Numbers" :subtitle="count($draftNumbers).' waybill number(s) ready for the inbound manifest.'">
            <div class="table-wrap">
                <table class="access-table">
                    <thead><tr><th>Waybill Number</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($draftNumbers as $number)
                            <tr><td>{{ $number }}</td><td>
                                <form method="POST" action="{{ route('inbound.waybills.remove') }}">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="waybill_number" value="{{ $number }}">
                                    <x-button type="submit" variant="secondary" aria-label="Remove waybill {{ $number }}">Remove</x-button>
                                </form>
                            </td></tr>
                        @empty
                            <tr><td colspan="2" class="empty-table">Add valid waybill numbers to start your inbound manifest.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('inbound.store') }}" class="form-actions">
                @csrf
                <x-button type="submit" variant="primary" :disabled="count($draftNumbers) === 0">Create Document Manifest</x-button>
            </form>
        </x-card>
    @endif

    <x-card title="Saved Inbound Manifests">
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Manifest</th><th>Waybill Numbers</th><th>Total</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse($inbounds as $inbound)
                        <tr>
                            <td><a href="{{ route('inbound.show', $inbound) }}"><strong>#{{ $inbound->id }}</strong></a></td>
                            <td><details><summary>View waybill numbers</summary>@foreach($inbound->waybill_numbers as $number)<div>{{ $number }}</div>@endforeach</details></td>
                            <td>{{ count($inbound->waybill_numbers) }}</td>
                            <td><span class="date-label">{{ $inbound->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-table">No inbound manifests saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($inbounds->hasPages())<div class="pagination-links">{{ $inbounds->links() }}</div>@endif
    </x-card>
@endsection
