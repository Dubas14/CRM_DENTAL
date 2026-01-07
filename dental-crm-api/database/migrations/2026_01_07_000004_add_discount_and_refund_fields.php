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
        // Додати поля знижки в invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('amount');
            $table->string('discount_type', 20)->nullable()->after('discount_amount'); // 'percent' або 'fixed'
        });

        // Додати поля refund в payments
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_refund')->default(false)->after('method');
            $table->text('refund_reason')->nullable()->after('is_refund');
            $table->foreignId('original_payment_id')->nullable()->after('refund_reason')->constrained('payments')->nullOnDelete();
            $table->foreignId('refunded_by')->nullable()->after('original_payment_id')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'discount_type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['original_payment_id']);
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['is_refund', 'refund_reason', 'original_payment_id', 'refunded_by', 'refunded_at']);
        });
    }
};

