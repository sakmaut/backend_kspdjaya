<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class M_ActivityLoggerLogin extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'log_activity_login';
    protected $fillable = [
        'id',
        'user',
        'event',
        'status',
        'url_api',
        'activity_description',
        'device_info',
        'ip_address',
        'browser',
        'time'
    ];
}
