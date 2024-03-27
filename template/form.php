<?php
$nonce = wp_create_nonce('sw_custom_form_nonce');
//    $form = new bookly_sw_custom();
    
?>


    <form class="sw_custom_form" data-nonce="<?php echo esc_attr($nonce); ?>">

    <div>
        <!-- Options field -->
        <label id="bookly_custom_form_services_label" for="service">Kies behandeling</label>
        <select id="bookly_custom_form_services" name="service" class="required">
            <?php echo $options; ?>
        </select>
    </div>

    <div>
    <!-- Date field -->
    <label for="date" id="bookly_custom_form_date_label">Kies de datum van je afspraak *
            <!-- Deze input word later doormiddel van JS omgezet naar een jQuery datepicker -->
        <input type="text" name="date" id="bookly_custom_form_date" placeholder='Datum' class="required" value=""></label>
    </div>

    <!-- Times field -->
    <div>
    <label>Kies een tijd:</label><select name="time"></select>
    </div>

    <!-- FirstName field -->
    <div>
        <label for="first_name" id="bookly_custom_form_first_name">Voornaam *
        <input name='first_name' type="text" placeholder="Voornaam" class="required" value="<?php if (isset($_GET['first_name'])){ echo esc_html($_GET['first_name']);}?>"/></label>    
    </div>

    <!-- LastName field -->
    <div>
        <label for="last_name" id="bookly_custom_form_last_name">Achternaam *
        <input name='last_name' type="text" placeholder="Achternaam" class="required"  value="<?php if (isset($_GET['last_name'])){ echo esc_html($_GET['last_name']);}?>"/></label>    
    </div>

    <!-- Email field -->
    <div>
         <label for="email" id="bookly_custom_form_email">Email *
         <input name='email' type="email" placeholder="Email" class="required"  value="<?php if (isset($_GET['email'])){ echo esc_html($_GET['email']);}?>"/></label>   
    </div>

    <!-- Telefoonnummer field -->
    <div>
        <label for="tel" id="bookly_custom_form_phone">Telefoonnummer *
        <input name='tel' type="tel" placeholder="Telefoonnummer" class="required" value="<?php if (isset($_GET['tel'])){ echo esc_html($_GET['tel']);}?>"/></label> 
    </div>

    <!-- Notes field -->
    <div>
        <label for="notes" id="bookly_custom_form_notes_label">Extra notitie
        <textarea id="bookly_custom_form_notes" name="notes" rows="4" value="<?php if (isset($_GET['notes'])){ echo esc_html($_GET['notes']);}?>" ></textarea></label>     
    </div>
		
	<div>
        <label for="repeat" id="bookly_custom_form_repeat_label">Nieuwe afspraak met dezelfde gegevens?
        <input type="checkbox" id="bookly_custom_form_repeat" name="repeat" <?php if (isset($_GET['first_name'])){ echo 'checked';}?>  /></label>     
    </div>
    <div>
        <label for="send payment URL in mail" id="bookly_custom_form_payment_url">Betaallink meesturen?
        <input type="checkbox" id="bookly_custom_form_payment_url" name="payment_url" /></label>     
    </div>
    <input type="hidden" id="delete_id" name="delete_id" value="<?php if (isset($_GET['delete_id'])){ echo esc_html($_GET['delete_id']);}?>">
                
    <input type="button" id='bookly_custom_form_confirm_button_3' value="Plaats uw reservering">
    <input type="button" id='sw_bookly_search_customer' value="Zoek klant op">
   

           
    </form>
    <div id="result_area_customer_search"></div>


    <style>
        .sw_custom_form *{
            
            display:block;

        }
    </style>


<?php   