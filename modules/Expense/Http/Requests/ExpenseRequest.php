<?php

namespace Modules\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'description' => 'nullable|string|max:1000',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'receipt_number' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Please select a category.',
            'expense_category_id.exists' => 'Selected category does not exist.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be greater than 0.',
            'expense_date.required' => 'Expense date is required.',
            'expense_date.before_or_equal' => 'Expense date cannot be in the future.',
        ];
    }
}