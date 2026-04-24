<?php
// app/Models/ApiRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequest extends Model
{
    protected $table = 'api_requests';

    protected $fillable = [
        'method', 'path', 'status_code', 'response_time_ms',
        'ip', 'user_agent', 'user_id'
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
        'user_id' => 'integer',
    ];
}
