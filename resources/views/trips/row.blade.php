<div class="trip-entry" data-trip-row>
<input type="hidden" name="{{ $collection }}[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
<div class="trip-form-grid">
@php
    $selects = match($collection) {
        'personnels'=>['employee_id'=>[$employees->where('is_active',true),'id','Employee']],
        'segments'=>['origin_id'=>[$locations,'id','Origin reference'],'destination_id'=>[$locations,'id','Destination reference']],
        'fuel_purchases'=>['fuel_supplier'=>[$suppliers->where('is_active',true),'id','Fuel supplier']],
        'costs'=>['cost_code'=>[$costCodes->where('is_active',true),'id','Cost code'],'shipping_rate_id'=>[$shippingRates,'id','Shipping tariff (optional)']],
    };
@endphp
@foreach($selects as $field=>[$records,$value,$label])
<div class="form-group"><label>{{ $label }}<select data-trip-searchable class="form-select" name="{{ $collection }}[{{ $index }}][{{ $field }}]" @required($field!=='shipping_rate_id')><option value="">Select</option>
@foreach($records as $record)
@php($text = match($field) { 'employee_id'=>$record->employee_id.' · '.$record->first_name.' '.$record->last_name.' · '.$record->personnel_type, 'origin_id','destination_id'=>implode(', ',array_filter([$record->province,$record->city,$record->brgy])), 'fuel_supplier'=>$record->fuel_supplier_name, 'cost_code'=>$record->cost_code.' · '.$record->cost_name, 'shipping_rate_id'=>$record->shipping_line_name.' · '.$record->port_origin.' → '.$record->port_destination.' · '.$record->vehicle_type.' · '.$record->shippine_rate })
<option value="{{ $record->$value }}" @selected(($row[$field]??'')==$record->$value) @if($field==='fuel_supplier') data-price="{{ $record->price_per_liter }}" @elseif($field==='cost_code') data-cash="{{ $record->is_cash_release ? 1 : 0 }}" @endif>{{ $text }}</option>
@endforeach</select></label></div>
@endforeach
@if($collection==='personnels')<div class="form-group"><label>Designation<select data-trip-searchable class="form-select" name="personnels[{{ $index }}][designation]" required><option value="">Select designation</option>@foreach($designations as $designation)<option value="{{ $designation }}" @selected(strcasecmp($row['designation']??'',$designation)===0)>{{ $designation }}</option>@endforeach</select></label><small data-personnel-rate-error hidden>No rate is configured for this position in the selected Trip Rate.</small></div>@endif
@if($collection==='costs')<div class="form-group"><label>Cash release<select data-trip-searchable class="form-select" name="costs[{{ $index }}][is_cash_released]"><option value="0" @selected(!($row['is_cash_released']??false))>No</option><option value="1" @selected($row['is_cash_released']??false)>Yes</option></select></label><small>Determined by the selected Other Cost definition.</small></div>@endif
@php($numbers = match($collection) {'personnels'=>['salary'],'segments'=>['total_distance'],'fuel_purchases'=>['liters_required','price_per_liter'],'costs'=>['released_amount','nonreleased_amount']})
@foreach($numbers as $field)<div class="form-group"><label>{{ ucwords(str_replace('_',' ',$field)) }}{{ $field==='total_distance' ? ' (km)' : '' }}<input class="form-input" type="number" step="0.01" min="0" max="999999.99" required name="{{ $collection }}[{{ $index }}][{{ $field }}]" value="{{ $row[$field]??0 }}" @if($field==='salary') readonly data-personnel-salary @endif></label></div>@endforeach
@if($collection==='fuel_purchases')<div class="form-group"><label>Total fuel price<input class="form-input" data-fuel-total value="{{ number_format($row['fuel_price']??0,2,'.','') }}" readonly></label></div>@endif
@if($collection==='costs')@foreach(['costgroup','remarks','reference_id'] as $field)<div class="form-group"><label>{{ ucwords(str_replace('_',' ',$field)) }}<input class="form-input" name="costs[{{ $index }}][{{ $field }}]" value="{{ $row[$field]??'' }}" maxlength="{{ $field==='remarks'?2000:($field==='costgroup'?100:150) }}"></label></div>@endforeach @endif
</div><button type="button" class="button button-outline" data-trip-remove>Remove entry</button>
</div>
