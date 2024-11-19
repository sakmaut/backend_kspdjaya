<?php

namespace App\Http\Resources;

use App\Models\M_CreditSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CustomerSearch extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $data = M_CreditSchedule::where('LOAN_NUMBER', $this->LOAN_NUMBER)
        ->where(function ($query) {
            $query->whereNull('PAID_FLAG')
                ->orWhere('PAID_FLAG', '<>', 'PAID');
        })->get();

        return [
            "loan_number" => $this->LOAN_NUMBER??null,
            "no_kontrak" => $this->ORDER_NUMBER??null,
            "nama" => $this->NAME??null,
            "no_polisi" => $this->POLICE_NUMBER?? null,
            "alamat" => $this->ADDRESS,
            "angsuran" => intval($this->INSTALLMENT??null),
            'status' => $data->isEmpty() ? 'LUNAS':'BELUM LUNAS'
        ];
    }
}
