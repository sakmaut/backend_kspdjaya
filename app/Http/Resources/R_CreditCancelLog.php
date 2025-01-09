<?php

namespace App\Http\Resources;

use App\Models\M_BpkbDetail;
use App\Models\M_Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_CreditCancelLog extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->ID,
            "credit_id" =>$this->CREDIT_ID ??null,
            "request_by" => User::find($this->REQUEST_BY)->fullname ??null,
            "request_branch" => M_Branch::find($this->REQUEST_BRANCH)->NAME??null,
            "request_date" => $this->REQUEST_DATE??null,
            "request_descr" => $this->REQUEST_DESCR??null,
            "oncharge_person" => User::find($this->ONCHARGE_PERSON)->fullname ??null,
            "oncharge_time" => $this->ONCHARGE_TIME??null,
            "oncharge_descr" => $this->ONCHARGE_DESCR??null,
            "oncharge_flag" => $this->ONCHARGE_FLAG??null,
        ];
    }
}
