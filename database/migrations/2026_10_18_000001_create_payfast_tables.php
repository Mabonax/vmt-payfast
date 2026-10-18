<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payfast_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reference')->unique();
            $table->string('application_reference');
            $table->string('merchant_identity');
            $table->string('tenant_id')->nullable();
            $table->string('order_reference')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->string('mode');
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('payment_method')->nullable();
            $table->text('credential_snapshot');
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_identity', 'tenant_id', 'application_reference'], 'payfast_context_index');
        });
        Schema::create('payfast_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('transaction_id')->nullable()->constrained('payfast_transactions');
            $table->string('provider_status')->nullable();
            $table->string('outcome');
            $table->string('error_category')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
        });
        Schema::create('payfast_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('payfast_transactions');
            $table->string('status');
            $table->timestamp('dispatched_at')->nullable();
            $table->unique(['transaction_id', 'status']);
        });
        Schema::create('payfast_fulfilments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('transaction_id')->constrained('payfast_transactions');
            $table->string('consumer');
            $table->timestamp('fulfilled_at');
            $table->unique(['transaction_id', 'consumer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payfast_fulfilments');
        Schema::dropIfExists('payfast_outbox');
        Schema::dropIfExists('payfast_events');
        Schema::dropIfExists('payfast_transactions');
    }
};
