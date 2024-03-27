<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly
  // Define the absolute path to the plugin's root directory
define('BOOKLY_SW', plugin_dir_path(__FILE__));
define('BOOKLY_SW_URL', plugin_dir_url(__FILE__));
/**
 * Plugin Name: Sitiweb Bookly Form
 * Description: Formulier doormiddel van API zodat er aan de start en aan het einde van een afspraak een medewerker ingepland is
 * Version: 1.0.1
 * Author: SitiWeb
 * Author URI: sitiweb.nl
 */
include('vendor/autoload.php');

if( ! class_exists( 'SitiWeb_Updater' ) ){
	include_once( plugin_dir_path( __FILE__ ) . 'updater.php' );
}

$updater = new SitiWeb_Updater( __FILE__ );
$updater->set_username( 'SitiWeb' );
$updater->set_repository( 'Bookly-custom-form' );
$updater->initialize();

require_once('classes/bookly-sw-custom.php');
require_once('classes/bookly-calender.php');
require_once('classes/bookly-email-new.php');
require_once('classes/bookly-email.php');

require_once('classes/bookly-mollie.php');

add_action('admin_menu', 'bookly_custom_admin_menu');

function sw_custom_admin_enqueue($hook)
{

    // Only add to the edit.php admin page.
    // See WP docs.
    if ("toplevel_page_bookly_custom_form_admin_form" !== $hook) {
        return;
    }
    wp_enqueue_script('bookly_custom_script', plugin_dir_url(__FILE__) . '/js/admin_script.js', array('jquery'));
    wp_localize_script('bookly_custom_script', 'ajax_var', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bookly_custom_check_nonce'),
        'next' =>  get_site_url() . "/wp-content/plugins/Bookly-custom-form/form_3.php"
    ));
    // Load the datepicker script (pre-registered in WordPress).
    wp_enqueue_script('jquery-ui-datepicker');

    // You need styling for the datepicker. For simplicity I've linked to the jQuery UI CSS on a CDN.
    wp_register_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css');
    wp_enqueue_style('jquery-ui');
}


add_action('admin_enqueue_scripts', 'sw_custom_admin_enqueue');

function my_enqueue_scripts() {
    // Enqueue your script
    wp_enqueue_script('sw-frontend-script',  BOOKLY_SW_URL.'/js/fontend.js', array('jquery'), '1.0', true);
    // Add localization data
    $localization_data = array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bookly_custom_check_nonce'),
        // Add more data here as needed
    );
    wp_localize_script('sw-frontend-script', 'ajax_var', $localization_data);
}
add_action('wp_enqueue_scripts', 'my_enqueue_scripts');

// Aanmaken van de back end admin paginas voor de plugin
function bookly_custom_admin_menu()
{
    // Main pagina aanmaken, deze bevat het afspraak formulier voor de admin
    add_menu_page(
        __('Sitiweb Bookly Form', 'my-textdomain'),
        __('Sitiweb Bookly Form', 'my-textdomain'),
        'edit_posts',
        'bookly_custom_form_admin_form',
        'bookly_custom_admin_page_contents',
        'dashicons-calendar-alt',
        85
    );
    // De tweede pagina voor de plugin. Deze bevat updaten.
    add_submenu_page(
        'bookly_custom_form_admin_form',
        __('Sitiweb Bookly Form Update', 'textdomain'),
        __('Afspraak bewerken', 'textdomain'),
        'manage_options',
        'bookly_custom_form_update_page',
        'bookly_custom_form_update_page'
    );
    // De twederdeede pagina voor de plugin. Deze bevat instellingen voor de plugin.
    add_submenu_page(
        'bookly_custom_form_admin_form',
        __('Sitiweb Bookly Form Instelingen', 'textdomain'),
        __('Instellingen', 'textdomain'),
        'manage_options',
        'bookly_custom_form_settings',
        'bookly_custom_admin_page_settings'
    );
    // De vierde pagina voor de plugin. Deze bevat een aangepaste agenda
    add_submenu_page(
        'bookly_custom_form_admin_form',
        __('Agenda', 'textdomain'),
        __('Agenda', 'textdomain'),
        'manage_options',
        'bookly_custom_agenda',
        'bookly_custom_agenda'
    );
}

// Functie voor de main back-end pagina. Het is de shortcode voor een afspraak maken met een admin attr
function bookly_custom_admin_page_contents()
{
?>
    <h1>
        <!-- Titel van deze pagina -->
        <?php esc_html_e('Sitiweb Bookly Admin Formulier', 'swbcf_admin_page_header'); ?>
    </h1>
<?php
    echo "<link rel='stylesheet' href='" . get_site_url() . "/wp-content/plugins/Bookly-custom-form/css/swbcf-admin-page-style.css'>";
    echo '<div style="width: 70vw;">';

    create_template();
    //echo do_shortcode('[swbcf_bookly_form admin=true]');
    echo '</div>';
}
function create_template()
{
    $form = new bookly_sw_custom();
    $options = $form->get_services_options();
    include('template/form.php');
}
// Functie voor de main back-end pagina. Het is de shortcode voor een afspraak maken met een admin attr
function bookly_custom_agenda()
{
?>
    <h1>
        <!-- Titel van deze pagina -->
        <?php esc_html_e('Sitiweb Bookly Admin Formulier', 'swbcf_admin_page_header'); ?>
    </h1>
    <?php
    echo "<div id='calendar'></div>";
}

add_action('admin_head', 'wp_admin_head');
function wp_admin_head()
{
    if (isset($_GET['page']) &&($_GET['page']) == 'bookly_custom_agenda') {
        $boeking = new bookly_calender();
        $afspraken = (($boeking->get_appointments(['filter' => ['startdate' => date('Y-m-d H:i:s', strtotime('-7 days')), 'enddate' => date('Y-m-d H:i:s', strtotime('+90 days'))]])));
       
        $new_afspraak = [];
        $color = ($boeking->color_array());

        foreach ($afspraken as $afspraak) {
            if ($afspraak['customer_appointment']['all_data']['full_name']){
                $customer_name =  $afspraak['customer_appointment']['all_data']['full_name'];
            }
            else{
                $customer_name = '';
            }
            $resources = [
                ['id' => 'a', 'title' => '1'],
                ['id' => 'a', 'title' => '2'],
                ['id' => 'a', 'title' => '3'],
            ];
            if (strpos($afspraak['service_id']['title'], 'Personeelslid') !== false) {


                $extended = [
                    'staff_name' => $afspraak['staff_id']['full_name'],
                    'customer' => $afspraak['customer_appointment'],
                    'appointment' => $afspraak
                ];
                if (!$afspraak['service_id']['title']) {
                    $new_afspraak = [
                        'resourceId' => 'a',
                        'start' => $afspraak['start_date'],
                        'end' => $afspraak['end_date'],
                        'title' => $afspraak['custom_service_name'] .' '.$customer_name,
                       
                        'color' => $color[$afspraak['service_id']['id']],
                        'data' => $extended
                        
                    ];
                } else {
                    $new_afspraak = [
                        'resourceId' => 'a',
                        'start' => $afspraak['start_date'],
                        'end' => $afspraak['end_date'],
                        'title' => $afspraak['service_id']['title'] .' '.$customer_name,
                        
                        'color' => $color[$afspraak['service_id']['id']],
                        'data' => $extended
                        
                    ];
                    $new_kalendar[] = (($new_afspraak));
                }
      

                
            }
            else{
                // $new_afspraak = [
                //     'resourceId' => 'b',
                //     'start' => $afspraak['start_date'],
                //     'end' => $afspraak['end_date'],
                //     'title' => $afspraak['service_id']['title'] .' '.$customer_name,
                    
                //     'color' => $color[$afspraak['service_id']['id']],
                //     'data' => $extended
                    
                // ];
                // $new_kalendar[] = (($new_afspraak));
            }
            
        }
      

        //print_p($new_kalendar);
    ?>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    // Hide hours between 22:00 and 7:00
                    slotMinTime: '07:00:00',  // Minimum visible time
                    slotMaxTime: '22:00:00',  // Maximum visible time
                    initialView: 'timeGridWeek',
                    headerToolbar: {
                        // Set your header options here
                        start: 'prev,next today',
                        center: 'title',
                        end: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    // header: {
                    //     left: 'prev,next today',
                    //     center: 'title',
                    //     right: 'resourceTimelineDay,resourceTimelineWeek'
                    // },
                    views: {
                        dayGrid: {
                            // Set your day view options here
                            visibleRange: {
                                start: '07:00',
                                end: '20:00'
                            },
                            slotDuration: '00:15:00',
                            scrollTime: '08:00:00'
                        },
                        timeGrid: {
                            // Set your week view options here
                            visibleRange: {
                                start: '2023-05-01',
                                end: '2023-05-31'
                            },
                            slotDuration: '00:30:00',
                            scrollTime: '09:00:00'
                        },
                        dayGridMonth: {
                            // Set your month view options here
                            fixedWeekCount: false,
                            weekNumbers: true,
                            eventLimit: true,
                            eventLimitClick: 'popover'
                        }
                    },
                    resourceLabelText: 'Columns',
                    resources: <?php echo json_encode($resources); ?>,
                    events: <?php echo json_encode($new_kalendar); ?>,

                    eventClick: function(info) {
                        // Close the previous popup if it exists
                        if (typeof currentPopup !== 'undefined') {
                            if (!currentPopup.closed)
                            currentPopup.close();
                        }
                        console.log(info.event);
                        // Create a new popup window with the event information
                        var popup = window.open('', 'Event Details', 'width=400,height=400');
                        currentPopup = popup;

                        var startTime = new Date(info.event.start);
                        var endTime = new Date(info.event.end);

                        var startDate = formatDate(startTime);
                        var endDate = formatDate(endTime);

                        var phone = '';
                        var name = '';
                        var first_name = '';
                        var last_name = '';
                        var email = '';
                        var delete_id = '';
                        
                        if (typeof info.event.extendedProps.data.customer.all_data.full_name !== undefined){
                            name = info.event.extendedProps.data.customer.all_data.full_name;
                        }
                        if (typeof info.event.extendedProps.data.customer.all_data.first_name !== undefined){
                            first_name = info.event.extendedProps.data.customer.all_data.first_name;
                        }
                        if (typeof info.event.extendedProps.data.customer.all_data.last_name !== undefined){
                            last_name = info.event.extendedProps.data.customer.all_data.last_name;
                        }
                        if (typeof info.event.extendedProps.data.customer.all_data.phone !== undefined){
                            phone = info.event.extendedProps.data.customer.all_data.phone;
                        }
                        if (typeof info.event.extendedProps.data.customer.all_data.email !== undefined){
                            email = info.event.extendedProps.data.customer.all_data.email;
                        }
                        if (typeof info.event.extendedProps.data.appointment.internal_not !== undefined){
                            delete_id = info.event.extendedProps.data.appointment.internal_note;
                        }
                        // Function to open the edit form in a new window
                        var baseUrl =  '<?php echo get_site_url()?>/wp-admin/admin.php';  // Replace with your actual form URL

                        var params = {
                            page: 'bookly_custom_form_admin_form',
                            first_name: first_name,
                            last_name: last_name,
                            tel: phone,
                            email: email,
                            delete_id: delete_id
                            // Add more parameters as needed
                        };

                        var queryString = Object.keys(params)
                            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
                            .join('&');

                        var editFormUrl = baseUrl + '?' + queryString;
                        // Add an edit button that redirects to the form

                        popup.document.write('<h2>' + info.event.title + '</h2>');
                        popup.document.write('<p>' + startDate + '</p>');
                        popup.document.write('<p>' + endDate + '</p>');
                        popup.document.write('<p>' + name + '</p>');
                        popup.document.write('<p>' + phone + '</p>');
                        popup.document.write('<p>' + email + '</p>');
                        
                        // Add an edit button that redirects to the form
                        popup.document.write('<button id="editButton">Edit</button>');

                        // Function to open the edit form in the original browser
                        function openEditForm() {
                        window.location.href = editFormUrl;
                        }

                        // Attach the openEditForm function to the edit button click event
                        var editButton = popup.document.getElementById('editButton');
                        editButton.addEventListener('click', openEditForm);

                    }
                });
                calendar.render();

            });
            // Function to open the edit form in a new window
            function openEditForm() {
                var editFormUrl = 'https://example.com/edit-form';  // Replace with your actual form URL
                window.open(editFormUrl, 'Edit Form', 'width=600,height=600');
            }

            function formatDate(date) {
                var day = date.getDate();
                var month = date.getMonth() + 1; // Months are zero-based
                var year = date.getFullYear();
                var hours = date.getHours();
                var minutes = date.getMinutes();

                // Pad single digits with leading zeros
                day = (day < 10) ? '0' + day : day;
                month = (month < 10) ? '0' + month : month;
                hours = (hours < 10) ? '0' + hours : hours;
                minutes = (minutes < 10) ? '0' + minutes : minutes;

                return day + '-' + month + '-' + year + ' ' + hours + ':' + minutes;
                }






        </script>

    <?php
    }
}

function bookly_custom_form_update_page()
{
    ?>
    <h1>
        <!-- Titel van deze pagina -->
        <?php esc_html_e('Sitiweb Bookly Update Formulier', 'swbcf_admin_page_header'); ?>
    </h1>
<?php
    echo "<link rel='stylesheet' href='" . get_site_url() . "/wp-content/plugins/Bookly-custom-form/css/swbcf-admin-page-style.css'>";
    echo '<div style="width: 70vw;">';
    echo do_shortcode('[swbcf_bookly_update_form admin=true]');
    echo '</div>';
}

// Functie voor de tweede pagina met instellingen voor de plugin.
function bookly_custom_admin_page_settings()
{
    global $wpdb;

    // Als het formulier met opties gesubmit is -> Sla de nieuwe opties op in de database
    if (isset($_POST['submit'])) {
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_main_color'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_main_color']) . '" WHERE OPTION_NAME = "primary_color"';
            $wpdb->query($query);
        }
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_accent_color'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_accent_color']) . '" WHERE OPTION_NAME = "accent_color"';
            $wpdb->query($query);
        }
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_text_color'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_text_color']) . '" WHERE OPTION_NAME = "text_color"';
            $wpdb->query($query);
        }

        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_user'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_user']) . '" WHERE OPTION_NAME = "user"';
            $wpdb->query($query);
        }
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_pass'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . base64_encode(esc_html($_POST['swbcf_pass'])) . '" WHERE OPTION_NAME = "pass"';
            $wpdb->query($query);
        }
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_intern_id'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_intern_id']) . '" WHERE OPTION_NAME = "intern_id"';
            $wpdb->query($query);
        }

        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_mob_tel'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_mob_tel']) . '" WHERE OPTION_NAME = "mob_num"';
            $wpdb->query($query);
        }
        // Sla alleen de nieuwe value op als deze in het formulier ingevuld is
        if (isset($_POST['swbcf_mail_name'])) {
            $query = 'UPDATE ' . $wpdb->prefix . 'swbcf_options SET OPTION_VALUE = "' . esc_html($_POST['swbcf_mail_name']) . '" WHERE OPTION_NAME = "mail_name"';
            $wpdb->query($query);
        }
    }

    // Haal de bestaande waardes uit de database op om later als standaard waarde van de invul velden in te stellen. 
    $primary = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "primary_color"');
    $accent = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "accent_color"');
    $text = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "text_color"');
    $mail_name = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "mail_name"');
    $mob = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "mob_num"');
    $user = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "user"');
    $pass = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "pass"');
    $intern_id = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "intern_id"');

?>
    <!-- Voeg de style voor dit formulier toe -->
    <link rel='stylesheet' href='<?php echo get_site_url(); ?>/wp-content/plugins/Bookly-custom-form/css/swbcf-admin-page-style.css'>

    <h1>
        <!-- Titel van deze pagina -->
        <?php esc_html_e('Sitiweb Bookly Form', 'swbcf_admin_page_header'); ?>
    </h1>
    <form action='' method='POST' id='swbcf_admin_form'>

        <h2> Kleur instellingen </h2>
        <label for="swbcf_main_color">
            <?php esc_html_e('Selecteer de primaire kleur', 'swbcf_primary_color'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="color" name='swbcf_main_color' value="<?php echo $primary->OPTION_VALUE; ?>" />
        </label>
        <label for="swbcf_accent_color">
            <?php esc_html_e('Selecteer de accent kleur', 'swbcf_accent_color'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="color" name='swbcf_accent_color' value="<?php echo $accent->OPTION_VALUE; ?>" />
        </label>
        <label for="swbcf_accent_color">
            <?php esc_html_e('Selecteer de tekst kleur', 'swbcf_text_color'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="color" name='swbcf_text_color' value="<?php echo $text->OPTION_VALUE; ?>" />
        </label>

        <h2> Account gegevens </h2>
        <label for="swbcf_user">
            <?php esc_html_e('Gebruikersnaam', 'swbcf_user'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="text" name='swbcf_user' value="<?php echo $user->OPTION_VALUE; ?>" />
        </label>
        <label for="swbcf_pass">
            <?php esc_html_e('Wachtwoord', 'swbcf_pass'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="password" name='swbcf_pass' value="<?php echo base64_decode($pass->OPTION_VALUE); ?>" />
        </label>
        <label for="swbcf_intern_id">
            <?php esc_html_e('Interne gebruiker ID', 'swbcf_pass'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="text" name='swbcf_intern_id' value="<?php echo $intern_id->OPTION_VALUE; ?>" />
        </label>

        <h2> E-mail instellingen </h2>
        <label for="swbcf_mail_name">
            <?php esc_html_e('Email ondertekenen als', 'swbcf_mob_tel'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="text" name='swbcf_mail_name' value="<?php echo $mail_name->OPTION_VALUE; ?>" />
        </label>
        <label for="swbcf_mob_tel">
            <?php esc_html_e('Mobiel nummer', 'swbcf_mob_tel'); ?>
            <!-- Vul het invulveld in met de bestaande waarde die eerder uit de database is opgehaald -->
            <input type="text" name='swbcf_mob_tel' value="<?php echo $mob->OPTION_VALUE; ?>" />
        </label>
        <p> Deze instelling word alleen gebruikt om het mobiele nummer op de bevestigings email te zetten. </p>

        <input type="submit" value="Opslaan" name='submit'>
    </form>
<?php
}

// De activatie hook voor de plugin. Deze code runt als de plugin geactiveerd word
register_activation_hook(__FILE__, "activate_swbcf");
// Functie voor de activatie hook
function activate_swbcf()
{
    global $wpdb;
    // Maak een nieuwe tabel aan in de database als deze nog niet bestaat. 
    // Deze tabel heeft drie kolommen.
    // Kolom 1 is een ID die automatisch word opgehoogt
    // Kolom 2 is de naam van de optie die word ingevult
    // Kolom 3 is de waarde van de optie die word ingevult
    $maindb = " CREATE TABLE IF NOT EXISTS 
                `{$wpdb->prefix}swbcf_options` 
                (
                    ID int(11) AUTO_INCREMENT,
                    OPTION_NAME varchar(255) NOT NULL,
                    OPTION_VALUE varchar(255) NOT NULL,
                    PRIMARY KEY  (ID)
                )
                COLLATE $wpdb->collate";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($maindb);

    // Vul de tabel met een aantal standaardwaardes, anders heeft het formulier na activatie geen style
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (1, "primary_color","#24c19d")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (2, "accent_color","#1c977a")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (3, "text_color","#ffffff")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (4, "mob_num","")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (7, "mail_name","")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (5, "user","")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (6, "pass","")');
    $wpdb->query('INSERT INTO ' . $wpdb->prefix . 'swbcf_options VALUES (8, "intern_id","")');
}

//Aanmaken van de shortcode
add_shortcode('swbcf_bookly_form', 'sw_bookly_form_func');
function sw_bookly_form_func($atts, $content = null)
{
    // Haal de meegegeven attr op uit de shortcode
    $a = shortcode_atts(array(
        'admin' => 'false',
    ), $atts);

    global $wpdb;
    // Haal de kleuren voor het formulier op uit de database.
    $primary = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "primary_color"');
    $accent = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "accent_color"');
    $text = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "text_color"');

?>
    <!--Laad de benodige libraries in voor jQuery datepicker  -->
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

    <script>
        // Stel de kleuren voor het formulier in als CSS variabelen
        let root = document.querySelector(':root');
        let primary = '<?php echo $primary->OPTION_VALUE; ?>';
        let accent = '<?php echo $accent->OPTION_VALUE; ?>';
        let text = '<?php echo $text->OPTION_VALUE; ?>';

        root.style.setProperty('--swbcf-primary-color', primary);
        root.style.setProperty('--swbcf-accent-color', accent);
        root.style.setProperty('--swbcf-text-color', text);
    </script>

    <!-- Link de css. Dit bestand referenced de css variabelen die hierboven aangemaakt zijn -->
    <link rel='stylesheet' href='<?php echo get_site_url(); ?>/wp-content/plugins/Bookly-custom-form/css/swbcf_var_style.css'>

    <h2>Afspraak inplannen</h2>

    <!-- Aanmaken van het formulier -->
    <div id="bookly_custom_form_container">

        <?php include('form_1.php'); ?>

    </div>


<?php

}

add_shortcode('swbcf_bookly_update_form', 'sw_bookly_update_form_func');
function sw_bookly_update_form_func($atts, $content = null)
{
    // Haal de meegegeven attr op uit de shortcode
    $a = shortcode_atts(array(
        'admin' => 'false',
    ), $atts);

    global $wpdb;
    // Haal de kleuren voor het formulier op uit de database.
    $primary = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "primary_color"');
    $accent = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "accent_color"');
    $text = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "text_color"');

?>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

    <script>
        // Stel de kleuren voor het formulier in als CSS variabelen
        let root = document.querySelector(':root');
        let primary = '<?php echo $primary->OPTION_VALUE; ?>';
        let accent = '<?php echo $accent->OPTION_VALUE; ?>';
        let text = '<?php echo $text->OPTION_VALUE; ?>';

        root.style.setProperty('--swbcf-primary-color', primary);
        root.style.setProperty('--swbcf-accent-color', accent);
        root.style.setProperty('--swbcf-text-color', text);
    </script>

    <!-- Link de css. Dit bestand referenced de css variabelen die hierboven aangemaakt zijn -->
    <link rel='stylesheet' href='<?php echo get_site_url(); ?>/wp-content/plugins/Bookly-custom-form/css/swbcf_var_style.css'>

    <h2>Afspraak aanpassen</h2>
    <div id="swbcf_updater_container">
        <lottie-player src="https://assets8.lottiefiles.com/private_files/lf30_owwwnvjg.json" background="transparent" speed="1" loop autoplay class='no_page_lottie'></lottie-player>
    </div>

    <script>
        let tempadmin2 = '<?php echo $a['admin'] ?>';
        jQuery('#swbcf_updater_container').load("<?php echo get_site_url(); ?>/wp-content/plugins/Bookly-custom-form/updater_form_1.php", {
            admin: tempadmin2
        });
    </script>


<?php
}



// Functie die de gegevens ophaalt van het account dat word gebruikt voor de API connectie
function get_cred()
{
    global $wpdb;
    // Username ophalen
    $user = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "user"');
    // Hashed password ophalen
    $pass = $wpdb->get_row('SELECT OPTION_VALUE FROM ' . $wpdb->prefix . 'swbcf_options WHERE OPTION_NAME = "pass"');
    // Unhash pass
    $return = $user->OPTION_VALUE . ':' . base64_decode($pass->OPTION_VALUE);

    return $return;
}

//Call to api method, copied van stack
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init();

    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);

            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    // Optional Authentication:
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // Credentials voor de API. Dit zijn de gegevens van een WP account. Gebruikersnaam eerst en dan na een : het wachtwoord
    curl_setopt($curl, CURLOPT_USERPWD, get_cred());

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}

function inline_admin_calendar_styles() {
    echo '<style>
      .fc-timegrid-slots td {
        height: 40px!important; /* Adjust the desired height here */
      }
    </style>';
  }
  add_action( 'admin_head', 'inline_admin_calendar_styles' );



// Include the frontend template
function include_frontend_template($atts) {

    // Haal de meegegeven attr op uit de shortcode
    $a = shortcode_atts(array(
        'admin' => 'false',
        'show_cat' => '',
        'show_service' => '',
    ), $atts);

    if (isset($_GET['categorieen'])){
        $a['show_cat'] = sanitize_text_field( $_GET['categorieen'] );
    }

    if (isset($_GET['dienst'])){
      
        $a['show_service'] = sanitize_text_field( $_GET['dienst'] );
    }

    $template_path = BOOKLY_SW . 'template/frontend.php';
    ob_start();
    if (file_exists($template_path)) {
        include $template_path;
    }
    $result = ob_get_contents();
    ob_end_clean();
    return $result;
}
add_shortcode('sw_boekingsform', 'include_frontend_template');

function get_all_payments($payement='checkout'){
    global $wpdb;

        // Step 2: Prepare the query (in this case, we don't actually need to use prepare since there are no variables)
    // Step 2: Prepare the query
    if($payement == 'checkout'){
        $prefix = 'tr_';
    }
    if($payement == 'link'){
        $prefix = 'pl_';
    }

    $query = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bookly_payments WHERE ref_id LIKE %s AND status NOT IN (%s, %s)",
        $prefix . '%', '123', '234'
    );
    
    $results = $wpdb->get_results($query);

    // Step 3: Execute the query
    $results = $wpdb->get_results($query);



    // Step 4: Loop through the results
    if (!empty($results)) {
        
        foreach ($results as $row) {
            if($payement == 'checkout'){
                check_payment($row->ref_id);
            }
            if($payement == 'link'){
               
                check_link_payment($row->ref_id);
            }
            
            echo "ref_id: " . $row->ref_id . "<br>";
        }
    } else {
        echo "0 results";
    }
}
if(isset($_GET['ref_id'])){
    if ($_GET['ref_id'] == 'link'){
        get_all_payments('link');
    }
    else{
        get_all_payments();
    }
    
}

function check_payment($id){
    try {
   


        /*
         * Initialize the Mollie API library with your API key.
         *
         * See: https://www.mollie.com/dashboard/developers/api-keys
         */
        require_once ABSPATH . 'wp-content/plugins/bookly-responsive-appointment-booking-tool/autoload.php';
        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey("live_eSWu84NcASMzABRpQta9CkcEktBuUQ");
        /*
         * Retrieve the payment's current state.
         */
        $payment = $mollie->payments->get($id);
        $orderId = $payment->metadata->order_id;

        /*
         * Update the order in the database.
         */

        //database_write($orderId, $payment->status);
    
        if ($payment->isPaid() && ! $payment->hasRefunds() && ! $payment->hasChargebacks()) {
            
            //set_mollie_paid($id, $payment->settlementAmount->value);
            set_mollie_status($id, 'completed');
    
        } elseif ($payment->isOpen()) {
            set_mollie_status($id, 'pending');
          
            /*
             * The payment is open.
             */
        } elseif ($payment->isPending()) {
            set_mollie_status($id, 'pending');
            /*
             * The payment is pending.
             */
        } elseif ($payment->isFailed()) {
            set_mollie_status($id, 'rejected');
            /*
             * The payment has failed.
             */
        } elseif ($payment->isExpired()) {
            set_mollie_status($id, 'rejected');
            /*
             * The payment is expired.
             */
        } elseif ($payment->isCanceled()) {
            set_mollie_status($id, 'rejected');
            /*
             * The payment has been canceled.
             */
        } elseif ($payment->hasRefunds()) {
            set_mollie_status($id, 'refunded');
            /*
             * The payment has been (partially) refunded.
             * The status of the payment is still "paid"
             */
        } elseif ($payment->hasChargebacks()) {
            set_mollie_status($id, 'refunded');
            /*
             * The payment has been (partially) charged back.
             * The status of the payment is still "paid"
             */
        }
    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        echo "API call failed: " . htmlspecialchars($e->getMessage());
        //remove_mollie($id);
    }
}

function check_link_payment($id){
    $payment_id = false;
    if (isset($id)){
        $payment_id = $id;
    }

    if ($payment_id) {
        // Log the webhook data to a file
        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey("live_eSWu84NcASMzABRpQta9CkcEktBuUQ");

        $payment = $mollie->paymentLinks->get($payment_id);
        echo '<pre style="background:black; color:green;">';
       
        echo '</pre>';
        if ($payment->isPaid()) {
            echo 'PAID';
            (new SWBooklyMollie())->change_payment_status($payment->id); 
        }
    

        // Now you can handle the webhook data
        // Implement your logic here based on the Mollie webhook data
        // For testing, you can simply log it as shown above.
    }
}

function set_mollie_status($ref_id, $status = 'pending'){

    global $wpdb;
    // Define the table name with the correct WordPress prefix.
    $table_name_payments = $wpdb->prefix . 'bookly_payments';

    // Prepare the SQL query.
    //$sql = $wpdb->prepare("UPDATE $table_name SET status = %s WHERE ref_id = %s", $status, $ref_id);
    $sql = $wpdb->prepare("SELECT * FROM $table_name_payments WHERE ref_id = %s", $ref_id);
    // Execute the query.
    $result = $wpdb->query($sql);
    error_log('result: ' . print_r($result, true));
    // Check if the query was successful.
    if ($result === false) {
        // Query failed, handle the error.
       return False;
    } else {
        $updated_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_payments WHERE ref_id = %s", $ref_id), ARRAY_A);
        
        error_log('Updated row: ' . print_r($updated_row, true));

        if ($updated_row) {
            // You can access the updated row's data here.
            $payment_id = $updated_row['id'];
            $table_name = $wpdb->prefix . 'bookly_customer_appointments';
            $table_name_appointment = $wpdb->prefix . 'bookly_appointments';
            $table_name_customer  = $wpdb->prefix . 'bookly_customers';
            $table_name_service  = $wpdb->prefix . 'bookly_services';
            // Now, perform a second query using $payment_id.

            // Now, perform a second query using $payment_id.
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE payment_id = %d", $payment_id), ARRAY_A);
    
            $b_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_appointment WHERE id = %d", $appointment['appointment_id']), ARRAY_A);
       
            $customer_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_customer WHERE id = %d", $appointment['customer_id']), ARRAY_A);
            
            $service_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_service WHERE id = %d", $b_appointment['service_id']), ARRAY_A);
           

            if ($appointment === false) {
                error_log('No appointment: ');
                // Second query failed, handle the error.
                echo 'Second query failed.';
                return false;
            } else {
                error_log('Sending emails: ');
                $wpdb->update(
                    $table_name_payments,
                    array('status' => 'completed'), // Data to update
                    array('id' => $updated_row['id']), // Where clause
                    array('%s'), // Data format
                    array('%d') // Where format
                );
               // (new sw_bookly_email)->send_payment_confirmation_email($b_appointment, $status, $customer_data, $appointment,$service_data);
              	(new sw_bookly_email)->update_payment_info('roberto@sitiweb.nl', $status, $customer_data, $appointment,$service_data);
                (new sw_bookly_email)->update_payment_info($customer_data->email, $status, $customer_data, $appointment,$service_data);
                return true;
            }
       
        } else {
            // Row not found, handle the error.
            echo 'Row not found after update.';
            return false;
        }
    }
}

function remove_mollie ($ref_id, $status = 'pending'){
   
    global $wpdb;

    // Replace 'your_ref_id' with the actual reference ID you want to update.
    $ref_id = $ref_id; // Replace 'your_ref_id' with the specific reference ID.

    // Define the table name with the correct WordPress prefix.
    $table_name = $wpdb->prefix . 'bookly_payments';

    // Prepare the SQL query.
    $sql = $wpdb->prepare("UPDATE $table_name SET ref_id = %s WHERE ref_id = %s", null, $ref_id);

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

function get_appointment_by_id($appointment_id){
    // Assuming you have retrieved a specific record from 'gjmf_bookly_customer_appointments' table.
    global $wpdb;
    // Query to retrieve data from 'gjmf_bookly_customer_appointments' table.
    $appointments_table = $wpdb->prefix . 'bookly_customer_appointments';
    $appointment_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $appointments_table WHERE appointment_id = %d ORDER BY id DESC", $appointment_id), ARRAY_A);

    if ($appointment_data) {
        // Now, you can access the appointment data and retrieve customer-related information.

        // Assuming you have a customer ID in the appointment data.
        $customer_id = $appointment_data['customer_id'];

        // Query to retrieve customer data from the 'customer' table.
        $customer_table = $wpdb->prefix . 'customer'; // Replace 'customer' with your actual table name.
        $customer_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customer_table WHERE id = %d", $customer_id), ARRAY_A);

        if ($customer_data) {
            // Now, you can access and work with the customer data.
            var_dump($customer_data);
        } else {
            // Handle the case where customer data is not found.
            echo 'Customer data not found.';
        }
    } else {
        // Handle the case where appointment data is not found.
        echo 'Appointment data not found.';
    }
}

function reminder_mail_sent_notice(){
    if(!isset($_GET['testimngngngn'])){
        return;
    }
    global $wpdb;

    $two_day_back = date('Y-m-d H:i:s',strtotime("-2 days"));
    
    $payment_table = $wpdb->prefix . 'bookly_payments';
    $customer_appointment_table = $wpdb->prefix . 'bookly_customer_appointments';

    $pending_payments = $wpdb->get_results($wpdb->prepare("SELECT id FROM $payment_table WHERE `status` = %s AND `created_at` < %s ORDER BY id DESC", 'pending', $two_day_back), ARRAY_A);
    foreach($pending_payments as $payment_id => $key){
        $customer_appointment = $wpdb->get_row($wpdb->prepare("SELECT appointment_id FROM $customer_appointment_table WHERE `payment_id` = %s ORDER BY id DESC", $payment_id), ARRAY_A);
        var_dump( $customer_appointment);
    }
    var_dump($pending_payments);
}
add_action('wp_head', 'reminder_mail_sent_notice');


// Schedule event on plugin activation
register_activation_hook(__FILE__, 'schedule_daily_event');
function schedule_daily_event() {
    if (!wp_next_scheduled('daily_test_function')) {
        wp_schedule_event(strtotime('08:00:00'), 'daily', 'daily_test_function');
    }
}

// Unschedule event on plugin deactivation
register_deactivation_hook(__FILE__, 'unschedule_daily_event');
function unschedule_daily_event() {
    wp_clear_scheduled_hook('daily_test_function');
}

// Hook into scheduled event
add_action('daily_test_function', 'test_function_sw2');
function test_function_sw2() {
    // Check if it's after 8 o'clock
    if (date('H') >= 8) {
        // Your function code here
        //if (isset($_GET['send_emails'])) {
            $test = new sw_bookly_email();
            $data = $test->prepare_data();
           
            foreach ($data as $key => $array) {
                
                $test->send_email($key, $array);
            }
        //}
    }
}
add_action('wp_head','test_function_sw2');