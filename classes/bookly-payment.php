<?php

class SW_Bookly_Payment{

    private $payment_id;
    public $payment;

    public function __construct($payment_id)
    {
        $this->payment_id = $payment_id;
    }

    public function get_id(){
        return $this->payment_id;
    }

    public function get_mollie_data(){
        require_once ABSPATH . 'wp-content/plugins/bookly-responsive-appointment-booking-tool/autoload.php';
        $mollie = new \Mollie\Api\MollieApiClient();
        
        // Create an instance of SWBooklyMollie to get the API key
        $bookly_mollie = new SWBooklyMollie();

        // Check if API type is "test" or "live" and set the API key accordingly
        if ($bookly_mollie->api_type === 'test') {
            $mollie->setApiKey($bookly_mollie->test_api_key);
        } else {
            $mollie->setApiKey($bookly_mollie->api_key);
        }
        /*
         * Retrieve the payment's current state.
         */
        $this->payment = $mollie->paymentLinks->get($this->payment_id);
        print_pre($this->payment);
        return $this->payment;
    } 

    public function get_payment_link(){
        if (!isset($this->payment)){
            $this->get_mollie_data();
        }
        if (isset($this->payment->_links) && isset($this->payment->_links->paymentLink->href)){
            return $this->payment->_links->paymentLink->href;
        }
        return false;
    }

    public function isPaid(){
        if (!isset($this->payment)){
            $this->get_mollie_data();
        }
        return $this->payment->isPaid();
    }

    public function isExpired(){
        if (!isset($this->payment)){
            $this->get_mollie_data();
        }
    }

    public function get_amount(){
        if (!isset($this->payment)){
            $this->get_mollie_data();
        }
        if (isset($this->payment->amount)){
            return $this->payment->amount->value;
        }
        return false;
    }

    public function get_expire(){
        if (!isset($this->payment)){
            $this->get_mollie_data();
        }
        if (isset($this->payment->expiresAt)){
            return $this->payment->expiresAt;
        }
        return false;
    }

    public function get_appointment_data(){
        global $wpdb;
        $table_name_payments = $wpdb->prefix . 'bookly_payments';
        $updated_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_payments WHERE ref_id = %s", $this->payment_id), ARRAY_A);
        $data = [];
        if ($updated_row) {
            // You can access the updated row's data here.
            $payment_id = $updated_row['id'];
            $table_name = $wpdb->prefix . 'bookly_customer_appointments';
            $table_name_appointment = $wpdb->prefix . 'bookly_appointments';
            $table_name_customer  = $wpdb->prefix . 'bookly_customers';
            $table_name_service  = $wpdb->prefix . 'bookly_services';
            // Now, perform a second query using $payment_id.
            
            // Now, perform a second query using $payment_id.
            $data['appointment'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE payment_id = %d", $payment_id), ARRAY_A);
    
            $data['customer_appointment'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_appointment WHERE id = %d", $appointment['appointment_id']), ARRAY_A);
       
            $data['customer'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_customer WHERE id = %d", $appointment['customer_id']), ARRAY_A);
            
            $data['service'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_service WHERE id = %d", $b_appointment['service_id']), ARRAY_A);
        }
        return $data;
    }

    
}