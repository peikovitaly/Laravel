<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

class KiberpayProcessor extends AbstractProcessor
{
    const CURRENCY_USD = 840;

    protected string $transactionTokenParam = 'customer_uid';
    protected string $accessToken;
    protected string $authKey;

    protected function paymentData(): array
    {
        return $this->prepareDataForForm();
    }

    /**
     * @return array
     */
    private function prepareDataForForm()
    {
        $response = $this->createPaymentRequest();
        return ($response->json('status') === 1) ?
            ['mode' => 'link', 'url' => $response->json('data.cus_url')] :
            ['mode' => 'none'];
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
     */
    private function createPaymentRequest()
    {
        $data = [
            'merchant_uid' => $this->transaction->id,
            'customer_uid' => $this->transaction->token,
            'payment_method' => 'P2P',
            'amount' => round($this->transaction->cost, 2),
            'currency' => self::CURRENCY_USD,
            'callback_url' => $this->processUrl(),
            'success_url' => $this->successUrl(),
            'fail_url' => $this->failUrl(),
        ];

        $response = Http::asJson()
            ->withHeaders([
                'Content-Type'=> 'application/json',
                'Accept'=> 'application/json;charset=UTF-8',
                'Authorization'=> 'AccessToken ' . $this->accessToken,
                'X-User-Authorization'=> 'Basic ' . $this->authKey,
            ])
            ->post('https://api.kiberpay.com/api/v1/inrequest', $data);

        return $response;
    }

    public function verify(string $case = null): bool
    {
        if (!$this->isValidCost((float) request('amount'))) {
            $this->debug('not valid cost: ' . request('amount'));
            return false;
        }

        if (request('currency') != self::CURRENCY_USD) {
            $this->debug('wrong shop_currency');
            return false;
        }

        if (request('status') != 'paid') {
            $this->debug('Wrong status:' . request('status'));
            return false;
        }

        return true;
    }
}

