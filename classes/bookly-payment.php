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
        $this->payment = $mollie->payments->get($id);
        return $this->payment;
    } 

    
}