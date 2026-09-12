<?php

use App\Models\ExchangeRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->decimal('rate', 12, 4)->nullable()->after('paid_at');
        });

        DB::table('sales')->whereNull('rate')->orderBy('id')->each(function ($sale) {
            $rate = ExchangeRate::where('created_at', '<=', $sale->created_at)
                ->orderByDesc('created_at')
                ->value('rate');

            if ($rate !== null) {
                DB::table('sales')->where('id', $sale->id)->update(['rate' => $rate]);
            }
        });

        DB::table('credit_movements')->where('type', 'pago')->whereNotNull('sale_id')->orderBy('sale_id')->each(function ($mov) {
            DB::table('sales')->where('id', $mov->sale_id)->whereNull('paid_at')->update(['paid_at' => $mov->created_at]);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'rate']);
        });
    }
};