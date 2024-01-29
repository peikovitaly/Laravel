<?php


namespace App\Services\Payments;


use Illuminate\Support\Arr;

class TestProcessor extends AbstractProcessor
{
    public function verify(string $case = null): bool
    {
        return true;
    }

    protected function paymentData(): array
    {
        $randomTestCase = Arr::random([AbstractProcessor::CASE_CANCEL, AbstractProcessor::CASE_PROCESS]);

        return [
            'mode' => 'link',
            'url' => $this->buildCaseUrl($randomTestCase) . '?' . $this->getTransactionTokenParam() . '=' . $this->transaction->token
        ];
    }
}
