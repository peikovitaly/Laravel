<?php

namespace App\Services\Payments\Operations;


use App\Helpers\DIHelper;
use App\Jobs\TelegramMessageJob;
use App\Models\Deposit;
use App\Notifications\DepositFailNotification;
use App\Notifications\DepositNotification;

class DepositOperation extends AbstractOperation
{
    public function process()
    {
        $deposit = $this->getDeposit();

        if ($deposit && $deposit->isPending()) {
            $this->confirmDeposit($deposit);
        }
    }

    public function confirmDeposit(Deposit $deposit)
    {
        $deposit->confirmed();
        $deposit->save();

        $deposit->increaseUserWallet();
        $deposit->user->notify(new DepositNotification($deposit));

        TelegramMessageJob::dispatch($deposit->telegramMessage());
        DIHelper::voluumService($deposit->user)->deposit($deposit);
    }

    public function fail()
    {
        $deposit = $this->getDeposit();

        if ($deposit && $deposit->isPending()) {
            $deposit->fail();
            $deposit->save();

            $deposit->user->notify(new DepositFailNotification($deposit));
        }

        $this->resultDescription = trans('payment.service-pay-fail');
    }

    public function cancel()
    {
        $this->cancelDeposit($this->getDeposit());
    }

    public function cancelDeposit(?Deposit $deposit)
    {
        if ($deposit && $deposit->isPending()) {
            $deposit->cancel();
            $deposit->save();

            $deposit->user->notify(new DepositFailNotification($deposit));
        }
    }

    /**
     * @return Deposit|object|null
     */
    public function getDeposit(): ?Deposit
    {
        return Deposit::query()->where('transaction_id', $this->transaction->id)->first();
    }
}
