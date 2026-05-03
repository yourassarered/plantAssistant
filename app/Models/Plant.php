<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, HasMany
};

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'planted_at',
        'height',
        'is_public',
        'user_id',
        'room_id',
    ];

    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
            'is_public' => 'boolean',
        ];
    }

    // --- Связи ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function careSettings(): HasMany
    {
        return $this->hasMany(CareSetting::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CareLog::class);
    }

    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }
}