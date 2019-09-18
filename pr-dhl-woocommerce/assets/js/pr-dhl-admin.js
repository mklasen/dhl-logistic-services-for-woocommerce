jQuery( document ).ready(function() {
	
	// Display or hide country list
	showCountryOrigin();
	jQuery('#woocommerce_pr_dhl_paket_dhl_store_origin').on("change", showCountryOrigin);

});

function showCountryOrigin(e) {
	var duties_calc = jQuery('#woocommerce_pr_dhl_paket_dhl_store_origin').val();
	var select_store = jQuery('#woocommerce_pr_dhl_paket_dhl_select_store_origin');

	if( ( duties_calc == 'select_country' ) ) {
		select_store.closest('tr').show();
	} else {
		select_store.closest('tr').hide();
	}
}