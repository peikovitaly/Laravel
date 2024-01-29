<?php

namespace App\Services\Payments;


class PerfectMoneyProcessor extends AbstractProcessor
{
    protected $payeeAccountUsd;
    protected $payeeAccountEur;
    protected $payeeAccount;
    protected $secret;
    protected string $transactionTokenParam = 'TRANSACTION_REF';

    private function definePayeeAccount()
    {
        $this->payeeAccount = $this->transaction->currencyCode() == 'EUR' ? $this->payeeAccountEur : $this->payeeAccountUsd;
    }

    protected function paymentData(): array
    {
        $this->definePayeeAccount();

        return [
            'mode' => 'form',
            'action' => 'https://perfectmoney.is/api/step1.asp',
            'method' => 'post',
            'fields' => [
                'PAYEE_ACCOUNT' => $this->payeeAccount,
                'PAYMENT_AMOUNT' => round($this->transaction->cost, 2),
                'PAYMENT_ID' => $this->transaction->id,
                'PAYEE_NAME' => e($this->transaction->description),
                'PAYMENT_UNITS' => $this->transaction->currencyCode(),
                'SUGGESTED_MEMO' => e($this->transaction->description),
                'SUGGESTED_MEMO_NOCHANGE' => 1,
                'PAYMENT_URL' => $this->successUrl(),
                'PAYMENT_URL_METHOD' => 'POST',
                'STATUS_URL' => $this->processUrl(),
                'NOPAYMENT_URL' => $this->failUrl(),
                'NOPAYMENT_URL_METHOD' => 'POST',
                'BAGGAGE_FIELDS' => $this->transactionTokenParam,
                $this->transactionTokenParam => $this->transaction->token
            ],
        ];
    }

    public function verify(string $case = null): bool
    {
        $this->definePayeeAccount();

        if (!$this->isValidCost((float) request('PAYMENT_AMOUNT'))) {
            $this->debug('not valid cost: ' . request('PAYMENT_AMOUNT'));
            return false;
        }

        if (request('PAYMENT_ID') != $this->transaction->id) {
            $this->debug('not valid PAYMENT_ID for transaction. used id=' . request('PAYMENT_ID'));
            return false;
        }

        if (request('PAYEE_ACCOUNT') != $this->payeeAccount) {
            $this->debug('not valid PAYEE_ACCOUNT: ' . request('PAYEE_ACCOUNT'));
            return false;
        }

        return request('V2_HASH') === $this->createSignature();
    }

    private function createSignature(): string
    {
        $token = request('PAYMENT_ID') .':'. request('PAYEE_ACCOUNT').':'.
            request('PAYMENT_AMOUNT') .':'. request('PAYMENT_UNITS').':'.
            request('PAYMENT_BATCH_NUM') .':'.
            request('PAYER_ACCOUNT') .':'. strtoupper(md5($this->secret)).':'.
            request('TIMESTAMPGMT');

        return strtoupper(md5($token));
    }
}
