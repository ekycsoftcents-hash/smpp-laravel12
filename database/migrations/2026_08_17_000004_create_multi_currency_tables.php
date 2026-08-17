<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('minor_unit')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 24, 12);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source')->default('admin');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'effective_at']);
            $table->index(['base_currency', 'quote_currency', 'enabled']);
        });

        Schema::table('rates', function (Blueprint $table) {
            $table->string('buy_currency', 3)->default('BDT')->after('buy_rate');
            $table->string('sell_currency', 3)->default('BDT')->after('sell_rate');
        });
    }

    public function down(): void
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn(['buy_currency', 'sell_currency']);
        });
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
