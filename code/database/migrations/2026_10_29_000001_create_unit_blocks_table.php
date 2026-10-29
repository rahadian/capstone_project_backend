<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_blocks');
    }
};
