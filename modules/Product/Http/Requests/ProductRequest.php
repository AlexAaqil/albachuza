<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $maxImages = config('product_images.max_per_product');
        $maxDimension = config('product_images.max_dimension');

        return [
            'name' => [
                'required', 
                'string', 
                'max:200',
            ],
            'sku' => [
                'nullable', 
                'string', 
                'max:100',
                Rule::unique('products', 'sku')->ignore($this->route('product')?->id)
            ],
            'size' => [
                'nullable', 
                'string', 
                'max:100'
            ],
            'barcode' => [
                'nullable', 
                'string', 
                'max:100',
            ],
            'description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'is_new' => ['boolean'],
            'track_inventory' => ['boolean'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0', Rule::requiredIf(fn()=>$this->boolean('track_inventory'))],
            'current_stock' => ['prohibited'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'product_brand_id' => ['nullable', 'exists:product_brands,id'],
            'images' => ['nullable', 'array', 'max:' . $maxImages],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:' . config('product_images.max_upload_kb'),
                'dimensions:max_width=' . $maxDimension . ',max_height=' . $maxDimension,
            ],
            // Final order of the images, sent by the edit form: an existing image id ("12")
            // or the position of a new upload ("new-0").
            'image_order' => ['nullable', 'array', 'max:' . $maxImages],
            'image_order.*' => ['string', 'regex:/^(\d+|new-\d+)$/'],
        ];
    }

    public function messages(): array
    {
        $maxImages = config('product_images.max_per_product');
        $maxMb = round(config('product_images.max_upload_kb') / 1024);
        $maxDimension = config('product_images.max_dimension');

        return [
            'name.required' => 'A product must have a name',
            'price.required' => 'The product price is required',
            'sku.unique' => 'This SKU is already in use. Please use a different one.',
            'barcode.unique' => 'This barcode is already in use.',
            'images.max' => "You can upload a maximum of {$maxImages} images.",
            'images.*.max' => "Each image must be less than {$maxMb}MB.",
            'images.*.mimes' => 'Only JPG, PNG, GIF and WEBP images are allowed.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.dimensions' => "Each image can be at most {$maxDimension}px wide or tall.",
        ];
    }
}
