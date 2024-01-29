<?php

namespace App\Services\Payments;


use App\Exceptions\CommonException;
use App\Helpers\Log;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Stripe\PaymentIntent;

class StripeProcessor extends AbstractProcessor
{
    protected $availableCurrencies = ['USD', 'EUR'];
    protected $publicKey;
    protected $secretKey;
    protected $webHookSecretKey;

    private $tolerance = 0;

    protected function paymentData(): array
    {
        $this->initApiKey();

        try {
            if (request('operation_id')) {
                $paymentIntent = PaymentIntent::create([
                    'amount' => round($this->transaction->cost, 2) * 100,
                    'currency' => strtolower($this->transaction->currencyCode()),
                    'payment_method' => request('operation_id'),
                    'description' => e($this->transaction->description),
                    'metadata' => [
                        'token' => $this->transaction->token
                    ],
                    'confirmation_method' => 'manual',
                    'confirm' => true,
                ]);
            }

            if (request('payment_intent_id')){
                $paymentIntent = PaymentIntent::retrieve(request('payment_intent_id'));
                $paymentIntent->confirm();
            }

        } catch (\Exception $e) {
            Log::payment($this->slug(), $e->getMessage());
            throw new CommonException(trans('app.payment-init-error'));
        }

        Log::payment($this->slug(), $paymentIntent->toJSON());

        $response = [
            'mode' => 'stripe',
            'processor_status' => $paymentIntent->status,
        ];

        if ($paymentIntent->status == 'requires_action') {
            $response['next_action'] = $paymentIntent->next_action;
            $response['client_secret'] = $paymentIntent->client_secret;
            $response['transaction_id'] = $this->transaction->token;
            $response['cancel_url'] = $this->failUrl() . '?' . $this->transactionTokenParam . '=' . $this->transaction->token;

        } else {
            $operation = $this->transaction->getOperation();
            $operation->process();

            $this->transaction->response_data = request()->all();
            $this->transaction->success();
            $this->transaction->save();

            $response['payment'] = new PaymentResource($this->transaction);
        }

        return $response;
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function extractTransactionToken(Request $request, $case)
    {
        if ($case === self::CASE_PROCESS) {
            return request('data.object.metadata.token', '');
        }

        return parent::extractTransactionToken($request, $case);
    }

    private function initApiKey()
    {
        \Stripe\Stripe::setApiKey($this->secretKey);
    }

    /**
     * @return \Stripe\Checkout\Session
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function createSession()
    {
        try {
            $this->initApiKey();
            $tokenParam = $this->transactionTokenParam . '=' . $this->transaction->token;

            return \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'name' => e($this->transaction->description),
                        'description' => e($this->transaction->description),
                        'images' => [],
                        'amount' => round($this->transaction->cost, 2) * 100,
                        'currency' => strtolower($this->transaction->currencyCode()),
                        'quantity' => 1,
                    ]
                ],
                'success_url' => $this->successUrl() . '?session_id={CHECKOUT_SESSION_ID}&' . $tokenParam,
                'cancel_url' => $this->failUrl() . '?' . $tokenParam,
                'client_reference_id' => $this->transaction->token,
            ]);
        } catch (\Exception $e) {
            $this->debug($e);
        }

        return null;
    }

    public function verify(string $case = null): bool
    {
        $this->initApiKey();

        $payload = request()->getContent();
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $this->debug('signature: ' . $signature);

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webHookSecretKey, $this->tolerance);
        } catch(\UnexpectedValueException $e) {
            $this->debug($e->getMessage());
            return false;
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            $this->debug($e->getMessage());
            return false;
        }

        $this->debug('event type: ' . $event->type);

        if ($event->type == 'checkout.session.completed' || $event->type == 'payment_intent.succeeded') {
            return true;
        }

        return false;
    }

    public function getPublicSettings(): array
    {
        return [
            'pub_key' => $this->getPublicKey()
        ];
    }
}
