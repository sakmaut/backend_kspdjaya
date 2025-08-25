<?php

namespace App\Http\Credit\TagihanDocument\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_TagihanDocument extends Model
{
    use HasFactory;

    protected $table = 'tagihan_dokument';
    protected $fillable = [
        'ID',
        'TAGIHAN_ID',
        'ORDER',
        'PATH'
    ];

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
}
