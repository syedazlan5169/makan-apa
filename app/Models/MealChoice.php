<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealChoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_name',
        'menu_item_id',
        'chosen_at',
    ];

    protected function casts(): array
    {
        return [
            'chosen_at' => 'datetime',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}