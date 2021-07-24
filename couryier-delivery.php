<?php
/**
 * @package couryier-delivery
 * @version 1.0.0
 */
/*
Plugin Name: Couryier For WooCommerce
Description: Plugin Enables Option To Add Couryier as shipping provider
Author: Couryier
Version: 1.0.0
* Requires at least: 5.4
* Requires PHP: 7.0

*/

class COURYIER_SHIPPING{
    
    public function __construct(){
        require_once 'utils/functions.php';
        require_once 'classes/class-couryier-api.php';
        register_activation_hook(__FILE__,array($this, 'activate_couryier_shipping_plugin'));
        $this->init_couryier();
        add_action('admin_footer',array($this,'couryier_admin_style_script_enqueue'));
        add_action('admin_menu',array($this,'couryier_menu_item'));
        add_action('wp_ajax_process_order_action',array($this,'process_order_action'));
        add_action('woocommerce_thankyou',array($this,'init_awb_tracking_on_create_order'),10,1);
        
    }

    function init_awb_tracking_on_create_order($order_id){
        $order=wc_get_order($order_id);
        $shipping_method=$order->get_shipping_method();
        if($shipping_method=="Couryier Parcels"){
            update_post_meta($order->get_id(),'awb_tracking_no',false);
            if((new COURYIER_SHIPPING_METHOD())->settings['auto_processing']=="yes"){
                COURYIER_API::processOrderToTimeExpress($order_id);
            }
            
            
        }
    }

    public static function get_client_details(){
        if(!COURYIER_API::getAccountNo())
            return array();
        $client=get_option('tes_user');
        return $client;
        
    }

    function process_order_action(){
        $order_id=intval($_POST['order_id']);
        //$awb_tracking_no=TIMEX_getDataArrayToProcess($order_id);
        $response=COURYIER_API::processOrderToTimeExpress($order_id);
        wp_send_json($response);
    }

    function couryier_admin_style_script_enqueue(){
        wp_enqueue_style('TES_admin_style',plugin_dir_url(__FILE__).'/admin/assets/tes-admin.css');
        wp_register_script('TES_admin_script',plugin_dir_url(__FILE__).'/admin/assets/tes-admin.js');
        $ajax_var=array(
            'ajaxUrl'=>admin_url('admin-ajax.php'),
            'process_order_action'=>'process_order_action'
        );
        wp_localize_script('TES_admin_script','tes',$ajax_var);
        wp_enqueue_script('TES_admin_script');
    }

    function init_couryier(){
        if(!COURYIER_API::getAccountNo())
            return; 
        add_filter('woocommerce_shipping_methods',array($this, 'add_couryier_shipping_method'),10,1);
        add_action('woocommerce_shipping_init',array($this,'couryier_shipping_method'));

    }

    function add_couryier_shipping_method($methods){
        $methods['couryier_shipping']="COURYIER_SHIPPING_METHOD";
        return $methods;
    }

    function couryier_shipping_method(){
        require_once 'classes/class-couryier-shipping.php';
    }
    
    function activate_couryier_shipping_plugin(){
        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){
            die('Plugin NOT activated: ' . "It needs WooCommerce to be activated");
        }
    }

    function make_login_logout(){
        include_once 'admin/couryier-login/login_logout.php';
        return $error;
    }

    function couryier_settings(){
        //TimeExpress Menu Page
        $error=$this->make_login_logout();

        if(!COURYIER_API::getAccountNo()){
            $this->init_login_form($error);
        }
        else{
            $this->couryier_user_settings();
        }
    
    }
    function init_login_form($error){

        include_once('admin/couryier-login/login-form.php');

    }

    function couryier_user_settings(){
            include_once('admin/couryier-settings/couryier-user-settings.php');
    }

    function couryier_new_orders(){
        //New Orders For couryier
        include_once 'admin/couryier-orders.php';

    }

    function couryier_menu_item(){
        add_menu_page(
            'Couryier',
            'Couryier',
            'manage_options',
            'couryier-delivery',
            array($this,'couryier_settings'),
            plugin_dir_url(__FILE__).'/admin/assets/imgs/icon.png',
            5
        );

        if(COURYIER_API::getAccountNo()){
            add_submenu_page(
                'couryier-delivery',
                'New Orders - Couryier Parcels',
                'New Orders',
                'manage_options',
                'new-tc-orders',
                array($this,'couryier_new_orders'),
                0
    
            );
        }
        
    }

}

global $couryier;
$couryier=new COURYIER_SHIPPING;
