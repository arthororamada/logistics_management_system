<div class="warehouse-form-grid">
    <x-card title="Warehouse Information" subtitle="Code and description used to identify this warehouse.">
        <div class="form-stack">
            <div class="form-group">
                <label for="warehouse_code">Warehouse code <span class="required">*</span></label>
                <div class="input-control @error('warehouse_code') has-error @enderror"><x-icon name="warehouse" :size="18" /><input id="warehouse_code" name="warehouse_code" value="{{ old('warehouse_code', $warehouse->warehouse_code) }}" maxlength="30" required placeholder="e.g. WH-MNL-01" style="text-transform: uppercase"></div>
                <p class="field-hint">Letters, numbers, underscores, and hyphens only.</p>
                @error('warehouse_code')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="form-group">
                <label for="warehouse_description">Warehouse description <span class="required">*</span></label>
                <div class="input-control @error('warehouse_description') has-error @enderror"><x-icon name="warehouse" :size="18" /><input id="warehouse_description" name="warehouse_description" value="{{ old('warehouse_description', $warehouse->warehouse_description) }}" maxlength="150" required placeholder="e.g. Manila Central Warehouse"></div>
                @error('warehouse_description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $warehouse->exists ? $warehouse->is_active : true))><span class="status-switch"></span><span><strong>Active warehouse</strong><small>Inactive warehouses remain on record but are unavailable for operations.</small></span></label>
        </div>
    </x-card>

    <x-card title="Location Details" subtitle="Complete postal address and geographic coordinates.">
        <div class="form-stack">
            <div class="form-group">
                <label for="complete_address">Complete address <span class="required">*</span></label>
                <textarea id="complete_address" name="complete_address" rows="3" maxlength="1000" required class="textarea-control @error('complete_address') has-error @enderror" placeholder="Street, building, unit, and district">{{ old('complete_address', $warehouse->complete_address) }}</textarea>
                @error('complete_address')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="warehouse-location-grid">
                <div class="form-group"><label for="city">City <span class="required">*</span></label><div class="input-control @error('city') has-error @enderror"><input id="city" name="city" value="{{ old('city', $warehouse->city) }}" maxlength="100" required placeholder="City"></div>@error('city')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div class="form-group"><label for="province">Province <span class="required">*</span></label><div class="input-control @error('province') has-error @enderror"><input id="province" name="province" value="{{ old('province', $warehouse->province) }}" maxlength="100" required placeholder="Province or state"></div>@error('province')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div class="form-group"><label for="country">Country <span class="required">*</span></label><div class="input-control @error('country') has-error @enderror"><input id="country" name="country" value="{{ old('country', $warehouse->country) }}" maxlength="100" required placeholder="Country"></div>@error('country')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="warehouse-coordinate-grid">
                <div class="form-group"><label for="latitude">Latitude <span class="required">*</span></label><div class="input-control @error('latitude') has-error @enderror"><input id="latitude" name="latitude" type="number" value="{{ old('latitude', $warehouse->latitude) }}" min="-90" max="90" step="0.0000001" required placeholder="14.5995124"></div><p class="field-hint">Value from -90 to 90.</p>@error('latitude')<p class="form-error">{{ $message }}</p>@enderror</div>
                <div class="form-group"><label for="longitude">Longitude <span class="required">*</span></label><div class="input-control @error('longitude') has-error @enderror"><input id="longitude" name="longitude" type="number" value="{{ old('longitude', $warehouse->longitude) }}" min="-180" max="180" step="0.0000001" required placeholder="120.9842195"></div><p class="field-hint">Value from -180 to 180.</p>@error('longitude')<p class="form-error">{{ $message }}</p>@enderror</div>
            </div>
        </div>
    </x-card>
</div>
<div class="form-actions"><a href="{{ route('warehouses.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
