<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add username column after name
            $table->string('username')->nullable()->after('name');
        });

        // Migrate existing email data: derive username from the part before @
        DB::table('users')->get()->each(function ($user) {
            $username = explode('@', $user->email)[0];
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });

        // Now enforce unique + not null
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
