<?php
namespace App\Services\Payments;


class BtcNodeProcessor extends AbstractProcessor
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
