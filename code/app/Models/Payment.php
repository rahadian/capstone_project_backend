<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'month',
        'amount',
        'status',
        'proof_url',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /* `proof_url` keeps the path on the `public` disk; the absolute URL is built on read. */
    public function getProofUrlAttribute(?string $value): ?string
    {
        return $value ? Storage::disk('public')->url($value) : null;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
