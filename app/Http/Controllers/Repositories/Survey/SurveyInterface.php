<?php

namespace App\Http\Controllers\Repositories\Survey;

interface SurveyInterface
{
    function getListSurveyByMcf($request);
    function getDetailSurvey($id);
}
