<?php
declare(strict_types=1);

namespace App\Services\Payments;


class BlockchainProcessor extends AbstractProcessor
{
    public function paymentData(): array
    {
        return [
            'mode' => 'crypto',
        ];
    }

    public function verify(string $case = null): bool
    {
        return false;
    }
}
