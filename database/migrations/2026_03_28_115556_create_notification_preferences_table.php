<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel', 50)->comment('email, push, sms, whatsapp');
            $table->boolean('enabled')->default(true);
            $table->json('types')->nullable()->comment('tipos específicos de notificação');
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
