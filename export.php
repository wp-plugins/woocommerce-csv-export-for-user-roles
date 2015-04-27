<?php if ( is_admin() && current_user_can('manage_options') ) { ?>
    <div class="woo-export-csv-wrapper">
        <h1>Woocommerce Order Report Snapshot</h1>
        <p>Use this tool to generate a snapshot of the customer orders made within the selected date range. This will generate a CSV file which contains an overview of the orders made by what user role.</p>
        <form id="woo-export-csv-form" action="" method="_GET">
            <input type="hidden" name="page" value="order-export">
            <label>Start Date:</label>
            <input type="text" size="9" placeholder="yyyy-mm-dd" value="<?php if ( ! empty( $_GET['start_date'] ) ) echo esc_attr( $_GET['start_date'] ); ?>" name="start_date" class="range_datepicker from" />
            <label>End Date:</label>
            <input type="text" size="9" placeholder="yyyy-mm-dd" value="<?php if ( ! empty( $_GET['end_date'] ) ) echo esc_attr( $_GET['end_date'] ); ?>" name="end_date" class="range_datepicker to" />
            <input type="hidden" name="woo_export_csv" value="csv">
            <input type="button" class="button woo-export-submit-btn" value="GO" />
        </form>
    </div>
    <div id="message" class="woo-export-msg"></div>
    <script>
        jQuery('document').ready(function(){
            var dates = jQuery( ".range_datepicker" ).datepicker({
                changeMonth: true,
                changeYear: true,
                defaultDate: "",
                dateFormat: "yy-mm-dd",
                numberOfMonths: 1,
                maxDate: "+0D",
                showButtonPanel: true,
                showOn: "focus",
                buttonImageOnly: true,
                onSelect: function( selectedDate ) {
                    var option = jQuery(this).is('.from') ? "minDate" : "maxDate",
                            instance = jQuery( this ).data( "datepicker" ),
                            date = jQuery.datepicker.parseDate(
                                    instance.settings.dateFormat ||
                                            jQuery.datepicker._defaults.dateFormat,
                                    selectedDate, instance.settings );
                    dates.not( this ).datepicker( "option", option, date );
                }
            });
            jQuery('.woo-export-submit-btn').click(function(){
                if(jQuery('#woo-export-csv-form input[name="start_date"]').val() == '' && jQuery('#woo-export-csv-form input[name="end_date"]').val() == ''){
                    jQuery('.woo-export-msg').removeClass('updated').addClass('error').html('<p>Please Fill up the Start Date and End Date.</p>');
                }
                else if(jQuery('#woo-export-csv-form input[name="start_date"]').val() == ''){
                    jQuery('.woo-export-msg').removeClass('updated').addClass('error').html('<p>Please Fill up the Start Date.</p>');
                }
                else if(jQuery('#woo-export-csv-form input[name="end_date"]').val() == ''){
                    jQuery('.woo-export-msg').removeClass('updated').addClass('error').html('<p>Please Fill up the End Date.</p>');
                }
                else if(jQuery('#woo-export-csv-form input[name="start_date"]').val() != '' && jQuery('#woo-export-csv-form input[name="end_date"]').val() != ''){
                    jQuery('.woo-export-msg').removeClass('error').addClass('updated').html('<p>Report has been generated, you should see a file in your downloads folder</p>');
                    jQuery('#woo-export-csv-form').submit();
                }
            })
        })
    </script>
<?php } ?>
