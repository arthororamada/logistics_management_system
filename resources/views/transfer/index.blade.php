@extends('layouts.app')
@section('title', 'Transfer')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Transfer']])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Transfer</h2><p>Create a transfer manifest with multiple waybill numbers.</p></div>
    </section>

    @if(auth()->user()->hasPermission('transfer.create'))
        <form method="POST" action="{{ route('transfer.waybills.add') }}">
            @csrf
            <x-card title="New Transfer" subtitle="Type or paste waybill numbers to validate and add to your document manifest.">
                <div class="form-stack">
                    <div class="form-group">
                        <label for="waybill_numbers">Waybill numbers <span class="required">*</span></label>
                        <textarea id="waybill_numbers" name="waybill_numbers" class="textarea-control @error('waybill_numbers') has-error @enderror" rows="10" maxlength="100000" required aria-describedby="waybill-hint @error('waybill_numbers') waybill-error @enderror" @error('waybill_numbers') aria-invalid="true" @enderror placeholder="Enter waybill numbers here...">{{ old('waybill_numbers') }}</textarea>
                        <p class="field-hint" id="waybill-hint">Each package must have status accepted_to_warehouse. Separate numbers with new lines, commas, semicolons, or spaces. Up to 1,000 numbers per transfer. Duplicate entries are saved once; leading zeros are preserved.</p>
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
                                <form method="POST" action="{{ route('transfer.waybills.remove') }}">
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
            <form method="POST" action="{{ route('transfer.store') }}" class="form-stack">
                @csrf
                <div class="form-actions"><x-button type="submit" variant="primary" :disabled="count($draftNumbers) === 0">Create Document Manifest</x-button></div>
            </form>
        </x-card>
    @endif

    <x-card title="Saved Transfers" subtitle="Previously saved transfer manifests.">
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Transfer</th><th>Waybill Numbers</th><th>Total</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse($transfers as $transfer)
                        <tr>
                            <td><a href="{{ route('transfer.show', $transfer) }}"><strong>#{{ $transfer->id }}</strong></a></td>
                            <td><details><summary>View waybill numbers</summary>@foreach($transfer->waybill_numbers as $number)<div>{{ $number }}</div>@endforeach</details></td>
                            <td>{{ count($transfer->waybill_numbers) }}</td>
                            <td><span class="date-label">{{ $transfer->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-table">No transfers saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())<div class="pagination-links">{{ $transfers->links() }}</div>@endif
    </x-card>
@endsection
