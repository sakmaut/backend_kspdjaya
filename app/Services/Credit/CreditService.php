<?php

namespace App\Services\Credit;

use App\Repository\Credit\CreditRepository;
use Ramsey\Uuid\Uuid;

class CreditService
{
    protected $creditRepository;
    protected $uuid;

    function __construct(
        CreditRepository $creditRepository
    ) {
        $this->creditRepository = $creditRepository;
        $this->uuid = Uuid::uuid7()->toString();
    }

    public function getCreditWithCustomer($loan_number)
    {
        return $this->creditRepository->creditWithCustomer($loan_number);
    }
}
