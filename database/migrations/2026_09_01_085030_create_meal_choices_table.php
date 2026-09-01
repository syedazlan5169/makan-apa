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
        Schema::create('meal_choices', function (Blueprint $table) {
            $table->id();
            $table->string('person_name', 255);
            $table->foreignId('menu_item_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamp('chosen_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_choices');
    }
};
