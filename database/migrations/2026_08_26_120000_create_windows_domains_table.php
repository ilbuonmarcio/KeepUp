<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('windows_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain_name')->unique();
            $table->string('ldap_host');
            $table->unsignedSmallInteger('ldap_port')->default(636);
            $table->string('ldap_security')->default('ldaps');
            $table->string('base_dn');
            $table->string('discovery_username');
            $table->text('discovery_password');
            $table->string('monitor_username');
            $table->string('auth_method');
            $table->text('monitor_password')->nullable();
            $table->string('ssh_private_key')->nullable();
            $table->float('threshold_uptime')->default(365);
            $table->unsignedInteger('threshold_updates_available')->default(1);
            $table->timestamp('last_discovered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->foreignId('windows_domain_id')
                ->nullable()
                ->after('id')
                ->constrained('windows_domains')
                ->cascadeOnDelete();
            $table->unique(['windows_domain_id', 'hostname_ip']);
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['windows_domain_id', 'hostname_ip']);
            $table->dropConstrainedForeignId('windows_domain_id');
        });

        Schema::dropIfExists('windows_domains');
    }
};
