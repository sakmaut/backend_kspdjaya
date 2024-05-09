<?php

namespace App\Http\Resources;

use App\Models\M_CrProspect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Models\M_HrEmployee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class R_CrProspect extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'data_ao' =>
                [
                    'id_ao' => $request->user()->id,
                    'nama_ao' => $request->user()->username,
                ],
            'visit_date' => date('Y-m-d', strtotime($this->visit_date)),
            'nama_debitur' => $this->nama,
            'alamat' => $this->alamat,
            'hp' => $this->hp,
            'slik' => $this->slik == "1" ? 'ya':"tidak",
            'slik_approval' => M_CrProspect::slik_approval($this->id)
        ];


        return $data;
    }
}
