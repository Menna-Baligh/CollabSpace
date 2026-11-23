<?php

namespace App\Http\Requests\Profile;

use App\Enums\UserAvailabilityStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'availability_status' => [
                'sometimes',
                Rule::enum(UserAvailabilityStatus::class),
            ],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
