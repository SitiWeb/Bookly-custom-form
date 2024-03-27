(function(jQuery) {

    jQuery(document).ready(function() {


        jQuery(document).on('change', '#bookly_custom_form_services', function() {
            jQuery('select[name=time]').html('');
            // Get the selected service ID
            var selectedServiceId = jQuery(this).val();
            var date = jQuery("#bookly_custom_form_date").val()

            if (!date) {
                jQuery('select[name=time]').html('');
                console.log('no date is selected');
                return;
            }

            // Execute your custom function
            getTimes(date);
        });

        jQuery("#bookly_custom_form_date").datepicker({

            minDate: 0,
            startDate: new Date(),
            firstDay: 1,
            // Dit is het formaat van de html standaard datepicker, en hiermee is de plugin origineel gebouwt. Om te voorkomen da dat helemaal opnieuw gemaakt zou moeten worden gebruik ik hetzelfde formaat hier
            dateFormat: "yy-mm-dd",
            // weekenden zijn geen geldige keuzes
            //beforeShowDay: jQuery.datepicker.noWeekends,
            beforeShowDay: function(date) {
                var day = date.getDay();
                // Disable Saturdays (day 6) and Sundays (day 0)
                return [(day != 0 && day != 6)];
            },
            onSelect: function(dateText, inst) {
                jQuery("#result_area").empty();
                getTimes(dateText);
                console.log((dateText));

            }
        });


        jQuery("#bookly_custom_form_confirm_button_3").on("click", function() {
            jQuery(".sw_custom_form_wrapper input").removeClass("red-border");
            jQuery(".sw_custom_form_wrapper seelct").removeClass("red-border");
            jQuery.ajax({
                type: 'POST',
                dataType: "json",
                url: ajax_var.url,
                data: {
                    action: "bookly_customer_create",
                    service: jQuery('select[name=service]').val(),
                    date: jQuery('input[name=date]').val(),
                    first_name: jQuery('input[name=first_name]').val(),
                    last_name: jQuery('input[name=last_name]').val(),
                    email: jQuery('input[name=email]').val(),
                    phone: jQuery('input[name=tel]').val(),
                    notes: jQuery('textarea[name=notes]').val(),
                    selected: jQuery('select[name=time] option:selected').val(),
                    staff: jQuery('select[name=time] option:selected').data('staff'),
                    delete_id: jQuery('input[name=delete_id]').val()
                },
                success: function(response) {
                    console.log(response);

                    jQuery('#result_area').html(response);
                    if (response.success) {
                        // Get the current URL
                        let url = window.location.href;
                        var repeat = jQuery('input[name=repeat]');
                        if (repeat.is(':checked')) {
                            var first_name = jQuery('input[name=first_name]').val();
                            var last_name = jQuery('input[name=last_name]').val();
                            var email = jQuery('input[name=email]').val();
                            var tel = jQuery('input[name=tel]').val();
                            var notes = jQuery('input[name=notes]').val();

                            // Add the GET parameters to the URL
                            url += '&first_name=' + first_name + '&last_name=' + last_name + '&email=' + email + '&tel=' + tel + '&notes';

                            // Redirect to the new URL
                            //window.location.href = url;
                        } else {
                            if ('redirect_url' in response) {
                                window.location.href = response.redirect_url;
                            } else {
                                jQuery('.sw_custom_form_wrapper').html('Jouw afspraak is bevestigd.');
                                // const url = window.location.href;
                                // const newUrl = cleanUrl(url);
                                // window.location.href = newUrl;
                            }


                        }


                    } else {
                        var itemToCheck = 'tel';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="tel"]').addClass('red-border');
                        }

                        itemToCheck = 'email';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="email"]').addClass('red-border');
                        }

                        itemToCheck = 'service';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="service"]').addClass('red-border');
                        }

                        itemToCheck = 'first_name';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="first_name"]').addClass('red-border');
                        }

                        itemToCheck = 'last_name';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="last_name"]').addClass('red-border');
                        }

                        itemToCheck = 'time';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="time"]').addClass('red-border');
                        }

                        itemToCheck = 'date';
                        if (response.error.includes(itemToCheck)) {
                            jQuery('[name="date"]').addClass('red-border');
                        }
                    }
                    jQuery('.radio-times').change(function() {
                        jQuery('#bookly_custom_form_confirm_button').show();
                    });



                },
                error: function(request, status, error) {
                    alert(request.responseText);
                }
            });
        });



        jQuery("#sw_bookly_search_customer").on("click", function() {

            var test = {
                action: "bookly_customer_search",
                email: jQuery('input[name=email]').val(),
                first_name: jQuery('input[name=first_name]').val(),
                last_name: jQuery('input[name=last_name]').val(),
            }

            jQuery.ajax({
                type: 'POST',
                dataType: "json",
                url: ajax_var.url,
                data: test,
                success: function(response) {
                    console.log(response);


                    if (response.count == 1) {
                        var html = '<div>Found ' + response.count + ' customer</div>';
                        jQuery('#result_area_customer_search').html(html);
                        jQuery('input[name=first_name]').val(response.data.first_name);
                        jQuery('input[name=last_name]').val(response.data.last_name);
                        jQuery('input[name=email]').val(response.data.email);
                        jQuery('input[name=tel]').val(response.data.phone);
                    } else if (response.count > 1) {
                        createTable(response.data)
                        console.log(response.data);
                    } else {
                        var html = '<div>Geen klant gevonden</div>';
                        jQuery('#result_area_customer_search').html(html);
                    }
                },
                error: function(request, status, error) {
                    alert(request.responseText);
                }
            });
        });




    });

    function createTable(people) {


        // Create an HTML table and add data from the array
        const table = document.createElement("table");
        table.setAttribute("data-type", "people");

        // Add CSS styling to the table
        table.style.width = "100%";

        // Create table header row
        const headerRow = document.createElement("tr");
        const headers = ["First Name", "Last Name", "Email", "Telephone"];
        for (const header of headers) {
            const th = document.createElement("th");
            th.textContent = header;
            th.style.textAlign = "left";
            headerRow.appendChild(th);
        }
        table.appendChild(headerRow);

        // Create table rows with data from array
        for (const [index, person] of people.entries()) {
            console.log(people);
            const row = document.createElement("tr");
            row.setAttribute("data-index", index);
            row.setAttribute("data-firstname", person.first_name);
            row.setAttribute("data-lastname", person.last_name);
            row.setAttribute("data-email", person.email);
            row.setAttribute("data-tel", person.phone);

            const firstNameCell = document.createElement("td");
            firstNameCell.textContent = person.first_name;
            firstNameCell.style.width = "25%";
            firstNameCell.style.color = "blue";
            firstNameCell.style.textDecoration = "underline";
            firstNameCell.style.cursor = "pointer";
            // Make first name clickable and log row data when clicked
            firstNameCell.addEventListener("click", () => {
                if (row.getAttribute("data-firstname") != "undefined") {
                    jQuery('input[name=first_name]').val(row.getAttribute("data-firstname"));
                }
                if (row.getAttribute("data-lastname") != "undefined") {
                    jQuery('input[name=last_name]').val(row.getAttribute("data-lastname"));
                }
                if (row.getAttribute("data-email") != "undefined") {
                    jQuery('input[name=email]').val(row.getAttribute("data-email"));
                }
                if (row.getAttribute("data-tel") != "undefined") {
                    jQuery('input[name=tel]').val(row.getAttribute("data-tel"));
                }

            });
            row.appendChild(firstNameCell);

            const lastNameCell = document.createElement("td");
            lastNameCell.textContent = person.last_name;
            lastNameCell.style.width = "25%";
            row.appendChild(lastNameCell);

            const emailCell = document.createElement("td");
            emailCell.textContent = person.email;
            emailCell.style.width = "25%";
            row.appendChild(emailCell);

            const telCell = document.createElement("td");
            telCell.textContent = person.tel;
            telCell.style.width = "25%";
            row.appendChild(telCell);

            table.appendChild(row);


        }

        // Add the table to the result div
        const resultDiv = document.getElementById("result_area_customer_search");
        resultDiv.appendChild(table);
    }
    /**
     * Retrieve available times by date
     */
    function getTimes(dateText) {
        var appointment_id = jQuery('#bookly_custom_form_services').val();
        var date = dateText;
        jQuery('#result-date').html('');
        jQuery.ajax({
            type: 'POST',
            dataType: "json",
            url: ajax_var.url,
            data: {
                action: "bookly_custom_check",
                appointment_id: appointment_id,
                date: date,
                nonce: ajax_var.nonce,
            },
            success: function(response) {
                console.log(response);
                if (response.times) {
                    jQuery('select[name=time]').html(response.times);

                    if (response.data.service.price && response.data.service.price != '0.00') {
                        jQuery('.sw_price').html('&#8364;' + parseFloat(response.data.service.price, 10).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString());
                    }
                    jQuery('.radio-times').change(function() {
                        jQuery('#bookly_custom_form_confirm_button').show();
                    });
                } else {
                    jQuery('select[name=time]').html('');
                    jQuery('#result-date').html('Er zijn helaas geen tijden beschikbaar op deze dag');
                }




            },
            error: function(request, status, error) {
                alert(request.responseText);
            }
        });

    }

})(jQuery);

function cleanUrl(url) {
    const pageParam = 'page';
    const baseUrl = url.split('?')[0];

    let queryParams = url.split('?')[1] || '';
    let filteredParams = [];

    if (queryParams.length > 0) {
        const paramsArr = queryParams.split('&');

        paramsArr.forEach((param) => {
            const [key, value] = param.split('=');

            if (key === pageParam || !key) {
                filteredParams.push(param);
            }
        });

        queryParams = filteredParams.join('&');
    }

    return queryParams.length > 0 ? `${baseUrl}?${queryParams}` : baseUrl;
}