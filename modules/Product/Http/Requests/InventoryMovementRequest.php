<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Enums\InventoryMovementTypes;

class InventoryMovementRequest extends FormRequest
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
        return [
            'quantity' => 'required|integer|min:1',
            'type' => ['required', 'integer', 'in:' . implode(',', array_column(InventoryMovementTypes::cases(), 'value'))],
            'notes' => 'nullable|string|max:500'
        ];
    }
}
