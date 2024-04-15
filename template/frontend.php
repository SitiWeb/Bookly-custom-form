<?php
$mollie = new SWBooklyMollie();
if ($mollie->api_type === 'test'){
    return '';
}

$nonce = wp_create_nonce('sw_custom_form_nonce');
//    $form = new bookly_sw_custom();
    //var_dump($a);
$show_cat =  [];
if (isset($a['show_cat']) && $a['show_cat']){
    $show_cat = explode(',', $a['show_cat']);
}

$show_service =  [];
if (isset($a['show_service']) && $a['show_service']){
    $show_service = explode(',', $a['show_service']);
}
elseif(isset($_GET['dienst']) && $_GET['dienst']){
	var_dump($_GET['dienst']);
    $show_service = explode(',', urldecode($_GET['dienst']));
}

?>

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<!-- Load jQuery UI CSS from a CDN -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">


<div class="sw_custom_form_wrapper">
    <form class="sw_custom_form">

        <div class="mb-4">
            <!-- Options field -->
            <label id="bookly_custom_form_services_label" for="service" class="block font-semibold">Kies behandeling</label>

            <select id="bookly_custom_form_services" name="service" class="required border rounded px-3 py-2 mt-1 w-full">
                <?php echo  (new bookly_sw_custom())->get_services_options($show_cat, $show_service); ?>
            </select>
        </div>

        <div class="mb-4">
            <!-- Date field -->
            <label for="date" id="bookly_custom_form_date_label" class="block font-semibold">Kies de datum van je afspraak *</label>
            <input type="text" name="date" id="bookly_custom_form_date" placeholder='Datum' class="required border rounded px-3 py-2 mt-1 w-full" value="">
            <div id="result-date"></div>
        </div>

        <!-- Times field -->
        <div class="mb-4">
            <label class="block font-semibold">Kies een tijd:</label>
            <select name="time" class="border rounded px-3 py-2 mt-1 w-full"></select>
        </div>

        <!-- FirstName field -->
        <div class="mb-4">
            <label for="first_name" id="bookly_custom_form_first_name" class="block font-semibold">Voornaam *</label>
            <input name='first_name' type="text" placeholder="Voornaam" class="required border rounded px-3 py-2 mt-1 w-full" value="<?php if (isset($_GET['first_name'])){ echo esc_html($_GET['first_name']);}?>">
        </div>

        <!-- LastName field -->
        <div class="mb-4">
            <label for="last_name" id="bookly_custom_form_last_name" class="block font-semibold">Achternaam *</label>
            <input name='last_name' type="text" placeholder="Achternaam" class="required border rounded px-3 py-2 mt-1 w-full" value="<?php if (isset($_GET['last_name'])){ echo esc_html($_GET['last_name']);}?>">
        </div>

        <!-- Email field -->
        <div class="mb-4">
            <label for="email" id="bookly_custom_form_email" class="block font-semibold">Email *</label>
            <input name='email' type="email" placeholder="Email" class="required border rounded px-3 py-2 mt-1 w-full" value="<?php if (isset($_GET['email'])){ echo esc_html($_GET['email']);}?>">
        </div>

        <!-- Telefoonnummer field -->
        <div class="mb-4">
            <label for="tel" id="bookly_custom_form_phone" class="block font-semibold">Telefoonnummer *</label>
            <input name='tel' type="tel" placeholder="Telefoonnummer" class="required border rounded px-3 py-2 mt-1 w-full" value="<?php if (isset($_GET['tel'])){ echo esc_html($_GET['tel']);}?>">
        </div>

        <!-- Notes field -->
        <div class="mb-4">
            <label for="notes" id="bookly_custom_form_notes_label" class="block font-semibold">Extra notitie</label>
            <textarea id="bookly_custom_form_notes" name="notes" rows="4" class="border rounded px-3 py-2 mt-1 w-full"><?php if (isset($_GET['notes'])){ echo esc_html($_GET['notes']);}?></textarea>
        </div>

        <div class="sw_price"></div>
        <input type="button" id='bookly_custom_form_confirm_button_3' value="Reserveren en betalen" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-2 px-4 rounded cursor-pointer">

    </form>
</div>

    <style>
        #bookly_custom_form_confirm_button_3{
            background-color:orange;
            padding:5px 10px;
            border-radius:5px;
            border:none;
            color:white;
            font-weight:700;
            font-size:1.2em;
            cursor:pointer;
        }
        .sw_custom_form *{
            
            display:block;

        }
        .red-border{
            border: 1px solid red!important;
        }
        .sw_custom_form_wrapper {
    display: flex;
    justify-content: center;
}
    </style>


<?php   