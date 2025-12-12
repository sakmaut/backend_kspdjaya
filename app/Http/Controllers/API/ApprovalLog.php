<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\M_SurveyApprovalLog;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class ApprovalLog extends Controller
{
    private $uuid;
    private $timeNow;

    public function __construct()
    {
        $this->uuid = Uuid::uuid7()->toString();
        $this->timeNow = Carbon::now('Asia/Jakarta');
    }

    public function surveyApprovalLog($signBy,$approvalID,$result)
    {
        $data_approval = [
            'ID' => $this->uuid,
            'SURVEY_APPROVAL_ID' => $approvalID,
            'ONCHARGE_APPRVL' => 'AUTO_APPROVED_BY_SYSTEM',
            'ONCHARGE_PERSON' => $signBy,
            'ONCHARGE_TIME' => $this->timeNow,
            'ONCHARGE_DESCR' => 'AUTO_APPROVED_BY_SYSTEM',
            'APPROVAL_RESULT' => $result
        ];

        M_SurveyApprovalLog::create($data_approval);
    }
}
