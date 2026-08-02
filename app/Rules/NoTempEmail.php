<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class NoTempEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = Str::lower(Str::after((string) $value, '@'));
        $blocklist = array_flip(config('disposable-email-domains', []));

        if (isset($blocklist[$domain])) {
            $fail('Email sementara (temporary email) tidak diizinkan. Gunakan alamat email permanen.');
        }
    }
}