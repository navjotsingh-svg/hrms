<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class FamilyRelationOptions
{
    public static function all(): array
    {
        return config('hrms.family_relations', []);
    }

    public static function validationRule(): \Illuminate\Validation\Rules\In
    {
        return Rule::in(self::all());
    }
}
