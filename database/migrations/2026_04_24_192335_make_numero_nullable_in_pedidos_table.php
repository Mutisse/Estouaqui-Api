<?php
// database/migrations/xxxx_xx_xx_xxxxxx_make_numero_nullable_in_pedidos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('numero')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('numero')->nullable(false)->change();
        });
    }
};
