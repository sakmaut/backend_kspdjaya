<?php

namespace App\Http\Controllers\API;

use App\Models\M_Credit;
use Illuminate\Support\Collection;

class LoanSearchService
{
    public function search(array $filters, array $options = []): Collection
    {
        $query = M_Credit::query()
            ->select([
                'ID',
                'LOAN_NUMBER',
                'ORDER_NUMBER',
                'CUST_CODE',
                'INSTALLMENT',
                'INSTALLMENT_DATE',
                'BRANCH',
                'STATUS',
                'CREDIT_TYPE'
            ])
            ->with([
                'customer:ID,CUST_CODE,NAME,ALIAS,ADDRESS',
                'collateral:CR_CREDIT_ID,POLICE_NUMBER',
                'branch:ID,NAME'
            ])
            ->where('STATUS', 'A');

        if (!empty($options['credit_types'])) {
            $query->whereIn('CREDIT_TYPE', $options['credit_types']);
        }

        $this->applySearchFilters($query, $filters);
        
        $query->orderBy('LOAN_NUMBER');

        if (!empty($options['limit'])) {
            return $query->limit($options['limit'])->get();
        }

        return $query->lazy(100)->collect();
    }

    private function applySearchFilters($query, array $filters): void
    {
        if (!empty($filters['no_kontrak'])) {
            $query->where('LOAN_NUMBER', 'LIKE', "%{$filters['no_kontrak']}%");
        }

        if (!empty($filters['nama'])) {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->where('NAME', 'LIKE', "%{$filters['nama']}%");
            });
        }

        if (!empty($filters['no_polisi'])) {
            $query->whereHas('collateral', function ($q) use ($filters) {
                $q->where('POLICE_NUMBER', 'LIKE', "%{$filters['no_polisi']}%");
            });
        }
    }
}
