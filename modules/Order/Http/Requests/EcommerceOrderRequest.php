<?php

namespace Modules\Order\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EcommerceOrderRequest extends FormRequest
{
    /**
     * Public storefront — no auth required. Guest checkout is allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for an ecommerce order submission.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Customer
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_phone' => ['required', 'phone:INTERNATIONAL'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],

            // Shipping
            'shipping_location' => ['required', 'string', 'max:200'],
            'shipping_area' => ['required', 'string', 'max:200'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],

            // Cart
            'cart_items' => ['required', 'array', 'min:1'],
            'cart_items.*.id' => ['required', 'integer', 'exists:products,id'],
            'cart_items.*.quantity' => ['required', 'integer', 'min:1'],

            // Payments (optional at creation)
            'payments' => ['sometimes', 'array'],
            'payments.*.method' => ['required_with:payments', 'string', 'max:50'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom messages for specific failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cart_items.required' => 'Your cart is empty.',
            'cart_items.min' => 'Your cart is empty.',
            'customer_email.email' => 'Please provide a valid email address.',
            'customer_phone.phone' => 'Please provide a valid phone number.',
        ];
    }

    /**
     * Normalize input before validation runs.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from text fields.
        $this->merge([
            'customer_name' => is_string($this->customer_name)  ? trim($this->customer_name)  : $this->customer_name,
            'customer_email' => is_string($this->customer_email) ? trim($this->customer_email) : $this->customer_email,
            'customer_phone' => is_string($this->customer_phone) ? trim($this->customer_phone) : $this->customer_phone,
        ]);
    }
}