<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 50)->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 255)->nullable();
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('change_given', 12, 2)->default(0);
            $table->string('status', 20)->default('completada');
            $table->string('control_type', 20)->default('inventariable');
            $table->text('notes')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
