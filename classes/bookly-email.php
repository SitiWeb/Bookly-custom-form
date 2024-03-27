<?php
class sw_bookly_email{
    public $admin_email;

    public function __construct(){
        $this->admin_email = 'zaandam@bodyunlimited.nl';
    }

    public function todays_appointments(){
        $days_start = strtotime('+2 days');
        $days_end = strtotime('+3 days');
        $boeking = new bookly_sw_custom();
        $afspraken = $boeking->get_appointments(['filter' => ['startdate' => date('Y-m-d H:i:s', $days_start), 'enddate' => date('Y-m-d H:i:s', $days_end),
																 'order' => 'ASC',
																  'orderby' => 'start_date',
															   ]]);

        return($afspraken);
    }

    public function prepare_data()
    {
        $appointments = $this->todays_appointments();
        global $wpdb;
        $table_name_customer  = $wpdb->prefix . 'bookly_customers';
        $action_array = [];
        
        foreach ($appointments as $appointment) {
            $emailadres = '';
            if (isset($appointment['customer_appointment'])){
                $customer_appointment = $appointment['customer_appointment'];
          
                if (isset($customer_appointment['customer_id'])){
                    $customer_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_customer WHERE id = %d", $customer_appointment['customer_id']), ARRAY_A);
                    if ($customer_data){
                        $appointment['customer_data'] = $customer_data;
                        if (isset($customer_data['email'])){
                            $emailadres = $customer_data['email'];
                        }
                       
                    }
                }
            }
            

           // var_dump($emailadres);
            
            if (!empty($emailadres)) {
                if (!isset($action_array[$emailadres])) {
                    
                    $action_array[$emailadres] = [];
                }

                $action_array[$emailadres][] = $appointment;
            }

            print_pre($action_array);
        }


        // Debugging: Print the final action array
        return $action_array;
        //print_pre($action_array);
    }

    public function send_email($customer_email, $data2){
        // Prepare email content in Dutch
        $data_appointments = [];
        foreach ($data2 as $appoint){
        
            if ($appoint['service_id']['category_id'] != 11){
                // Convert string to DateTime objects
                $startDateTime = new DateTime($appoint['start_date']);
                $endDateTime = new DateTime($appoint['end_date']);
                $data_appointments[] = [ 
                    'naam' => $appoint['customer_data']['first_name'],
                    'title' => $appoint['service_id']['title'],
                    'start_time' => $startDateTime->format('H:i'),
                    'end_time' => $endDateTime->format('H:i'),
                    'date' => $endDateTime->format('d-m'),
                ];
            }
           
        }

       if ($data_appointments){

            $subject = "Herinnering: Uw afspraak staat gepland";
    
                $message = "Beste ".$data_appointments[0]['naam'].",<br><br>Dit is een herinnering voor uw afspraak op ".$data_appointments[0]['date']." bij BodyUnlimited";
                $message .= "<ul>";
                foreach ($data_appointments as $data) {
                    $message .= "<li>" . $data['title'] . " op " . $data['date']. " om " . $data['start_time']. " uur</li>";
                }
                $message .= "</ul>";
        }

        // Send email using global wp_mail function
        global $wp_mail;

        $to = $customer_email;

        $message_html = "
        <html>
        <head>
        <title>Herrineringsemail</title>
        </head>
        <body>
        ".$message."
        <p>
        Met vriendelijke groet,<br>
        Stephanie van Body Unlimited Zaandam</p>
        <p>Paltrokstraat 16<br>1508 EK Zaandam
        </p>
        </body>
        </html>
        ";

        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        $headers .= 'From: <zaandam@bodyunlimited.nl>' . "\r\n";
        $sent = false;
        // echo $message_html;
        $sent = mail( 'roberto@sitiweb.nl',$subject,$message_html,$headers);
        $sent = mail('zaandam@bodyunlimited.nl',$subject,$message_html,$headers);
        if ($sent) {
            // Email sent successfully
            echo "Reminder email sent to " . $to . "<br>";
        } else {
            // Email sending failed
            echo "Failed to send reminder email to " . $to . "<br>";
        }
    }


    public function do_send_email($customer_email, $message, $subject){
        // Send email using global wp_mail function
         global $wp_mail;

        $to = $customer_email;

        $message_html = "
        <html>
        <head>
        <title>".$subject."</title>
        </head>
        <body>
        ".$message."
        </body>
        </html>
        ";

        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        $headers .= 'From: <zaandam@bodyunlimited.nl>' . "\r\n";

        $sent = mail($to,$subject,$message_html,$headers);

        if ($sent) {
            // Email sent successfully
            return True;
        } else {
            // Email sending failed
            return False;
        }
    }

    public function send_payment_confirmation_email($appointment ,$order_status,$customer, $appointment_data,$service_data){
        $customer_email = $customer['email'];
        $appointment_id = $appointment_data['appointment_id'];


        if(!empty($appointment['start_date'])){
            $appointment_date = date('Y-m-d', strtotime($appointment['start_date'])); // Grabs the date
            $appointment_time = date('H:i', strtotime($appointment['start_date'])); // Grabs the time
        }
        $afspraak = [
            'Dienst' => $service_data['title'],
            'Datum' => $appointment_date,
            'Tijd' => $appointment_time,
            'Tijdsduur' => $this->secondsToTime($service_data['duration']),
        ];
        $klantinfo = [
            'First name' => $customer['first_name'],
            'Last name' => $customer['last_name'],
            'Telefoonnummer' => $customer['phone'],
            'Emailadress' => $customer['email'],
        ];

        switch ($order_status) {
            case "completed":
                $order_status_set = "Afgerond";
                break;
            case "pending":
                $order_status_set = "Wachtend op betaling";
                break;  
            case "rejected":
                $order_status_set = "Geannuleerd";
                break;
            case "refunded":
                $order_status_set = "Geannuleerd";
                break;    
            default:
                $order_status_set = "Onbekend";
                break;
        }

        if(!empty($order_status_set)){
            $subject = "Order bevestiging: je betaling is ".$order_status_set;
            $message = "Beste ".$customer['first_name'].",<br><br>Dit is een bericht voor uw afspraak op ".$appointment_date." om ".$appointment_time." bij BodyUnlimited.";
            $message .= "<br>";
            $message .= "De status van je afspraak is op dit moment <b>".$order_status_set."</b>.";
        }
        
        $tables = '';
        if(!empty($klantinfo)){
            $klantinfohtml = '<table border="1">';
            foreach($klantinfo as $key => $value){
                $klantinfohtml .= '<tr><td>'.$key.'</td>';
                $klantinfohtml .= '<td>'.$value.'</td></tr>';
            }
            $klantinfohtml .= '</table>';
            $tables .= $klantinfohtml;

        }
        if(!empty($afspraak)){
            $afspraakhtml = '<table border="1">';

            foreach($afspraak as $key => $value){
                $afspraakhtml .= '<tr><td>'.$key.'</td>';
                $afspraakhtml .= '<td>'.$value.'</td></tr>';
            }
            $afspraakhtml .= '</table>';
            // $tables .= $afspraakhtml;
        }
        // Send email using global wp_mail function
        global $wp_mail;

        $to = $customer_email;

        $message_html = "
        <html>
            <head>
                <title></title>
            </head>
            <body>
            ".$message."
            
                <p>
                Met vriendelijke groet,<br>
                Stephanie van Body Unlimited Zaandam</p>
                <p>Paltrokstraat 16<br>1508 EK Zaandam
                </p>
            </body>
        </html>";

        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        $headers .= 'From: <zaandam@bodyunlimited.nl>' . "\r\n";

        $sent = mail($to,$subject,$message_html,$headers);
        if ($sent) {
            // Email sent successfully
            echo "Reminder email sent to " . $to . "<br>";
        } else {
            // Email sending failed
            echo "Failed to send reminder email to " . $to . "<br>";
        }
        $admin_subject = "Order bevestiging: je betaling is wachtend op betaling";
        $admin_message = "Beste beheerder,<br><br> Dit is een bericht over de betaalstatus van afspraak NR." .$appointment_id." De status van deze afspraak is: <b>".$order_status_set."</b>.";
        $admin_message_html = "
        <html>
            <head>
                <title>Herrineringsemail</title>
            </head>
            <body>".$admin_message."
                <div style='display: flex;'>".$tables."</div>
                <p>
                Met vriendelijke groet,<br>
                Stephanie van Body Unlimited Zaandam</p>
                <p>Paltrokstraat 16<br>1508 EK Zaandam
                </p>
            </body>
        </html>";
        $admin_headers = "MIME-Version: 1.0" . "\r\n";
        $admin_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

        // More headers
        $admin_headers .= 'From: <zaandam@bodyunlimited.nl>' . "\r\n";
        $sent_admin = mail($this->admin_email,$admin_subject,$admin_message_html,$admin_headers);
        if ($sent_admin) {
            // Email sent successfully
            echo "Reminder email sent to admin<br>";
        } else {
            // Email sending failed
            echo "Failed to send reminder email to admin<br>";
        }
    }
    private function secondsToTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
    
        $result = '';
    
        if ($hours > 0) {
            
            if ($hours > 1) {
                $result .= $hours . ' uren';
            }else{
                $result .= $hours . ' uur';
            }
            $result .= ' ';
        }
    
        if ($minutes > 0) {
            
            if ($minutes > 1) {
                $result .= $minutes . ' minuten';
            }else{
                $result .= $minutes . ' minuut';
            }
        }
    
        return $result;
    }
    

    public function do_send_status_email($appointment, $status){
        // Send email using global wp_mail function
        global $wp_mail, $wpdb;
        $customer_id = $appointment['customer_id'];
        $appointment = $appointment['appointment_id'];
        get_appointment_by_id($appointment);
        //(new sw_bookly_email)->do_send_status_email($_POST['email'], 'Bevestiging afspraak', 'Bevestiging afspraak');


    }

    public function send_notification($result , $payment_url = ''){
		$mail = new Bookly_custom_email();
        $info = json_decode($result->info, 1);
		if (!isset($info['notification']) ){
			return;
		}
		if (($info['notification'])  != 1){
			return;
		}
        $mail->set_data($result);
        $notification = $mail->get_admin_notification($result);
        $mail->set_recipient('zaandam@bodyunlimited.nl');
        $mail->set_payment_url($payment_url);
        $mail->set_subject( $notification['subject'] );
        $mail->set_content( $notification['message'] );
        $mail->send_email();
      
        $notification = $mail->get_notification($result, false, $payment_url);
        $mail->set_recipient($result->customer['email']);
        $mail->set_payment_url($payment_url);
        $mail->set_subject( $notification['subject'] );
        $mail->set_content( $notification['message'] );
        $mail->send_email();
    }

  



    public function new_send_confirm_email(){
        $mail = new Bookly_custom_email();
        $mail->set_recipient('test@test.nl');
        $mail->set_subject('test');
        $mail->set_content('testing');
        $mail->send_email();
        $mail->set_recipient('roberto@sitiweb.nl');
        $mail->send_email();
    }



    public function send_payment_notification($result, $payment_url_redirect){
        $mail = new Bookly_custom_email();
        $info = json_decode($result->info, 1);
		if (!isset($info['notification']) ){
			return;
		}
		if (($info['notification'])  != 1){
			return;
		}
        $mail->set_data($result);
        $mail->set_payment_url($payment_url_redirect);

        //admin notification
        $notification = $mail->get_admin_notification($result);
        $mail->set_recipient($this->admin_email);
        $mail->set_subject( $notification['subject'] );
        $mail->set_content( $notification['message'] );
        $mail->send_email();
        
        //User notification
        $notification = $mail->get_notification($result, false, $payment_url_redirect);
        $mail->set_recipient( $result->customer['email'] );
        $mail->set_subject( $notification['subject'] );
        $mail->set_content( $notification['message'] );
        $mail->send_email();
    }

    public function update_payment_info($emailadress, $order_status, $customer, $appointment,$service_data){
        $mail = new Bookly_custom_email();
        $info = json_decode($service_data->info, 1);

        if(!empty($appointment['start_date'])){
            $appointment_date = date('Y-m-d', strtotime($appointment['start_date'])); // Grabs the date
            $appointment_time = date('H:i', strtotime($appointment['start_date'])); // Grabs the time
        }
        $afspraak = [
            'Dienst' => $service_data['title'],
            'Datum' => $appointment_date,
            'Tijd' => $appointment_time,
            'Tijdsduur' => $this->secondsToTime($service_data['duration']),
        ];
        $klantinfo = [
            'First name' => $customer['first_name'],
            'Last name' => $customer['last_name'],
            'Telefoonnummer' => $customer['phone'],
            'Emailadress' => $customer['email'],
        ];

        switch ($order_status) {
            case "completed":
                $order_status_set = "Afgerond";
                break;
            case "pending":
                $order_status_set = "Wachtend op betaling";
                break;  
            case "rejected":
                $order_status_set = "Geannuleerd";
                break;
            case "refunded":
                $order_status_set = "Geannuleerd";
                break;    
            default:
                $order_status_set = "Onbekend";
                break;
        }

        if(!empty($order_status_set)){
            $subject = "Order bevestiging: je betaling is ".$order_status_set;
            $message = "Beste ".$customer['first_name'].",<br><br>Dit is een bericht voor uw afspraak op ".$appointment_date." om ".$appointment_time." bij BodyUnlimited.";
            $message .= "<br>";
            $message .= "De status van je afspraak is op dit moment <b>".$order_status_set."</b>.";
        }
        
        $tables = '';
        if(!empty($klantinfo)){
            $klantinfohtml = '<table border="1">';
            foreach($klantinfo as $key => $value){
                $klantinfohtml .= '<tr><td>'.$key.'</td>';
                $klantinfohtml .= '<td>'.$value.'</td></tr>';
            }
            $klantinfohtml .= '</table>';
            $tables .= $klantinfohtml;

        }
        if(!empty($afspraak)){
            $afspraakhtml = '<table border="1">';

            foreach($afspraak as $key => $value){
                $afspraakhtml .= '<tr><td>'.$key.'</td>';
                $afspraakhtml .= '<td>'.$value.'</td></tr>';
            }
            $afspraakhtml .= '</table>';
            // $tables .= $afspraakhtml;
        }
        // Send email using global wp_mail function
        global $wp_mail;

        $to = $emailadress;

        $message_html = "
        <html>
            <head>
                <title></title>
            </head>
            <body>
            ".$message."
            
                <p>
                Met vriendelijke groet,<br>
                Stephanie van Body Unlimited Zaandam</p>
                <p>Paltrokstraat 16<br>1508 EK Zaandam
                </p>
            </body>
        </html>";

        error_log( $message_html );

        $mail->set_recipient( $to );
        $mail->set_subject( 'Updated afspraak' );
        
        $mail->set_content( $message_html );
        $mail->send_email();
        
        // //User notification
        // $notification = $mail->get_notification($service_data, false);
        // $mail->set_recipient( $emailadress );
        // $mail->set_subject( $notification['subject'] );
        // $mail->set_content( $notification['message'] );
        // $mail->send_email();
    }
}

