<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ContractDraftVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_draft_id',
        'draft_version',
        'schema_version',
        'canonical_payload',
        'payload_hash',
        'raw_legacy_payload',
        'source',
        'action',
        'created_by',
    ];

    protected $hidden = [
        'canonical_payload',
        'raw_legacy_payload',
    ];

    protected function casts(): array
    {
        return [
            'canonical_payload' => 'array',
            'raw_legacy_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Las versiones de borrador son inmutables. Cree una nueva versión.');
        });
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ContractDraft::class, 'contract_draft_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contrato(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Contrato::class, 'contract_draft_version_id');
    }

    public function documentVersions(): HasMany
    {
        return $this->hasMany(ContractDocumentVersion::class, 'contract_draft_version_id');
    }
}
