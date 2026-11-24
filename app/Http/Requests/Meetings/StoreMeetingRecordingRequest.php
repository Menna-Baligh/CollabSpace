<?php

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRecordingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recording' => ['required', 'file', 'mimes:mp4,m4a,webm,ogg,wav,mp3', 'max:102400'], // max 100MB
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
