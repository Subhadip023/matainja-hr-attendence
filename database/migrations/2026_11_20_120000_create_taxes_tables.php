<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax rates you manage (CGST 9%, SGST 9%, IGST 18%, ...) and the taxes
 * picked on each invoice. Invoices keep their own copy of the name and rate,
 * so editing a tax later never changes an invoice already issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);              // e.g. CGST
            $table->decimal('rate', 5, 2);            // percent, e.g. 9.00
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false); // ticked on new invoices
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'rate']);
        });

        Schema::create('invoice_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('name', 100);
            $table->decimal('rate', 5, 2);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        // Move the old per-line tax % onto the invoice (one tax per rate used),
        // keeping the amounts already calculated so totals don't change.
        $rows = DB::table('invoice_items')->where('tax_rate', '>', 0)
            ->get(['invoice_id', 'tax_rate', 'amount'])
            ->groupBy(fn ($r) => $r->invoice_id.'|'.(float) $r->tax_rate);
        foreach ($rows as $group) {
            $rate = (float) $group->first()->tax_rate;
            DB::table('invoice_taxes')->insert([
                'invoice_id' => $group->first()->invoice_id,
                'tax_id' => null,
                'name' => 'GST',
                'rate' => $rate,
                'amount' => round($group->sum(fn ($r) => round((float) $r->amount * $rate / 100, 2)), 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0);
        });
        Schema::dropIfExists('invoice_taxes');
        Schema::dropIfExists('taxes');
    }
};
