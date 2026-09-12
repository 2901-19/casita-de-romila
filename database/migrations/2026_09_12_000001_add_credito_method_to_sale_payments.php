<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $checks = DB::select("
                SELECT conname FROM pg_constraint
                WHERE conrelid = 'sale_payments'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) ILIKE '%method%'
            ");

            foreach ($checks as $check) {
                DB::statement("ALTER TABLE sale_payments DROP CONSTRAINT \"{$check->conname}\"");
            }
        } elseif ($driver === 'sqlite') {
            Schema::table('sale_payments', function (Blueprint $table) {
                $table->string('method', 50)->change();
            });
        }

        DB::table('credit_movements')
            ->where('type', 'pago')
            ->whereNotNull('sale_id')
            ->orderBy('sale_id')
            ->each(function ($mov) {
                $exists = DB::table('sale_payments')
                    ->where('sale_id', $mov->sale_id)
                    ->where('method', 'credito')
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('sale_payments')->insert([
                    'sale_id' => $mov->sale_id,
                    'method' => 'credito',
                    'amount' => round(((float) $mov->amount) * ((float) $mov->rate), 2),
                    'created_at' => $mov->created_at,
                    'updated_at' => $mov->created_at,
                ]);
            });
    }

    public function down(): void
    {
    }
};