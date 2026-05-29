<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'planted_at',
        'height',
        'is_public',
        'public_hidden_at',
        'public_hidden_by',
        'public_hidden_reason',
        'is_public_locked',
        'hidden_due_to_block',
        'was_public_before_block',
        'user_id',
        'room_id',
    ];

    protected $casts = [
        'planted_at' => 'datetime',
        'is_public' => 'boolean',
        'public_hidden_at' => 'datetime',
        'is_public_locked' => 'boolean',
        'hidden_due_to_block' => 'boolean',
        'was_public_before_block' => 'boolean',
        'height' => 'float',
    ];

    /**
     * Пользователь, которому принадлежит растение
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Комната, в которой находится растение
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Настройки ухода за растением
     */
    public function careSettings()
    {
        return $this->hasMany(CareSetting::class);
    }

    /**
     * История ухода за растением
     */
    public function careLogs()
    {
        return $this->hasMany(CareLog::class);
    }

    /**
     * Советы для растения
     */
    public function tips()
    {
        return $this->hasMany(Tip::class);
    }

    /**
     * Лайки растения
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function images()
    {
        return $this->hasMany(PlantImage::class)->latest();
    }

    public function latestImage()
    {
        return $this->hasOne(PlantImage::class)->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'target_id')
            ->where('target_type', Report::TARGET_PLANT);
    }
}
