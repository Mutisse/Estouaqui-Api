<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            // ✅ SEM FOREIGN KEY - apenas string para referência
            $table->string('notification_id', 36)->nullable();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 100);
            $table->string('channel', 50)->comment('email, push, sms, whatsapp');
            $table->string('status', 20)->default('pending')->comment('pending, sent, failed');
            $table->text('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('type');
            $table->index('notification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
