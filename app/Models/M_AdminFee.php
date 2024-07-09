<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_AdminFee extends Model
{
    use HasFactory;
    protected $table = 'admin_fee';
    protected $fillable = [
        'id',
        'branch',
        'category',
        'start_value',
        'end_value',
        'start_date',
        'end_date'
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

    public function links()
    {
        return $this->hasMany(M_AdminType::class,'admin_fee_id');
    }
}
