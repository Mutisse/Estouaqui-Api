<?php
// app/Models/DailyMetric.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    protected $table = 'daily_metrics';

    protected $fillable = [
        'date', 'total_requests', 'avg_response_time', 'error_rate',
        'total_users', 'new_users', 'total_services', 'total_revenue',
        'active_prestadores', 'avg_rating', 'additional_metrics'
    ];

    protected $casts = [
        'date' => 'date',
        'total_requests' => 'integer',
        'avg_response_time' => 'float',
        'error_rate' => 'float',
        'total_users' => 'integer',
        'new_users' => 'integer',
        'total_services' => 'integer',
        'total_revenue' => 'decimal:2',
        'active_prestadores' => 'integer',
        'avg_rating' => 'float',
        'additional_metrics' => 'array',
    ];
}
