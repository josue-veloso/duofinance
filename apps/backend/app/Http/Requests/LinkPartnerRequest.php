<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'partnerEmail' => ['required', 'email'],
            'user1Quota' => ['required', 'numeric', 'min:0.01', 'max:0.99'],
        ];
    }
}
