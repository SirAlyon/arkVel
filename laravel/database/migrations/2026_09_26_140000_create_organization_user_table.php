<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Cast applicativo verso App\Enums\MembershipRole (string in DB).
            $table->string('role');
            // Lo staff invitato deve essere verificato dall'Org Owner prima di operare.
            $table->boolean('verified')->default(false);
            $table->timestamp('invited_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index('verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
