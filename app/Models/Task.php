<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    public const TYPE_MAINTENANCE_PAYMENT = 'maintenance_payment';
    public const TYPE_PROPERTY_TAX = 'property_tax';
    public const TYPE_CONTRACT_RENEWAL = 'contract_renewal';
    public const TYPE_RENT_COLLECTION = 'rent_collection';

    protected $fillable = [
        'title',
        'description',
        'due_date',
        'status',
        'priority',
        'task_type',
        'period_key',
        'source_type',
        'source_id',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function alreadyExists(
        string $taskType,
        string $periodKey,
        ?string $sourceType,
        $sourceId
    ): bool {
        return static::query()
            ->where('task_type', $taskType)
            ->where('period_key', $periodKey)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
