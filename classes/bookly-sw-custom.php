<?php
// require_once('../../../../wp-load.php');
class bookly_sw_custom
{
    protected $plugin_name;

    protected $appointment_slug;

    protected $staff_slug;

    protected $service_slug;

    protected $customer_slug;

    protected $appointment_table;

    protected $staff_table;

    protected $service_table;

    protected $category_table;

    protected $customer_table;

    protected $payments_table;

    protected $holidays_table;

    protected $staff_services_table;

    protected $staff_schedule_items;

    protected $schedule_item_breaks;

    protected $customer_appointments_table;
    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    		The version of this plugin.
     * @param      string    api_namespace    	The namespace url for rest api.
     * @param      string    restbase    		The baseurl slug for rest api.	
     * @param      string    listrestbase    	The list baseurl slug for rest api.
     * @since      1.0.0
     */
    public function __construct()
    {
        global $wpdb;



        $tableprefix = $wpdb->prefix . "bookly_";


        $this->plugin_name                     = 'sw-custom-form';

        $this->timeslots                    = 'timeslots';
        $this->staff_slug                      = 'staff';
        $this->service_slug                  = 'services';
        $this->customer_slug                  = 'customers';
        $this->appointment_slug             = 'appointments';

        $this->staff_table                     = $tableprefix . 'staff';
        $this->service_table                 = $tableprefix . 'services';
        $this->category_table                 = $tableprefix . 'categories';
        $this->customer_table                 = $tableprefix . 'customers';
        $this->payments_table                = $tableprefix . 'payments';
        $this->holidays_table                = $tableprefix . 'holidays';
        $this->appointment_table             = $tableprefix . 'appointments';
        $this->staff_services_table            = $tableprefix . 'staff_services';
        $this->customer_groups_table        = $tableprefix . 'customer_groups';
        $this->staff_schedule_items            = $tableprefix . 'staff_schedule_items';
        $this->schedule_item_breaks            = $tableprefix . 'schedule_item_breaks';
        $this->customer_appointments_table    = $tableprefix . 'customer_appointments';
    }

    public function get_appointments($params)
    {

        global $wpdb;

        /* possible params are: 
         * filter[staff,customer,appointments,month,startdata,enddate,order,orderby,limit,offset]
         * filtercf
         * */

        //$params = $request->get_params();
        $condition = $offset = '';
        $order = "ORDER BY $this->appointment_table.`id` DESC";
        $limit = '1000';
        $filtercf_arr = array();
        if (isset($params['filter']) && is_array($params['filter']) && count($params['filter']) > 0) {
            $filter_arr = $params['filter'];
            if (isset($filter_arr['staff']) && !empty($filter_arr['staff'])) {
                $condition = "LEFT JOIN $this->staff_table ON $this->appointment_table.staff_id = $this->staff_table.id WHERE $this->staff_table.email LIKE '" . sanitize_email($filter_arr['staff']) . "' ";
            }

            if (isset($filter_arr['customer']) && !empty($filter_arr['customer'])) {
                $condition = "WHERE id IN(SELECT appointment_id FROM $this->customer_appointments_table LEFT JOIN $this->customer_table ON $this->customer_appointments_table.customer_id = $this->customer_table.id WHERE $this->customer_table.email LIKE '" . sanitize_email($filter_arr['customer']) . "' )";
            }

            if (isset($filter_arr['appointments']) && !empty($filter_arr['appointments'])) {
                if ($filter_arr['appointments'] == 'today') {
                    $currentday = date('Y-m-d 00:00:00');
                    $nextday = date('Y-m-d 23:59:00');
                    $condition = "WHERE `start_date` BETWEEN '" . $currentday . "' AND '" . $nextday . "' ";
                }
            }

            if (isset($filter_arr['month']) && !empty($filter_arr['month'])) {
                $start_date = date('Y-' . $filter_arr["month"] . '-01 00:00:00');
                $lastday = cal_days_in_month(CAL_GREGORIAN, $filter_arr['month'], date('Y'));;
                $end_date = date('Y-' . $filter_arr["month"] . "-$lastday 23:59:00");
                $condition = "WHERE `start_date` BETWEEN '" . $start_date . "' AND '" . $end_date . "' ";
            }

            if (isset($filter_arr['startdate']) && !empty($filter_arr['startdate']) && isset($filter_arr['enddate']) && !empty($filter_arr['enddate'])) {
                $start_date = $filter_arr["startdate"] . ' 00:00:00';
                $end_date = $filter_arr["enddate"] . ' 23:59:00';
                $condition = "WHERE `start_date` BETWEEN '" . $start_date . "' AND '" . $end_date . "' ";
            }

            if (isset($filter_arr['order']) && !empty($filter_arr['order']) && isset($filter_arr['orderby']) && !empty($filter_arr['orderby'])) {
                $order = "ORDER BY  $this->appointment_table." . $filter_arr['orderby'] . " " . $filter_arr['order'];
            }

            if (isset($filter_arr['limit']) && !empty($filter_arr['limit'])) {
                $limit = $filter_arr['limit'];
            }

            if (isset($filter_arr['offset']) && !empty($filter_arr['offset'])) {
                $offset = 'OFFSET ' . intval($limit) * intval($filter_arr['offset']);
            }
        }

        if (isset($params['filtercf']) && is_array($params['filtercf'])) {
            foreach ($params['filtercf'] as $filterconditions) {
                $filtercf_arr[$filterconditions['key']] = array('key' => $filterconditions['key'], 'value' => $filterconditions['value'], 'operator' => $filterconditions['operator']);
            }
        }


        $sql = "SELECT *, $this->appointment_table.id as app_id FROM $this->appointment_table $condition $order LIMIT $limit $offset";

        $result = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($result) && count($result) > 0) {
            $lists = array();
            $customfieldslabels = get_option('bookly_custom_fields_data');
            foreach ($result as $list_key => $list_value) {
                $list_value['id'] = $list_value['app_id'];
                if (isset($list_value['staff_id']) && !empty($list_value['staff_id'])) {
                    $staffsql = "SELECT id, wp_user_id, attachment_id, full_name, email, phone, info FROM $this->staff_table where id = " . $list_value['staff_id'];
                    $staffresult = $wpdb->get_row($staffsql, ARRAY_A);
                    $list_value['staff_id'] = $staffresult;
                }

                if (isset($list_value['service_id']) && !empty($list_value['service_id'])) {
                    $servicesql = "SELECT id, category_id, title, duration, price FROM $this->service_table where id = " . $list_value['service_id'];
                    $serviceresult = $wpdb->get_row($servicesql, ARRAY_A);
                    $list_value['service_id'] = $serviceresult;
                }

                $customer_sql = "SELECT customer_id, number_of_persons, payment_id, units, status, notes, custom_fields FROM $this->customer_appointments_table where appointment_id =" . $list_value['id'];
                $customer_data = $wpdb->get_row($customer_sql, ARRAY_A);
               
                if (is_array($customer_data) && !empty($customer_data)) {

                    
                    if (!empty($customer_data['customer_id'])) {

                        $user = $this->get_customers(['id' => $customer_data['customer_id']]);
                        if (is_array($user) && count($user) == 1) {
                            $customer_data['all_data'] = $user[0];
                        }
                    }
                    $customfields = json_decode($customer_data['custom_fields']);
                    if (is_array($customfields) && count($customfields) > 0) {
                        if (!empty($customfieldslabels)) {
                            $customfieldslabels = json_decode($customfieldslabels);
                            if (is_array($customfieldslabels) && count($customfieldslabels) > 0) {
                                foreach ($customfieldslabels as $clables) {
                                    $customfarr[$clables->id] = $clables->label;
                                }
                            }
                            $customfields_data = array();

                            $ava_fields = array();
                            foreach ($customfields as $customdata) {
                                if (array_key_exists($customdata->id, $customfarr)) {
                                    $customfields_data[] = array('id' => $customdata->id, 'label' =>  $customfarr[$customdata->id], 'value' => $customdata->value);
                                    $ava_fields[] = $customfarr[$customdata->id];
                                } else {
                                    $customfields_data[] = array('id' => $customdata->id, 'value' => $customdata->id);
                                }

                                if (is_array($filtercf_arr) && count($filtercf_arr) > 0) {
                                    if (array_key_exists($customfarr[$customdata->id], $filtercf_arr)) {

                                        if ($filtercf_arr[$customfarr[$customdata->id]]['key'] == $customfarr[$customdata->id]) {
                                            if ($filtercf_arr[$customfarr[$customdata->id]]['operator'] == '=') {
                                                if ($filtercf_arr[$customfarr[$customdata->id]]['value'] == $customdata->value) {
                                                } else {
                                                    $unwanted_list_key[$list_key] =  $list_key;
                                                }
                                            } elseif ($filtercf_arr[$customfarr[$customdata->id]]['operator'] == '!=') {
                                                if ($filtercf_arr[$customfarr[$customdata->id]]['value'] != $customdata->value) {
                                                } else {
                                                    $unwanted_list_key[$list_key] =  $list_key;
                                                }
                                            } elseif ($filtercf_arr[$customfarr[$customdata->id]]['operator'] == '>') {
                                                if ($customdata->value > $filtercf_arr[$customfarr[$customdata->id]]['value']) {
                                                } else {
                                                    $unwanted_list_key[$list_key] =  $list_key;
                                                }
                                            } elseif ($filtercf_arr[$customfarr[$customdata->id]]['operator'] == '<') {
                                                if ($customdata->value < $filtercf_arr[$customfarr[$customdata->id]]['value']) {
                                                } else {
                                                    $unwanted_list_key[$list_key] =  $list_key;
                                                }
                                            } elseif ($filtercf_arr[$customfarr[$customdata->id]]['operator'] == 'like') {
                                                if (in_array($filtercf_arr[$customfarr[$customdata->id]]['value'], $customdata->value)) {
                                                } else {
                                                    $unwanted_list_key[$list_key] =  $list_key;
                                                }
                                            }
                                        }
                                    }
                                }
                            }


                            if (isset($ava_fields) && is_array($ava_fields) && count($ava_fields) > 0) {
                                foreach ($filtercf_arr as $cfkey => $cfvalues) {
                                    if (!in_array($cfkey, $ava_fields)) {
                                        $unwanted_list_key[$list_key] =  $list_key;
                                    }
                                }
                            }

                            $customer_data['custom_fields'] = $customfields_data;
                        }
                    } else {
                        /* $unwanted_list_key[$list_key] =  $list_key; */
                    }


                    $list_value['customer_appointment'] = $customer_data;

                    /* Added Payment details */
                    if (isset($customer_data['payment_id']) && $customer_data['payment_id'] > 0) {
                        $payment_sql = "SELECT `type`, `total`, `tax`, `paid`, `paid_type`, `gateway_price_correction`, `status`, `details`, `created_at`, `updated_at` FROM $this->payments_table where id =" . $customer_data['payment_id'];
                        $payment_data = $wpdb->get_row($payment_sql, ARRAY_A);
                        if (is_array($payment_data) && !empty($payment_data)) {

                            if (isset($payment_data['details'])) {
                                $payment_data['details'] = unserialize($payment_data['details']);
                            }

                            $list_value['payment_details'] = $payment_data;
                            $payment_data['details'] = '';
                        }
                    }
                }


                $lists[$list_key] = $list_value;
            }
        } else {
            $response = array(
                'message'   => 'No result found.',
                'status'        => 404
            );
            $lists = new WP_REST_Response($response);
        }

        if (isset($unwanted_list_key) && is_array($unwanted_list_key) && count($unwanted_list_key) > 0) {
            $lists = array_values(array_diff_key($lists, array_flip($unwanted_list_key)));
        }

        return $lists;
    }

    public function get_services()
    {
        global $wpdb;
        $sql = "SELECT * FROM $this->service_table ORDER BY position";
        $result = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($result) && count($result) > 0) {
            $lists = array();
            foreach ($result as $list_key => $list_value) {
                if (isset($list_value['category_id']) && !empty($list_value['category_id'])) {
                    $staffsql = "SELECT id, name FROM $this->category_table where id = " . $list_value['category_id'];
                    $staffresult = $wpdb->get_row($staffsql, ARRAY_A);
                    $list_value['category_id'] = $staffresult;
                }

                $lists[$list_key] = $list_value;
            }
        } else {
            $message = esc_html__('No result found.', 'wpo-bookly-api');
            $lists = new WP_REST_Response($message);
        }

        return $lists;
    }

    public function get_services_options($include_only_cat = [], $include_only_service = [])
    {

        $return = '';
        // De eerste optie is de 'standaardwaarde'. Deze word altijd toegevoegd maar is geen valide keuze om een afspraak mee in te plannen
        if (isset($id) && $id == '') {
            $return = '<option value="" disabled selected>Behandeling</option>';
        }

        // Haal alle mogelijke diensten op uit de API
        $services = $this->get_services();
        $new_services = [];

        foreach ($services as $service) {

            if (isset($include_only_cat) && $include_only_cat != [] && isset($service['category_id']['id']) && !in_array($service['category_id']['id'], $include_only_cat)) {
                print_pre($services);
                continue;
            }

            if (isset($include_only_service) && $include_only_service != [] && isset($service['id']) && !in_array($service['id'], $include_only_service)) {
                continue;
            }



            if ($service && isset($service['category_id'])) {
                $new_services[$service['category_id']['name']][] = $service;
            }
        }

        foreach ($new_services as $key => $group) {
            if ($key === 'placeholder' || empty($key)) {
                continue;
            }

            // Loop door alle diensten heen
            $first_in_group = false;
            foreach ($group as $service) {
                // Explode de naam van de dienst op een spatie, hiermee kan nagekeken worden of deze dienst een personeelslid is
                $name_prefix = explode(' ', $service['title']);
                if (!current_user_can('delete_posts')) {
                    if (isset($service["info"])) {
                        $json = json_decode($service["info"], 1);

                        if (!isset($json['guest']) || $json['guest'] != "1") {
                            continue;
                        }
                    }
                }


                if (!$first_in_group) {
                    $return .= '<optgroup label="' . $key . '">';
                    $first_in_group = true;
                }

                //Toon alleen de 'hoofd' dienst en niet de collaberatives, toon ook niet als de service een personeelslid is
                if ($service['type'] != 'collaberative' && $name_prefix[0] != 'Personeelslid') {
                    // Voeg de dienst toe aan de lijst van keuzes
                    if (isset($id) && $id == $service['id']) {
                        $return .= '<option selected value=' . $service['id'] . '>' . $service['title'] . '</option>';
                    } else {
                        $return .= '<option value=' . $service['id'] . '>' . $service['title'] . '</option>';
                    }
                }
            }
            $return .= '</optgroup >';
        }

        // Return de lijst met alle 'legale' keuzes van diensten
        return $return;
    }


    public function get_single_service($id)
    {
        global $wpdb;
        $serviceid     = $id;
        $sql = "SELECT * FROM $this->service_table where id = $serviceid";
        $result = $wpdb->get_row($sql, ARRAY_A);

        if (is_array($result) && count($result) > 0) {
            $lists = array();
            if (isset($result['category_id']) && !empty($result['category_id'])) {
                $staffsql = "SELECT id, name FROM $this->category_table where id = " . $result['category_id'];
                $staffresult = $wpdb->get_row($staffsql, ARRAY_A);
                $result['category_id'] = $staffresult;
            }

            $lists = $result;
        } else {
            $message = esc_html__('No result found.', 'wpo-bookly-api');
            $lists = new WP_REST_Response($message);
        }

        return $lists;
    }

    /*Get All Staff Records*/
    public function get_staffs($params = '')
    {
        global $wpdb;
        /**
         * Possible $params:
         * filter['filter_service_id']
         */

        if (isset($params['filter']) && is_array($params['filter']) && count($params['filter']) > 0) {
            $filter_arr = $params['filter'];

            if (isset($filter_arr['filter_service_id']) && !empty($filter_arr['filter_service_id'])) {
                $service_id =    $filter_arr['filter_service_id'];
            }
        }

        $sql = "SELECT * FROM $this->staff_table ";

        if (isset($service_id) && !empty($service_id)) {
            $sql = "SELECT * FROM $this->staff_table WHERE id IN (SELECT staff_id FROM $this->staff_services_table WHERE service_id = $service_id)";
        }

        $result = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($result) && count($result) > 0) {
            foreach ($result as $staffdata) {
                //$staffsdata[$staffdata['id']] = $staffdata;
                $servicesql =  "SELECT $this->service_table.id, $this->service_table.title
				FROM $this->service_table
				INNER JOIN $this->staff_services_table ON $this->service_table.id = $this->staff_services_table.service_id where staff_id = " . $staffdata['id'];

                $serviceresult = $wpdb->get_results($servicesql, ARRAY_A);

                if (is_array($serviceresult) && count($serviceresult) > 0) {
                    $staffsdata[$staffdata['id']]['services'] = $serviceresult;
                }
            }
            return $staffsdata;
        } else {
            $message =  'No result found.';
            return $message;
        }
    }

    public function get_open_times($date)
    {
        // De gekozen datum formatten
        $customer_date = date('Y-m-d', strtotime($date));

        // Zet de default tijd zone
        date_default_timezone_set("Europe/Amsterdam");

        // Zet de datum om naar de dag van de datum
        $day = strtolower(date('l', strtotime($date)));
        // Ophalen van de openingstijd
        $day_start_time = get_option('bookly_bh_' . $day . '_start');
        // Ophalen van de sluitingstijd
        $day_end_time = get_option('bookly_bh_' . $day . '_end');
        return ['open' => $day_start_time, 'close' => $day_end_time];
    }
    public function new_get_times()
    {

        // Ensure POST data is set
        if (isset($_POST['appointment_id']) && isset($_POST['date'])) {

            $service_id = $_POST['appointment_id'];
            $day = $_POST['date'];

            // Convert the provided date to a DateTime object
            $selectedDate = DateTime::createFromFormat('Y-m-d', $day); // Assuming date is in 'YYYY-MM-DD' format
            if (!$selectedDate) {
                // Handle invalid date format
                return ['result' => 'error', 'type' => 'invalid_date', 'message' => 'invalid date format'];
            }

            if (!current_user_can('edit_posts')) {


                // Get tomorrow's date
                $tomorrow = new DateTime('tomorrow');

                // Check if the selected date is before tomorrow
                if ($selectedDate < $tomorrow) {
                    return ['result' => 'error', 'type' => 'date_to_low', 'message' => 'Date must be at least tomorrow.'];
                }
            }
        }
        $staff = 1;



        $service = $this->get_single_service($service_id);




        if (isset($service['info'])) {
            $service_info = json_decode($service['info']);
        }

        $personeel_voor = null;
        if (!empty($service_info->Personeel_voor)) {
            $personeel_voor = $this->personel_time($service_info->Personeel_voor, $day, $staff);
        }

        $personeel_start = null;
        if (!empty($service_info->Personeel_start)) {
            $personeel_start = $this->personel_time($service_info->Personeel_start, $day, $staff);
        }

        $personeel_midden = null;
        if (!empty($service_info->Personeel_midden_na_hoeveel_minuten) && !empty($service_info->Personeel_midden_tijd)) {
            $personeel_midden = $this->personel_time($service_info->Personeel_midden_tijd, $day, $staff);
            $new_start = [];
            foreach ($personeel_voor as $time) {
                $dt = new DateTime($time);
                $dt->modify("+{$service_info->Personeel_midden_na_hoeveel_minuten} minutes");
                $timeslot = $dt->format('Y-m-d H:i:s');

                if (in_array($timeslot, $personeel_midden)) {
                    $new_start[] = $time;
                }
            }
            $personeel_voor = $new_start;
        }



        if (isset($service_info->Kamer)) {

            $rooms = explode(',', $service_info->Kamer);
            $rooms_available = [];
            foreach ($rooms as $room) {

                switch ($room) {
                    case '1':
                        $room_id = 7;
                        break;

                    case '2':
                        $room_id = 6;
                        break;

                    case '3':
                        $room_id = 5;
                        break;

                    case '4':
                        $room_id = 8;
                        break;

                    case '5':
                        $room_id = 9;
                        break;

                    case '6':
                        $room_id = 10;
                        break;

                    default:
                        wp_die('error');
                        break;
                }
                $params = [
                    'staffid' => $room_id,
                    'date' => $day,
                    'serviceid' => $service_id
                ];


                $result = $this->WPO_Get_TimeSlots($params);


                foreach ($result as $timeslot) {
                    if (!in_array($timeslot, $rooms_available)) {
                        $rooms_available[] = $timeslot;
                    }
                }
            }
        }

        $result = $rooms_available;

        if (is_array($personeel_start)) {
            $result = array_intersect($personeel_start, $result);
        }
        if (is_array($personeel_voor)) {
            $result = array_intersect($personeel_voor, $result);
        }

        $time_array = array();
        // Vul de tijd array met de tijden uit de beschikbare range

        foreach ($result as $time) {
            // Voeg de tijd toe als key en maak de value en check die later aangeeft of deze tijd beschikbaar is
            $checktime = date("H:i", strtotime($time));
            $time_array += [$checktime => 'open'];
        }

        return ['result' => 'success', 'service' => $service, 'time_array' => $time_array];
    }


    private function personel_time($time, $day, $staff)
    {
        $personeel = [];
        switch ($time) {
            case '90':

                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 48
                ];
                $personeel = $this->WPO_Get_TimeSlots($params);
                // var_dump($personeel);
                break;
            case '60':

                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 49
                ];
                $personeel = $this->WPO_Get_TimeSlots($params);
                // var_dump($personeel);
                break;

            case '45':
                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 50
                ];
                $personeel = $this->WPO_Get_TimeSlots($params);
                break;

            case '30':
                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 12
                ];
                $personeel = $this->WPO_Get_TimeSlots($params);
                break;


            case '15':
                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 11
                ];
                $personeel = $this->WPO_Get_TimeSlots($params);
                break;

            default:
                break;
        }
        return $personeel;
    }

    public function process_appointments($appointments, $service)
    {

        // Loop de alle ingeplande afpraken heen om te bepalen welke tijden beschikbaar zijn
        foreach ($appointments as $appointment) {

            // print_p($appointments);
            // Haal de start tijd en datum van de afspraak op
            $start = $appointment['start_date'];
            // Explode de datum en tijd zodat ze apart aanspreekbaar zijn. [0] = datum, [1] = tijd
            $start_array = explode(' ', $start);
            // Haal de eind tijd en datum van de afspraak op
            $end = $appointment['end_date'];
            // Explode de datum en tijd zodat ze apart aanspreekbaar zijn. [0] = datum, [1] = tijd
            $end_array = explode(' ', $end);

            // Determine de naam van de kamer. Hierdoor kan bepaalt worden of een kamer bezet is. 
            // Het kamernummer staat in de beschrijving van de dienst, als onderdeel van een JSON
            $appointment_service_id = $appointment['service_id']['id'];
            $appointment_service = $service->services[$appointment_service_id];
            $appointment_service_info = $appointment_service['info'];
            $appointment_service_info = json_decode($appointment_service_info);
            $appointment_rooms = $appointment_service_info->Kamer;

            // $room = 3;
            // var_dump($appointment_service_info);
            // Ga per ingeplande afspraak na of deze in de gekozen kamer en op de gekozen datum plaatsvind
            if (date('Y-m-d', strtotime($start_array[0])) == $customer_date && $room == $appointment_room) {
                // Loop door alle tijden heen
                foreach ($service->times as $time => $check) {

                    // Als de bestaande afspraak op het gekozen moment plaats vind, zet de tijd op niet beschikbaar

                    // De gekozen starttijd is bezet door de afspraak in de current loop
                    if (date("H:i", strtotime($time)) >= date("H:i", strtotime($start_array[1])) && date("H:i", strtotime($time)) < date("H:i", strtotime($end_array[1]))) {
                        $service->times[$time] = 'close';
                    }

                    // De gekozen eindtijd is bezet door de afspraak in de current loop
                    if (date("H:i", strtotime('+' . $service->totaal, $time)) > date("H:i", strtotime($start_array[1])) && date("H:i", strtotime('+' . $service->totaal, $time)) <= date("H:i", strtotime($end_array[1]))) {
                        $service->times[$time] = 'close';
                    }

                    // De gekozen tijdspan overkoepeld de afspraak in de current loop compleet. Tijdslot is dus niet beschikbaar
                    if (date("H:i", strtotime($time)) < date("H:i", strtotime($start_array[1])) && date("H:i", strtotime('+' . $service->totaal, $time)) > date("H:i", strtotime($end_array[1]))) {
                        $service->times[$time] = 'close';
                    }
                }
            }
        }
        return $service;
    }


    public function get_timestamp_by($date, $time)
    {
        return strtotime($date . ' ' . $time);
    }

    public function process_staff($date, $service)
    {
        // Haal de informatie van alle medewerkers op en maak hier een PHP object van
        $staff = $this->get_staffs();
        $appointments = [];
        $available = [];

        foreach ($staff as $staff_member) {
            // if($staff_member['category_id']){
            //     continue;
            // }
            $params = ['filter' => [
                'startdate' => $date,
                'enddate' => date("Y-m-d", strtotime($date . " +1day")),

            ]];

            $workday = false;
            $time = '9:00';
            if ($this->staff_work_day($staff_member['id'], $date, $time)) {
                $workday = true;
            }
            $appointments = $this->get_appointments($params);

            foreach ($service->times as $time => $check) {

                $is_free = false;
                if ($workday) {
                    if ($check == 'open') {
                        $is_free = true;

                        if (($appointments->data->status) !== 404) {

                            foreach ($appointments as $appointment) {
                                if ($appointment['staff_id']['id'] !== $staff_member['id']) {
                                    continue;
                                }

                                $timestamp = $this->get_timestamp_by($date, $time);
                                //

                                if ($timestamp >= strtotime($appointment['start_date']) &&  $timestamp <= strtotime($appointment['end_date'])) {
                                    $is_free = false;
                                }
                            }
                        }
                    }
                }
                if ($is_free) {
                    if ($available[$time]['status'] == 'open') {
                        $available[$time]['staff'][] = $staff_member['id'];
                    } else {
                        $available[$time]['status'] = 'open';
                        $available[$time]['staff'] = [$staff_member['id']];
                    }
                } else {
                    if (isset($available[$time]['staff'])) {
                    } else {
                        $available[$time]['status'] = 'close';
                    }
                }
            }
        }
        $service->available = $available;
        return $service;
    }
    public function staff_work_day($staff_id, $date, $time)
    {
        $daynum = date("w", strtotime($date));
        $daynum = intval($daynum) + 1;
        if ($daynum == 8) {
            $daynum = 1;
        }
        global $wpdb;
        $query = 'SELECT start_time, end_time FROM ' . $wpdb->prefix . 'bookly_staff_schedule_items WHERE staff_id = "' . $staff_id . '" AND day_index = "' . $daynum . '"';
        $result = $wpdb->get_row($query);
        if (date("H:i", strtotime($result->start_time)) <= date("H:i", strtotime($time)) && date("H:i", strtotime($result->end_time)) > date("H:i", strtotime($time))) {
            return true;
        } else {
            return false;
        }
    }
    public function create_customer($data)
    {


        $params = [
            'email' => $data['email'],
        ];
        //Maak via de API een nieuwe klant aan
        $search = $this->get_customers($params);

        if ($search) {

            //var_dump($this->update_customer($search[0]['id'], $params));
            return $search[0]['id'];
        } else {
            $params = [
                'full_name' => $data['first_name'] . ' ' . $data['last_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone']
            ];
            $result = $this->create_customers($params);
            return $result['customer_id'];
        }
    }

    public function get_customers($params = [])
    {
        global $wpdb;
        $condition = $customfieldslabels = $condition_arg = '';

        $customfieldslabels = get_option('bookly_customer_information_data');
        if (!empty($customfieldslabels)) {
            $customfieldslabels = json_decode($customfieldslabels);
            if (is_array($customfieldslabels) && count($customfieldslabels) > 0) {
                $counterkey = 0;
                foreach ($customfieldslabels as $customkeys => $clables) {
                    if ($clables->type == 'text-content') {
                        continue;
                    }
                    $customfarr[$clables->id] = $clables->label;
                    $customfieldstype[strtolower($clables->label)] = array('type' => $clables->type, 'id' => $counterkey, 'label' => $clables->label);
                    $counterkey++;
                }
            }
            if (isset($params['filter']) && count($params['filter']) > 0) {
                foreach ($params['filter'] as $filtercondtions) {
                    if (array_key_exists(strtolower($filtercondtions['key']), $customfieldstype)) {
                        if ($customfieldstype[$filtercondtions['key']]['type'] == 'checkboxes' || $customfieldstype[$filtercondtions['key']]['type'] == 'radio-buttons' || $customfieldstype[$filtercondtions['key']]['type'] == 'drop-down') {
                            $conditions_arr[] = "JSON_SEARCH(JSON_EXTRACT(info_fields, '$[" . $customfieldstype[$filtercondtions['key']]['id'] . "].value'), 'all', '" . $filtercondtions['value'] . "') is not null";
                        } else {
                            $conditions_arr[] = "JSON_EXTRACT(info_fields, '$[" . $customfieldstype[$filtercondtions['key']]['id'] . "].value') " . $filtercondtions['operator']  . " '" . $filtercondtions['value'] . "'";
                        }
                    }
                }
            }
            if (is_array($conditions_arr)) {
                if (count($conditions_arr) > 1) {
                    $conditions_arr = implode(' and ', $conditions_arr);
                    $condition_arg = ' where ' . $conditions_arr;
                } else {
                    $condition_arg = 'where ' . $conditions_arr[0];
                }
            }
        }

        if (isset($params['group']) && !empty($params['group'])) {
            if (!empty($condition_arg)) {
                $condition_arg = $condition_arg . " and group_id = (select id from " . $this->customer_groups_table . " where name = '" . $params['group'] . "') ";
            } else {
                $condition_arg = "where group_id = (select id from " . $this->customer_groups_table . " where name = '" . $params['group'] . "') ";
            }
        }

        if (isset($params['datefilter']) && count($params['datefilter']) > 0) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `created` " . $params['datefilter']['type'] . " '" . $params['datefilter']['value'] . "' ";
            } else {
                $condition_arg = " where `created` " . $params['datefilter']['type'] . " '" . $params['datefilter']['value'] . "' ";
            }
        }

        if (isset($params['email'])) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `email` = '" . $params['email'] . "' ";
            } else {
                $condition_arg = " where `email` = '" . $params['email'] . "' ";
            }
        }
        if (isset($params['first_name']) && count($params['first_name']) > 0) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `first_name` = '" . $params['first_name'] . "' ";
            } else {
                $condition_arg = " where `first_name` = '" . $params['first_name'] . "' ";
            }
        }

        if (isset($params['last_name']) && count($params['last_name']) > 0) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `last_name` = '" . $params['last_name'] . "' ";
            } else {
                $condition_arg = " where `last_name` = '" . $params['last_name'] . "' ";
            }
        }

        if (isset($params['name'])) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `full_name` like '%" . $params['name'] . "%' ";
            } else {
                $condition_arg = " where `full_name` like '%" . $params['name'] . "%' ";
            }
        }


        if (isset($params['id']) && is_array($params['id']) && count($params['id']) > 0) {
            if (isset($condition_arg) && !empty($condition_arg)) {
                $condition_arg .= " and `id` = '" . $params['id'] . "' ";
            } else {
                $condition_arg = " where `id` = '" . $params['id'] . "' ";
            }
        }

        $sql = "SELECT * FROM $this->customer_table $condition_arg";

        $result = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($result) && count($result) > 0) {
            foreach ($result as $customersdata) {
                $customfields = json_decode($customersdata['info_fields']);
                if (is_array($customfields) && count($customfields) > 0) {
                    if (!empty($customfieldslabels)) {

                        $customfields_data = array();
                        foreach ($customfields as $customdata) {
                            if (array_key_exists($customdata->id, $customfarr)) {
                                $customfields_data[] = array('id' => $customdata->id, 'label' =>  $customfarr[$customdata->id], 'value' => $customdata->value);
                            } else {
                                $customfields_data[] = array('id' => $customdata->id, 'value' => $customdata->id);
                            }
                        }
                        $customersdata['info_fields'] = $customfields_data;
                    }
                }
                if (isset($customersdata['group_id']) && $customersdata['group_id'] > 0) {
                    $group_sql = "SELECT * FROM $this->customer_groups_table where id = " . $customersdata['group_id'];
                    $group_data = $wpdb->get_row($group_sql, ARRAY_A);

                    $customersdata['group_id'] = $group_data;
                }
                $allcustomers[] = $customersdata;
            }

            return $allcustomers;
        } else {
            $message = esc_html__('No result found.', 'wpo-bookly-api');
            return [];
        }
    }

    public function create_customers($params = [])
    {
        global $wpdb;

        $wp_user_id = $facebook_id = $group_id = $full_name = $first_name = $last_name = $phone = $email = $birthday = $country = $state = $postcode = $city = $street = $street_number = $additional_address = $notes = $info_fields = $created  = '';

        if (isset($params['full_name']) && !empty($params['full_name'])) {
            $full_name = sanitize_text_field($params['full_name']);
        }

        if (empty($full_name)) {
            $response = array(
                'message'   => 'full_name is required field to create Customer',
                'status'        => 400
            );
            return $response;
        }

        if (isset($params['group_id']) && !empty($params['group_id'])) {
            $group_id = sanitize_email($params['group_id']);
        }

        if (isset($params['facebook_id']) && !empty($params['facebook_id'])) {
            $facebook_id = sanitize_email($params['facebook_id']);
        }

        if (isset($params['email']) && !empty($params['email'])) {
            $email = sanitize_email($params['email']);
        }

        if (isset($params['wp_user_id']) && !empty($params['wp_user_id'])) {
            $wp_user_id = sanitize_text_field($params['wp_user_id']);
        }

        if (isset($params['first_name']) && !empty($params['first_name'])) {
            $first_name = sanitize_text_field($params['first_name']);
        }

        if (isset($params['last_name']) && !empty($params['last_name'])) {
            $last_name = sanitize_text_field($params['last_name']);
        }

        if (isset($params['phone']) && !empty($params['phone'])) {
            $phone = sanitize_text_field($params['phone']);
        }

        if (isset($params['birthday']) && !empty($params['birthday'])) {
            $birthday = sanitize_text_field($params['birthday']);
        }

        if (isset($params['country']) && !empty($params['country'])) {
            $country = sanitize_text_field($params['country']);
        }

        if (isset($params['state']) && !empty($params['state'])) {
            $state = sanitize_text_field($params['state']);
        }

        if (isset($params['postcode']) && !empty($params['postcode'])) {
            $postcode = sanitize_text_field($params['postcode']);
        }

        if (isset($params['city']) && !empty($params['city'])) {
            $city = sanitize_text_field($params['city']);
        }
        if (isset($params['street']) && !empty($params['street'])) {
            $street = sanitize_text_field($params['street']);
        }

        if (isset($params['additional_address']) && !empty($params['additional_address'])) {
            $additional_address = sanitize_text_field($params['additional_address']);
        }

        if (isset($params['notes']) && !empty($params['notes'])) {
            $notes = sanitize_text_field($params['notes']);
        }

        if (isset($params['info_fields']) && !empty($params['info_fields'])) {
            $info_fields = sanitize_text_field($params['info_fields']);
        }

        if (isset($params['street_number']) && !empty($params['street_number'])) {
            $street_number = sanitize_text_field($params['street_number']);
        }

        if (isset($params['created']) && !empty($params['created'])) {
            $created = sanitize_text_field($params['created']);
        } else {
            $created = date("Y-m-d H:s:i");
        }

        $wpdb->insert(
            $this->customer_table,
            array(
                'wp_user_id' => $wp_user_id,
                'facebook_id' => $facebook_id,
                'group_id' => $group_id,
                'full_name' => $full_name,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'email' => $email,
                'birthday' => $birthday,
                'country' => $country,
                'state' => $state,
                'postcode' => $postcode,
                'city' => $city,
                'street' => $street,
                'street_number' => $street_number,
                'additional_address' => $additional_address,
                'notes' => $notes,
                'info_fields' => $info_fields,
                'created_at' => $created,
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if ($wpdb->last_error) {
            $response = array(
                'Error'        => $wpdb->last_error,
                'status'     => 400
            );
            return $response;
        } else {

            $customer_id = $wpdb->insert_id;
            $response = array(
                'message'       => 'Customer Created.',
                'customer_id'    => $customer_id,
                'status'        => 201
            );
            return $response;
        }
    }




    public function update_customer($customerid, $params)
    {
        global $wpdb;
        if (empty($customerid)) {
            return false;
        }
        //$customerid = $request['id'];
        $user_data = array();

        $sql = "SELECT * FROM $this->customer_table where id = $customerid";
        $user_data = $wpdb->get_row($sql, ARRAY_A);

        if (is_array($user_data) && count($user_data) > 0) {

            $wp_user_id = $user_data['wp_user_id'];
            $facebook_id = $user_data['facebook_id'];
            $group_id = $user_data['group_id'];
            $full_name = $user_data['full_name'];
            $first_name = $user_data['first_name'];
            $last_name = $user_data['last_name'];
            $phone = $user_data['phone'];
            $email = $user_data['email'];
            $birthday = $user_data['birthday'];
            $country = $user_data['country'];
            $state = $user_data['state'];
            $postcode = $user_data['postcode'];
            $city = $user_data['city'];
            $street = $user_data['street'];
            $street_number = $user_data['street_number'];
            $additional_address = $user_data['additional_address'];
            $notes = $user_data['notes'];
            $info_fields = $user_data['info_fields'];

            if (isset($params['group_id']) && !empty($params['group_id'])) {
                $group_id = sanitize_text_field($params['group_id']);
            }

            if (isset($params['facebook_id']) && !empty($params['facebook_id'])) {
                $facebook_id = sanitize_text_field($params['facebook_id']);
            }

            if (isset($params['full_name']) && !empty($params['full_name'])) {
                $full_name = sanitize_text_field($params['full_name']);
            }

            if (isset($params['email']) && !empty($params['email'])) {
                $email = sanitize_email($params['email']);
            }

            if (isset($params['wp_user_id']) && !empty($params['wp_user_id'])) {
                $wp_user_id = sanitize_text_field($params['wp_user_id']);
            }

            if (isset($params['first_name']) && !empty($params['first_name'])) {
                $first_name = sanitize_text_field($params['first_name']);
            }

            if (isset($params['last_name']) && !empty($params['last_name'])) {
                $last_name = sanitize_text_field($params['last_name']);
            }

            if (isset($params['phone']) && !empty($params['phone'])) {
                $phone = sanitize_text_field($params['phone']);
            }

            if (isset($params['birthday']) && !empty($params['birthday'])) {
                $birthday = sanitize_text_field($params['birthday']);
            }

            if (isset($params['country']) && !empty($params['country'])) {
                $country = sanitize_text_field($params['country']);
            }

            if (isset($params['state']) && !empty($params['state'])) {
                $state = sanitize_text_field($params['state']);
            }

            if (isset($params['postcode']) && !empty($params['postcode'])) {
                $postcode = sanitize_text_field($params['postcode']);
            }

            if (isset($params['city']) && !empty($params['city'])) {
                $city = sanitize_text_field($params['city']);
            }
            if (isset($params['street']) && !empty($params['street'])) {
                $street = sanitize_text_field($params['street']);
            }

            if (isset($params['street_number']) && !empty($params['street_number'])) {
                $street_number = sanitize_text_field($params['street_number']);
            }

            if (isset($params['additional_address']) && !empty($params['additional_address'])) {
                $additional_address = sanitize_text_field($params['additional_address']);
            }

            if (isset($params['notes']) && !empty($params['notes'])) {
                $notes = sanitize_text_field($params['notes']);
            }

            if (isset($params['info_fields']) && !empty($params['info_fields'])) {
                $info_fields = sanitize_text_field($params['info_fields']);
            }

            $wpdb->update(
                $this->customer_table,
                array(
                    'wp_user_id' => $wp_user_id,
                    'facebook_id' => $facebook_id,
                    'group_id' => $group_id,
                    'full_name' => $full_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => $phone,
                    'email' => $email,
                    'birthday' => $birthday,
                    'country' => $country,
                    'state' => $state,
                    'postcode' => $postcode,
                    'city' => $city,
                    'street' => $street,
                    'street_number' => $street_number,
                    'additional_address' => $additional_address,
                    'notes' => $notes,
                    'info_fields' => $info_fields
                ),
                array('id' => $customerid),
                array(
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                ),
                array('%d')
            );


            if ($wpdb->last_error) {
                $response = array(
                    'Error'        => $wpdb->last_error,
                    'status'     => 400
                );
                return $response;
            } else {
                $response = array(
                    'message'       => 'Customer details updated.',
                    'customer_id'    => $customerid,
                    'status'        => 200
                );
                return $response;
            }
        } else {
            $response = array(
                'Error'        => 'Please check Customer ID again.',
                'status'     => 400
            );
            return $response;
        }
    }
    public function send_confirmation_email($mail, $first_name, $last_name, $date, $time, $afspraak)
    {
        // Haal de naam van het bedrijf op uit de options tabel
        $company_name = get_option('bookly_co_name');
        // Haal de verzend email op uit de options tabel
        $sender_mail = get_option('bookly_email_sender');
        // Haal de naam van de mail verzender op uit de options tabel
        $sender_name = get_option('bookly_email_sender_name');
        // Haal het telefoonnummer van het bedrijf op uit de options tabel
        $company_phone = get_option('bookly_co_phone');
        // Haal het adres van het bedrijf op uit de options tabel
        $company_adress = get_option('bookly_co_address');

        global $wpdb;
        // Haal het mobiele nummer op uit de eigen plugin tabel. Dit nummer kan worden ingevuld in de backend
        $mob = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "mob_num"');
        // Haal de naam om de mail te ondertekenen op uit de eigen plugin tabel. Dit nummer kan worden ingevuld in de backend
        $mail_name = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "mail_name"');

        // Maak een array aan met alle data die de email in moet
        $variables = array();
        $variables['company_name'] = $company_name;
        $variables['first_name'] = $first_name;
        $variables['last_name'] = $last_name;
        $variables['date'] = $date;
        $variables['time'] = $time;
        $variables['afspraak'] = $afspraak;
        $variables['site_url'] = '<a href="' . get_site_url() . '">' . get_site_url() . '</a>';
        $variables['tel'] = $company_phone;
        $variables['adress'] = $company_adress;
        $variables['mob_tel'] = $mob->OPTION_VALUE;
        $variables['signed'] = $mail_name->OPTION_VALUE;

        // Geef aan naar welk adres de mail verstuurd moet worden, deze variable word als parameter meegegeven aan de function
        $to = $mail;
        // Maak de headers aan, geef aan dat de mail html bevat en stel de afzender in
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = 'From: ' . $sender_name . ' <' . $sender_mail . '>';
        // Stel het onderwerp van de email in
        $subject = 'Uw afspraak bij ' . $company_name;
        // Haal het template van de email op uit een ander bestand
        $body = file_get_contents("html/mail-template.html");
        // Loop door de template heen en plaats alle variabelen in de mail
        foreach ($variables as $key => $value) {
            $body = str_replace('{{ ' . $key . ' }}', $value, $body);
        }
        // Stuur de mail
        wp_mail($to, $subject, $body, $headers);
    }

    public function create_appointments($params = [])
    {
        global $wpdb;
        //$params		= $request->get_params();
        /**
         * possible params:
         * location_id
         * staff_id
         * staff_any
         * service_id
         * custom_service_name
         * custom_service_price
         * start_date
         * end_date
         * extras_duration
         * internal_note
         * google_event_id
         * google_event_etag
         * created_from
         * customer_appointment[series_id]
         * customer_appointment[package_id]
         * customer_appointment[customer_id]
         * customer_appointment[number_of_persons]
         * customer_appointment[units]
         * customer_appointment[status]
         * customer_appointment[notes]
         * customer_appointment[extras]
         * customer_appointment[status_changed_at]
         * customer_appointment[token]
         * customer_appointment[time_zone]
         * customer_appointment[rating]
         * customer_appointment[time_zone_offset]
         * customer_appointment[rating_comment]
         * customer_appointment[locale]
         * customer_appointment[compound_service_id]
         * customer_appointment[collaborative_token]
         * customer_appointment[custom_fields]
         * payment_details[coupon_id]
         * payment_details[type]
         * payment_details[total]
         * payment_details[tax]
         * payment_details[paid]
         * payment_details[paid_type]
         * payment_details[gateway_price_correction]
         * payment_details[status]
         * payment_details[details]
         * payment_details[created]
         * online_meeting_data
         * online_meeting_id
         * online_meeting_provider
         * outlook_event_series_id
         * outlook_event_change_key
         * outlook_event_id
         */
        $pd_coupon_id = $pd_type = $pd_total = $pd_tax = $pd_paid = $pd_paid_type = $pd_gateway_price_correction = $pd_status = $pd_details = $pd_created = $ca_notes = $ca_status = $ca_units = $ca_extras_multiply_nop =  $ca_number_of_persons = $ca_customer_id = $location_id = $staff_id = $staff_any = $service_id = $custom_service_name = $custom_service_price = $start_date = $end_date = $extras_duration = $internal_note = $google_event_id = $google_event_etag = $created_from = $ca_package_id = $ca_extras = $ca_status_changed_at = $ca_token = $ca_time_zone = $ca_time_zone_offset = $ca_rating = $ca_rating_comment = $ca_locale = $ca_compound_service_id = $ca_compound_token = $ca_created_from = $ca_created = $ca_custom_fields = '';
        $series_id = null;

        if (isset($params['location_id']) && !empty($params['location_id'])) {
            $location_id = sanitize_text_field($params['location_id']);
        } else {
            $location_id = null;
        }

        if (isset($params['staff_id']) && !empty($params['staff_id'])) {
            $staff_id = sanitize_text_field($params['staff_id']);
        }

        if (isset($params['staff_any']) && !empty($params['staff_any'])) {
            $staff_any = sanitize_text_field($params['staff_any']);
        }

        if (isset($params['service_id']) && !empty($params['service_id'])) {
            $service_id = sanitize_text_field($params['service_id']);
        }

        if (isset($params['custom_service_name']) && !empty($params['custom_service_name'])) {
            $custom_service_name = sanitize_text_field($params['custom_service_name']);
        } else {
            $custom_service_name = null;
        }

        if (isset($params['custom_service_price']) && !empty($params['custom_service_price'])) {
            $custom_service_price = sanitize_text_field($params['custom_service_price']);
        } else {
            $custom_service_price = null;
        }

        if (isset($params['start_date']) && !empty($params['start_date'])) {
            $start_date = sanitize_text_field($params['start_date']);
        }

        if (isset($params['end_date']) && !empty($params['end_date'])) {
            $end_date = sanitize_text_field($params['end_date']);
        }

        if (isset($params['extras_duration']) && !empty($params['extras_duration'])) {
            $extras_duration = sanitize_text_field($params['extras_duration']);
        }

        if (isset($params['internal_note']) && !empty($params['internal_note'])) {
            $internal_note = sanitize_text_field($params['internal_note']);
        } else {
            $internal_note = null;
        }

        if (isset($params['google_event_id']) && !empty($params['google_event_id'])) {
            $google_event_id = sanitize_text_field($params['google_event_id']);
        } else {
            $google_event_id = null;
        }

        if (isset($params['google_event_etag']) && !empty($params['google_event_etag'])) {
            $google_event_etag = sanitize_text_field($params['google_event_etag']);
        } else {
            $google_event_etag = null;
        }

        if (isset($params['created_from']) && !empty($params['created_from'])) {
            $created_from = sanitize_text_field($params['created_from']);
        }

        if (isset($params['customer_appointment']['series_id']) && !empty($params['customer_appointment']['series_id'])) {
            $series_id = sanitize_text_field($params['customer_appointment']['series_id']);
        } else {
            $series_id = null;
        }

        if (isset($params['customer_appointment']['package_id']) && !empty($params['customer_appointment']['package_id'])) {
            $ca_package_id = sanitize_text_field($params['customer_appointment']['package_id']);
        } else {
            $ca_package_id = null;
        }

        if (isset($params['customer_appointment']['customer_id']) && !empty($params['customer_appointment']['customer_id'])) {
            $ca_customer_id = sanitize_text_field($params['customer_appointment']['customer_id']);
        }

        if (isset($params['customer_appointment']['number_of_persons']) && !empty($params['customer_appointment']['number_of_persons'])) {
            $ca_number_of_persons = sanitize_text_field($params['customer_appointment']['number_of_persons']);
        }

        if (isset($params['customer_appointment']['units']) && !empty($params['customer_appointment']['units'])) {
            $ca_units = sanitize_text_field($params['customer_appointment']['units']);
        }

        if (isset($params['customer_appointment']['status']) && !empty($params['customer_appointment']['status'])) {
            $ca_status = sanitize_text_field($params['customer_appointment']['status']);
        }

        if (isset($params['customer_appointment']['notes']) && !empty($params['customer_appointment']['notes'])) {
            $ca_notes = sanitize_text_field($params['customer_appointment']['notes']);
        } else {
            $ca_notes = null;
        }

        if (isset($params['customer_appointment']['extras']) && !empty($params['customer_appointment']['extras'])) {
            $ca_extras = sanitize_text_field($params['customer_appointment']['extras']);
        } else {
            $ca_extras = null;
        }

        if (isset($params['customer_appointment']['status_changed_at']) && !empty($params['customer_appointment']['status_changed_at'])) {
            $ca_status_changed_at = sanitize_text_field($params['customer_appointment']['status_changed_at']);
        } else {
            $ca_status_changed_at = null;
        }

        if (isset($params['customer_appointment']['token']) && !empty($params['customer_appointment']['token'])) {
            $ca_token = sanitize_text_field($params['customer_appointment']['token']);
        } else {
            $ca_token = null;
        }

        if (isset($params['customer_appointment']['time_zone']) && !empty($params['customer_appointment']['time_zone'])) {
            $ca_time_zone = sanitize_text_field($params['customer_appointment']['time_zone']);
        } else {
            $ca_time_zone = null;
        }

        if (isset($params['customer_appointment']['time_zone_offset']) && !empty($params['customer_appointment']['time_zone_offset'])) {
            $ca_time_zone_offset = sanitize_text_field($params['customer_appointment']['time_zone_offset']);
        } else {
            $ca_time_zone_offset = null;
        }

        if (isset($params['customer_appointment']['rating']) && !empty($params['customer_appointment']['rating'])) {
            $ca_rating = sanitize_text_field($params['customer_appointment']['rating']);
        }

        if (isset($params['customer_appointment']['rating_comment']) && !empty($params['customer_appointment']['rating_comment'])) {
            $ca_rating_comment = sanitize_text_field($params['customer_appointment']['rating_comment']);
        }

        if (isset($params['customer_appointment']['locale']) && !empty($params['customer_appointment']['locale'])) {
            $ca_locale = sanitize_text_field($params['customer_appointment']['locale']);
        }

        if (isset($params['customer_appointment']['compound_service_id']) && !empty($params['customer_appointment']['compound_service_id'])) {
            $ca_compound_service_id = sanitize_text_field($params['customer_appointment']['compound_service_id']);
        } else {
            $ca_compound_service_id = null;
        }

        if (isset($params['customer_appointment']['compound_token']) && !empty($params['customer_appointment']['compound_token'])) {
            $ca_compound_token = sanitize_text_field($params['customer_appointment']['compound_token']);
        } else {
            $ca_compound_token = null;
        }

        if (isset($params['customer_appointment']['created_from']) && !empty($params['customer_appointment']['created_from'])) {
            $ca_created_from = sanitize_text_field($params['customer_appointment']['created_from']);
        }

        if (isset($params['customer_appointment']['created']) && !empty($params['customer_appointment']['created'])) {
            $ca_created = sanitize_text_field($params['customer_appointment']['created']);
        }

        if (isset($params['customer_appointment']['extras_multiply_nop']) && !empty($params['customer_appointment']['extras_multiply_nop'])) {
            $ca_extras_multiply_nop = sanitize_text_field($params['customer_appointment']['extras_multiply_nop']);
        }
        if (isset($params['customer_appointment']['extras_consider_duration']) && !empty($params['customer_appointment']['extras_consider_duration'])) {
            $ca_extras_consider_duration = sanitize_text_field($params['customer_appointment']['extras_consider_duration']);
        }
        if (isset($params['customer_appointment']['collaborative_service_id']) && !empty($params['customer_appointment']['collaborative_service_id'])) {
            $ca_collaborative_service_id = sanitize_text_field($params['customer_appointment']['collaborative_service_id']);
        } else {
            $ca_collaborative_service_id = null;
        }

        if (isset($params['customer_appointment']['collaborative_token']) && !empty($params['customer_appointment']['collaborative_token'])) {
            $ca_collaborative_token = sanitize_text_field($params['customer_appointment']['collaborative_token']);
        } else {
            $ca_collaborative_token = null;
        }

        if (isset($params['customer_appointment']['custom_fields']) && !empty($params['customer_appointment']['custom_fields'])) {
            $ca_custom_fields = sanitize_text_field($params['customer_appointment']['custom_fields']);
        }

        if (isset($params['payment_details']['coupon_id']) && !empty($params['payment_details']['coupon_id'])) {
            $pd_coupon_id = sanitize_text_field($params['payment_details']['coupon_id']);
        }

        if (isset($params['payment_details']['type']) && !empty($params['payment_details']['type'])) {
            $pd_type = sanitize_text_field($params['payment_details']['type']);
        }

        if (isset($params['payment_details']['total']) && !empty($params['payment_details']['total'])) {
            $pd_total = sanitize_text_field($params['payment_details']['total']);
        }

        if (isset($params['payment_details']['tax']) && !empty($params['payment_details']['tax'])) {
            $pd_tax = sanitize_text_field($params['payment_details']['tax']);
        }

        if (isset($params['payment_details']['paid']) && !empty($params['payment_details']['paid'])) {
            $pd_paid = sanitize_text_field($params['payment_details']['paid']);
        }

        if (isset($params['payment_details']['paid_type']) && !empty($params['payment_details']['paid_type'])) {
            $pd_paid_type = sanitize_text_field($params['payment_details']['paid_type']);
        }

        if (isset($params['payment_details']['gateway_price_correction']) && !empty($params['payment_details']['gateway_price_correction'])) {
            $pd_gateway_price_correction = sanitize_text_field($params['payment_details']['gateway_price_correction']);
        }

        if (isset($params['payment_details']['status']) && !empty($params['payment_details']['status'])) {
            $pd_status = sanitize_text_field($params['payment_details']['status']);
        }

        if (isset($params['payment_details']['details']) && !empty($params['payment_details']['details'])) {
            $pd_details = serialize($params['payment_details']['details']);
        }

        if (isset($params['payment_details']['created']) && !empty($params['payment_details']['created'])) {
            $pd_created = sanitize_text_field($params['payment_details']['created']);
        } else {
            $pd_created = null;
        }

        if (isset($params['outlook_event_id']) && !empty($params['outlook_event_id'])) {
            $outlook_event_id = sanitize_text_field($params['outlook_event_id']);
        } else {
            $outlook_event_id = null;
        }

        if (isset($params['outlook_event_change_key']) && !empty($params['outlook_event_change_key'])) {
            $outlook_event_change_key = sanitize_text_field($params['outlook_event_change_key']);
        } else {
            $outlook_event_change_key = null;
        }

        if (isset($params['outlook_event_series_id']) && !empty($params['outlook_event_series_id'])) {
            $outlook_event_series_id = sanitize_text_field($params['outlook_event_series_id']);
        } else {
            $outlook_event_series_id = null;
        }

        if (isset($params['online_meeting_provider']) && !empty($params['online_meeting_provider'])) {
            $online_meeting_provider = sanitize_text_field($params['online_meeting_provider']);
        } else {
            $online_meeting_provider = null;
        }

        if (isset($params['online_meeting_id']) && !empty($params['online_meeting_id'])) {
            $online_meeting_id = sanitize_text_field($params['online_meeting_id']);
        } else {
            $online_meeting_id = null;
        }

        if (isset($params['online_meeting_data']) && !empty($params['online_meeting_data'])) {
            $online_meeting_data = sanitize_text_field($params['online_meeting_data']);
        } else {
            $online_meeting_data = null;
        }
        // wp_die($ca_customer_id);
        //wp_die($this->customer_appointments_table);


        $wpdb->insert(
            $this->appointment_table,
            array(
                'location_id' => $location_id,
                'staff_id' => $staff_id,
                'staff_any' => $staff_any,
                'service_id' => $service_id,
                'custom_service_name' => $custom_service_name,
                'custom_service_price' => $custom_service_price,
                'start_date' => "$start_date",
                'end_date' => "$end_date",
                'extras_duration' => "$extras_duration",
                'internal_note' => $internal_note,
                'google_event_id' => $google_event_id,
                'google_event_etag' => $google_event_etag,
                'outlook_event_id' => $outlook_event_id,
                'outlook_event_change_key' => $outlook_event_change_key,
                'outlook_event_series_id' => $outlook_event_series_id,
                'online_meeting_provider' => $online_meeting_provider,
                'online_meeting_id' => $online_meeting_id,
                'online_meeting_data' => $online_meeting_data,
                'created_from' => "$created_from",
                'created_at'    => date("Y-m-d H:s:i")
            ),
            array(
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );

        $appointment_id = $wpdb->insert_id;

        if ($wpdb->last_error) {
            $response = array(
                'Error'   => $wpdb->last_error,
                'status'  => 400
            );
            return $response;
        } else {
            if (empty($pd_coupon_id)) {
                $pd_coupon_id = NULL;
            }



            $wpdb->insert(
                $this->payments_table,
                array(
                    'coupon_id' => $pd_coupon_id,
                    'type'         => "$pd_type",
                    'total'     => $pd_total,
                    'tax'         => $pd_tax,
                    'paid'         => $pd_paid,
                    'paid_type' => $pd_paid_type,
                    'gateway_price_correction' => $pd_gateway_price_correction,
                    'status'     => $pd_status,
                    'details'     => "$pd_details",
                    'created_at'     => "$pd_created"
                ),
                array(
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s'
                )
            );

            $payments_id = $wpdb->insert_id;

            if ($wpdb->last_error) {
                $response = array(
                    'Error'   => $wpdb->last_error,
                    'status'  => 400
                );
                return $response;
            } else {

                $wpdb->insert(
                    $this->customer_appointments_table,
                    array(
                        'series_id'                 => $series_id,
                        'package_id'                 => "$ca_package_id",
                        'customer_id'                 => $ca_customer_id,
                        'appointment_id'             => $appointment_id,
                        'payment_id'                 => $payments_id,
                        'number_of_persons'         => $ca_number_of_persons,
                        'units'                     => $ca_units,
                        'notes'                     => $ca_notes,
                        'extras'                     => "$ca_extras",
                        'custom_fields'             => "$ca_custom_fields",
                        'status'                     => "$ca_status",
                        'status_changed_at'         => "$ca_status_changed_at",
                        'token'                     => "$ca_token",
                        'time_zone'                 => $ca_time_zone,
                        'time_zone_offset'             => $ca_time_zone_offset,
                        'rating'                    => "$ca_rating",
                        'rating_comment'             => "$ca_rating_comment",
                        'locale'                     => "$ca_locale",
                        'compound_service_id'         => $ca_compound_service_id,
                        'compound_token'             => $ca_compound_token,
                        'created_from'                 => "$ca_created_from",
                        'extras_multiply_nop'         => "$ca_extras_multiply_nop",

                        'collaborative_service_id'     => $ca_collaborative_service_id,
                        'collaborative_token'         => $ca_collaborative_token,
                        'created_at'                     => "$ca_created",
                    ),
                    array(
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s'
                    )
                );

                $customer_appointmentid = $wpdb->insert_id;

                if ($wpdb->last_error) {
                    var_dump($wpdb->last_error);
                    wp_die();
                    $response = array(
                        'Error'   => $wpdb->last_error,
                        'status'        => 400
                    );
                    return $response;
                } else {

                    $response = array(
                        'message'   => 'Appointment Created Sucessfully.',
                        'appointmentID'    => $appointment_id,
                        'customer_appointmentID' => $customer_appointmentid,
                        'payment_id' => $payments_id,
                        'status'    => 201
                    );

                    return $response;
                }
            }
        }
    }


    private function insert_custom_appointment($customer_id = 0, $data_before = null, $data_middle = null, $data_after = null, $middle_staff = null, $current_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bookly_sw_custom';

        $before_id = 0;
        $middle_id = 0;
        $after_id = 0;
        $middle_staff = 0;
        if (isset($data_before['appointmentID'])) {
            $before_id = $data_before['appointmentID'];
        }
        if (isset($data_middle['appointmentID'])) {
            $middle_id = $data_middle['appointmentID'];
        }
        if (isset($data_after['appointmentID'])) {
            $after_id = $data_after['appointmentID'];
        }
        if (isset($middle_staff['appointmentID'])) {
            $middle_staff = $middle_staff['appointmentID'];
        }

        $data = array(
            'customer_id' => $customer_id,
            'id_before' => $before_id,
            'id_middle' => $middle_id,
            'id_after' => $after_id,
            'id_middle_staff' => $middle_staff,
        );

        $format = array('%d', '%d', '%d', '%d', '%d');


        if ($current_id) {
            // Update the existing row
            $wpdb->update($table, $data, array('id' => $current_id), $format);
        } else {
            // Insert a new row
            $wpdb->insert($table, $data, $format);
        }

        if ($wpdb->last_error !== '') {
            $wpdb->print_error();
        }

        $my_id = $wpdb->insert_id;

        return $my_id;
    }
    /**
     * Load the data into an object
     */
    public function load_appointment($id, $date = false, $time = false, $customer_data = false, $staff = false)
    {
        $service = $this->get_single_service($id);


        if (isset($service['info'])) {
            $info = json_decode(stripslashes($service['info']));
        } else {
            $info = false;
        }

        // Haal alle diensten op
        $all_services = $this->get_services();

        // Maak een lege array aan om te vullen met diensten
        $services_array = array();
        $placeholders = array();
        foreach ($all_services as $single_service) {
            // Sla alle diensten op in de array met de id van de dienst als key
            $services_array[$single_service['id']] = $single_service;
            if ($single_service['category_id']['id'] == 11) {
                $new_title = str_replace('Personeelslid ', '', $single_service['title']);
                $new_title = str_replace('min', '', $new_title);

                $placeholders[$new_title * 60] = $single_service;
            }
        }
        $service['placeholders'] = $placeholders;
        $service['services'] = $services_array;

        if (isset($info->Personeel_voor) && $info->Personeel_voor != 0) {

            if ($info->Personeel_voor == 0) {
                $service['voor']['enabled'] = false;
            } else {
                $service['voor']['enabled'] = true;
                $service['voor']['duration'] = $info->Personeel_voor * 60;
                $service['voor']['offset'] = 0;
                $service['voor']['placeholders'] = $placeholders[$service['voor']['duration']];
                if ($time) {

                    $service['voor']['starttime'] = $time;

                    if ($date) {
                        $service['voor']['startdate'] = $date . ' ' . $time;
                        $service['voor']['endtime'] = date('H:i', strtotime($service['voor']['starttime'] . " +" . $service['voor']['duration'] . "sec"));
                        $service['midden']['enddate'] = $date . ' ' . $service['voor']['endtime'];
                    }
                }
            }
        } else {
            $service['voor']['enabled'] = false;
            $service['voor']['duration'] = 0;
            $service['voor']['offset'] = 0;
        }


        $service['midden']['enabled'] = true;
        $service['midden']['offset'] = 0;
        $service['midden']['duration'] = $service['duration'];
        if ($time) {
            $service['midden']['starttime'] = date('H:i', strtotime($time . " +" . $service['midden']['offset'] . "sec"));
            if ($date) {
                $service['midden']['startdate'] = $date . ' ' . $service['midden']['starttime'];
                $service['midden']['endtime'] = date('H:i', strtotime($service['midden']['starttime'] . " +" . $service['midden']['duration'] . "sec"));
                $service['midden']['enddate'] = $date . ' ' . $service['midden']['endtime'];
            }
        }



        if (isset($info->Kamer)) {

            $service['midden']['kamer'] = $info->Kamer;
            if (isset($info->Kamer)) {

                $rooms = explode(',', $info->Kamer);
                $rooms_available = [];
                foreach ($rooms as $room) {

                    switch ($room) {
                        case '1':
                            $room_id = 7;
                            break;

                        case '2':
                            $room_id = 6;
                            break;

                        case '3':
                            $room_id = 5;
                            break;

                        case '4':
                            $room_id = 8;
                            break;

                        case '5':
                            $room_id = 9;
                            break;


                        case '6':
                            $room_id = 10;
                            break;

                        default:
                            wp_die('error');
                            break;
                    }
                    $params = [
                        'staffid' => $room_id,
                        'date' => $date,
                        'serviceid' => $id
                    ];


                    $result = $this->WPO_Get_TimeSlots($params);


                    foreach ($result as $timeslot) {
                        if ($date . ' ' . $time . ':00' == $timeslot) {
                            $rooms_available_id = $room_id;
                            break;
                        }
                    }
                    if ($rooms_available_id) {
                        break;
                    }
                }
            }
            $service['midden']['kamer'] = $rooms_available_id;
        } else {
            $service['midden']['kamer'] = 0;
        }
        if (!empty($info->Personeel_midden_tijd)) {
            $service['midden_personeel']['enabled'] = true;
            $service['midden_personeel']['duration'] = $info->Personeel_midden_tijd  * 60;
            $service['midden_personeel']['offset'] = $info->Personeel_midden_na_hoeveel_minuten  * 60;
            $service['midden_personeel']['placeholders'] = $placeholders[$service['midden_personeel']['duration']];
            if ($time) {
                $service['midden_personeel']['starttime'] = date('H:i', strtotime($time . " +" . $service['midden_personeel']['offset'] . "sec"));
                if ($date) {
                    $service['midden_personeel']['startdate'] = $date . ' ' . $service['midden_personeel']['starttime'];
                    $service['midden_personeel']['endtime'] = date('H:i', strtotime($service['midden_personeel']['starttime'] . " +" . $service['midden_personeel']['duration'] . "sec"));
                    $service['midden_personeel']['enddate'] = $date . ' ' . $service['midden_personeel']['endtime'];
                }
            }
        }

        if (!empty($info->Personeel_eind)) {
            $service['na']['enabled'] = true;
            $service['na']['duration'] = $info->Personeel_eind  * 60;
            $service['na']['offset'] = $service['duration'] - $info->Personeel_eind * 60;
            $service['na']['placeholders'] = $placeholders[$service['na']['duration']];
            if ($time) {
                $service['na']['starttime'] = date('H:i', strtotime($time . " +" . $service['na']['offset'] . "sec"));
                if ($date) {
                    $service['na']['startdate'] = $date . ' ' . $service['na']['starttime'];
                    $service['na']['endtime'] = date('H:i', strtotime($service['na']['starttime'] . " +" . $service['na']['duration'] . "sec"));
                    $service['na']['enddate'] = $date . ' ' . $service['na']['endtime'];
                }
            }
        } else {
            $service['na']['enabled'] = false;
            $service['na']['duration'] = 0;
        }

        if ($customer_data) {
            $service['customer'] = $customer_data;
        }

        if ($date) {
            $service['selected_date'] = $date;
        }

        if ($staff) {
            $service['selected_staff'] = $staff;
        }




        $service['totaal'] = $service['voor']['duration'] + $service['na']['duration'] +  $service['duration'];
        //$object =  $array;

        //var_dump($service);
        return (object) $service;
    }

    /**
     * Creates the HTML for time options
     */
    public function create_time_options($times)
    {

        $html = '';

        foreach ($times as $key => $time) {

            $html .= '<option data-staff="" value="' . $key . '">' . $key . '</option>';
        }

        return $html;
    }

    /**
     * function to create appointments
     */
    public function new_create_appointments()
    {

        // get service object
        $appointment_id = sanitize_text_field($_POST['service']);
        $time = sanitize_text_field($_POST['selected']);
        $date = sanitize_text_field($_POST['date']);
        $delete_id = sanitize_text_field($_POST['delete_id']);

        $staff = 1;


        $customer_data = [
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_text_field($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'notes' => sanitize_text_field($_POST['notes']),
            //'interne_notes' => sanitize_text_field($_POST['intern']),
        ];

        $service = $this->load_appointment($appointment_id, $date, $time, $customer_data, $staff);
        $price = 0;
        if (isset($service->price)) {
            $price = ($service->price);
        }
        $payment_type = $payment_total = $payment_tax = $payment_paid = $payment_paid_type = $gateway_price_correction = $payment_status = $payment_details = null;

        $payment_type = 'mollie';
        $payment_total = $price;
        $payment_tax = $price * 21 / 121;
        $payment_paid = 0;
        $payment_paid_type = 'in_full';
        $gateway_price_correction = 0.00;
        $payment_status = 'pending';
        $payment_details = '';

        /*
        * payment_details[coupon_id]
         * payment_details[type]
         * payment_details[total]
         * payment_details[tax]
         * payment_details[paid]
         * payment_details[paid_type]
         * payment_details[gateway_price_correction]
         * payment_details[status]
         * payment_details[details]
         * payment_details[created]
         * */
        $payment_data = [
            'coupon_id' => null,
            'type' => $payment_type,
            'total' => $payment_total,
            'tax' => $payment_tax,
            'paid' => $payment_paid,
            'paid_type' => $payment_paid_type,
            'gateway_price_correction' => $gateway_price_correction,
            'status' => $payment_status,
            'details' => $payment_details,
            'created' => current_time('mysql'),
        ];




        $html = '';


        $customer_id = $this->create_customer($customer_data);

        $current_id = $this->insert_custom_appointment($customer_id);

        $data_before =  $data_middle = $data_after = $data_midden_personeel = null;

        if ($service->voor['enabled']) {
            $start_date = $service->voor['startdate'];
            $minutes_to_add = $service->voor['duration'] / 60;

            $time = new DateTime($start_date);
            $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));

            $stamp = $time->format('Y-m-d H:i:s');


            $end_date = $stamp;

            //$end_date = $start_date + $service->duration;
            $service_id = $service->voor['placeholders']['id'];
            $staff = $staff;


            $data_before = $this->new_appointment($service, $start_date, $end_date, $staff, $customer_id, $service_id, $current_id);
        }
        if ($service->midden['enabled']) {

            $start_date = $service->midden['startdate'];
            $end_date = $service->midden['enddate'];


            if (isset($service->midden['kamer'])) {
                $staff = $service->midden['kamer'];
            }

            //$staff = 5;//$this->get_staffs(['filter'=>['filter_service_id' =>  $appointment_id]]);


            if (isset($price) && $price > 0) {
                $data_middle = $this->new_appointment($service, $start_date, $end_date, $staff, $customer_id, $appointment_id, $current_id, $payment_data);
                $service->payment_object = $payment_data;
            } else {
                $data_middle = $this->new_appointment($service, $start_date, $end_date, $staff, $customer_id, $appointment_id, $current_id);
            }
        }

        if ($service->na['enabled']) {

            // $minutes_to_add = $service->na['enddate'] / -60;

            // $time = new DateTime($start_date );
            // $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));
            $staff = 1;
            // $stamp = $time->format('Y-m-d H:i:s');
            $start_date = $service->na['startdate'];

            //$start_date = $service->na['startdate'] - $service->duration;

            $end_date = $service->na['enddate'];
            $service_id = $service->na['placeholders']['id'];


            $data_after = $this->new_appointment($service, $start_date, $end_date, $staff, $customer_id, $appointment_id, $current_id);
        }

        if (isset($service->midden_personeel) && $service->midden_personeel['enabled']) {

            // $minutes_to_add = $service->na['enddate'] / -60;

            // $time = new DateTime($start_date );
            // $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));

            // $stamp = $time->format('Y-m-d H:i:s');
            $start_date = $service->midden_personeel['startdate'];

            //$start_date = $service->na['startdate'] - $service->duration;

            $end_date = $service->midden_personeel['enddate'];
            $service_id = $service->midden_personeel['placeholders']['id'];
            $staff = 1;

            $data_midden_personeel = $this->new_appointment($service, $start_date, $end_date, $staff, $customer_id, $appointment_id, $current_id);
        }
        $service->custom_id = $current_id;
        $this->insert_custom_appointment($customer_id, $data_before, $data_middle, $data_after, $data_midden_personeel, $current_id);
        if ($current_id && $delete_id) {
            $this->delete_by_custom_id($delete_id);
        }
        return $service;
    }

    public function delete_by_custom_id($delete_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bookly_sw_custom';

        $query = $wpdb->prepare(
            "SELECT *
            FROM $table
            WHERE id = %d",
            $delete_id
        );

        $result = $wpdb->get_row($query);
        // var_dump($delete_id);
        // wp_die();

        if ($result) {
            if ($result->id_before) {
                $this->delete_row($result->id_before);
            }
            if ($result->id_middle) {
                $this->delete_row($result->id_middle);
            }
            if ($result->id_after) {
                $this->delete_row($result->id_after);
            }
        } else {
        }
    }
    private function delete_row($row_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bookly_appointments';



        $result = $wpdb->delete($table, array('id' => $row_id), array('%d'));

        if ($result === false) {
            // An error occurred during the deletion
            $wpdb->print_error();
        } else {
            // Deletion was successful
            $rows_affected = $wpdb->rows_affected;
        }
    }

    public function new_appointment($service, $start_date, $end_date, $staff, $customer_id, $service_id = false, $current_id = 0, $payment_data = [])
    {
        $post_data = [];

        //Maak een PHP object aan met alle benodigde data om een afspraak in te plannen
        if ($service_id) {
            $post_data['service_id'] = $service_id;
        } else {
            $post_data['service_id'] = $service->id;
        }

        $post_data['internal_note'] = $current_id;
        $post_data['start_date'] = $start_date;
        $post_data['end_date'] = $end_date;
        $post_data['staff_id'] = $staff;
        $post_data['created_from'] = 'SitiWeb Bookly Form';
        // $post_data['internal_note'] = $service->customer['interne_notes'];
        $customer_appointment['customer_id'] = esc_html($customer_id);
        $customer_appointment['notes'] =  $service->customer['notes'];

        $current_date_time = new DateTime('now', new DateTimeZone('Europe/Amsterdam'));
        $current_date_time = $current_date_time->format('Y-m-d H:i');

        $customer_appointment['created'] = esc_html($current_date_time);
        $customer_appointment['status'] = 'approved';
        //$customer_appointment = (array) $customer_appointment;
        $post_data['customer_appointment'] = $customer_appointment;
        if (isset($payment_data['total']) && $payment_data['total'] > 0) {
            $post_data['payment_details'] = $payment_data;
        }





        return $this->create_appointments(($post_data));

        /**
         * possible params:
         * location_id
         * staff_id
         * staff_any
         * service_id
         * custom_service_name
         * custom_service_price
         * start_date
         * end_date
         * extras_duration
         * internal_note
         * google_event_id
         * google_event_etag
         * created_from
         * customer_appointment[series_id]
         * customer_appointment[package_id]
         * customer_appointment[customer_id]
         * customer_appointment[number_of_persons]
         * customer_appointment[units]
         * customer_appointment[status]
         * customer_appointment[notes]
         * customer_appointment[extras]
         * customer_appointment[status_changed_at]
         * customer_appointment[token]
         * customer_appointment[time_zone]
         * customer_appointment[rating]
         * customer_appointment[time_zone_offset]
         * customer_appointment[rating_comment]
         * customer_appointment[locale]
         * customer_appointment[compound_service_id]
         * customer_appointment[collaborative_token]
         * customer_appointment[custom_fields]
         * payment_details[coupon_id]
         * payment_details[type]
         * payment_details[total]
         * payment_details[tax]
         * payment_details[paid]
         * payment_details[paid_type]
         * payment_details[gateway_price_correction]
         * payment_details[status]
         * payment_details[details]
         * payment_details[created]
         * online_meeting_data
         * online_meeting_id
         * online_meeting_provider
         * outlook_event_series_id
         * outlook_event_change_key
         * outlook_event_id
         */
    }

    public function WPO_Get_TimeSlots($params)
    {
        global $wpdb;
        //$params	=	$request->get_params();
        $staffid = $params['staffid'];
        $serviceid = $params['serviceid'];
        $slotdate = $params['date'];
        $times = array();
        $padding_left = $padding_right = 0;
        if (empty($staffid) || empty($serviceid) || empty($slotdate)) {
            $response = array(
                'message'   => 'staffid, serviceid and date are required fields.',
                'status'        => 400
            );
            return new WP_REST_Response($response);
        }

        if ($slotdate < date('Y-m-d')) {
            return new WP_REST_Response(array('message' => 'Slot date should be greater then ' . date('Y-m-d'), 'status'        => 400));
        }

        /** Checked booking end date */
        $max_days_for_booking =    get_option('bookly_gen_max_days_for_booking', true);
        if ($max_days_for_booking > 0) {
            $booking_end_date = date('Y-m-d', strtotime('+' . $max_days_for_booking . ' days'));
            if ($slotdate > $booking_end_date) {
                return new WP_REST_Response(array('message' => 'Slot date should be less then ' . $booking_end_date, 'status'  => 400));
            }
        }

        $date = substr($slotdate, 4);

        $dayoffsql = "SELECT * FROM $this->holidays_table WHERE `staff_id` = $staffid &&  `date` like '%$date'";
        $dayoff = $wpdb->get_results($dayoffsql, ARRAY_A);

        if (!empty($dayoff) && is_array($dayoff)) {
            foreach ($dayoff as $holiday) {
                if ($holiday['repeat_event'] == 1) {
                    return new WP_REST_Response(array());
                } else if ($holiday['date'] == $slotdate) {
                    return new WP_REST_Response(array());
                }
            }
        }

        $dayofweek = date('w', strtotime($slotdate));
        $weekday = $dayofweek + 1;

        $timesql = "SELECT id, day_index, start_time, end_time FROM $this->staff_schedule_items WHERE `staff_id` = $staffid &&  `day_index` = $weekday";
        $timeslots = $wpdb->get_row($timesql, ARRAY_A);
        /** Show error message based on minimum lead time */
        $min_time_slot = '';
        $min_time_prior_booking =    get_option('bookly_gen_min_time_prior_booking', true);
        if ($min_time_prior_booking  > 0) {
            $min_time_slot = date("Y-m-d H:i:s", strtotime(" + $min_time_prior_booking hours"));
            if ($min_time_slot > $slotdate . ' ' . $timeslots['end_time']) {
                return new WP_REST_Response(array());
            }
        }




        if (is_array($timeslots) && count($timeslots) > 0  && !empty($timeslots['start_time']) && !empty($timeslots['end_time'])) {
            /*Get Appointments based on staffID and selected Date*/
            $appointmentsql = "SELECT * FROM $this->appointment_table
			LEFT JOIN $this->customer_appointments_table
			ON $this->appointment_table.id = $this->customer_appointments_table.appointment_id
			WHERE $this->appointment_table.`staff_id` = $staffid AND $this->appointment_table.`start_date` LIKE '%" . $slotdate . "%'";
            $appointments = $wpdb->get_results($appointmentsql);


            /* Get staff break times*/
            $timebreaksql = "SELECT start_time, end_time FROM $this->schedule_item_breaks WHERE `staff_schedule_item_id` = " . $timeslots['id'];
            $timebreaks = $wpdb->get_results($timebreaksql, ARRAY_A);

            /*Get service interval*/
            $timeintervalsql = "SELECT slot_length, duration, padding_left, padding_right FROM $this->service_table WHERE `id` = $serviceid";
            $time_intervaldata = $wpdb->get_row($timeintervalsql);

            if (isset($time_intervaldata->padding_left) && !empty($time_intervaldata->padding_left)) {
                $padding_left = $time_intervaldata->padding_left;
            }

            if (isset($time_intervaldata->padding_right) && !empty($time_intervaldata->padding_right)) {
                $padding_right = $time_intervaldata->padding_right;
            }

            /* Set default slot interval */
            if (isset($time_intervaldata->slot_length) && !empty($time_intervaldata->slot_length)) {
                $serviceduration =    $time_intervaldata->duration;
                if ($time_intervaldata->slot_length == 'default') {
                    $default_interval =    get_option('bookly_gen_time_slot_length');
                    $time_interval = $default_interval * 60;
                } else if ($time_intervaldata->slot_length == 'as_service_duration') {
                    $time_interval = $time_intervaldata->duration;
                } else {
                    $time_interval = $time_intervaldata->slot_length;
                }
            }

            $start_time = $timeslots["start_time"];
            $end_time = $timeslots["end_time"];
            $start_time = strtotime(date("$slotdate $start_time"));
            $end_time = strtotime(date("$slotdate $end_time"));

            /** Checked interval in not empty */
            if (isset($time_interval) && !empty($time_interval)) {
                for ($time = $start_time; $time < $end_time; $time = $time + $time_interval) {
                    /** Removed slot based on minimum lead time */
                    if ($min_time_slot > 0 && $min_time_slot > date("Y-m-d H:i:s", $time)) {
                        continue;
                    }

                    /*Checked current day time greter then slot interval*/
                    if ($slotdate == date('Y-m-d') && $time < strtotime(date("H:i"))) {
                        continue;
                    }

                    if (is_array($timebreaks) && count($timebreaks) > 0) {
                        /*Updated slots time based on break*/
                        foreach ($timebreaks as $timebreak) {
                            $break_start_time     = $timebreak['start_time'];
                            $break_start_time     = strtotime(date("$slotdate $break_start_time"));
                            $break_end_time     = $timebreak['end_time'];
                            $break_end_time     = strtotime(date("$slotdate $break_end_time"));

                            if ($break_start_time >= $time && $break_start_time < $time + $time_interval) {
                                $time = $break_end_time;
                            }

                            if ($time >= $break_start_time && $time < $break_end_time) {
                                $time = $break_end_time;
                            }
                        }
                    }

                    if (is_array($appointments) && count($appointments)) {
                        /*removed slots based on appointment time*/
                        foreach ($appointments as $appointment) {

                            $appointment_date = strtotime($appointment->start_date);
                            $appointment_edate = strtotime($appointment->end_date);

                            /** if time slot length and service duration both are same */
                            if ($time_interval ==  $serviceduration) {

                                /* Checked slot is not in between start and enddate time*/
                                if ($time > $appointment_date && $time < $appointment_edate) {
                                    continue 2; /*continue to main loop*/
                                }

                                /** if appointment start date equals to slot starting time */
                                if ($appointment_date == $time) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($padding_left != 0 && ($appointment_edate + $padding_left) > $time && ($appointment_edate + $padding_left) < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($appointment_date - ($padding_left + $padding_right) > $time &&  $appointment_date - ($padding_left + $padding_right) < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }
                            } else {

                                /* Checked slot is not in between start and enddate time*/
                                if ($time >= $appointment_date && $time < $appointment_edate) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($time <= $appointment_date && ($time + $serviceduration) > $appointment_edate) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($appointment_date >= $time && $appointment_date < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }

                                /*Checked appointment end date not in slot length*/
                                if ($appointment_edate > $time && $appointment_edate < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($padding_left != 0 && ($appointment_edate + $padding_left) > $time && ($appointment_edate + $padding_left) < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }

                                if ($appointment_date - ($padding_left + $padding_right) > $time &&  $appointment_date - ($padding_left + $padding_right) < ($time + $serviceduration)) {
                                    continue 2; /*continue to main loop*/
                                }
                            }
                        }
                    }

                    /*Checked last slot time less or equal to end time*/
                    /** check service duration is greater then slot length for last slot */
                    if ($serviceduration > $time_interval) {
                        if ($time + $serviceduration <= $end_time) {
                            $times[] = date('Y-m-d H:i:s', $time);
                        }
                    } else {
                        if ($time + $time_interval <= $end_time) {
                            $times[] = date('Y-m-d H:i:s', $time);
                        }
                    }
                }
                $message =    $times;
            } else {
                $message = array();
            }
        } else {
            $message = array();
        }

        return ($message);
    }

    public function verify_filled()
    {

        if (empty($_POST['service'])) {
            $error[] = 'service';
        }
        if (empty($_POST['selected'])) {
            $error[] = 'time';
        }
        if (empty($_POST['date'])) {
            $error[] = 'date';
        }
        if (empty($_POST['first_name'])) {
            $error[] = 'first_name';
        }
        if (empty($_POST['last_name'])) {
            $error[] = 'last_name';
        }
        if (empty($_POST['email'])) {
            $error[] = 'email';
        }
        if (isset($error)) {
            return $error;
        }
        return true;
    }
}





add_action("wp_ajax_bookly_custom_check", "bookly_custom_check");
add_action("wp_ajax_nopriv_bookly_custom_check", "bookly_custom_check");

function bookly_custom_check()
{

    if (!wp_verify_nonce($_REQUEST['nonce'], "bookly_custom_check_nonce")) {
        exit("No naughty business please");
    }

    $form = new bookly_sw_custom();
    $result = $form->new_get_times();
    if ($result['result'] != 'success') {
        echo wp_json_encode($result);
        die();
    }
    $times =  $form->create_time_options($result['time_array']);


    $result = [
        'data' => $result,
        'times' => $times
    ];




    echo wp_json_encode($result);

    die();
}



add_action("wp_ajax_bookly_customer_create", "bookly_customer_create");
add_action("wp_ajax_nopriv_bookly_customer_create", "bookly_customer_create");

function bookly_customer_create()
{
    // if (!wp_verify_nonce($_REQUEST['nonce'], "bookly_customer_create_nonce")) {
    //     //   exit("No naughty business please");
    // }

    $form = new bookly_sw_custom();
    $verified = $form->verify_filled();

    if ($verified !== true) {
        echo wp_json_encode([
            'success' => false,
            'error' => $verified,
        ]);
        die();
    }

    $result = $form->new_create_appointments();


    if (isset($result->final_step_url) && !empty($result->final_step_url)) {
        $redirect = $result->final_step_url;
    }
    if (current_user_can('delete_posts')) {
        $payment_url_redirect = false;
        $payment_url = sanitize_text_field($_POST['payment_url']);
        if (isset($payment_url) && $payment_url == true) {
            if (isset($result->payment_object['total']) && $result->payment_object['total'] != $result->payment_object['paid']) {
                // Example usage:
                $inputDateTime = $result->midden['startdate'];
                $expiry_time = calculateTime($inputDateTime);
           
                $payment_url_redirect = ((new SWBooklyMollie())->mollie_get_payment_link($result->payment_object['total'] - $result->payment_object['paid'],  get_site_url(), $result->custom_id,  get_site_url() . '/wp-content/plugins/Bookly-custom-form/mollie-webhook-2.php', $expiry_time));
            }
        }

        (new sw_bookly_email)->send_payment_notification($result, $payment_url_redirect);
        echo wp_json_encode([
            'success' => true,
            'custom_id' => $result,
            'payment_url' => $payment_url_redirect,
        ]);
        die();
    }
    if (isset($result->payment_object['total']) && $result->payment_object['total'] != $result->payment_object['paid']) {

        $url = new SWBooklyMollie();
        if (!isset($redirect)) {
            $redirect = 'https://agenda.bodyunlimited.nl';
        }

        $result2 = $url->create_payment($result->payment_object['total'], $result->custom_id, $redirect);
    }

    if (isset($result) && isset($result2)) {
        (new sw_bookly_email)->send_notification($result);
        echo wp_json_encode([
            'success' => true,
            'custom_id' => $result,
            'redirect_url' => $result2->getCheckoutUrl()
        ]);
    } else {
        (new sw_bookly_email)->send_notification($result);
        if (isset($redirect)) {
            echo wp_json_encode([
                'success' => true,
                'custom_id' => $result,
                'redirect_url' =>  $redirect
            ]);
        } else {
            echo wp_json_encode([
                'success' => true,
                'custom_id' => $result,
            ]);
        }
    }



    die();
}

add_action("wp_ajax_bookly_customer_search", "bookly_customer_search");
add_action("wp_ajax_nopriv_bookly_customer_search", "bookly_customer_search");

function bookly_customer_search()
{

    if (!wp_verify_nonce($_REQUEST['nonce'], "bookly_customer_search_nonce")) {
        //   exit("No naughty business please");
    }
    if (!current_user_can('delete_posts')) {
        exit("Incorrect authority");
    }
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['first_name']);
    $email = sanitize_text_field($_POST['email']);

    $params = [

        'name' => $first_name,


    ];
    $form = new bookly_sw_custom();

    $result = $form->get_customers($params);
    $count = count($result);

    if ($count == 1) {
        $reponse = [
            'success' => true,
            'count' => $count,
            'data' => $result[0],
        ];
        echo wp_json_encode($reponse);
        die();
    }

    if ($count > 0) {
        $reponse = [
            'success' => true,
            'count' => $count,
            'data' => $result,
        ];
        echo wp_json_encode($reponse);
        die();
    }

    $reponse = [
        'success' => false,
        'count' => 0,
    ];
    echo wp_json_encode($reponse);
    die();
}


function print_p($value)
{
    echo '<pre>';
    var_dump($value);
    echo '</pre>';
}

//add_action('wp_head','sw_head_2');
function sw_head_2()
{

    $service_id = 24;
    $day = date('Y-m-d', strtotime(date('Y-m-d') . ' + 4 days'));
    $staff = 1;


    $form = new bookly_sw_custom();

    $service = $form->get_single_service($service_id);

    if (isset($service['info'])) {
        $service_info = json_decode($service['info']);
    }
    $personeel = [];

    if (isset($service_info->Personeel_voor)) {



        switch ($service_info->Personeel_voor) {
            case '30':
                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 12
                ];
                $personeel = $form->WPO_Get_TimeSlots($params);
                break;


            case '15':
                $params = [
                    'staffid' => $staff,
                    'date' => $day,
                    'serviceid' => 11
                ];
                $personeel = $form->WPO_Get_TimeSlots($params);
                break;

            default:
                break;
        }
    }

    if (isset($service_info->Kamer)) {
        print_p($service_info->Kamer);
        $rooms = explode(',', $service_info->Kamer);
        $rooms_available = [];
        foreach ($rooms as $room) {

            switch ($room) {
                case '1':
                    $room_id = 7;
                    break;

                case '2':
                    $room_id = 6;
                    break;

                case '3':
                    $room_id = 5;
                    break;

                case '4':
                    $room_id = 8;
                    break;

                case '5':
                    $room_id = 9;
                    break;


                case '6':
                    $room_id = 10;
                    break;

                default:
                    wp_die('error');
                    break;
            }

            $params = [
                'staffid' => $room_id,
                'date' => $day,
                'serviceid' => 11
            ];


            $result = $form->WPO_Get_TimeSlots($params);


            foreach ($result as $timeslot) {
                if (!in_array($timeslot, $rooms_available)) {
                    $rooms_available[] = $timeslot;
                }
            }
        }
    }

    $params = [
        'staffid' => 5,
        'date' => $day,
        'serviceid' => $service_id
    ];
    $room = $form->WPO_Get_TimeSlots($params);

    if ($personeel) {
        $result = array_intersect($personeel, $room);
    }
    //$result = $form->WPO_Get_TimeSlots($params);
}


function calculateTime($inputDateTime)
{
    $now = new DateTime();
    $input = new DateTime($inputDateTime);


    // Check if the input datetime is within the next 2 days
    $twoDaysLater = clone $now;
    $twoDaysLater->modify('+2 days');

    if ($input >= $now && $input <= $twoDaysLater) {

        $now->modify('+2 hours');
        return $now->format('Y-m-d\TH:i:sP');
    }

    return $twoDaysLater->format('Y-m-d\TH:i:sP');
}




function print_pre($value)
{
    $your_ip = $_SERVER['REMOTE_ADDR'];
    $desired_ip = '92.68.7.38';
    // Compare your IP address with the desired IP address
    if ($your_ip === $desired_ip) {
        echo '<pre style="background-color:white">';
        var_dump($value);
        echo '</pre>';
    }
}
