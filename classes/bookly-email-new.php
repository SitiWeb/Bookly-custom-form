<?php

class Bookly_custom_email{

    public $recipient;
    public $subject;
    public $content;
    public $type;
    public $data;
    public $payment_url;

    public function __construct(){

    }

    public function get_content(){
        return $this->prepare_notification($this->content);
    }

    public function set_content($value){
        $this->content = $value;
    }
    

    public function get_recipient(){
        return $this->recipient;
    }

    public function set_recipient($value){
        $this->recipient = $value;
    }

    public function get_payment_url(){
        return $this->payment_url;
    }

    public function set_payment_url($value){
        $this->payment_url = $value;
    }

    public function get_subject(){
        return $this->subject;
    }

    public function set_subject($value){
        $this->subject = $value;
    }
    

    public function get_type(){
        return $this->type;
    }

    public function set_type($value){
        $this->type = $value;
    }

    public function send_email(){
        if (!$this->recipient){
            echo 'No recipient set';
            return false;
        }
        if (!isset($this->subject)){
            echo 'No subject set';
            return false;
        }
        if (!$this->content){
            echo 'No content set';
            return false;
        }

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        $headers .= 'From: <zaandam@bodyunlimited.nl>' . "\r\n";
        return wp_mail($this->recipient, $this->subject, $this->content, $headers);
    }

    public function get_data(){
        return $this->data;
    }

    public function set_data($data){
        $this->data = $data;
    }

    public function prepare_notification($string, $payment_url = ''){

        $starttijd = false;
        $eindtijd = false;

        if (!$this->data){
       
            return $string;
        }
        if (!$starttijd && isset($this->data->voor)){
            if (isset($this->data->voor['starttime'])){
                $starttijd = $this->data->voor['starttime'];
            }
        }

        if (!$starttijd && isset($this->data->midden)){
            if (isset($this->data->midden['starttime'])){
                $starttijd = $this->data->midden['starttime'];
            }
        }

        if (!$eindtijd && isset($this->data->na)){
            if (isset($this->data->na['endtime'])){
                $eindtijd = $this->data->na['endtime'];
            }
        }

        if (!$eindtijd && isset($this->data->midden)){
            if (isset($this->data->midden['endtime'])){
                $eindtijd = $this->data->midden['endtime'];
            }
        }

        $payment_url_content = '';
        if (isset($this->payment_url) && $this->payment_url ){
  
            $payment_url_content = '<a href="'.$this->payment_url.'">Klik hier om te betalen</a>';
        }

        $replacements = array(
            "{client_name}" => $this->data->customer['first_name'] . ' ' . $this->data->customer['last_name'],
            "{client_first_name}" => $this->data->customer['first_name'],
            "{client_last_name}" => $this->data->customer['last_name'],
            "{client_email}" => $this->data->customer['email'],
            "{client_phone}" => $this->data->customer['phone'],
            "{service_name}" => $this->data->title,
            "{appointment_date}" => $this->data->selected_date,
            "{appointment_time}" => '<strong>'.$starttijd . '</strong> - ' . $eindtijd,
            "{online_meeting_join_url}" => "",
            "{company_name}" => "Bodyunlimited",
            "{company_address}" => "Paltrokstraat 16<br> 1508 EK Zaandam",
            "{company_phone}" => "075 – 750 3296",
            "{company_website}" => "<a href='https://bodyunlimited.nl'>bodyunlimited.nl</a>",
            "{company_logo}" => "",
            "{payment_url}" => $payment_url_content,
        );
        
        $replaced_content = str_replace(array_keys($replacements), array_values($replacements), $string);
        
        return $replaced_content;
    }

    public function get_notification_from_db($data, $admin){
     
        global $wpdb;

        $search_value = '"'.$data->id.'"'; // Serialized representation of "14"
        if (!$admin){

            
            $query = $wpdb->prepare("
                SELECT * 
                FROM {$wpdb->prefix}bookly_notifications 
                WHERE settings LIKE %s AND type = 'new_booking'
            ", '%' . $search_value . '%');

            

            $results = $wpdb->get_row($query, 'ARRAY_A');

            if (!$results){
                $query = $wpdb->prepare("
                SELECT * 
                FROM {$wpdb->prefix}bookly_notifications 
                WHERE id = 7");
                $results = $wpdb->get_row($query, 'ARRAY_A');
            }
        }
        else{
            $query = $wpdb->prepare("
                SELECT * 
                FROM {$wpdb->prefix}bookly_notifications 
                WHERE id = 8");
                $results = $wpdb->get_row($query, 'ARRAY_A');
        }
        return($results);

       
    }

    public function get_notification($data, $admin = false, $payment_url = false)
    {
        $notification = $this->get_notification_from_db($data, $admin);
        if ($notification){      
            if (true){
                $message = nl2br($notification['message']);
                $subject = $notification['subject'];

                $message = $this->prepare_notification($message, $data, $payment_url);
                $subject = $this->prepare_notification($subject, $data, $payment_url);
            }
            $result = [
                'notification' => $notification,
                'message' => $message,
                'subject' => $subject,
            ];
   
            return $result;
        }

        else{
            return false;
        }
        return false;
    }

    public function get_admin_notification($data)
    {
        $notification = $this->get_notification_from_db($data,1);
        if ($notification){      
            if (true){
                $message = nl2br($notification['message']);
                $subject = $notification['subject'];

                $message = $this->prepare_notification($message, $data);
                $subject = $this->prepare_notification($subject, $data);
            }
            $result = [
                'notification' => $notification,
                'message' => $message,
                'subject' => $subject,
            ];
   
            return $result;
        }

        else{
            return false;
        }
        return false;
    }

  
}

//add_action('wp_head','test_function_sw');
function test_function_sw(){
    $mail = new Bookly_custom_email();
    $mail->set_recipient('roberto@sitiweb.nl');
    $mail->set_subject('test');
    $mail->set_content('test content');
    $mail->send_email(); 
}
