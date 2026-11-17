<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store temporary staff passwords encrypted (the Staff model now casts the
     * column as `encrypted`). Ciphertext is longer than 255 chars, so the column
     * becomes TEXT, and existing plain-text values are encrypted in place.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->text('initial_password')->nullable()->change();
        });

        DB::table('staff')->whereNotNull('initial_password')->orderBy('id')->each(function (object $row) {
            if ($this->isEncrypted($row->initial_password)) {
                return;
            }

            DB::table('staff')->where('id', $row->id)->update([
                'initial_password' => Crypt::encryptString($row->initial_password),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('staff')->whereNotNull('initial_password')->orderBy('id')->each(function (object $row) {
            if (! $this->isEncrypted($row->initial_password)) {
                return;
            }

            DB::table('staff')->where('id', $row->id)->update([
                'initial_password' => Crypt::decryptString($row->initial_password),
            ]);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('initial_password')->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
