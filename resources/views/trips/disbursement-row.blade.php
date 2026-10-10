@php($assignment = $trip->personnels->firstWhere('employee_id',$release['employee_id']??null))
<div class="trip-entry" data-disbursement-row>
    <strong data-disbursement-employee>{{ $assignment?->employee_name ?: $employees->get($release['employee_id']??null)?->first_name }}</strong>
    <input type="hidden" name="disbursements[{{ $index }}][employee_id]" value="{{ $release['employee_id']??'' }}">
    <div class="trip-form-grid">
        <div class="form-group"><label>Cash / GCash released<input class="form-input" type="number" step="0.01" min="{{ $assignment?->cash_released??0 }}" max="{{ $assignment?->salary??999999.99 }}" required name="disbursements[{{ $index }}][cash_released]" value="{{ $release['cash_released']??0 }}"></label></div>
        <div class="form-group"><label>Non-released amount<input class="form-input" data-disbursement-balance value="{{ number_format(max(0,($assignment?->salary??0)-($release['cash_released']??0)),2,'.','') }}" readonly></label></div>
    </div>
</div>
