<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_bs')->nullable()->after('price_override');
        });

        Schema::table('combos', function (Blueprint $table) {
            $table->unsignedSmallInteger('round_bs')->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('round_bs');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('round_bs');
        });
    }
};