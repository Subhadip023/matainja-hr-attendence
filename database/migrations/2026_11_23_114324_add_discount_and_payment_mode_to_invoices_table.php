<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Flat amount taken off the subtotal before tax (GST is charged on the discounted value).
            $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
            $table->string('payment_mode', 50)->nullable()->after('currency'); // NEFT, UPI, Cheque, ...
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount', 'payment_mode']);
        });
    }
};
