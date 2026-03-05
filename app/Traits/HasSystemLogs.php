<?php
// app/Traits/HasSystemLogs.php

namespace App\Traits;

use App\Services\SystemLogService;
use Illuminate\Support\Facades\Request;

trait HasSystemLogs
{
    /**
     * Boot the trait
     */
    protected static function bootHasSystemLogs()
    {
        // Log creation
        static::created(function ($model) {
            if (property_exists($model, 'logEvents') && in_array('created', $model->logEvents)) {
                $entityType = class_basename($model);
                
                if ($entityType === 'Task') {
                    SystemLogService::logTaskCreation($model, Request::instance());
                } elseif ($entityType === 'Yacht') {
                    SystemLogService::logYachtCreation($model, Request::instance());
                } elseif ($entityType === 'Bid') {
                    SystemLogService::logBidCreation($model, Request::instance());
                }
            }
        });
        
        // Log updates
        static::updated(function ($model) {
            if (property_exists($model, 'logEvents') && in_array('updated', $model->logEvents)) {
                $oldData = $model->getOriginal();
                $newData = $model->getAttributes();
                $entityType = class_basename($model);
                
                if ($entityType === 'Task') {
                    SystemLogService::logTaskUpdate($model, $oldData, $newData, Request::instance());
                } elseif ($entityType === 'Yacht') {
                    SystemLogService::logYachtUpdate($model, $oldData, $newData, Request::instance());
                } elseif ($entityType === 'Bid') {
                    SystemLogService::logBidUpdate($model, $oldData, $newData, Request::instance());
                }
            }
        });
        
        // Log deletion
        static::deleted(function ($model) {
            if (property_exists($model, 'logEvents') && in_array('deleted', $model->logEvents)) {
                $entityType = class_basename($model);
                $modelData = $model->getOriginal();
                
                if ($entityType === 'Task') {
                    SystemLogService::logTaskDeletion($modelData, Request::instance());
                }
            }
        });
    }
    
    /**
     * Get all system logs for this model
     */
    public function systemLogs()
    {
        return $this->morphMany(\App\Models\SystemLog::class, 'entity');
    }
}