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
            'type' => ['required', Rule::in([
                AccountType::Checking->value, AccountType::Savings->value,
                AccountType::Wallet->value, AccountType::Investment->value,
            ])],
            'transient' => ['boolean'],
            'initial_balance' => ['required', 'numeric'],
        ];
    }
}
