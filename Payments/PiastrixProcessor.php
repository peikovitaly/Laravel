<?php
declare(strict_types=1);

namespace App\Services\Payments;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

class PiastrixProcessor extends AbstractProcessor
{
    const USD_CODE = 840;

    protected string $shopId;
    protected string $secretKey;
    protected string $transactionTokenParam = 'shop_order_id';

    protected function paymentData(): array
    {
        $params = [
            'mode' => 'form',
            'action' => 'https://pay.piastrix.com/' . App::getLocale() . '/pay',
            'method' => 'post',
            'fields' => [
                'shop_id' => $this->shopId,
                'amount' => round((float) $this->transaction->cost, 2),
                'currency' => self::USD_CODE,
                'description' => e($this->transaction->description),
                'payway' => 'card_rub',
                $this->transactionTokenParam => $this->transaction->token,
                'failed_url' => $this->failUrl(),
                'success_url' => $this->successUrl(),
                'callback_url' => $this->processUrl(),
            ],
        ];

        $params['fields']['sign'] = $this->sign($params['fields'], ['failed_url', 'success_url', 'callback_url', 'description', 'payway']);

        return $params;
    }

    public function verify(string $case = null): bool
    {
        if (!$this->isValidCost((float) request('shop_amount'))) {
            $this->debug('not valid cost: ' . request('shop_amount'));
            return false;
        }

        if (request('shop_currency') != self::USD_CODE) {
            $this->debug('wrong shop_currency');
            return false;
        }

        if (request('shop_id') != $this->shopId) {
            $this->debug('wrong shop id');
            return false;
        }

        if (request('status') != 'success') {
            return false;
        }

        $fields = array_filter(request()->all());

        return $this->sign($fields, ['sign']) === request('sign');
    }

    protected function sign(array $fields, array $exceptFields = [])
    {
        $fields = Arr::except($fields, $exceptFields);
        ksort($fields);
        return hash('sha256', join(':', $fields) . $this->secretKey);
    }

    public function afterVerify(bool $verifyStatus)
    {
        exit('OK');
    }

    public function billStatus(string $shopOrderId)
    {
        $data = [
            'now' => now()->toDateTimeString(),
            'shop_id' => $this->shopId,
            'shop_order_id' => $shopOrderId,
        ];

        $data['sign'] = $this->sign($data);

        return Http::asJson()
            ->post('https://core.piastrix.com/bill/shop_order_status', $data)
            ->json();
    }
}
