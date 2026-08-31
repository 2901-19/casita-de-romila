<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comandas', function (Blueprint $table) {
            $table->id();
            $table->string('comanda_number', 20);
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->string('status', 20)->default('montada');
            $table->string('order_type', 20)->default('local');
            $table->string('customer_name', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->decimal('total', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['comanda_number', 'created_at']);
        });

        Schema::create('comanda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->nullOnDelete();
            $table->foreignId('combo_id')->nullable()->nullOnDelete()->constrained();
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_items');
        Schema::dropIfExists('comandas');
    }
};
