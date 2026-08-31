<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->onDelete('set null');
            $table->enum('type', ['cargo', 'abono', 'pago']);
            $table->decimal('amount', 12, 2);
            $table->decimal('rate', 12, 4)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_movements');
    }
};
