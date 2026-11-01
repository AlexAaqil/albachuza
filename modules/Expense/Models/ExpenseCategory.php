<?php

namespace Modules\Expense\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Support\Concerns\HasSlug;
use Modules\Support\Concerns\HasUuid;

class ExpenseCategory extends Model
{
    use HasSlug, HasUuid;

    protected $guarded = [];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        $search_term = '%' . strtolower($search) . '%';

        return $query->where(function ($q) use ($search_term) {
            $q->whereRaw('LOWER(name) LIKE ?', [$search_term])
                ->orWhereRaw('LOWER(description) LIKE ?', [$search_term]);
        });
    }
}