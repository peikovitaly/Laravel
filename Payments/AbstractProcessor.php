<?php

namespace App\Services\Payments;


use App\Exceptions\CommonException;
use App\Helpers\Log;
use App\Traits\Initializer;
use App\Models\{PaymentSystem, Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class AbstractProcessor
{
    use Initializer;

    const MODE_LIVE = 'live';
    const MODE_TEST = 'test';

    const CASE_PROCESS = 'process';
    const CASE_SUCCESS = 'success';
    const CASE_FAIL = 'fail';
    const CASE_CANCEL = 'cancel';

    protected PaymentSystem $paymentSystem;
    protected string $transactionTokenParam = 'trans_token';
    protected ?Transaction $transaction = null;

    public static function instance(string $slug, ?PaymentSystem $paymentSystem = null): ?AbstractProcessor
    {
        try {
            if (!$paymentSystem) {
                $paymentSystem = PaymentSystem::findBySlug($slug);
            }

            return app(__NAMESPACE__ . '\\' . Str::studly($slug) . 'Processor', [
                'paymentSystem' => $paymentSystem,
            ]);
        } catch (\Exception $e) {
            Log::payment($slug, $e->getMessage());
        }

        return null;
    }

    public function __construct(PaymentSystem $paymentSystem)
    {
        $this->paymentSystem = $paymentSystem;
        $this->configure(config('payments.' . $paymentSystem->slug, []));
    }

    public function setTransaction(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getName(): string
    {
        return $this->paymentSystem->getName();
    }

    public function slug(): string
    {
        return $this->paymentSystem->slug;
    }

    public function paymentSystemId(): int
    {
        return $this->paymentSystem->id;
    }

    public function getPrecision(): int
    {
        return $this->transaction->cost_precision;
    }

    public function extractTransactionToken(Request $request, string $case)
    {
        return $request->get($this->transactionTokenParam);
    }

    public function getTransactionTokenParam(): string
    {
        return $this->transactionTokenParam;
    }

    protected function buildCaseUrl(string $case): string
    {
        return route('payment.case', ['slug' => $this->slug(), 'case' => $case]);
    }

    public function successUrl(): string
    {
        return $this->buildCaseUrl(self::CASE_SUCCESS);
    }

    public function failUrl(): string
    {
        return $this->buildCaseUrl(self::CASE_FAIL);
    }

    public function cancelUrl(): string
    {
        return $this->buildCaseUrl(self::CASE_CANCEL);
    }

    public function processUrl(): string
    {
        return $this->buildCaseUrl(self::CASE_PROCESS);
    }

    public function isValidCost(float $value): bool
    {
        return ($this->transaction && $value > 0 && $value == (float) $this->transaction->cost);
    }

    public function debug($message)
    {
        Log::payment($this->slug(), is_string($message) ? $message : print_r($message, true));
    }

    /**
     * Prepare payment params to continue payment process on front side
     * @return array
     * @throws CommonException
     */
    public function preparePaymentData(): array
    {
        if (!$this->transaction) {
            throw new CommonException('Transaction not specified');
        }

        return $this->paymentData();
    }
    abstract protected function paymentData(): array;

    /**
     * Verifying payment data received from payment IPN for process case
     * @param string|null $case
     * @return bool
     */
    abstract public function verify(string $case = null): bool;

    public function afterVerify(bool $verifyStatus)
    {
    }

    public function getPublicSettings(): array
    {
        return [];
    }
}
