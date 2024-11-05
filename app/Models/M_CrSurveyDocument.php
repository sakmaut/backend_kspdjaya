<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M_CrSurveyDocument extends Model
{
    use HasFactory;
    protected $table = 'cr_survey_document';
    protected $fillable = [
       'ID',
       'CR_SURVEY_ID',
       'TYPE',
       'COUNTER_ID',
       'PATH',
       'SIZE',
       'CREATED_BY',
       'CREATED_AT',
       'TIMEMILISECOND'
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

    public static function attachment($survey_id, $array = []) {
        $attachment = self::select('TYPE', DB::raw('MAX(CREATED_AT) as latest_created_at'))
            ->where('CR_SURVEY_ID', $survey_id)
            ->whereIn('TYPE', $array)
            ->groupBy('TYPE')
            ->orderBy('latest_created_at', 'desc')
            ->get();
    
        return $attachment;
    }
    

    public static function attachmentGetAll($survey_id,$array = []){
            $attachment = self::where('CR_SURVEY_ID', $survey_id)
                        ->whereIn('TYPE', $array)
                        ->orderBy('TIMEMILISECOND', 'desc')
                        ->get();

        return $attachment;
    }
}
