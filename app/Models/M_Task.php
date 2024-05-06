<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Task extends Model
{
    use HasFactory;
    protected $table = 'task';

    protected $fillable = [
        'ID',
        'NAME',
        'FLAG',
        'ROUTE',
        'PAR1',
        'STATUS'
    ];
    
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if ($model->getKey() == null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }

    public static function joinTaskPusher($userId){

        $query = self::select('*')
                            ->leftJoin('task_pusher', 'task_pusher.TASK_ID', '=', 'task.ID')
                            ->where('task_pusher.USER_ID', $userId)
                            ->get();

        return $query;
    }
}
