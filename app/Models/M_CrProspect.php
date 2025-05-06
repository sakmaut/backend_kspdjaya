<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M_CrProspect extends Model
{
    use HasFactory;
    protected $table = 'cr_prospect';
    protected $fillable = [
        'id',
        'ao_id',
        'visit_date',
        'tujuan_kredit',
        'jenis_produk',
        'plafond',
        'tenor',
        'nama',
        'ktp',
        'kk',
        'tgl_lahir',
        'alamat',
        'rt',
        'rw',
        'province',
        'city',
        'kelurahan',
        'kecamatan',
        'zip_code',
        'hp',
        'usaha',
        'sector',
        'coordinate',
        'accurate',
        'slik',
        'created_at',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at'
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

    public function cr_application()
    {
        return $this->hasOne(M_CrApplication::class, 'CR_SURVEY_ID');
    }

    public function survey_approval()
    {
        return $this->hasOne(M_SurveyApproval::class, 'CR_SURVEY_ID');
    }
}
