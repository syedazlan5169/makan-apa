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
       Schema::create('meal_rejections', function (Blueprint $table) {
            $table->id();
            $table->string('person_name');
            $table->foreignId('menu_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamp('rejected_at');
            $table->timestamps();
            $table->index(['person_name', 'menu_item_id']);
            $table->index('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_rejections');
    }
};
