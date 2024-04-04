<?php

/*
 * How to verify Mollie API Payments in a webhook.
 *
 * See: https://docs.mollie.com/guides/webhooks
 */

use Bookly\Lib\Entities\Payment;
include_once('../../../wp-load.php');


$swmollie = new SWBooklyMollie();
$mail = new sw_bookly_email();


if (!function_exists('set_mollie_paid')) {
    function set_mollie_paid($ref_id, $paid = 0.00)
    {
  
        global $wpdb;
        error_log($paid);
        error_log($ref_id);

        // Replace 'your_ref_id' with the actual reference ID you want to update.
        $ref_id = $ref_id; // Replace 'your_ref_id' with the specific reference ID.

        // Define the table name with the correct WordPress prefix.
        $table_name = $wpdb->prefix . 'bookly_payments';

        // Prepare the SQL query.
        $sql = $wpdb->prepare("UPDATE $table_name SET paid = %d WHERE ref_id = %s", $paid, $ref_id);
        
        // Execute the query.
        $result = $wpdb->query($sql);

        // Check if the query was successful.
        if ($result === false) {
            // Query failed, handle the error.
            return False;
        } else {
            
            return True;
        }
    }
}

// if (!function_exists('set_mollie_status')) {
//     function set_mollie_status($ref_id, $status = 'pending')
//     {

//         global $wpdb;

//         // Replace 'your_ref_id' with the actual reference ID you want to update.
//         $ref_id = $ref_id; // Replace 'your_ref_id' with the specific reference ID.

//         // Define the table name with the correct WordPress prefix.
//         $table_name = $wpdb->prefix . 'bookly_payments';

//         // Prepare the SQL query.
//         $sql = $wpdb->prepare("UPDATE $table_name SET status = %s WHERE ref_id = %s", $status, $ref_id);

//         // Execute the query.
//         $result = $wpdb->query($sql);
        
//         // Check if the query was successful.
//         if ($result === false) {
//             // Query failed, handle the error.
//             return False;
//         } else {
//             return True;
//         }
//     }
// }

if (!function_exists('get_all_payments')) {
    function get_all_payments()
    {
        global $wpdb;

        // Step 2: Prepare the query
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bookly_payments WHERE ref_id IS NOT NULL"
        );

        // Step 3: Execute the query
        $results = $wpdb->get_results($query);

        // Step 4: Loop through the results
        if (!empty($results)) {
            foreach ($results as $row) {
                echo "ref_id: " . $row->ref_id . " - Other Column: " . $row->other_column . "<br>";
            }
        } else {
            echo "0 results";
        }
    }
}


try {
    if (!isset($_POST['id'])) {
        echo 0;
        return;
    }

    error_log('Post: ' . print_r($_POST, true));
    /*
     * Initialize the Mollie API library with your API key.
     *
     * See: https://www.mollie.com/dashboard/developers/api-keys
     */
    require_once ABSPATH . 'wp-content/plugins/bookly-responsive-appointment-booking-tool/autoload.php';
    $mollie = new \Mollie\Api\MollieApiClient();
    $mollie->setApiKey($swmollie->get_api_key());
    /*
     * Retrieve the payment's current state.
     */
    $payment = $mollie->payments->get($_POST["id"]);
    $orderId = $payment->metadata->order_id;
    error_log('payment: ' . print_r($payment, true));
    error_log('Order: ' . print_r($orderId, true));
    /*
     * Update the order in the database.
     */
    //database_write($orderId, $payment->status);


    $sw_bookly = new bookly_sw_custom();
    if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
       
        set_mollie_paid($_POST["id"], $payment->settlementAmount->value);
        
        set_mollie_status($_POST["id"], 'completed');
        
        /*
         * The payment is paid and isn't refunded or charged back.
         * At this point you'd probably want to start the process of delivering the product to the customer.
         */
    } elseif ($payment->isOpen()) {
        error_log('Expired: isOpen: ' . print_r($orderId, true));
        set_mollie_status($_POST["id"], 'pending');
       
        /*
         * The payment is open.
         */
    } elseif ($payment->isPending()) {
        error_log('Expired: isPending: ' . print_r($orderId, true));
        set_mollie_status($_POST["id"], 'pending');

        /*
         * The payment is pending.
         */
    } elseif ($payment->isFailed()) {
        error_log('Expired: isFailed: ' . print_r($orderId, true));
        
        set_mollie_status($_POST["id"], 'rejected');
        $sw_bookly->delete_by_custom_id($orderId);
   
        /*
         * The payment has failed.
         */
    } elseif ($payment->isExpired()) {
        error_log('Expired: isExpired: ' . print_r($orderId, true));
        
        set_mollie_status($_POST["id"], 'rejected');
        $sw_bookly->delete_by_custom_id($orderId);

        /*
         * The payment is expired.
         */
    } elseif ($payment->isCanceled()) {
        error_log('Expired: isCanceled: ' . print_r($orderId, true));
        
        set_mollie_status($_POST["id"], 'rejected');
        $sw_bookly->delete_by_custom_id($orderId);

        /*
         * The payment has been canceled.
         */
    } elseif ($payment->hasRefunds()) {
        error_log('Expired: hasRefunds: ' . print_r($orderId, true));
        
        set_mollie_status($_POST["id"], 'refunded');
        $sw_bookly->delete_by_custom_id($orderId);

        /*
         * The payment has been (partially) refunded.
         * The status of the payment is still "paid"
         */
    } elseif ($payment->hasChargebacks()) {
        error_log('Expired: Deleting: ' . print_r($orderId, true));
        
        set_mollie_status($_POST["id"], 'rejected');
        $sw_bookly->delete_by_custom_id($orderId);

        /*
         * The payment has been (partially) charged back.
         * The status of the payment is still "paid"
         */
    }
} catch (\Mollie\Api\Exceptions\ApiException $e) {
    echo "API call failed: " . htmlspecialchars($e->getMessage());
}





