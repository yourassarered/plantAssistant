<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plant_id',
        'author_id',
        'content',
        'status',
        'status_changed_at',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'target_id')
            ->where('target_type', Report::TARGET_TIP);
    }
}
