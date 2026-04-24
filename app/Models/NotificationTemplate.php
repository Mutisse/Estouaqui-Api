<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $table = 'notification_templates';

    protected $fillable = [
        'type',
        'title_pt',
        'title_en',
        'body_pt',
        'body_en',
        'icon',
        'color',
        'channels',
        'active',
    ];

    protected $casts = [
        'channels' => 'array',
        'active' => 'boolean',
    ];

    public static function findByType(string $type): ?self
    {
        return self::where('type', $type)->where('active', true)->first();
    }
}
