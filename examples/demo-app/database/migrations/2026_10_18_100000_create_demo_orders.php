<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('amount_cents');
            $table->string('status');
            $table->unsignedInteger('completion_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_orders');
    }
};
