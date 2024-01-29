<?php

namespace App\Services\Payments;

use App\Http\Resources\DepositHistoryResource;
use App\Models\Managers\DepositsManager;

class BankTransferProcessor extends AbstractProcessor
{
    public function verify(string $case = null): bool
    {
        return true;
    }

    protected function paymentData(): array
    {
        return [
            'mode'    => 'none',
            'deposit' => new DepositHistoryResource(DepositsManager::getDepositByTransactionId($this->transaction->id))
        ];
    }
}
