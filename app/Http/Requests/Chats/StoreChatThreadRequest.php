<?php

namespace App\Http\Requests\Chats;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_message_id' => ['required', 'integer', 'exists:chat_messages,id'],
            'title' => ['required', 'string', 'max:150'],
        ];
    }
}
