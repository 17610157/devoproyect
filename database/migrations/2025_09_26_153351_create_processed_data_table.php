<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processed_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->integer('no')->nullable();
            $table->string('producto', 100)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('solicitado', 12,2)->nullable();
            $table->decimal('facturado', 12,2)->nullable();
            $table->decimal('faltante', 12,2)->nullable();
            $table->decimal('precio', 12,2)->nullable();
            $table->decimal('importe', 12,2)->nullable();
            $table->decimal('peso', 12,2)->nullable();
            $table->date('fecha_disponibilidad')->nullable();
            $table->date('cambio_fecha_disponibilidad')->nullable();
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_data');
    }
};
