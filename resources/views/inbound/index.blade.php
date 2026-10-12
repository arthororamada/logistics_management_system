@extends('layouts.app')
@section('title', 'Inbound')
@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Document Manifests'], ['label' => 'Inbound']])
@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Document Manifests</p><h2>Inbound</h2><p>Accept packages from the courier to the warehouse.</p></div>
    </section>

    @if(auth()->user()->hasPermission('inbound.create'))
        <form method="POST" action="{{ route('inbound.waybills.add') }}">
            @csrf
            <x-card title="New Inbound" subtitle="Enter waybill numbers to validate their package status.">
                <div class="form-stack">
                    <div class="form-group">
                        <label for="waybill_numbers">Waybill numbers <span class="required">*</span></label>
                        <textarea id="waybill_numbers" name="waybill_numbers" class="textarea-control @error('waybill_numbers') has-error @enderror" rows="10" maxlength="100000" required aria-describedby="waybill-hint @error('waybill_numbers') waybill-error @enderror" @error('waybill_numbers') aria-invalid="true" @enderror placeholder="Enter waybill numbers here...">{{ old('waybill_numbers') }}</textarea>
                        <p class="field-hint" id="waybill-hint">Each package must have status for_pickup. Separate numbers with new lines, commas, semicolons, or spaces. Up to 1,000 numbers per batch. Duplicates are added once; leading zeros are preserved.</p>
                        @if($errors->has('waybill_numbers'))<div class="form-error" id="waybill-error" role="alert">@foreach($errors->get('waybill_numbers') as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                    </div>
                </div>
                <div class="form-actions"><x-button type="submit" variant="primary" icon="plus">Add Waybill Number/s</x-button></div>
            </x-card>
        </form>

        <x-card title="Added Waybill Numbers" :subtitle="count($draftNumbers).' waybill number(s) ready to be accepted to the warehouse.'">
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
                            <tr><td colspan="2" class="empty-table">Add valid waybill numbers to start accepting packages to the warehouse.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('inbound.store') }}" class="form-actions">
                @csrf
                <x-button type="submit" variant="primary" :disabled="count($draftNumbers) === 0">Accept to Warehouse</x-button>
            </form>
        </x-card>
    @endif

@endsection
