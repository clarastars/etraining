<?php

namespace App\Services;

use App\Models\Back\Invoice;
use App\Services\NoonPaymentService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * https://docs.noonpayments.com/start/introduction
 *
 */
class NoonService implements PaymentServiceInterface
{
    /**
     * Creates a payment url for a specific invoice.
     *
     * @param \App\Models\Back\Invoice $invoice
     * @return string URL of payment form
     * @throws \Exception
     */
    public function createPaymentUrlForInvoice(Invoice $invoice): string
    {
        $centerId = $invoice->trainee->company->center_id;

        $centerId = 5676; // As of 22-12-2024 - Change all payments to Jasarah.

        $webhookUrl = ($centerId == 5676 ? 'https://prod.jasarah-ksa.com/noon' : 'https://app.jisr-ksa.com/noon');

        $traineeDisplayName = $this->sanitizeContactNameForNoon((string) ($invoice->trainee->name ?? ''));
        if ($traineeDisplayName === '') {
            $traineeDisplayName = 'Trainee';
        }

        $url = NoonPaymentService::getInstance()->initiate(
            $centerId,
            [
            'order' => [
                'reference' => $invoice->id,
                'amount' => $invoice->grand_total,
                'currency' => 'SAR',
                'name' => $traineeDisplayName,
                'description' => 'Training fees for period - '.$invoice->from_date.' - '.$invoice->to_date,
                // 'ipAddress' => request()->ip(),
            ],
            'billing' => [
                'contact' => [
                    'firstName' => Str::before($traineeDisplayName, ' '),
                    'lastName' => Str::afterLast($traineeDisplayName, ' '),
                    'phone' => $invoice->trainee->clean_phone,
                    //'email' => $invoice->trainee->email,
                ],
            ],
            'deviceFingerPrint' => [
                'sessionId' => request()->fingerprint(),
            ],
            'configuration' => [
                'locale' => 'ar',
                'webhookUrl' => $webhookUrl,
                'returnUrl' => 'https://prod.jasarah-ksa.com/trainees/payment/card/charge-payment',
                // 'generateShortLink' => true, // TODO: When sharing the invoice with SMS.
            ]
        ]);


        if ($url->resultCode === 0) {
            return $url->result->checkoutData->postUrl;
        }

        throw new RuntimeException('Noon payment fatal error: '.$url->resultCode.' - '.$url->message);
    }

    /**
     * Remove Unicode format / bidi marks (e.g. U+200F RLM) and normalize whitespace for Noon payloads.
     */
    private function sanitizeContactNameForNoon(string $name): string
    {
        $stripped = preg_replace('/\p{Cf}/u', '', $name);

        if ($stripped === null) {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($stripped));

        return $collapsed === null ? '' : $collapsed;
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public function getOrder($order_id, $center_id)
    {
        return NoonPaymentService::getInstance()->getOrder($order_id, $center_id);
    }

    /**
     * Get order(s) by reference (invoice ID)
     *
     * @param string $reference The invoice ID used as reference
     * @param int $center_id The center ID (5676 for Jasarah, 0 for Jisr)
     * @return mixed
     */
    public function getOrderByReference($reference, $center_id)
    {
        return NoonPaymentService::getInstance()->getOrderByReference($reference, $center_id);
    }

    public function isOrderSuccessful(string $order_id, $center_id): bool
    {
        $order = $this->getOrder($order_id, $center_id);
        return $this->isPaymentSuccess($order);
    }

    public function isPaymentSuccess($order): bool
    {
        return isset($order->result->transactions) &&
            is_array($order->result->transactions) &&
            $order->result->transactions[0]->type == "SALE" &&
            $order->result->transactions[0]->status == "SUCCESS";
    }
}
