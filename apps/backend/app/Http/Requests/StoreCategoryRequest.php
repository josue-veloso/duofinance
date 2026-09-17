<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['required', 'string', 'max:20'],
        ];
    }
}
