<?php

namespace App\Services;

class UserContext
{
    public function __construct(
        public string $email,
        public ?string $timezone = null,
    ) {}
}
