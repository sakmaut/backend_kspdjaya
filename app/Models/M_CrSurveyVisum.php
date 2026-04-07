<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrSurveyVisum extends Model
{
    use HasFactory;
    protected $table = 'cr_survey_visum';
    protected $fillable = [
        'id',
        'nama_konsumen',
        'alamat_konsumen',
        'no_handphone',
        'status_konsumen',
        'hasil_followup',
        'sumber_order',
        'keterangan',
        'path',
        'created_by',
        'created_at'
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

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
