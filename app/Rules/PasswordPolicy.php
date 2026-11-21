<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy implements ValidationRule
{
    /**
     * Get the underlying standard Laravel Password rule instance.
     */
    public static function rule(): Password
    {
        return Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = Validator::make(
            [$attribute => $value],
            [$attribute => static::rule()]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->get($attribute) as $message) {
                $fail($message);
            }
        }
    }
}
