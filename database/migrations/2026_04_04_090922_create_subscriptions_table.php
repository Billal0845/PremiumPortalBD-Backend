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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_package_id')->constrained('product_packages')->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('whatsapp');
            $table->string('customer_email')->nullable();

            $table->date('start_date');
            $table->date('expiry_date');
            $table->decimal('subscription_fee', 10, 2);

            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');

            $table->boolean('credentials_given')->default(false);
            $table->text('admin_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
