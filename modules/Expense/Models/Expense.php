<?php

namespace Modules\Expense\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;
use Modules\Support\Concerns\HasUuid;

class Expense extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function getCategoryNameAttribute(): string
    {
        return $this->category?->name ?? 'Uncategorized';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForDateRange($query, $start_date, $end_date)
    {
        return $query->whereBetween('expense_date', [$start_date, $end_date]);
    }

    public function scopeByCategory($query, $category_id)
    {
        return $query->where('expense_category_id', $category_id);
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        $search_term = '%' . strtolower($search) . '%';

        return $query->where(function ($q) use ($search_term) {
            $q->whereRaw('LOWER(description) LIKE ?', [$search_term])
                ->orWhereRaw('LOWER(receipt_number) LIKE ?', [$search_term])
                ->orWhereHas('category', function ($categoryQuery) use ($search_term) {
                    $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$search_term]);
                });
        });
    }
}