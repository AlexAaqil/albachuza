<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

trait HasCreatorAuditTrail
{
    /**
     * Boot the trait.
     */
    public static function bootHasCreatorAuditTrail(): void
    {
        static::creating(function (Model $model): void {
            $model->setCreatorAndUpdater();
        });
        
        static::updating(function (Model $model): void {
            $model->setUpdater();
        });
    }
    
    /**
     * Set the creator and updater for the model.
     */
    protected function setCreatorAndUpdater(): void
    {
        $userId = $this->getCurrentUserId();
        
        if ($userId) {
            $this->created_by = $userId;
            $this->updated_by = $userId;
        }
    }
    
    /**
     * Set the updater for the model.
     */
    protected function setUpdater(): void
    {
        $userId = $this->getCurrentUserId();
        
        if ($userId) {
            $this->updated_by = $userId;
        }
    }
    
    /**
     * Get the current user ID from various sources.
     */
    protected function getCurrentUserId(): ?int
    {
        // Check if we're in a console context
        if (App::runningInConsole()) {
            // For CLI operations, you might want to set a default system user
            // or return null to skip setting
            return null;
        }
        
        // For web requests, use the authenticated user
        return Auth::check() ? Auth::id() : null;
    }
    
    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}