<?php

namespace Modules\Expense\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'expense_count' => $this->whenCounted('expenses'),
            'total_amount' => $this->whenLoaded('expenses', function () {
                return $this->expenses->sum('amount');
            }),
        ];
    }
}