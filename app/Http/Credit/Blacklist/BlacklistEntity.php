<?php

namespace App\Http\Credit\Blacklist;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlacklistEntity extends Model
{
    use HasFactory;

    protected $table = 'cr_blacklist_case';
    protected $fillable = [
        'id',
        'category',
        'value',
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
