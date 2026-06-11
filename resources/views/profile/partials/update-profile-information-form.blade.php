<section>
    <header class="mb-3">
        <h2 class="h6 fw-semibold mb-1">{{ __('Profile Information') }}</h2>
        <p class="text-muted small mb-0">
            {{ __("Update your account name and email address.") }}
        </p>
    </header>

    <form id="profileForm">
        <div class="mb-3">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="form-control" required autofocus autocomplete="name">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="form-control" required autocomplete="username">
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <span class="text-success small d-none" id="profileSaveStatus"></span>
        </div>
    </form>
</section>
