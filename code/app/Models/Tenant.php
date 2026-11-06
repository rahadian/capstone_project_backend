<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'owner_id',
        'name',
        'phone',
        'id_card_url',
        'ktp_data_json',
        'ktp_status',
    ];

    protected $casts = [
        'ktp_data_json' => 'array',
    ];

    /* `id_card_url` keeps the path on the `public` disk; the absolute URL is built on read. */
    public function getIdCardUrlAttribute(?string $value): ?string
    {
        return $value ? Storage::disk('public')->url($value) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'tenant_id');
    }

}
