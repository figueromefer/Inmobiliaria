<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractDraft extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'source',
        'external_id',
        'status',
        'contrato_id',
        'cliente_id',
        'propiedad_id',
        'inquilino_id',
        'created_by',
        'published_by',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'pk_cliente');
    }

    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id', 'pk_propiedad');
    }

    public function inquilino(): BelongsTo
    {
        return $this->belongsTo(Inquilino::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ContractDraftVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractDraftVersion::class);
    }

    public function publicRequest(): HasOne
    {
        return $this->hasOne(ContractPublicRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
