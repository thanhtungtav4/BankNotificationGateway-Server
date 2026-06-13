<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password')->nullable();
            $table->string('role')->default('viewer'); // admin, viewer
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
