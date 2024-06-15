<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TransactionLog extends Model
{
    use HasFactory;
    protected $table = 'transaction_log';
    protected $fillable = [
        'id',
        'table_name',
        'table_id',
        'field_name',
        'old_value',
        'new_value',
        'altered_by',
        'altered_time'
    ];
    protected $guarded = [];
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
