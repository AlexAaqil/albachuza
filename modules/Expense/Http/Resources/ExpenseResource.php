<?php

namespace Modules\Expense\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'description' => $this->description,
            'amount' => $this->amount,
            'formatted_amount' => 'KES ' . number_format($this->amount, 2),
            'expense_date' => $this->expense_date->format('Y-m-d'),
            'expense_date_formatted' => $this->expense_date?->format('d M Y'),
            'payment_method' => $this->payment_method,
            'receipt_number' => $this->receipt_number,
            'category_name' => $this->category_name,
            'expense_category_id' => $this->expense_category_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ];
            }),
        ];
    }
}