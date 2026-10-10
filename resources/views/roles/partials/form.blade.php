@php($checkedPermissions = (array) old('permissions', $selectedPermissions->all()))
<div class="role-form-grid">
    <x-card title="Role Details" subtitle="Use a clear name that reflects this role’s responsibility." collapsible>
        <div class="form-stack">
            <div class="form-group"><label for="name">Role name <span class="required">*</span></label><div class="input-control @error('name') has-error @enderror"><x-icon name="shield" :size="18" /><input id="name" name="name" value="{{ old('name', $role->name) }}" maxlength="100" required placeholder="e.g. Registrar"></div>@error('name')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" class="textarea-control @error('description') has-error @enderror" rows="4" maxlength="255" placeholder="Describe what this role is responsible for.">{{ old('description', $role->description) }}</textarea>@error('description')<p class="form-error">{{ $message }}</p>@enderror</div>
            @if($role->is_system)<div class="system-note"><x-icon name="shield" :size="18" /><span>This is a protected system role. Its identifier and deletion status cannot be changed.</span></div>@endif
        </div>
    </x-card>

    <x-card title="Permissions" subtitle="Select the capabilities users with this role should have." collapsible>
        <div class="permission-groups">
            @foreach($permissionGroups as $group => $permissions)
                <section class="permission-group" data-permission-group>
                    <header><div><h3>{{ $group }}</h3><span>{{ $permissions->count() }} permissions</span></div><label><input type="checkbox" data-select-group> Select all</label></header>
                    <div class="permission-options">
                        @foreach($permissions as $permission)
                            <label class="permission-option"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $checkedPermissions))><span><strong>{{ $permission->name }}</strong><small>{{ $permission->description }}</small><code>{{ $permission->slug }}</code></span></label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </x-card>
</div>
<div class="form-actions"><a href="{{ route('roles.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
