<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* `units.image`, `payments.proof_url`, and `tenants.id_card_url` used to store
   absolute URLs produced by `Storage::disk('public')->url()`, which burned the
   APP_URL host into each row as it was written. They now keep the path on the
   `public` disk, and the model accessor builds the absolute URL on read. This
   migration normalizes existing rows: drop the scheme and host, keep the path
   after `/uploads/`. */
return new class extends Migration
{
    private const COLUMNS = [
        'units' => 'image',
        'payments' => 'proof_url',
        'tenants' => 'id_card_url',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            DB::table($table)
                ->where($column, 'like', 'http%://%')
                ->orderBy('id')
                ->each(function (object $row) use ($table, $column) {
                    $value = $row->{$column};
                    $marker = '/uploads/';
                    $pos = strpos($value, $marker);

                    if ($pos !== false) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$column => substr($value, $pos + strlen($marker))]);
                    }
                });
        }
    }

    public function down(): void
    {
        // The original host cannot be restored without historical APP_URL data; leave the path as is.
    }
};
