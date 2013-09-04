<?php
/**
 * Plugin Name: WooCommerce Gerêncianet Gateway
 * Plugin URI: https://github.com/pedroigor91/Woocommerce-Gerencianet
 * Description: Gateway de pagamento Gerêncianet para WooCommerce.
 * Author: Pedro Igor, claudiosanches
 * Author URI: http://manp.com.br/
 * Version: 0.0.1
 * License: GPLv2 or later
 * Text Domain: wcgerencianet
 * Domain Path: /languages/
 */

add_action('plugins_loaded', 'woocommerce_gerencianet_init', 0);
function woocommerce_gerencianet_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_GerenciaNet extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'gerencianet';
      $this -> medthod_title = 'GerenciaNET';
      $this -> has_fields = false;

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> vendedor_id = $this -> settings['vendedor_id'];
      $this -> token = $this -> settings['token'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> liveurl = 'https://integracao.gerencianet.com.br/xml/cobrancaonline/emite/html';

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      add_action('init', array(&$this, 'check_gerencianet_response'));
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_gerencianet', array(&$this, 'receipt_page'));
   }
    function init_form_fields(){

       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Habilitar/Desabilitar', 'gerencianet'),
                    'type' => 'checkbox',
                    'label' => __('Habilitar o Ger&ecirc;nciaNet.', 'gerencianet'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('T&iacute;tulo:', 'gerencianet'),
                    'type'=> 'text',
                    'description' => __('Este controla o t&iacute;tulo que o usu&aacute;rio v&ecirc; durante o checkout.', 'gerencianet'),
                    'default' => __('GerenciaNET', 'gerencianet')),
                'description' => array(
                    'title' => __('Descri&ccedil;&atilde;o:', 'gerencianet'),
                    'type' => 'textarea',
                    'description' => __('Isto controla a descri&ccedil;&atilde;o que o usu&aacute;rio v&ecirc; durante o checkout.', 'gerencianet'),
                    //'default' => __('', 'gerencianet)
                    ),
                'merchant_id' => array(
                    'title' => __('ID do Usu&aacute;rio', 'mrova'),
                    'type' => 'text',
                    //'description' => __('')),
                //'salt' => array(
                    //'title' => __('Salt', 'mrova'),
                    //'type' => 'text',
                    //'description' =>  __('Given to Merchant by GerenciaNet', 'gerencianet'),
                ),
                'redirect_page_id' => array(
                    'title' => __('P&aacute;gina Voltar'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Selecionar P&aacute;gina'),
                    'description' => "URL da p&aacute;gina de sucesso"
                )
            );
    }

       public function admin_options(){
        echo '<h3>'.__('Ger&ecirc;nciaNet Gateway para Woocommerce', 'mrova').'</h3>';
        echo '<p>'.__('Um dos mais completos do Brasil.').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';

    }

    /**
     *  There are no payment fields for gerencianet, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Obrigado pelo sua pedido, por favor clique no bot&atilde;o abaixo para pagar com o Ger&ecirc;nciaNet.', 'mrova').'</p>';
        echo $this -> generate_gerencianet_form($order);
    }
    /**
     * Generate gerencianet button link
     **/
    public function generate_gerencianet_form($order_id){

        global $woocommerce;

        $order = new WC_Order($order_id);
        $txnid = $order_id.'_'.date("ymds");

        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

        $productinfo = "Pedido $order_id";

        $str = "$this->vendedor_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
        $hash = hash('sha512', $str);

        $gerencianet_args = array(
          'key' => $this -> vendedor_id,
          'txnid' => $txnid,
          'amount' => $order -> order_total,
          'productinfo' => $productinfo,
          'firstname' => $order -> billing_first_name,
          'lastname' => $order -> billing_last_name,
          'address1' => $order -> billing_address_1,
          'address2' => $order -> billing_address_2,
          'city' => $order -> billing_city,
          'state' => $order -> billing_state,
          'country' => $order -> billing_country,
          'zipcode' => $order -> billing_zip,
          'email' => $order -> billing_email,
          'phone' => $order -> billing_phone,
          'surl' => $redirect_url,
          'furl' => $redirect_url,
          'curl' => $redirect_url,
          'hash' => $hash,
          'pg' => 'NB'
          );

        $gerencianet_args_array = array();
        foreach($gerencianet_args as $key => $value){
          $gerencianet_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
        return '<form action="'.$this -> liveurl.'" method="post" id="gerencianet_payment_form">
            ' . implode('', $gerencianet_args_array) . '
            <input type="submit" class="button-alt" id="submit_gerencianet_payment_form" value="'.__('Pagar via Ger&ecirc;nciaNet', 'mrova').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'mrova').'</a>
            <script type="text/javascript">
jQuery(function(){
jQuery("body").block(
        {
            message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'mrova').'",
                overlayCSS:
        {
            background: "#fff",
                opacity: 0.6
    },
    css: {
        padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:"32px"
    }
    });
    jQuery("#submit_gerencianet_payment_form").click();});</script>
            </form>';


    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
        $order = new WC_Order($order_id);
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        );
    }

    /**
     * Check for valid gerencianet server callback
     **/
    function check_gerencianet_response(){
        global $woocommerce;
        if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
            $order_id_time = $_REQUEST['txnid'];
            $order_id = explode('_', $_REQUEST['txnid']);
            $order_id = (int)$order_id[0];
            if($order_id != ''){
                try{
                    $order = new WC_Order($order_id);
                    $merchant_id = $_REQUEST['key'];
                    $amount = $_REQUEST['Amount'];
                    $hash = $_REQUEST['hash'];

                    $status = $_REQUEST['status'];
                    $productinfo = "Pedido $order_id";
                    echo $hash;
                    echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
                    $checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
                    $transauthorised = false;
                    if($order -> status !=='completed'){
                        if($hash == $checkhash)
                        {

                          $status = strtolower($status);

                            if($status=="success"){
                                $transauthorised = true;
                                $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                $this -> msg['class'] = 'woocommerce_message';
                                if($order -> status == 'processing'){

                                }else{
                                    $order -> payment_complete();
                                    $order -> add_order_note('GerenciaNet payment successful<br/>Unnique Id from GerenciaNet: '.$_REQUEST['mihpayid']);
                                    $order -> add_order_note($this->msg['message']);
                                    $woocommerce -> cart -> empty_cart();
                                }
                            }else if($status=="pending"){
                                $this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
                                $this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
                                $order -> add_order_note('GerenciaNet payment status is pending<br/>Unnique Id from GerenciaNet: '.$_REQUEST['mihpayid']);
                                $order -> add_order_note($this->msg['message']);
                                $order -> update_status('on-hold');
                                $woocommerce -> cart -> empty_cart();
                            }
                            else{
                                $this -> msg['class'] = 'woocommerce_error';
                                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
                                //Here you need to put in the routines for a failed
                                //transaction such as sending an email to customer
                                //setting database status etc etc
                            }
                        }else{
                            $this -> msg['class'] = 'error';
                            $this -> msg['message'] = "Security Error. Illegal access detected";

                            //Here you need to simply ignore this and dont need
                            //to perform any operation in this condition
                        }
                        if($transauthorised==false){
                            $order -> update_status('failed');
                            $order -> add_order_note('Failed');
                            $order -> add_order_note($this->msg['message']);
                        }
                        add_action('the_content', array(&$this, 'showMessage'));
                    }}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
                    }

            }



        }

    }

    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
     // get all pages
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gerencianet_gateway($methods) {
        $methods[] = 'WC_GerenciaNet';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gerencianet_gateway' );
}

?>
