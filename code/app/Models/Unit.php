<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonPeriod;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'facilities',
        'image',
        'address',
        'price',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'facilities' => 'array',
    ];

    protected $appends = ['booked_dates'];

    /**
     * Get the owner of the unit.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'unit_id');
    }

    public function getBookedDatesAttribute(): array
    {
        $activeContracts = $this->contracts()
            ->where('status','active')
            ->get(['start_date','end_date']);

        $bookedDates=[];

        foreach ($activeContracts as $contract){
            if($contract->start_date && $contract->end_date){
                $period = CarbonPeriod::create($contract->start_date, $contract->end_date);
                foreach($period as $date){
                    $bookedDates[] = $date->format('Y-m-d');
                }
            }
        }

        return array_values(array_unique($bookedDates));
    }
}
