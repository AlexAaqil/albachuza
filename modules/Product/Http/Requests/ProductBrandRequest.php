<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductBrandRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_brands', 'name')->ignore($this->route('product_brand')?->id),
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Brand name must be filled',
            'name.unique' => 'A brand with this name already exists.',
        ];
    }
}
