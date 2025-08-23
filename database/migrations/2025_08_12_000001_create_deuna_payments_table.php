<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deuna_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->unique()->index(); // DeUna transaction reference
            $table->string('order_id')->index(); // Our internal order ID
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', [
                'created',
                'pending',
                'completed',
                'failed',
                'cancelled',
                'refunded',
            ])->default('created')->index();

            // Customer information
            $table->json('customer'); // Store customer data as JSON

            // Payment items
            $table->json('items'); // Store items data as JSON

            // DeUna specific fields
            $table->string('transaction_id')->nullable(); // DeUna internal transaction ID
            $table->text('qr_code_base64')->nullable(); // Base64 QR code from DeUna
            $table->text('payment_url')->nullable(); // Payment link from DeUna
            $table->string('numeric_code', 10)->nullable(); // 6-digit numeric code from DeUna
            $table->string('point_of_sale')->nullable(); // Point of sale identifier
            $table->string('qr_type')->default('dynamic'); // static or dynamic
            $table->string('format', 1)->default('2'); // DeUna format type

            // Additional information
            $table->json('metadata')->nullable(); // Additional metadata
            $table->text('failure_reason')->nullable(); // Reason for failure
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('cancel_reason')->nullable();

            // Raw responses for debugging
            $table->json('raw_create_response')->nullable(); // Raw DeUna create response
            $table->json('raw_status_response')->nullable(); // Raw DeUna status response

            // Timestamps
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('transaction_id');

            // Foreign key constraints - Remove for now since order_id is a string reference
            // We'll store order_id as a reference but not enforce foreign key constraint
            // $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deuna_payments');
    }
};
