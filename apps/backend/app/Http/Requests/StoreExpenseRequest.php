<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'splitMode' => ['sometimes', 'string', 'in:DEFAULT,EQUAL_50_50,CUSTOM,FULL_USER1,FULL_USER2'],
            'customUser1Quota' => ['nullable', 'numeric', 'min:0', 'max:1', 'required_if:splitMode,CUSTOM'],
            'status' => ['sometimes', 'string', 'in:CONFIRMED,PENDING_APPROVAL'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'purchaseDate' => ['required', 'date_format:Y-m-d'],
            'paidByUserId' => ['nullable', 'exists:users,id'],
            'isRecurring' => ['sometimes', 'boolean'],
            'dayOfMonth' => ['nullable', 'integer', 'min:1', 'max:28', 'required_if:isRecurring,true'],
            'startMonth' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'endMonth' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'A descrição da despesa é obrigatória.',
            'totalAmount.required' => 'O valor total é obrigatório.',
            'totalAmount.min' => 'O valor total deve ser maior que zero.',
            'purchaseDate.required' => 'A data da compra é obrigatória.',
            'purchaseDate.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
        ];
    }
}
