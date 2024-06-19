<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class M_CrSurveyDocument extends Model
{
    use HasFactory;
    protected $table = 'cr_survey_document';
    protected $fillable = [
       'ID',
       'CR_SURVEY_ID',
       'TYPE',
       'PATH',
       'CREATED_BY',
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

    public static function attachment($survey_id,$array = []){
        $attachment = self::where('CR_SURVEY_ID', $survey_id)
                        ->whereIn('TYPE', $array)
                        ->orderBy('CREATED_AT', 'desc')
                        ->get()
                        ->groupBy('TYPE')
                        ->map(function($items) {
                            return $items->first();
                        });

        return $attachment;
    }

    public static function attachmentGetAll($survey_id,$array = []){
        $attachment = self::where('CR_SURVEY_ID', $survey_id)
            ->whereIn('TYPE', $array)
            ->get();

        return $attachment;
    }
}
