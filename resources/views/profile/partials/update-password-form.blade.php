<section>
    <header class="mb-3">
        <h2 class="h6 fw-semibold mb-1">{{ __('Update Password') }}</h2>
        <p class="text-muted small mb-0">
            {{ __('Use a strong password to keep your account secure.') }}
        </p>
    </header>

    <form id="passwordForm">
        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="form-control" autocomplete="current-password" required>
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" class="form-control" autocomplete="new-password" required>
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Update Password') }}</button>
            <span class="text-success small d-none" id="passwordSaveStatus"></span>
        </div>
    </form>
</section>
