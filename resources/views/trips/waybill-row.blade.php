<div class="trip-entry" data-trip-row>
    <input type="hidden" name="waybills[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="trip-form-grid">
        @foreach(['dm_id'=>'DM ID','waybill_number'=>'Waybill Number','drops_number'=>'Number of Drops','quantity'=>'Quantity','kilogram'=>'Kilogram','cbm'=>'CBM'] as $field=>$label)
        <div class="form-group"><label>{{ $label }}
            <input class="form-input" name="waybills[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}" required
                @if(in_array($field,['dm_id','waybill_number'])) readonly @else type="number" min="0" step="{{ $field==='cbm' ? 'any' : '1' }}" max="{{ $field==='cbm' ? '999999999' : '2147483647' }}" @endif>
        </label></div>
        @endforeach
    </div>
    <button type="button" class="button button-outline" data-trip-remove>Remove entry</button>
</div>
