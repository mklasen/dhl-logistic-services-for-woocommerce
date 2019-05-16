jQuery( function( $ ) {

	var wc_shipment_dhl_label_items = {
	
		// init Class
		init: function() {
			$( '#woocommerce-shipment-dhl-label' )
				.on( 'click', '#dhl-label-button', this.save_dhl_label );

			$( '#woocommerce-shipment-dhl-label' )
				.on( 'click', 'a#dhl_delete_label', this.delete_dhl_label );

			$( '#woocommerce-shipment-dhl-label' )
				.on( 'change', '#pr_dhl_return_address_enabled', this.show_hide_return );
			wc_shipment_dhl_label_items.show_hide_return();

			$( '#woocommerce-shipment-dhl-label' )
				.on( 'change', '#pr_dhl_identcheck', this.show_hide_ident );
			wc_shipment_dhl_label_items.show_hide_ident();

			$( '#woocommerce-shipment-dhl-label' )
				.on( 'change', '#pr_dhl_product', this.validate_product_return );

			$( '#woocommerce-dhl-dp-order' )
				.on( 'click', '#pr_dhl_add_to_order', this.add_item_to_order.bind(this) );

			$( '#woocommerce-dhl-dp-order' )
				.on( 'click', '.pr_dhl_order_remove_item', this.remove_item_from_order.bind(this) );

			$( '#woocommerce-dhl-dp-order' )
				.on( 'click', '#pr_dhl_finalize_order', this.finalize_order.bind(this) );
		},
	
		validate_product_return: function () {
			var selected_product = $( '#pr_dhl_product' ).val();

			if( selected_product != 'V01PAK' && selected_product != 'V01PRIO' ) {
				$('#pr_dhl_return_address_enabled').prop('checked', false).trigger('change');
				$('#pr_dhl_return_address_enabled').prop('disabled', 'disabled');
			} else {
				$('#pr_dhl_return_address_enabled').removeAttr('disabled');
			}

		},

		show_hide_return: function () {
			var is_checked = $( '#pr_dhl_return_address_enabled' ).prop('checked');

			$( '#shipment-dhl-label-form' ).children().each( function () {
				
				// If class exists, and is not 'pr_dhl_return_address_enabled' but is 'pr_dhl_return_' field
			    if( ( $(this).attr("class") ) &&
			    	( $(this).attr("class").indexOf('pr_dhl_return_address_enabled') == -1 ) &&
			    	( $(this).attr("class").indexOf('pr_dhl_return') >= 0 ) 
			    ) {
			    	
			    	if ( is_checked ) {
			    		$(this).show();
			    	} else {
			    		$(this).hide();
			    	}
			    }
			});
		},
	
		show_hide_ident: function () {
			var is_checked = $( '#pr_dhl_identcheck' ).prop('checked');

			$( '#shipment-dhl-label-form' ).children().each( function () {
				
				// If class exists, and is not 'pr_dhl_return_address_enabled' but is 'pr_dhl_return_' field
			    if( ( $(this).attr("class") ) &&
			    	( $(this).attr("class").indexOf('pr_dhl_identcheck_field ') == -1 ) &&
			    	( $(this).attr("class").indexOf('pr_dhl_identcheck') >= 0 ) 
			    ) {
			    	
			    	if ( is_checked ) {
			    		$(this).show();
			    	} else {
			    		$(this).hide();
			    	}
			    }
			});
		},

		save_dhl_label: function () {
			// Remove any errors from last attempt to create label
			$( '#shipment-dhl-label-form .wc_dhl_error' ).remove();

			$( '#shipment-dhl-label-form' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
			
			// loop through inputs within id 'shipment-dhl-label-form'
			
			var data = {
				action:                   'wc_shipment_dhl_gen_label',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
			};
			
			// var data = new Array();
			$(function(){ 
				$('#shipment-dhl-label-form').each(function(i, div) {

				    $(div).find('input').each(function(j, element){
				        if( $(element).attr('type') == 'checkbox' ) {
				        	if ( $(element).prop('checked') ) {
					        	data[ $(element).attr('name') ] = 'yes';
				        	} else {
					        	data[ $(element).attr('name') ] = 'no';
				        	}
				        } else {
				        	data[ $(element).attr('name') ] = $(element).val();
				        }
				    });

				    $(div).find('select').each(function(j, element){
			        	data[ $(element).attr('name') ] = $(element).val();
				    });

				    $(div).find('textarea').each(function(j, element){
			        	data[ $(element).attr('name') ] = $(element).val();
				    });
		    	});
		    });
			
			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#shipment-dhl-label-form' ).unblock();
				if ( response.error ) {
					$( '#shipment-dhl-label-form' ).append('<p class="wc_dhl_error">' + response.error + '</p>');
				} else {
					// Disable all form items
					$(function(){ 
						$('#shipment-dhl-label-form').each(function(i, div) {

						    $(div).find('input').each(function(j, element){
						       $(element).prop('disabled', 'disabled');
						    });

						    $(div).find('select').each(function(j, element){
						        $(element).prop('disabled', 'disabled');
						    });

						    $(div).find('textarea').each(function(j, element){
						        $(element).prop('disabled','disabled');
						    });
				    	});
				    });

					$( '#dhl-label-button').remove();
					$( '#shipment-dhl-label-form' ).append(dhl_label_data.print_button);
					$( '#dhl-label-print').attr("href", response.label_url ); // update new url
					$( '#shipment-dhl-label-form' ).append(dhl_label_data.delete_label);

					if( response.tracking_note ) {

						$( '#woocommerce-order-notes' ).block({
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6
							}
						});
						
						var data = {
							action:                   'woocommerce_add_order_note',
							post_id:                  woocommerce_admin_meta_boxes.post_id,
							note_type: 				  response.tracking_note_type,
							note:					  response.tracking_note,
							security:                 woocommerce_admin_meta_boxes.add_order_note_nonce
						};

						$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response_note ) {
							// alert(response_note);
							$( 'ul.order_notes' ).prepend( response_note );
							$( '#woocommerce-order-notes' ).unblock();
							$( '#add_order_note' ).val( '' );
						});							
					}

				}
			});		

			return false;
		},

		delete_dhl_label: function () {

			$( '#shipment-dhl-label-form .wc_dhl_error' ).remove();

			$( '#shipment-dhl-label-form' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
			
			var data = {
				action:                   'wc_shipment_dhl_delete_label',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				pr_dhl_label_nonce:       $( '#pr_dhl_label_nonce' ).val()
			};
			
			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#shipment-dhl-label-form' ).unblock();
				if ( response.error ) {
					$( '#shipment-dhl-label-form' ).append('<p class="wc_dhl_error">Error: ' + response.error + '</p>');
				} else {

					$( '#shipment-dhl-label-form .wc_dhl_delete' ).remove();
					// Enable all form items
					$(function(){ 
						$('#shipment-dhl-label-form').each(function(i, div) {

						    $(div).find('input').each(function(j, element){
						       $(element).removeAttr('disabled');
						    });

						    $(div).find('select').each(function(j, element){
						        $(element).removeAttr('disabled');
						    });

						    $(div).find('textarea').each(function(j, element){
						        $(element).removeAttr('disabled');
						    });

				    	});
				    });
					
					$( '#dhl-label-print').remove();
					$( '#shipment-dhl-label-form' ).append(dhl_label_data.main_button);

					if( response.dhl_tracking_num ) {
						// alert(response.dhl_tracking_num);
						var tracking_note;
						$('ul.order_notes li').each(function(i) {
						   tracking_note = $(this);
						   tracking_note_html = $(this).html()
						   if (tracking_note_html.indexOf(response.dhl_tracking_num) >= 0) {
							
								// var tracking_note = $( this ).closest( 'li.tracking_note' ); 
								$( tracking_note ).block({
									message: null,
									overlayCSS: {
										background: '#fff',
										opacity: 0.6
									}
								});

								var data_note = {
									action:   'woocommerce_delete_order_note',
									note_id:  $( tracking_note ).attr( 'rel' ),
									security: woocommerce_admin_meta_boxes.delete_order_note_nonce
								};

								$.post( woocommerce_admin_meta_boxes.ajax_url, data_note, function() {
									$( tracking_note ).remove();
								});
								
							   	return false;					   	
							}
						});
					}
				}
			});

			return false;
		},

		lock_order_controls: function () {
			$( '#pr_dhl_add_to_order, #pr_dhl_order_remove_item, #pr_dhl_finalize_order' ).attr( 'disabled', 'disabled' );
		},

		unlock_order_controls: function () {
			$( '#pr_dhl_add_to_order, #pr_dhl_order_remove_item, #pr_dhl_finalize_order' ).removeAttr( 'disabled' );
		},

		add_item_to_order: function () {
			var data = {
				action:                   'wc_shipment_dhl_add_order_item',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				pr_dhl_order_nonce:       $( '#pr_dhl_order_nonce' ).val()
			};

			this.lock_order_controls();

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#pr_dhl_order_items_table' ).replaceWith( response );

				this.unlock_order_controls();
			}.bind(this) );
		},

		remove_item_from_order: function ( event ) {
			var click_target = $( event.target );
			var item_row = click_target.closest('tr');
			var item_barcode = item_row.find('.pr_dhl_item_barcode');

			var data = {
				action:                   'wc_shipment_dhl_remove_order_item',
				item_barcode:             item_barcode.text(),
				pr_dhl_order_nonce:       $( '#pr_dhl_order_nonce' ).val()
			};

			this.lock_order_controls();

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				$( '#pr_dhl_order_items_table' ).replaceWith( response );

				this.unlock_order_controls();
			}.bind(this) );
		},

		finalize_order: function () {
			var data = {
				action:                   'wc_shipment_dhl_finalize_order',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				pr_dhl_order_nonce:       $( '#pr_dhl_order_nonce' ).val()
			};

			this.lock_order_controls();

			$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
				this.unlock_order_controls();
			}.bind(this) );
		},
	};
	
	wc_shipment_dhl_label_items.init();

} );
