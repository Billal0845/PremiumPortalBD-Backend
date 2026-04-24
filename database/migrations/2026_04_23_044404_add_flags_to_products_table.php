<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_top_selling')->default(false)->after('status');
            $table->boolean('is_trending')->default(false)->after('is_top_selling');
            $table->boolean('is_new_arrival')->default(false)->after('is_trending');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_top_selling',
                'is_trending',
                'is_new_arrival'
            ]);
        });
    }
};