<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:200'],
            'totalAmount' => ['sometimes', 'numeric', 'min:0.01'],
            'installmentsCount' => ['sometimes', 'integer', 'min:1', 'max:96'],
            'isShared' => ['sometimes', 'boolean'],
            'splitMode' => ['sometimes', 'string', 'in:DEFAULT,EQUAL_50_50,CUSTOM,FULL_USER1,FULL_USER2'],
            'customUser1Quota' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'status' => ['sometimes', 'string', 'in:CONFIRMED,PENDING_APPROVAL,REJECTED'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'purchaseDate' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
