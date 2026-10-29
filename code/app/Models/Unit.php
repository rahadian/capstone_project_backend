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

    protected $appends = ['booked_dates', 'blocked_dates'];

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

    public function blocks(): HasMany
    {
        return $this->hasMany(UnitBlock::class, 'unit_id');
    }

    /**
     * Rentang yang diterima bersifat setengah terbuka [start, end), seperti contracts.end_date.
     * Blok unit disimpan inklusif, jadi pemanggil yang menerima input inklusif menggeser end-nya.
     */
    public function hasOverlap(string $start, string $end, ?int $ignoreContractId = null): bool
    {
        $contractOverlap = $this->contracts()
            ->where('status', 'active')
            ->when($ignoreContractId, fn ($query) => $query->whereKeyNot($ignoreContractId))
            // whereDate: SQLite menyimpan kolom date sebagai 'Y-m-d H:i:s', perbandingan string biasa meleset di batas hari.
            ->whereDate('start_date', '<', $end)
            ->whereDate('end_date', '>', $start)
            ->exists();

        return $contractOverlap || $this->blocks()
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();
    }

    public function getBookedDatesAttribute(): array
    {
        $dates = $this->datesBetween(
            $this->contracts()->where('status', 'active')->get(['start_date', 'end_date']),
            includeEnd: false
        );

        return $this->sortDates(array_merge(
            $dates,
            $this->getBlockedDatesAttribute()
        ));
    }

    public function getBlockedDatesAttribute(): array
    {
        return $this->sortDates($this->datesBetween($this->blocks()->get(['start_date', 'end_date'])));
    }

    private function datesBetween(iterable $ranges, bool $includeEnd = true): array
    {
        $dates = [];

        foreach ($ranges as $range) {
            if ($range->start_date && $range->end_date) {
                $end = $includeEnd ? $range->end_date : $range->end_date->copy()->subDay();

                // Rentang setengah terbuka bisa kosong bila end_date == start_date.
                if ($end->lt($range->start_date)) {
                    continue;
                }

                $period = CarbonPeriod::create($range->start_date, $end);
                foreach ($period as $date) {
                    $dates[] = $date->format('Y-m-d');
                }
            }
        }

        return $dates;
    }

    private function sortDates(array $dates): array
    {
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
