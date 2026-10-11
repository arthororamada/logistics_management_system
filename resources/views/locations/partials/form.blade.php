<x-card title="Location Information" subtitle="Code, name, and geographic coordinates for this location.">
    <div class="form-stack">
        <div class="form-group">
            <label for="location_code">Location code <span class="required">*</span></label>
            <div class="input-control @error('location_code') has-error @enderror"><x-icon name="location" :size="18" /><input id="location_code" name="location_code" value="{{ old('location_code', $location->location_code) }}" maxlength="30" required placeholder="e.g. MNL-HUB-01" style="text-transform: uppercase"></div>
            <p class="field-hint">Letters, numbers, underscores, and hyphens only.</p>
            @error('location_code')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label for="location_name">Location name <span class="required">*</span></label>
            <div class="input-control @error('location_name') has-error @enderror"><x-icon name="location" :size="18" /><input id="location_name" name="location_name" value="{{ old('location_name', $location->location_name) }}" maxlength="150" required placeholder="e.g. Manila Central Hub"></div>
            @error('location_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div class="warehouse-coordinate-grid">
            <div class="form-group"><label for="location_lat">Latitude <span class="required">*</span></label><div class="input-control @error('location_lat') has-error @enderror"><input id="location_lat" name="location_lat" type="number" value="{{ old('location_lat', $location->location_lat) }}" min="-90" max="90" step="0.00000001" required placeholder="14.59951240"></div><p class="field-hint">Value from -90 to 90.</p>@error('location_lat')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label for="location_long">Longitude <span class="required">*</span></label><div class="input-control @error('location_long') has-error @enderror"><input id="location_long" name="location_long" type="number" value="{{ old('location_long', $location->location_long) }}" min="-180" max="180" step="0.00000001" required placeholder="120.98421950"></div><p class="field-hint">Value from -180 to 180.</p>@error('location_long')<p class="form-error">{{ $message }}</p>@enderror</div>
        </div>
    </div>
</x-card>
<div class="form-actions"><a href="{{ route('locations.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
