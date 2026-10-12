<x-card title="Waybill Information" data-trip-section="waybills" :collapsible="true" :collapsed="true" id="trip-section-waybills">
    <div class="form-group"><label for="trip-dm-id">DM ID</label>
        <select data-trip-searchable class="form-select" id="trip-dm-id" data-trip-dm>
            <option value="">{{ old('trip_type',$trip->trip_type) ? 'Select pending DM' : 'Select a trip type first' }}</option>
            @foreach($pendingManifests as $manifest)
                <option value="{{ $manifest->id }}">#{{ $manifest->id }} ({{ count($manifest->waybill_numbers) }} waybills)</option>
            @endforeach
        </select>
    </div>
    <button type="button" class="button button-outline" data-trip-add-dm>Add selected DM</button>
    <p data-trip-dm-message role="status">Select a pending DM to review its waybills. Complete all six fields for each row.</p>
    <div data-trip-collection="waybills">
        @foreach(old('waybills',$trip->waybills->toArray()) as $row)
            @include('trips.waybill-row',['row'=>$row,'index'=>$loop->index])
        @endforeach
    </div>
    <template id="trip-template-waybills">@include('trips.waybill-row',['row'=>[],'index'=>'__INDEX__'])</template>
</x-card>
