<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payerId' => ['required', 'exists:users,id'],
            'receiverId' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:140'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
