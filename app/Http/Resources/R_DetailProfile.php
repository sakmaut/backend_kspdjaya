<?php

namespace App\Http\Resources;

use App\Models\M_HrEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailProfile extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachment = M_HrEmployeeDocument::where('EMPLOYEE_ID',$request->user()->employee_id)->get();

        return [
            'USER_ID' => $request->user()->id,
            'EMPLOYEE_ID' => $request->user()->employee_id,
            'USERNAME' => $request->user()->username,
            'EMAIL' => $request->user()->email,
            'NAMA' => $this->NAMA,
            "GENDER" => $this->GENDER,
            "HP" => $this->HP,
            "STATUS_MST" => $this->STATUS_MST,
            "ATTACHMENT" => [$attachment]
        ];
    }
}
