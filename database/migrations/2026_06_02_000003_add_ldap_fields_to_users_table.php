<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ldap_username')->nullable()->unique()->after('email');
            $table->string('ldap_description')->nullable()->after('ldap_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['ldap_username']);
            $table->dropColumn(['ldap_username', 'ldap_description']);
        });
    }
};
