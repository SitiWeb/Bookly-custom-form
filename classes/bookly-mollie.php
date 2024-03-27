<?php
// Load the original plugin's autoload file to load the necessary classes.
require_once ABSPATH . 'wp-content/plugins/bookly-responsive-appointment-booking-tool/autoload.php';
use Bookly\Lib\Entities\Payment;
class SWBooklyMollie{
    private $api_key;
    private $api_type;
    private $test_api_key;

    public function __construct()
    {
        $this->test_api_key = 'test_RkveJWA6a47SNKgz2nsrNAScB3cr6p';
        $this->api_key = 'live_eSWu84NcASMzABRpQta9CkcEktBuUQ';
        $this->api_type = 'live';
    }

    public function get_api_key(){
        if ($this->api_type == 'live'){
            return $this->api_key;
        }
        return $this->test_api_key;
    }

    public function create_payment($amount, $name, $redirectUrl = 'https://agenda.bodyunlimited.nl/' ){
        global $wpdb;
        $webhookUrl = get_site_url().'/wp-content/plugins/Bookly-custom-form/mollie-webhook.php';
        
   
        
        // Define the table name with the correct WordPress prefix.
        $table_name = $wpdb->prefix . 'bookly_sw_custom';
        // Prepare the SQL query.
        $sql = $wpdb->prepare("SELECT id_middle FROM $table_name WHERE id = %d", $name);
        // Get the results from the database.
        $id_middle = $wpdb->get_var($sql);
        if ($id_middle){
            // Define the table name with the correct WordPress prefix.
            $table_name = $wpdb->prefix . 'bookly_customer_appointments';

            // Prepare the SQL query.
            $sql = $wpdb->prepare("SELECT payment_id FROM $table_name WHERE appointment_id = %d", $id_middle);

            // Get the result from the database.
            $payment_id = $wpdb->get_var($sql);
          
     
        }

        

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($this->get_api_key());
        $payment = $mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => $amount
            ],
            "description" =>  strval($name),
            "redirectUrl" =>  $redirectUrl,
            "webhookUrl"  =>  $webhookUrl,
            "metadata" => [
                "order_id" => $name,
            ],
        ]);



        // Define the table name with the correct WordPress prefix.
        $table_name = $wpdb->prefix . 'bookly_payments';

        // Prepare the SQL query.
        $sql = $wpdb->prepare("UPDATE $table_name SET ref_id = %s WHERE id = %d", $payment->id, $payment_id);

            // Execute the query.
        $resulkt =  $wpdb->query($sql);

        return $payment;
        //header("Location: " . $payment->getCheckoutUrl(), true, 303);
        
    }
    public function create_ideal_payment($amount, $orderId){
        /*
        * How to prepare an iDEAL payment with the Mollie API.
        */

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($this->api_key );
            
            /*
            * First, let the customer pick the bank in a simple HTML form. This step is actually optional.
            */
            if ($_SERVER["REQUEST_METHOD"] != "POST") {
                ob_start();
                $method = $mollie->methods->get(\Mollie\Api\Types\PaymentMethod::IDEAL, ["include" => "issuers"]);

                echo '<form method="post">Select your bank: <select name="issuer">';

                foreach ($method->issuers() as $issuer) {
                    echo '<option value=' . htmlspecialchars($issuer->id) . '>' . htmlspecialchars($issuer->name) . '</option>';
                }

                echo '<option value="">or select later</option>';
                echo '</select><button>OK</button></form>';
                $out = ob_get_contents();
                ob_end_clean();
                return $out;
               
            }

            /*
            * Generate a unique order id for this example. It is important to include this unique attribute
            * in the redirectUrl (below) so a proper return page can be shown to the customer.
            */
            $orderId = time();

            /*
            * Determine the url parts to these example files.
            */
            $protocol = isset($_SERVER['HTTPS']) && strcasecmp('off', $_SERVER['HTTPS']) !== 0 ? "https" : "http";
            $hostname = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF']);

            /*
            * Payment parameters:
            *   amount        Amount in EUROs. This example creates a € 27.50 payment.
            *   method        Payment method "ideal".
            *   description   Description of the payment.
            *   redirectUrl   Redirect location. The customer will be redirected there after the payment.
            *   webhookUrl    Webhook location, used to report when the payment changes state.
            *   metadata      Custom metadata that is stored with the payment.
            *   issuer        The customer's bank. If empty the customer can select it later.
            */
            $payment = $mollie->payments->create([
                "amount" => [
                    "currency" => "EUR",
                    "value" => $amount, // You must send the correct number of decimals, thus we enforce the use of strings
                ],
                "method" => \Mollie\Api\Types\PaymentMethod::IDEAL,
                "description" => "Order #{$orderId}",
                "redirectUrl" => "{$protocol}://{$hostname}{$path}/return.php?order_id={$orderId}",
                "webhookUrl" => "{$protocol}://{$hostname}{$path}/webhook.php",
                "metadata" => [
                    "order_id" => $orderId,
                ],
                "issuer" => ! empty($_POST["issuer"]) ? $_POST["issuer"] : null,
            ]);

            /*
            * In this example we store the order with its payment status in a database.
            */
            //database_write($orderId, $payment->status);

            /*
            * Send the customer off to complete the payment.
            * This request should always be a GET, thus we enforce 303 http response code
            */
            header("Location: " . $payment->getCheckoutUrl(), true, 303);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            echo "API call failed: " . htmlspecialchars($e->getMessage());
        }
    }

    public function mollie_get_payment_link($price ,$url, $payment_id, $webhookUrl, $expire = false){
        global $wpdb;
        $formattedValue = number_format($price, 2);
        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($this->get_api_key() );
        $paymentLink = $mollie->paymentLinks->create([
        "amount" => [
            "currency" => "EUR",
            "value" => strval($formattedValue),
        ],
        "description" => "Bodyunlimited",
        "expiresAt" => $expire,
        "redirectUrl" => $url,
        "webhookUrl" => $webhookUrl,
        ]);


        // Define the table name with the correct WordPress prefix.
        $table_name = $wpdb->prefix . 'bookly_payments';

        // Prepare the SQL query.
        $sql = $wpdb->prepare("UPDATE $table_name SET ref_id = %s WHERE id = %d", $paymentLink->id, $this->get_payment_by_custom_id( $payment_id));

        // Execute the query.
        $wpdb->query($sql);
    
     
        return $paymentLink->getCheckoutUrl();
    }

    public function get_row_by_appointment_id($id){
        global $wpdb;

        // Define the table name
        $table_name = $wpdb->prefix . 'bookly_customer_appointments';

        // Define the appointment_id you're looking for
        $appointment_id = $id;

        // Prepare and execute the SQL query
        $query = $wpdb->prepare("SELECT payment_id FROM $table_name WHERE appointment_id = %d", $appointment_id);
        $result = $wpdb->get_var($query);

        // Check if a payment_id was found
        if ($result !== null) {
           return $result;
        } else {
            return false;
        }
    }

    public function get_middle_row($id){
        global $wpdb;
        // Define the table name with the correct WordPress prefix.
        $table_name = $wpdb->prefix . 'bookly_sw_custom';
        // Prepare the SQL query.
        $sql = $wpdb->prepare("SELECT id_middle FROM $table_name WHERE id = %d", $id);
        // Get the results from the database.
        $id_middle = $wpdb->get_var($sql);
        return $id_middle;
    }

    public function get_payment_by_custom_id($id){
        $custom_id = $this->get_middle_row($id);
        if ($custom_id){
            return $this->get_row_by_appointment_id($custom_id);
        }
        return false;
    }

    public function change_payment_status($ref_id){
        global $wpdb; // This assumes you are using WordPress's database access object $wpdb

        // Define your ref_id and the new status value
        $ref_id = $ref_id;
        $new_status = 'completed';

        // Update the payments table
        $table_name = $wpdb->prefix . 'bookly_payments';

        // Define the SQL query
        $sql = $wpdb->prepare(
            "UPDATE $table_name SET status = %s WHERE ref_id = %s",
            $new_status,
            $ref_id
        );

        // Execute the query
        $wpdb->query($sql);

        // Check if the update was successful
        if ($wpdb->last_error) {
            // Handle the error, if any
            error_log('error updating payment: ' . $wpdb->last_error);
            echo "Error updating payment status: " . $wpdb->last_error;
        } else {
            error_log('payment updated: ' . $ref_id);
            // Update was successful
            echo "Payment status updated to 'completed' for ref_id: $ref_id";
        }
    }
}


$your_ip = $_SERVER['REMOTE_ADDR'];
$desired_ip = '92.68.7.38';
// Compare your IP address with the desired IP address
if ($your_ip === $desired_ip) {
    // // Code to be executed if the IP addresses match
    // echo (new SWBooklyMollie())->get_payment_by_custom_id(1280); 
    // wp_die();
} 
