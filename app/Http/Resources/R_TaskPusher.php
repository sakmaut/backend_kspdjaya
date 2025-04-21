<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_TaskPusher extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = M_Branch::where('ID', $this->created_branch)->first();

        $gettype = $this->type;
        $setType = ['payment', 'request_payment', 'request_discount', 'repayment'];
        $cekType = in_array($gettype, $setType);

        if($cekType){
            $setRoute = 'payment_approval';
        }else{
            $setRoute = 'payment_cancel';
        }

        return [
            'title' => $this->title,
            'route' => $setRoute,
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
