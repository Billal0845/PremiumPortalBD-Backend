<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('screenshot_path'); // Remove old manual field
            $table->string('merchant_transaction_id')->nullable()->after('order_number'); // To send to EPS
            $table->string('eps_transaction_id')->nullable()->after('merchant_transaction_id'); // From EPS
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('screenshot_path')->nullable();
            $table->dropColumn(['merchant_transaction_id', 'eps_transaction_id']);
        });
    }
};