<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->auditCreated();
        });

        static::updated(function (Model $model) {
            $model->auditUpdated();
        });

        static::deleted(function (Model $model) {
            $model->auditDeleted();
        });
    }

    /**
     * Log creation.
     */
    protected function auditCreated(): void
    {
        AuditLog::logAction(
            'created',
            $this,
            auth()->user(),
            $this->getAttributes()
        );
    }

    /**
     * Log update.
     */
    protected function auditUpdated(): void
    {
        $changes = [
            'old' => $this->getOriginal(),
            'new' => $this->getChanges(),
        ];

        AuditLog::logAction(
            'updated',
            $this,
            auth()->user(),
            $changes
        );
    }

    /**
     * Log deletion.
     */
    protected function auditDeleted(): void
    {
        AuditLog::logAction(
            'deleted',
            $this,
            auth()->user(),
            $this->getAttributes()
        );
    }

    /**
     * Get audit logs for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }
}
