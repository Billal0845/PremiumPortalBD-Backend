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
        Schema::table('product_packages', function (Blueprint $table) {
            // Drop the old string column
            $table->dropColumn('duration_label');

            // Add the new integer column
            $table->unsignedInteger('duration_months')->nullable()->after('package_name')->comment('Duration in months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_packages', function (Blueprint $table) {
            $table->dropColumn('duration_months');
            $table->string('duration_label')->nullable()->after('package_name');
        });
    }
};