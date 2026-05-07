<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'planted_at' => 'datetime',
        'is_public' => 'boolean',
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
}