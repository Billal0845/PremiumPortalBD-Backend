<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')->unique();

            $table->string('customer_name');
            $table->string('whatsapp');
            $table->string('email')->nullable();

            $table->decimal('total_amount', 10, 2)->default(0);

            $table->enum('order_status', ['pending', 'verified', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'verified'])->default('pending');

            $table->string('screenshot_path');
            $table->text('notes')->nullable();

            $table->boolean('subscription_created')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
