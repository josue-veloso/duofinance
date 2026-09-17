<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:200'],
            'totalAmount' => ['required', 'numeric', 'min:0.01'],
            'installmentsCount' => ['sometimes', 'integer', 'min:1', 'max:96'],
            'isShared' => ['sometimes', 'boolean'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'dayOfMonth' => ['required', 'integer', 'min:1', 'max:28'],
            'startMonth' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'endMonth' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
