<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatementImportRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.date' => ['required', 'date'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.type' => ['required', Rule::in(['income', 'expense'])],
            'items.*.category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'items.*.installment_total' => ['nullable', 'integer', 'between:1,48'],
        ];
    }
}
