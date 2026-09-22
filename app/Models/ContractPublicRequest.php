<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPublicRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_draft_id', 'public_reference', 'token_hash', 'expires_at', 'revoked_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ContractDraft::class, 'contract_draft_id');
    }

    public function accepts(string $token): bool
    {
        return $this->revoked_at === null
            && $this->submitted_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && hash_equals($this->token_hash, hash('sha256', $token));
    }
}
