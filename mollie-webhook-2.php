<?php

include_once('../../../wp-load.php');
require_once ABSPATH . 'wp-content/plugins/bookly-responsive-appointment-booking-tool/autoload.php';
use Bookly\Lib\Entities\Payment;
// Get the raw webhook data
$raw_webhook_data = $_POST;
$payment_id = false;
if (isset($_POST['id'])){
    $payment_id = $_POST['id'];
}
error_log('Payment_id = '. $payment_id);

if ($payment_id) {
    // Log the webhook data to a file
    $mollie = new \Mollie\Api\MollieApiClient();
    $api_key = (new SWBooklyMollie())->get_api_key();
    $mollie->setApiKey($api_key);

    $payment = $mollie->paymentLinks->get($payment_id);
    $orderId = $payment->metadata->order_id;
    error_log('Order_id = '. $orderId);
    $log_message = "Mollie Webhook Data: " . json_encode($payment);
    error_log($log_message, 0);
    $log_message = "Mollie Webhook order: " . ($payment->id);
    error_log($log_message, 0);
    if ($payment->isPaid()) {
        error_log('set is paid');
        (new SWBooklyMollie())->change_payment_status($payment->id); 
    }
    elseif ($payment->isFailed()) {
        error_log('Expired: isFailed: ' . print_r($orderId, true));
        $sw_bookly->delete_by_custom_id($orderId);
        set_mollie_status($_POST["id"], 'rejected');
   
        /*
         * The payment has failed.
         */
    } elseif ($payment->isExpired()) {
        error_log('Expired: isExpired: ' . print_r($orderId, true));
        $sw_bookly->delete_by_custom_id($orderId);
        set_mollie_status($_POST["id"], 'rejected');

        /*
         * The payment is expired.
         */
    } elseif ($payment->isCanceled()) {
        error_log('Expired: isCanceled: ' . print_r($orderId, true));
        $sw_bookly->delete_by_custom_id($orderId);
        set_mollie_status($_POST["id"], 'rejected');

        /*
         * The payment has been canceled.
         */
    } elseif ($payment->hasRefunds()) {
        error_log('Expired: hasRefunds: ' . print_r($orderId, true));
        $sw_bookly->delete_by_custom_id($orderId);
        set_mollie_status($_POST["id"], 'refunded');

        /*
         * The payment has been (partially) refunded.
         * The status of the payment is still "paid"
         */
    } elseif ($payment->hasChargebacks()) {
        error_log('Expired: Deleting: ' . print_r($orderId, true));
        $sw_bookly->delete_by_custom_id($orderId);
        set_mollie_status($_POST["id"], 'rejected');

        /*
         * The payment has been (partially) charged back.
         * The status of the payment is still "paid"
         */
    }
 

    // Now you can handle the webhook data
    // Implement your logic here based on the Mollie webhook data
    // For testing, you can simply log it as shown above.
}