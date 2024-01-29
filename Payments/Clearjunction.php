<?php

namespace App\Services\Payments;


class Clearjunction extends AbstractProcessor
{
    protected function paymentData(): array
    {
        return [];
    }

    public function verify(string $case = null): bool
    {
        return false;
    }
}
