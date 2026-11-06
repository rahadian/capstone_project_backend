<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Penghentian sewa dicatat tanpa memotong `end_date`: tanggal kesepakatan tetap
   utuh, sedangkan `ended_at` menyimpan kapan sewa benar-benar berhenti. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->date('ended_at')->nullable()->after('end_date');
            $table->string('end_reason', 32)->nullable()->after('ended_at');
            $table->string('end_note', 500)->nullable()->after('end_reason');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'end_reason', 'end_note']);
        });
    }
};
