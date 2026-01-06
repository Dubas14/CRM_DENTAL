<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'procedure_id')) {
                $table->dropForeign(['procedure_id']);
                $table->dropColumn('procedure_id');
            }

            $table->decimal('amount', 10, 2)->default(0)->change();
            $table->decimal('paid_amount', 10, 2)->default(0)->change();
            $table->string('status')->default('unpaid')->change(); // unpaid, partially_paid, paid
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('paid_amount', 10, 2)->default(0)->change();
            $table->string('status')->default('pending')->change();
        });
    }
};


