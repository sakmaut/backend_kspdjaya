<?php

namespace App\Http\Controllers\Repositories\Survey;

use App\Http\Controllers\Controller;
use App\Models\M_CrSurvey;
use Illuminate\Http\Request;

class SurveyRepository implements SurveyInterface
{
    protected $surveyEntity;

    function __construct(M_CrSurvey $surveyEntity)
    {
        $this->surveyEntity = $surveyEntity;
    }

    function getListSurveyByMcf($request)
    {
        $getUserId = $request->user()->id;

        $query = $this->surveyEntity->with('survey_approval')
            ->where('created_by', $getUserId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return $query;
    }
}
