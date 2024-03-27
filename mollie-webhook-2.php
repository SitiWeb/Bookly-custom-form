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
 

    // Now you can handle the webhook data
    // Implement your logic here based on the Mollie webhook data
    // For testing, you can simply log it as shown above.
}