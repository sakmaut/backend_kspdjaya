<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_Saving extends Model
{
    use HasFactory;
    protected $table = 'saving';
    protected $fillable = [
        'ID',
        'CUST_CODE',
        'ACC_NUM',
        'BALANCE'
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

    public function customer()
    {
        return $this->hasOne(M_Customer::class, 'CUST_CODE', 'CUST_CODE');
    }

    public function saving_log()
    {
        return $this->hasMany(M_SavingLog::class, 'SAVING_ID');
    }
}
