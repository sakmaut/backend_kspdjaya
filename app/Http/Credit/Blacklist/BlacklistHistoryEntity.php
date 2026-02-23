<?php

namespace App\Http\Credit\Blacklist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlacklistHistoryEntity extends Model
{
    use HasFactory;

    protected $table = 'cr_blacklist_history';
    protected $fillable = [
       'id',
       'cr_blacklist_id',
       'status',
       'reason',
       'created_by',
       'created_at'
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';
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
}
