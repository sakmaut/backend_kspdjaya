<?php

namespace App\Http\Controllers\Repositories\Prospect;

use App\Models\M_CrProspect;

class ProspectRepository
{
    protected $prospectEntity;

    function __construct(M_CrProspect $prospectEntity)
    {
        $this->prospectEntity = $prospectEntity;
    }

    function getAllProspect()
    {
        return M_CrProspect::all();
    }

    function getDetailProspect($request)
    {
        $checkSurveyExist = M_CrProspect::where('id', $request)->first();

        return $checkSurveyExist;
    }
}
