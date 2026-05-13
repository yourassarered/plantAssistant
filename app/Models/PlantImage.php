<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlantImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'path',
        'original_name',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
