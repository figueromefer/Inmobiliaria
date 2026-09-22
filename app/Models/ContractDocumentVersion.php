<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocumentVersion extends Model
{
    use HasFactory;

    public const STATUS_NOT_REQUESTED = 'not_requested';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'drive_file_id',
        'drive_folder_id',
        'url',
        'status',
        'attempts',
        'last_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $documentVersion): void {
            $identityFields = [
                'contract_draft_version_id',
                'document_version',
                'template_key',
                'template_id',
                'snapshot_hash',
                'idempotency_key',
                'created_by',
            ];

            if ($documentVersion->isDirty($identityFields)) {
                throw new LogicException('La identidad de una versión documental es inmutable.');
            }
        });
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(ContractDraftVersion::class, 'contract_draft_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
