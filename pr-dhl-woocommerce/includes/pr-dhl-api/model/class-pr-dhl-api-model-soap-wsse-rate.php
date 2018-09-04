<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class PR_DHL_API_Model_SOAP_WSSE_Rate extends PR_DHL_API_SOAP_WSSE implements PR_DHL_API_Rate {

	private $args = array();

	// 'LI', 'CH', 'NO'
	protected $eu_iso2 = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'RO', 'SI', 'SK', 'ES', 'SE', 'GB');

	public function __construct( ) {
		try {

			parent::__construct( );

		} catch (Exception $e) {
			throw $e;
		}
	}

/*
	protected function validate_field( $key, $value ) {

		try {

			switch ( $key ) {
				case 'weight':
					$this->validate( $value );
					break;
				case 'hs_code':
					$this->validate( $value, 'string', 4, 11 );
					break;
				default:
					parent::validate_field( $key, $value );
					break;
			}
			
		} catch (Exception $e) {
			throw $e;
		}
	}*/

	public function get_dhl_rates( $args ) {
		error_log('get_dhl_rates');
		$this->set_arguments( $args );
		$soap_request = $this->set_message();

		try {
			$soap_client = $this->get_access_token( $args['dhl_settings']['api_user'], $args['dhl_settings']['api_pwd'] );
			PR_DHL()->log_msg( '"getRateRequest" called with: ' . print_r( $soap_request, true ) );

			$response_body = $soap_client->getRateRequest($soap_request);
			// error_log(print_r($soap_client->__getLastRequest(),true));
			// error_log(print_r($response_body,true));

			PR_DHL()->log_msg( 'Response Body: ' . print_r( $response_body, true ) );
		
	
			if( ! empty( $response_body->Provider->Notification->code ) ) {
				throw new Exception( $response_body->Provider->Notification->code . ' - ' . $response_body->Provider->Notification->Message );
			}

			if( is_array( $response_body->Provider->Notification ) ) {
				if( ! empty( $response_body->Provider->Notification[0]->code ) ) {
					throw new Exception( $response_body->Provider->Notification[0]->code . ' - ' . $response_body->Provider->Notification[0]->Message );
				}
			}				
			

			if( isset( $response_body->Provider->Service ) ) {
				return $this->get_returned_rates( $response_body->Provider->Service );
			} else {
				throw new Exception( __('No services returned', 'pr-shipping-dhl') );
			}

		} catch (Exception $e) {
			// error_log('get dhl label Exception');
			// error_log(print_r($soap_client->__getLastRequest(),true));
			PR_DHL()->log_msg( 'Exception Response: ' . print_r( $response_body, true ) );
			throw $e;
		}
	}

	protected function get_returned_rates( $returned_rates ) {
		$new_returned_rates = array();
		foreach ($returned_rates as $key => $value) {
			$new_returned_rates[ $value->type ]['name'] = $value->Charges->Charge[0]->ChargeType;
			$new_returned_rates[ $value->type ]['amount'] = $value->TotalNet->Amount;
			$new_returned_rates[ $value->type ]['delivery_time'] = $value->DeliveryTime;
		}

		return $new_returned_rates;
	}

	public function delete_dhl_label_call( $args ) {
		$soap_request =	array(
					'Version' =>
						array(
								'majorRelease' => '2',
								'minorRelease' => '2'
						),
					'shipmentNumber' => $args['tracking_number']
				);

		try {

			$soap_client = $this->get_access_token( $args['api_user'], $args['api_pwd'] );
			$response_body = $soap_client->deleteShipmentOrder( $soap_request );

		} catch (Exception $e) {
			throw $e;
		}

		if( $response_body->Status->statusCode != 0 ) {
			throw new Exception( sprintf( __('Could not delete label - %s', 'pr-shipping-dhl'), $response_body->Status->statusMessage ) );
		} 
	}

	public function delete_dhl_label( $args ) {
		// Delete the label remotely first
		try {
			$this->delete_dhl_label_call( $args );
		} catch (Exception $e) {
			throw $e;			
		}

		// Then delete file
		$upload_path = wp_upload_dir();
		$label_path = str_replace( $upload_path['url'], $upload_path['path'], $args['label_url'] );
		
		if( file_exists( $label_path ) ) {
			$res = unlink( $label_path );
			
			if( ! $res ) {
				throw new Exception( __('DHL Label could not be deleted!', 'pr-shipping-dhl' ) );
			}
		}

	}

	protected function save_label_file( $order_id, $format, $label_data ) {
		$label_name = 'dhl-label-' . $order_id . '.' . $format;
		$upload_path = wp_upload_dir();
		$label_path = $upload_path['path'] . '/'. $label_name;
		$label_url = $upload_path['url'] . '/'. $label_name;

		if( validate_file($label_path) > 0 ) {
			throw new Exception( __('Invalid file path!', 'pr-shipping-dhl' ) );
		}

		// $label_data_decoded = base64_decode($label_data);
		$label_data_decoded = file_get_contents( $label_data );

		$file_ret = file_put_contents( $label_path, $label_data_decoded );
		
		if( empty( $file_ret ) ) {
			throw new Exception( __('DHL Label file cannot be saved!', 'pr-shipping-dhl' ) );
		}

		return $label_url;
	}

	protected function set_arguments( $args ) {
		// Validate set args
		if ( empty( $args['dhl_settings']['api_user'] ) ) {
			throw new Exception( __('Please, provide the username in the DHL shipping settings', 'pr-shipping-dhl' ) );
		}

		if ( empty( $args['dhl_settings']['api_pwd'] )) {
			throw new Exception( __('Please, provide the password for the username in the DHL shipping settings', 'pr-shipping-dhl') );
		}

		// Validate order details
		if ( empty( $args['dhl_settings']['account_num'] ) ) {
			throw new Exception( __('Please, provide an account in the DHL shipping settings', 'pr-shipping-dhl' ) );
		}

		// Convert weight
		$args['order_details']['weight'] = $this->maybe_convert_weight( $args['order_details']['weight'] );

		$this->args = $args;
	}
	

	protected function set_message() {
		if( ! empty( $this->args ) ) {
			
			if( isset( $this->args['dhl_settings']['cutoff_time'] ) ) {
				// Get current date/time
				$today = new DateTime();
				$today_timestamp = $today->getTimestamp();
				// If passed cut off time, check next day's delivery NOT today's
				if( $today_timestamp >= strtotime( $this->args['dhl_settings']['cutoff_time'] ) ) {
					// Add 1 day
					$today->add( new DateInterval('P1D') );
					// Set time to 09:00 since it's next day, otherwise time could be set late
					$ship_time_stamp = $today->format('Y-m-d\T09:00:00\G\M\TP');
				} else {
					$ship_time_stamp = $today->format('Y-m-d\TH:i:s\G\M\TP');
				}

			}

			$unit_of_measure = $this->get_unit_of_measure();

			$dhl_label_body = 
				array(
					'Version' =>
						array(
								'majorRelease' => '2',
								'minorRelease' => '2'
						),
					'RequestedShipment' => 
						array (
								'DropOffType' => 'REGULAR_PICKUP',
								'Ship' => 
									array( 
										'Shipper' =>
											array(
													'StreetLines' => $this->args['dhl_settings']['shipper_address'],
													'StreetLines2' => $this->args['dhl_settings']['shipper_address2'],
													'City' => $this->args['dhl_settings']['shipper_address_city'],
													'StateOrProvinceCode' => $this->args['dhl_settings']['shipper_address_state'],
													'PostalCode' => $this->args['dhl_settings']['shipper_address_zip'],
													'CountryCode' => $this->args['dhl_settings']['shipper_country']
											),
										'Recipient' =>
											array(
													'StreetLines' => $this->args['shipping_address']['address'],
													'StreetLines2' => $this->args['shipping_address']['address_2'],
													'City' => $this->args['shipping_address']['city'],
													'StateOrProvinceCode' => $this->args['shipping_address']['state'],
													'PostalCode' => $this->args['shipping_address']['postcode'],
													'CountryCode' => $this->args['shipping_address']['country']
											),									
									),
								'Packages' => 
									array(
										'RequestedPackages' =>
											array(
												'number' =>  1,
												'Weight' =>  
													array(
														'Value' => $this->args['order_details']['weight']
													),
												'Dimensions' =>
													array(
														'Length' =>  1, // CONVERT ALL TO CM ALWAYS!
														'Width' =>  1,
														'Height' =>  1,
													),
											),
									),
								'NextBusinessDay' => 'Y',
								// 'ShipTimestamp' => date('Y-m-d\TH:i:s\G\M\TP', time() + 60*60*24 ),
								// 'ShipTimestamp' => date('Y-m-d\TH:i:s\G\M\TP', time() ), // 2018-03-05T15:33:16GMT+01:00
								'ShipTimestamp' => $ship_time_stamp, // 2018-03-05T15:33:16GMT+01:00
								'UnitOfMeasurement' => $unit_of_measure,
								// 'Content' => 'NON_DOCUMENTS', // 'NON_DOCUMENTS' for non-EU and 'DOCUMENT' for EU packages!
								'PaymentInfo' => 'DDP',
								'Account' => $this->args['dhl_settings']['account_num'],
						),
				);

			if( PR_DHL()->is_crossborder_shipment( $this->args['shipping_address']['country'] ) ) {
				$dhl_label_body['RequestedShipment']['Content'] = 'NON_DOCUMENTS';
			} else {
				$dhl_label_body['RequestedShipment']['Content'] = 'DOCUMENTS';
			}

			// error_log(print_r($dhl_label_body,true));
			// Unset/remove any items that are empty strings or 0, even if required!
			$this->body_request = $this->walk_recursive_remove( $dhl_label_body );

			return $this->body_request;
		}
		
	}	
}
