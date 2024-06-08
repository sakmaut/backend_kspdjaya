<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrProspectDocument extends Model
{
    use HasFactory;
    protected $table = 'cr_prospect_document';
    protected $fillable = [
       'ID',
       'CR_PROSPECT_ID',
       'TYPE',
       'PATH',
       'INDEX_NUM',
       'VALID_CHECK',
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

    public static function attachment($prospect_id,$array = []){
        $attachment = M_CrProspectDocument::where('CR_PROSPECT_ID', $prospect_id)
            ->whereIn('TYPE', $array)
            ->get();

        return $attachment;
    }
}
