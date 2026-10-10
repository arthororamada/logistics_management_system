<x-card title="Personnel Details"><div class="vehicle-form-grid">
@foreach(['employee_id','last_name','first_name','middle_name','address','contact_number','position','drivers_license','remarks'] as $field)
<div class="form-group"><label>{{ ucwords(str_replace('_',' ',$field)) }}</label><input class="form-input" name="{{ $field }}" value="{{ old($field,$personnel->{$field}) }}"></div>
@endforeach
<div class="form-group"><label>Personnel Type</label><select class="form-select" name="personnel_type" required><option value="">Select personnel type</option>@foreach($personnelTypes as $item)<option value="{{ $item->personnel_type }}" @selected(old('personnel_type',$personnel->personnel_type)===$item->personnel_type)>{{ $item->personnel_type }}</option>@endforeach</select></div>
<div class="form-group"><label>Warehouse</label><select class="form-select" name="warehouse_code" required><option value="">Select warehouse</option>@foreach($warehouses as $item)<option value="{{ $item->warehouse_code }}" @selected(old('warehouse_code',$personnel->warehouse_code)===$item->warehouse_code)>{{ $item->warehouse_code }}</option>@endforeach</select></div>
<label class="checkbox-field"><input type="hidden" name="is_mechanic" value="0"><input type="checkbox" name="is_mechanic" value="1" @checked(old('is_mechanic',$personnel->is_mechanic))> Is Mechanic</label>
<label class="checkbox-field"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$personnel->is_active ?? true))> Active</label>
</div><div class="form-actions"><a href="{{ route('personnel.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div></x-card>
