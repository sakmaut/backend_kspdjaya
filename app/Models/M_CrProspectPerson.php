<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrProspectPerson extends Model
{
    use HasFactory;
    protected $table = 'cr_prospect_person';
    protected $fillable = [
        'id',
        'cr_prospect_id',
        'nama',
        'ktp',
        'tgl_lahir',
        'pekerjaan',
        'status'
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
