@php
    $legacyFuel = !empty($row['fuel_purchase_id']) && empty($row['cost_code']);
    $type = $legacyFuel ? 'FUEL_PURCHASE' : strtoupper(trim($costCodes->get($row['cost_code'] ?? null)?->cost_code ?? ''));
    $fuel = in_array($type, ['FUEL_PURCHASE', 'CASH_FUEL']);
    $roro = $type === 'RORO_COST';
    $shipping = $shippingRates->firstWhere('id', $row['shipping_rate_id'] ?? null);
    $line = $row['shipping_line_name'] ?? $shipping?->shipping_line_name ?? '';
    $routeGroups = $shippingRates->groupBy(fn ($rate) => json_encode([$rate->shipping_line_name, $rate->port_origin, $rate->port_destination]));
@endphp
@if(!empty($row['fuel_purchase_id']))<input type="hidden" name="costs[{{ $index }}][fuel_purchase_id]" value="{{ $row['fuel_purchase_id'] }}">@endif
<div class="form-group"><label>Cost Code<select data-trip-searchable data-cost-code class="form-select" name="costs[{{ $index }}][cost_code]" @required(!$legacyFuel)><option value="" @if($legacyFuel) data-code="FUEL_PURCHASE" @endif>{{ $legacyFuel ? 'FUEL_PURCHASE' : 'Select cost code' }}</option>
@foreach($availableCostCodes as $code)
<option value="{{ $code->id }}" data-code="{{ strtoupper(trim($code->cost_code)) }}" @selected(($row['cost_code']??'')==$code->id)>{{ $code->cost_code }} &middot; {{ $code->cost_name }}</option>
@endforeach</select></label></div>
<div class="form-group" data-cost-fields="supplier" @if($type!=='FUEL_PURCHASE') hidden @endif><label>Fuel Supplier<select data-trip-searchable class="form-select" name="costs[{{ $index }}][fuel_supplier]" @disabled($type!=='FUEL_PURCHASE') @required($type==='FUEL_PURCHASE')><option value="">Select fuel supplier</option>@foreach($suppliers->where('is_active',true) as $supplier)<option value="{{ $supplier->id }}" data-price="{{ $supplier->price_per_liter }}" @selected(($row['fuel_supplier']??'')==$supplier->id)>{{ $supplier->fuel_supplier_name }}</option>@endforeach</select></label></div>
@foreach(['liters_required'=>'Fuel Liters Required','price_per_liter'=>'Price Per Liter'] as $field=>$label)
<div class="form-group" data-cost-fields="fuel" @if(!$fuel) hidden @endif><label>{{ $label }}<input class="form-input" type="number" step="0.01" min="0" max="999999.99" name="costs[{{ $index }}][{{ $field }}]" value="{{ $row[$field]??'' }}" @disabled(!$fuel) @required($fuel)></label></div>
@endforeach
<div class="form-group" data-cost-fields="shipping" @if(!$roro) hidden @endif><label>Shipping Line<select data-trip-searchable class="form-select" name="costs[{{ $index }}][shipping_line_name]" @disabled(!$roro) @required($roro)><option value="">Select shipping line</option>@foreach($shippingLines as $shippingLine)<option value="{{ $shippingLine }}" @selected($line===$shippingLine)>{{ $shippingLine }}</option>@endforeach</select></label></div>
<div class="form-group" data-cost-fields="shipping" @if(!$roro) hidden @endif><label>Shipping Route<select data-trip-searchable class="form-select" name="costs[{{ $index }}][shipping_rate_id]" @disabled(!$roro || !$line) @required($roro)><option value="">Select shipping route</option>@foreach($shippingRates as $rate)<option value="{{ $rate->id }}" data-line="{{ $rate->shipping_line_name }}" data-amount="{{ $rate->shippine_rate }}" @disabled($rate->shipping_line_name!==$line) @if($rate->shipping_line_name!==$line) hidden @endif @selected(($row['shipping_rate_id']??'')==$rate->id)>{{ $rate->port_origin }} → {{ $rate->port_destination }}@if($routeGroups->get(json_encode([$rate->shipping_line_name,$rate->port_origin,$rate->port_destination]))->count()>1) &middot; {{ $rate->vehicle_type }} &middot; PHP {{ number_format($rate->shippine_rate,2) }} &middot; #{{ $rate->id }}@endif</option>@endforeach</select></label></div>
<div class="form-group"><label>Cost Amount<input class="form-input" type="number" step="0.01" min="0" max="999999.99" required name="costs[{{ $index }}][cost_amount]" value="{{ $row['cost_amount'] ?? number_format(($row['released_amount']??0)+($row['nonreleased_amount']??0),2,'.','') }}" @readonly($fuel || $roro)></label></div>
<div class="form-group"><label>Cost Remark<input class="form-input" name="costs[{{ $index }}][remarks]" value="{{ $row['remarks']??'' }}" maxlength="2000"></label></div>
