<?php
namespace App\Services\Payments;


class LtcNodeProcessor extends AbstractProcessor
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
