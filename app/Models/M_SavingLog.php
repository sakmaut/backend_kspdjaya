<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SavingLog extends Model
{
    use HasFactory;
    protected $table = 'saving_log';
    protected $fillable = [
        'ID',
        'SAVING_ID',
        'TRX_TYPE',
        'TRX_DATE',
        'BALANCE',
        'DESCRIPTION',
        'CREATED_BY',
        'BOOK',
        'ROW',
        'PAGE',
        'LAST_BALANCE'
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

    public function savings()
    {
        return $this->hasOne(M_Saving::class, 'ID', 'SAVING_ID');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'CREATED_BY');
    }
}
