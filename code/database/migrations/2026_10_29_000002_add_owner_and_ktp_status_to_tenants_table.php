<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->string('ktp_status', 20)->default('unverified')->after('ktp_data_json');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['owner_id']);
            $table->dropColumn(['owner_id', 'ktp_status']);
        });
    }
};
