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
        $attachment = M_HrEmployeeDocument::where('USERS_ID',$request->user()->id)->get();

        return [
            'USER_ID' => $request->user()->id,
            'USERNAME' => $request->user()->username,
            'EMAIL' => $request->user()->email,
            'NAMA' => $request->user()->fullname,
            "GENDER" => $request->user()->gender,
            "HP" => $request->user()->mobile_number,
            "STATUS" => $request->user()->status,
            "ATTACHMENT" => [$attachment]
        ];
    }
}
