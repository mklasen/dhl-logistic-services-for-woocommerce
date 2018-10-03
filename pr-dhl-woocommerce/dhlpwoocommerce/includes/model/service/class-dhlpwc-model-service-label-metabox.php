<?php

if (!defined('ABSPATH')) { exit; }

if (!class_exists('DHLPWC_Model_Service_Label_Metabox')) :

class DHLPWC_Model_Service_Label_Metabox extends DHLPWC_Model_Core_Singleton_Abstract
{

    // TODO: temporarily use a whitelist system due not all options being fully supported yet
    // Either missing input, or not tested, especially country specific options
    protected $whitelist = array(
        // Delivery option
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_PS,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_DOOR,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_BP,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_H, // (requires custom address selection)

        // Service option
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_COD_CASH,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_EXP,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_BOUW,
        //DHLPWC_Model_Meta_Order_Option_Preference::OPTION_REFERENCE2, // (requires a custom layout, it's an additional option for reference option)
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_EXW,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_EA,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_EVE,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_RECAP,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_COD_CHECK,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_INS,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_REFERENCE,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_HANDT,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_NBB,
        //DHLPWC_Model_Meta_Order_Option_Preference::OPTION_ADD_RETURN_LABEL,
        //DHLPWC_Model_Meta_Order_Option_Preference::OPTION_SSN,
        //DHLPWC_Model_Meta_Order_Option_Preference::OPTION_PERS_NOTE, // (will be added later in the API, but for now it's not yet available)
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_SDD,
        DHLPWC_Model_Meta_Order_Option_Preference::OPTION_S,
    );

    public function order_labels($order_id, $labels)
    {
        $content = '';
        if (!empty($labels)) {
            foreach ($labels as $label) {
                $view = new DHLPWC_Template('order.meta.label');
                $content .= $view->render(array(
                    'label_size'        => $label['label_size'],
                    'label_description' => __(sprintf('PARCELTYPE_%s', $label['label_size']), 'dhlpwc'),
                    'tracker_code'      => $label['tracker_code'],
                    'actions'           => $this->get_label_actions($label, $order_id),
                ), false);
            }
        } else {
            $view = new DHLPWC_Template('order.meta.no-label');
            $content .= $view->render(array(), false);
        }

        $view = new DHLPWC_Template('order.meta.label-container');
        return $view->render(array(
            'content' => $content
        ), false);
    }

    public function private_or_business_form($checked)
    {
        $view = new DHLPWC_Template('order.meta.form.to-business');
        return $view->render(array(
            'checked' => $checked,
        ), false);
    }

    public function options_form($order_id, $selected_options, $to_business)
    {
        $service = DHLPWC_Model_Service_Access_Control::instance();
        $allowed_options = $service->check(DHLPWC_Model_Service_Access_Control::ACCESS_CAPABILITY_ORDER_OPTIONS, array(
            'order_id' => $order_id,
            'to_business' => $to_business,
        ));

        // Filter response to options
        $delivery_options = array();
        $service_options = array();
        if ($allowed_options) {
            $service = DHLPWC_Model_Service_Label_Option::instance();

            foreach ($allowed_options as $allowed_option) {
                // TODO temporarily use a whitelist
                if (!in_array($allowed_option->key, $this->whitelist)) {
                    continue;
                }

                /* @var DHLPWC_Model_API_Data_Option $option */
                $option = $allowed_option;
                $option->image_url = $service->get_image_url($option->key);
                $option->description = $service->get_local_description($option->key);
                $option->exclusion_list = array();

                // Making a special case: H is also a delivery_type
                if ($option->key === DHLPWC_Model_Meta_Order_Option_Preference::OPTION_H) {
                    $option->option_type = DHLPWC_Model_API_Data_Option::OPTION_TYPE_DELIVERY;
                }

                if (!empty($option->exclusions)) {
                    foreach($option->exclusions as $exclusion) {
                        $option->exclusion_list[] = $exclusion->key;
                    }
                }

                if (in_array($option->key, $selected_options)) {
                    $option->preselected = true;
                }

                // Update input template
                switch($option->key) {
                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_REFERENCE):
                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_TEXT;
                        $option->input_template_data = array(
                            'placeholder' => __('Reference', 'dhlpwc'),
                            'placeholder2' => __('Second reference', 'dhlpwc'),
                        );
                        break;
//                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_PERS_NOTE):
//                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_TEXT;
//                        $option->input_template_data = array(
//                            'placeholder' => __('Message', 'dhlpwc'),
//                        );
//                        break;
                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_PS):
                        $order_meta_service = new DHLPWC_Model_Service_Order_Meta_Option();
                        $parcelshop = $order_meta_service->get_parcelshop($order_id);

                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_PARCELSHOP;
                        $option->input_template_data = array(
                            'parcelshop' => $parcelshop,
                        );
                        break;
                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_INS):
                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_PRICE;
                        $option->input_template_data = array(
                            'placeholder' => __('In euros (€)', 'dhlpwc'),
                        );
                        break;
//                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_SSN):
//                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_ADDRESS;
//                        $option->input_template_data = array();
//                        break;
                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_COD_CASH):
                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_PRICE;
                        $option->input_template_data = array(
                            'placeholder' => __('In euros (€)', 'dhlpwc'),
                        );
                        break;
                    case (DHLPWC_Model_Meta_Order_Option_Preference::OPTION_H):
                        $option->input_template = DHLPWC_Model_API_Data_Option::INPUT_TEMPLATE_TERMINAL;
                        $option->input_template_data = array();
                        break;
                    default:
                        $option->input_template = null;
                        $option->input_template_data = null;
                }

                if (!empty($option->input_template)) {
                    $view = new DHLPWC_Template('order.meta.form.input.'.$option->input_template);
                    $option->input_template = $view->render($option->input_template_data, false);
                }

                if ($option->option_type === DHLPWC_Model_API_Data_Option::OPTION_TYPE_DELIVERY) {
                    // Making a special case: DOOR goes first
                    if ($option->key === DHLPWC_Model_Meta_Order_Option_Preference::OPTION_DOOR) {
                        array_unshift($delivery_options, $option);
                    } else {
                        $delivery_options[] = $option;
                    }
                } else {
                    $service_options[] = $option;
                }

            }
        }

        $view = new DHLPWC_Template('order.meta.form.options');
        return $view->render(array(
            'delivery_options' => $delivery_options,
            'service_options' => $service_options,
        ), false);
    }

    public function sizes_form($order_id, $selected_options, $to_business)
    {
        $view = new DHLPWC_Template('order.meta.form.sizes-headline');
        $option_texts = $selected_options;
        array_walk($option_texts, function(&$value, &$key) {
            $value = __(sprintf('OPTION_%s', $value), 'dhlpwc');
        });

        $size_view = $view->render(array(
            'message' => implode(' + ', $option_texts),
        ), false);

        $service = DHLPWC_Model_Service_Access_Control::instance();
        $sizes = $service->check(DHLPWC_Model_Service_Access_Control::ACCESS_CAPABILITY_PARCELTYPE, array(
            'order_id' => $order_id,
            'options' => $selected_options,
            'to_business' => $to_business,
        ));

        if (!empty($sizes)) {
            foreach ($sizes as $size) {
                $view = new DHLPWC_Template('order.meta.form.size');
                $size_view .= $view->render(array(
                    'parceltype' => $size,
                    'description' => __(sprintf('PARCELTYPE_%s', $size->key), 'dhlpwc'),
                ), false);

            }
        } else {
            $view = new DHLPWC_Template('order.meta.form.no-sizes');
            $size_view .= $view->render(array(), false);
        }

        return $size_view;
    }

    protected function get_label_actions($label, $post_id)
    {
        $locale = str_replace('_', '-', get_locale());

        $service = DHLPWC_Model_Service_Postcode::instance();
        $postcode = $service->get_postcode_from_order($post_id);

        $service = DHLPWC_Model_Service_Track_Trace::instance();
        $tracking_url = $service->get_url($label['tracker_code'], $postcode, $locale);

        $actions = array(
            array(
                'url'    => $label['pdf']['url'],
                'name'   => __('Download PDF label', 'dhlpwc'),
                'action' => "dhlpwc_action_download",
            ),
            array(
                'url'    => esc_url($tracking_url),
                'name'   => __('Follow track & trace', 'dhlpwc'),
                'action' => "dhlpwc_action_follow_tt",
            ),
            array(
                'url'    => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'name'   => __('Delete PDF label', 'dhlpwc'),
                'action' => "dhlpwc_action_delete",
            ),
        );

        // Create template
        $action_view = '';

        $service = DHLPWC_Model_Service_Access_Control::instance();
        $external_link = $service->check(DHLPWC_Model_Service_Access_Control::ACCESS_OPEN_LABEL_LINKS_EXTERNAL);

        foreach ($actions as $action) {
            $view = new DHLPWC_Template('admin.action-button');
            $action_view .= $view->render(array(
                'action'        => $action,
                'post_id'       => $post_id,
                'label_id'      => $label['label_id'],
                'external_link' => $external_link,
            ), false);
        }

        $view = new DHLPWC_Template('admin.action-button-container');
        return $view->render(array(
            'content'  => $action_view,
        ), false);
    }

}

endif;