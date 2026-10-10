@php($checkedRoles = (array) old('roles', $selectedRoles->all()))
<div class="user-form-grid">
    <x-card title="Account Information" subtitle="Basic identity and username used to sign in.">
        <div class="form-stack">
            <div class="form-group"><label for="name">Full name <span class="required">*</span></label><div class="input-control @error('name') has-error @enderror"><x-icon name="user" :size="18" /><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="150" required placeholder="Enter the user’s full name"></div>@error('name')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label for="username">Username <span class="required">*</span></label><div class="input-control @error('username') has-error @enderror"><x-icon name="user" :size="18" /><input id="username" name="username" value="{{ old('username', $user->username) }}" minlength="3" maxlength="50" autocomplete="off" required placeholder="e.g. maria.santos"></div><p class="field-hint">Letters, numbers, periods, underscores, and hyphens only.</p>@error('username')<p class="form-error">{{ $message }}</p>@enderror</div>
            <label class="account-status-control"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $user->exists ? $user->is_active : true))><span class="status-switch"></span><span><strong>Active account</strong><small>Inactive users cannot sign in to the application.</small></span></label>
        </div>
    </x-card>

    <x-card title="Security" subtitle="{{ $user->exists ? 'Leave blank to keep the current password.' : 'Set the user’s initial password.' }}">
        <div class="form-stack">
            <div class="form-group"><label for="password">Password @unless($user->exists)<span class="required">*</span>@endunless</label><div class="input-control @error('password') has-error @enderror"><x-icon name="key" :size="18" /><input id="password" name="password" type="password" autocomplete="new-password" @required(!$user->exists) placeholder="Minimum 8 characters"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></div>@error('password')<p class="form-error">{{ $message }}</p>@enderror</div>
            <div class="form-group"><label for="password_confirmation">Confirm password @unless($user->exists)<span class="required">*</span>@endunless</label><div class="input-control"><x-icon name="key" :size="18" /><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$user->exists) placeholder="Repeat the password"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></div></div>
            <div class="password-guidance"><x-icon name="shield" :size="18" /><span>Passwords must contain at least eight characters, including letters and numbers.</span></div>
        </div>
    </x-card>

    <x-card title="Assigned Roles" subtitle="Select at least one role to define this user’s access.">
        <div class="role-selection-grid">
            @foreach($roles as $role)
                <label class="role-selection-option"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $checkedRoles))><span class="role-selection-check"><x-icon name="shield" :size="18" /></span><span><strong>{{ $role->name }}</strong><small>{{ $role->description ?: 'Custom application role.' }}</small><em>{{ $role->permissions()->count() }} permissions</em></span></label>
            @endforeach
        </div>
        @error('roles')<p class="form-error role-error">{{ $message }}</p>@enderror
    </x-card>
</div>
<div class="form-actions"><a href="{{ route('users.index') }}" class="button button-outline">Cancel</a><x-button type="submit" variant="primary">{{ $submitLabel }}</x-button></div>
