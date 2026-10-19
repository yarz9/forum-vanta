<?php

namespace Plisio\Payment;


use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class PlisioGateway extends AbstractProvider
{
    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('plisio_title');
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        return 'https://api.plisio.net/api/v1/';
    }

    /**
     * @param array $options
     * @param array $errors
     * @return bool
     */
    public function verifyConfig(array &$options, &$errors = [])
    {
        if (empty($options['secret_key']))
        {
            return false;
        }

        return true;
    }

    /**
     * @param Controller $controller
     * @param PurchaseRequest $purchaseRequest
     * @param Purchase $purchase
     * @return \XF\Mvc\Reply\View
     */
    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        $paymentProfile = $purchase->paymentProfile;
        $purchaser = $purchase->purchaser;

        $siteUrl = \XF::app()->options()->boardUrl;

        $payment = [
            'api_key' => $paymentProfile->options['secret_key'],
            'order_name' => 'Order # ' . $purchaseRequest->request_key,
            'order_number' => $purchaseRequest->request_key,
            'source_amount' => $purchase->cost,
            'source_currency' => $purchase->currency,
            'callback_url' => $siteUrl . '/payment_callback.php?_xfProvider=Plisio',
            'email' => $purchaser->email,
            'plugin' => 'xenforo',
            'version' => '1.0.0',
        ];

        try
        {
            $response = \XF::app()->http()->client()->get($this->getApiEndpoint() . 'invoices/new', [
                'query' => $payment,
                'allow_redirects' => true,
            ]);
            $orderResponse = json_decode($response->getBody()->getContents(), true);
        }
        catch (\Exception $e)
        {
            \XF::logException($e);

            return $controller->error(\XF::phrase('something_went_wrong_please_try_again'));
        }

        if ($orderResponse['status'] !== 'error')
        {

            return $controller->redirect($orderResponse['data']['invoice_url']);
        }

        return $controller->error(\XF::phrase('something_went_wrong_please_try_again'));

    }

    /**
     * @param Request $request
     *
     * @return CallbackState
     */
    public function setupCallback(Request $request): CallbackState
    {
        $state = new CallbackState();

        $state->providerId = $request->filter('_xfProvider', 'str');

        $state->ip = $request->getIp();
        $state->rawInput = $request->getInputForLogs();

        $state->transactionId = $state->rawInput['order_number'] ?? null;
        $state->requestKey = $state->rawInput['order_number'] ?? null;

        $state->token = $state->rawInput['verify_hash'] ?? null;
        $state->status = $state->rawInput['status'] ?? null;

        $state->httpCode = 200;

        return $state;
    }

    /**
     * @param CallbackState $state
     *
     * @return bool
     */
    public function validateCallback(CallbackState $state): bool
    {
        if ($state->transactionId && $state->requestKey)
        {
            if ($state->getPaymentProfile()->provider_id == $state->providerId)
            {
                return parent::validateCallback($state);
            }

            $state->logType = 'info';
            $state->logMessage = 'Invalid provider.';

            return false;
        }

        var_dump($state);
        $state->logType = 'info';
        $state->logMessage = 'No Transaction ID or Request Key. No action to take.';

        return false;
    }

    /**
     * @param CallbackState $state
     *
     * @return bool
     */
    public function validatePurchasableData(CallbackState $state): bool
    {
        $paymentProfile = $state->getPaymentProfile();

        $options = $paymentProfile->options;
        if (!empty($options['secret_key']))
        {
            if (!empty($state->token))
            {
                return true;
            } else {
                $state->logType = 'error';
                $state->logMessage = 'Invalid signature.';

                return false;
            }
        }

        $state->logType = 'error';
        $state->logMessage = 'Invalid secret key.';

        return false;
    }

    /**
     * @param CallbackState $state
     */
    public function getPaymentResult(CallbackState $state): void
    {
        if (($state->status == 'completed') || ($state->status == 'mismatch'))
        {
            $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
        }
    }

    /**
     * @param CallbackState $state
     */
    public function prepareLogData(CallbackState $state): void
    {
        $state->logDetails = [
            'ip'           => $state->ip,
            'request_time' => \XF::$time,
            'raw_input'    => $state->rawInput,
        ];
    }

    /**
     * @param PaymentProfile $paymentProfile
     * @param                $unit
     * @param                $amount
     * @param int            $result
     *
     * @return bool
     */
    public function supportsRecurring(PaymentProfile $paymentProfile, $unit, $amount, &$result = self::ERR_NO_RECURRING): bool
    {
        return false;
    }

    /**
     * @return array
     */
    protected function getSupportedRecurrenceRanges(): array
    {
        return [];
    }
}