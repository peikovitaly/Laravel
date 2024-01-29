<?php

namespace App\Services\Payments;


class PayeerProcessor extends AbstractProcessor
{
    protected $shop;
    protected $secretKey;
    protected $availableIps = ['185.71.65.92', '185.71.65.189', '149.202.17.210'];
    protected string $transactionTokenParam = 'm_orderid';

    protected function paymentData(): array
    {
        return [
            'mode' => 'form',
            'action' => 'https://payeer.com/merchant/',
            'method' => 'post',
            'fields' => $this->getFields(),
        ];
    }

    private function getFields(): array
    {
        $fields = [
            'm_shop' => $this->shop,
            'm_orderid' => $this->transaction->token,
            'm_amount' => number_format($this->transaction->cost, 2, '.', ''),
            'm_curr' => $this->transaction->currencyCode(),
            'm_desc' => base64_encode(e($this->transaction->description))
        ];

        $fields['m_sign'] = $this->createSign($fields);

        return $fields;
    }

    protected function createSign($params)
    {
        $params[] = $this->secretKey;

        return strtoupper(hash('sha256', implode(":", $params)));
    }

    public function verify(string $case = null): bool
    {
        if (config('app.env') == 'local') {
            $this->availableIps[] = '127.0.0.1';
        }

        if (!in_array(request()->getClientIp(), $this->availableIps)) {
            $this->debug('Client IP (' . request()->getClientIp() . ') not in availableIps');
            return false;
        }

        if (!isset($_POST['m_operation_id']) || !isset($_POST['m_sign'])) {
            return false;
        }

        if (!$this->isValidCost((float) request()->get('m_amount'))) {
            $this->debug('cost not valid: ' . request()->get('m_amount'));
            return false;
        }

        $arHash = [
            request('m_operation_id'),
            request('m_operation_ps'),
            request('m_operation_date'),
            request('m_operation_pay_date'),
            request('m_shop'),
            request('m_orderid'),
            request('m_amount'),
            request('m_curr'),
            request('m_desc'),
            request('m_status')
        ];

        if (isset($_POST['m_params'])) {
            $arHash[] = $_POST['m_params'];
        }

        $localSign = $this->createSign($arHash);

        if ($localSign != request('m_sign')) {
            $this->debug('Wrong localSign = ' . $localSign);
            return false;
        }

        if (request('m_status') != 'success') {
            $this->debug('status must be success. Actual status ' . request('m_status'));
            return false;
        }

        return true;
    }

    public function afterVerify(bool $verifyStatus)
    {
        ob_end_clean();

        if ($verifyStatus) {
            exit($_POST['m_orderid'] . '|success');
        } else {
            exit($_POST['m_orderid'] . '|error');
        }
    }
}
