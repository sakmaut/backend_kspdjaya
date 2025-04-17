<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_Credit;
use App\Models\M_Customer;
use App\Models\M_KwitansiDetailPelunasan;
use App\Models\M_KwitansiStructurDetail;
use App\Models\M_LogPrint;
use App\Models\M_Payment;
use App\Models\M_PaymentAttachment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_TasksPushers extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $branch = M_Branch::where('ID', $this->created_branch)->first();

        return [
            'title' => $this->title,
            'type' => $this->type,
            'type_id' => $this->type_id,
            'status' => $this->status,
            'descr' => $this->descr,
            'created_by' => $this->created_by,
            'branch_name' => $branch->NAME ?? '',
            'created_at' => $this->created_at,
        ];
    }
}
