<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M_ActivityLogger extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'log_activity';
    protected $fillable = [
        'id',
        'user_id',
        'method',
        'status',
        'url_api',
        'activity_description',
        'device_info',
        'ip_address',
        'user_agent'
    ];
}
