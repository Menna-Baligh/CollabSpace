<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'message' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasMessage = filled($this->input('message'));
            $hasAttachment = $this->hasFile('attachment');

            if (! $hasMessage && ! $hasAttachment) {
                $v->errors()->add('message', 'At least one of message or attachment is required.');
            }
        });
    }
}
