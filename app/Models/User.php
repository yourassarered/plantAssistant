<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'rank',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'rank' => 'integer',
    ];

    /**
     * Роль пользователя
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Растения пользователя
     */
    public function plants()
    {
        return $this->hasMany(Plant::class);
    }

    /**
     * Комнаты пользователя
     */
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Советы, оставленные пользователем
     */
    public function tips()
    {
        return $this->hasMany(Tip::class, 'author_id');
    }

    /**
     * Лайки пользователя
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Подписчики (кто подписан на этого пользователя)
     */
    public function followers()
    {
        return $this->hasMany(Follow::class, 'following_id');
    }

    /**
     * Подписки (на кого подписан этот пользователь)
     */
    public function following()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }
}