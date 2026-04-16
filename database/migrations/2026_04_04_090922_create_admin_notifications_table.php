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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('type'); // new_order, subscription_expiring, etc.
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('channel')->default('dashboard'); // dashboard / whatsapp
            $table->boolean('is_read')->default(false);

            $table->foreignId('related_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
