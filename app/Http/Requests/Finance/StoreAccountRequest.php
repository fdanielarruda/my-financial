<?php

namespace App\Http\Requests\Finance;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'person_id' => ['required', Rule::exists('people', 'id')->where('user_id', $userId)],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'initial_balance' => ['required', 'numeric'],
            'credit_limit' => ['required_if:type,credit_card', 'nullable', 'numeric', 'min:0'],
            'closing_day' => ['required_if:type,credit_card', 'nullable', 'integer', 'between:1,31'],
            'due_day' => ['required_if:type,credit_card', 'nullable', 'integer', 'between:1,31'],
            'payment_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('user_id', $userId)],
        ];
    }
}
