<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_SG_RT_M5RE extends Model
{
    use HasFactory;
    protected $table = 'sg_rt_m5re';
    protected $fillable = [
       'ID',
       'RESTAT',
       'RECODE',
       'RENAME',
       'RETELP',
       'RECUCO',
       'RECYCO',
       'REBRCO',
       'REOWCO',
       'REATCO',
       'REFSTA',
       'REDTOD',
       'REODRT',
       'REAECO',
       'REFIFL',
       'REBCBL',
       'REBLBL',
       'RECLBL',
       'RELDBL',
       'REHLBL',
       'REK1BL',
       'REK2BL',
       'REMIBL',
       'REITCO',
       'REDIST',
       'RELPGA',
       'REDYAS',
       'REDTLA',
       'REDTNA',
       'REDTLT',
       'REDTBG',
       'REDTEN',
       'REAACO',
       'REFAST',
       'REFSUB',
       'REFSAC',
       'REFBLD',
       'REFBLC',
       'REFRFD',
       'REFRFC',
       'REFPAD',
       'REFPAC',
       'REDTSC',
       'REDTLC',
       'REKIOS',
       'RDITCO',
       'RDISDR',
       'RDDYIS',
       'RDFPIN',
       'RDDTAI',
       'RDCALD',
       'RDPAMT',
       'RDDTBP',
       'RDDTEP',
       'RDOWCO',
       'RDDNUM',
       'RDFACO',
       'RDLOCO',
       'RDECCO',
       'RDCOCO',
       'RDDTLC',
       'RCITCO',
       'RCISCR',
       'RCDYIS',
       'RCFPIN',
       'RCDTAI',
       'RCCALD',
       'RCCNUM',
       'RCBSTX',
       'RCDTLC'
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
