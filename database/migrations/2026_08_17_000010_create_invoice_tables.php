<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('subtotal', 20, 6)->default(0);
            $table->decimal('tax', 20, 6)->default(0);
            $table->decimal('total', 20, 6)->default(0);
            $table->decimal('paid', 20, 6)->default(0);
            $table->string('status')->default('DRAFT')->index();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 20, 6)->default(1);
            $table->decimal('unit_price', 20, 6)->default(0);
            $table->decimal('amount', 20, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 20, 6);
            $table->string('currency', 3)->default('BDT');
            $table->string('method')->default('MANUAL');
            $table->string('status')->default('POSTED');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
