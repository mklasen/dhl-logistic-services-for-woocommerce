<?php

if (!defined('ABSPATH')) { exit; }

if (!class_exists('DHLPWC_Controller_Checkout')) :

class DHLPWC_Controller_Checkout
{

    public function __construct()
    {
        add_filter('woocommerce_validate_postcode', array($this, 'validate_postcode'), 10, 3);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'add_option_meta'), 10, 2);

        add_action('wp_loaded', array($this, 'set_parcelshop_hooks'));
    }

    public function set_parcelshop_hooks()
    {
        $service = DHLPWC_Model_Service_Access_Control::instance();
        if ($service->check(DHLPWC_Model_Service_Access_Control::ACCESS_CHECKOUT_PARCELSHOP)) {
            add_action('woocommerce_after_checkout_validation', array($this, 'validate_parcelshop_selection'), 10, 2);
        }
    }

    /**
     * Add The Netherlands, Belgium and Luxembourg to the postcode check (missing in default WooCommerce)
     *
     * @param $valid
     * @param $postcode
     * @param $country
     * @return bool
     */
    public function validate_postcode($valid, $postcode, $country)
    {
        switch ($country) {
            case 'NL' :
            case 'BE' :
            case 'LU' :
            case 'CH' :
                $service = DHLPWC_Model_Service_Postcode::instance();
                $valid = $service->validate($postcode, $country);
                break;
        }
        return $valid;
    }

    public function add_option_meta($order_id, $data)
    {
        $service = DHLPWC_Model_Service_Shipping_Preset::instance();
        $presets = $service->get_presets();

        foreach($presets as $preset_data) {
            $preset = new DHLPWC_Model_Meta_Shipping_Preset($preset_data);

            if (isset($data['shipping_method']) && is_array($data['shipping_method']) && in_array('dhlpwc-'.$preset->frontend_id, $data['shipping_method'])) {
                $service = new DHLPWC_Model_Service_Order_Meta_Option();
                foreach($preset->options as $option) {
                    if ($option === DHLPWC_Model_Meta_Order_Option_Preference::OPTION_PS) {
                        list($parcelshop_id, $country) = WC()->session->get('dhlpwc_parcelshop_selection_sync');
                        $service->save_option_preference($order_id, $option, $parcelshop_id);
                    } else {
                        $service->save_option_preference($order_id, $option);
                    }
                }
            }
        }
    }

    public function validate_parcelshop_selection($data, $errors)
    {
        if (isset($data['shipping_method']) && is_array($data['shipping_method']) && in_array('dhlpwc-parcelshop', $data['shipping_method'])) {
            list($parcelshop_id, $country) = WC()->session->get('dhlpwc_parcelshop_selection_sync');
            if (empty($parcelshop_id) || empty($country)) {
                $errors->add('dhlpwc_parcelshop_selection_sync', __('Choose a DHL ServicePoint', 'dhlpwc'));
            }
            $shipping_country = WC()->customer->get_shipping_country();
            if ($country != $shipping_country) {
                $errors->add('dhlpwc_parcelshop_selection_sync_country', __('The DHL ServicePoint country cannot be different than the shipping address country.', 'dhlpwc'));
            }
        }
    }

}

endif;