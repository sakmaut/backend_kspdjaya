<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CustomerPhone extends Model
{
    use HasFactory;
    protected $table = 'customer_phone';
    protected $fillable = [
        'ID',
        'CUSTOMER_ID',
        'ALIAS',
        'PHONE_NUMBER',
        'CREATED_BY',
        'CREATED_AT'
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

    public function user()
    {
        return $this->belongsTo(User::class, 'CREATED_BY', 'id');
    }
}
