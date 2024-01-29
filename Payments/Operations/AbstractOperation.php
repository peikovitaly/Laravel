<?php

namespace App\Services\Payments\Operations;


use App\Models\Transaction;
use Illuminate\Support\Str;

abstract class AbstractOperation
{
    const KIND_DEPOSIT = 'deposit';

    protected Transaction $transaction;
    protected $resultDescription;

    public static function instance(string $slug, array $parameters = []): ?AbstractOperation
    {
        try {
            return app(__NAMESPACE__ . '\\' . Str::studly($slug) . 'Operation', $parameters);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return null;
        }
    }

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function success()
    {
    }

    public function fail()
    {
    }

    public function cancel()
    {
    }

    abstract function process();
}
