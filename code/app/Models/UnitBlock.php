<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'start_date',
        'end_date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
