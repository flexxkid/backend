<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Allowances', function (Blueprint $table) {
            $table->decimal('DefaultAmount', 12, 2)->default(0)->after('AllowanceName');
        });
    }

    public function down(): void
    {
        Schema::table('Allowances', function (Blueprint $table) {
            $table->dropColumn('DefaultAmount');
        });
    }
};
