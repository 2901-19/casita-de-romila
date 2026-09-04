<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_items', function (Blueprint $table) {
            $table->string('order_type', 20)->default('local')->after('product_name');
            $table->string('note', 255)->nullable()->after('order_type');
            $table->boolean('collected')->default(false)->after('subtotal');
        });

        DB::statement('UPDATE comanda_items SET order_type = (SELECT order_type FROM comandas WHERE comandas.id = comanda_items.comanda_id)');

        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'notes']);
        });

        Schema::create('comanda_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_payments');

        Schema::table('comandas', function (Blueprint $table) {
            $table->string('order_type', 20)->default('local');
            $table->string('notes', 255)->nullable();
        });

        DB::statement('UPDATE comandas SET order_type = (SELECT order_type FROM comanda_items WHERE comanda_items.comanda_id = comandas.id LIMIT 1)');

        Schema::table('comanda_items', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'note', 'collected']);
        });
    }
};
