<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtractStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $userId)->where('type', 'credit_card'),
            ],
            'file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ];
    }
}
