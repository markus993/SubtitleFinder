<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'file_path',
        'file_name',
        'language',
        'language_detected',
        'subtitle_path',
        'status',
        'content_type',
        'season',
        'episode',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'language_detected' => 'boolean',
        'season' => 'integer',
        'episode' => 'integer',
    ];
}
