<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFileRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Max 20MB = 20480 KB
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,svg,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,tar,gz,7z',
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
