<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_DeployList extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ID,
            'nama_pic' => $this->USER_ID ?? "",
            'no_kontrak' => $this->LOAN_NUMBER ?? "",
            'nama_customer' => $this->NAMA_CUST ?? "",
            'cycle_awal' => $this->CYCLE_AWAL ?? "",
            'nbot' => $this->N_BOT ?? "",
            'desa' => $this->DESA ?? "",
            'kec' => $this->KEC ?? "",
            'ket' => "",
        ];
    }
}
