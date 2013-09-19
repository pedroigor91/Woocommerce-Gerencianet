<?php
/**
 * WC_GerenciaNet_Gateway Gateway Class.
 *
 * Built the GerenciaNet method.
 *
 * @since 0.0.1
 */
class WC_GerenciaNet_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id             = 'gerencianet';
        $this->icon           = apply_filters( 'woocommerce_gerencianet_icon', WOO_GERENCIANET_URL . 'images/gerencianet.png' );
        $this->has_fields     = false;
        $this->method_title   = __( 'Ger&ecirc;nciaNet', 'wcgerencianet' );

        // API URLs.
        $this->prod_boleto = 'https://integracao.gerencianet.com.br/xml/boleto/emite/xml';
        $this->dev_boleto  = 'https://testeintegracao.gerencianet.com.br/xml/boleto/emite/xml';
        $this->prod_cobranca = 'https://integracao.gerencianet.com.br/xml/cobrancaonline/emite/xml';
        $this->dev_cobranca = 'https://testeintegracao.gerencianet.com.br/xml/cobrancaonline/emite/xml';

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->get_option( 'title' );
        $this->description    = $this->get_option( 'description' );
        $this->token          = $this->get_option( 'token' );
        $this->invoice_prefix = $this->get_option( 'invoice_prefix' );
        $this->sandbox        = $this->get_option( 'sandbox' );
        $this->debug          = $this->get_option( 'debug' );

        // Actions.
        add_action( 'woocommerce_api_wc_gerencianet_gateway', array( $this, 'check_ipn_response' ) );
        add_action( 'valid_gerencianet_ipn_request', array( $this, 'successful_request' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Valid for use.
        $this->enabled = ( 'yes' == $this->get_option( 'enabled' ) ) && ! empty( $this->token ) && $this->is_valid_for_use();

        // Checks if token is not empty.
        if ( empty( $this->token ) )
            add_action( 'admin_notices', array( $this, 'token_missing_message' ) );

        // Active logs.
        if ( 'yes' == $this->debug )
            $this->log = $woocommerce->logger();
    }

    /**
     * Check if this gateway is enabled and available in the user's country.
     *
     * @return bool
     */
    public function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), array( 'BRL' ) ) )
            return false;

        return true;
    }

    /**
     * Admin Panel Options.
     */
    public function admin_options() {
        echo '<h3>' . __( 'Ger&ecirc;nciaNet standard', 'wcgerencianet' ) . '</h3>';
        echo '<p>' . __( 'Ger&ecirc;nciaNet standard works by sending the user to Ger&ecirc;nciaNet to enter their payment information.', 'wcgerencianet' ) . '</p>';

        // Checks if is valid for use.
        if ( ! $this->is_valid_for_use() ) {
            echo '<div class="inline error"><p><strong>' . __( 'Ger&ecirc;nciaNet Disabled', 'wcgerencianet' ) . '</strong>: ' . __( 'Works only with Brazilian Real.', 'wcgerencianet' ) . '</p></div>';
        } else {
            // Generate the HTML For the settings form.
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
    }

    /**
     * Initialise Gateway Settings Form Fields.
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'wcgerencianet' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Ger&ecirc;nciaNet standard', 'wcgerencianet' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcgerencianet' ),
                'desc_tip' => true,

                'default' => __( 'Ger&ecirc;nciaNet', 'wcgerencianet' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcgerencianet' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcgerencianet' ),
                'default' => __( 'Pay via Ger&ecirc;nciaNet', 'wcgerencianet' )
            ),
            'token' => array(
                'title' => __( 'Ger&ecirc;nciaNet Token', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'Please enter your Ger&ecirc;nciaNet token. This is needed to process the payment and notifications', 'wcgerencianet' ),
                'default' => ''
            ),
            'invoice_prefix' => array(
                'title' => __( 'Invoice Prefix', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'Please enter a prefix for your invoice numbers. If you use your Ger&ecirc;nciaNet account for multiple stores ensure this prefix is unqiue as Ger&ecirc;nciaNet will not allow orders with the same invoice number.', 'wcgerencianet' ),
                'desc_tip' => true,
                'default' => 'WC-'
            ),
            'testing' => array(
                'title' => __( 'Gateway Testing', 'wcgerencianet' ),
                'type' => 'title',
                'description' => ''
            ),
            'sandbox' => array(
                'title' => __( 'Ger&ecirc;nciaNet Sandbox', 'wcgerencianet' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Ger&ecirc;nciaNet sandbox', 'wcgerencianet' ),
                'default' => 'no',
                'description' => __( 'Ger&ecirc;nciaNet sandbox can be used to test payments.', 'wcgerencianet' ),
            ),
            'debug' => array(
                'title' => __( 'Debug Log', 'wcgerencianet' ),
                'type' => 'checkbox',
                'label' => __( 'Enable logging', 'wcgerencianet' ),
                'default' => 'no',
                'description' => sprintf( __( 'Log Ger&ecirc;nciaNet events, such as API requests, inside %s', 'wcgerencianet' ), '<code>woocommerce/logs/gerencianet-' . sanitize_file_name( wp_hash( 'gerencianet' ) ) . '.txt</code>' )
            )
        );
    }

    /**
     * Add error message in checkout.
     *
     * @param string $message Error message.
     *
     * @return string         Displays the error message.
     */
    protected function add_error( $message ) {
        global $woocommerce;

        if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) )
            wc_add_error( $message );
        else
            $woocommerce->add_error( $message );
    }

    /**
     * Format money.
     *
     * @param  string $value WooCommerce default value.
     *
     * @return string        Value without dot or comma.
     */
    protected function format_money( $value ) {
        return str_replace( array( ',', '.' ), '', $value );
    }

    /**
     * Generate the payment xml.
     *
     * @param object  $order Order data.
     *
     * @return string        Payment xml.
     */
    protected function generate_payment_xml( $order ) {
        // Include the WC_GerenciaNet_SimpleXML class.
        require_once WOO_GERENCIANET_PATH . 'includes/class-wc-gerencianet-simplexml.php';

        $xml = new WC_GerenciaNet_SimpleXML( '<?xml version="1.0" encoding="utf-8"  ?><boleto></boleto>' );

        $xml->addChild( 'token', $this->token );
        $node_clients = $xml->addChild( 'clientes' );
        $node_client = $node_clients->addChild( 'cliente' );

        $node_client->addChild( 'nomeRazaoSocial' )->addCData( $order->billing_first_name . ' ' . $order->billing_last_name );
        $node_client_options = $node_client->addChild( 'opcionais' );
        $return = 'woocommerce_' . $order->id;

        $node_client_options->addChild( 'email', $order->billing_email );
        //$node_client_options->addChild( 'cep', str_replace( array( '-', ' ' ), '', $order->billing_postcode ) );
        //$node_client_options->addChild( 'rua' )->addCData( $order->billing_address_1 );
        //$node_client_options->addChild( 'complemento' )->addCData( $order->billing_address_2 );
        //$node_client_options->addChild( 'estado', $order->billing_state );
        //$node_client_options->addChild( 'cidade' )->addCData( $order->billing_city );
        //$node_client_options->addChild( 'retorno', $return );

        // Shipping info.
        if ( isset( $order->billing_postcode ) && ! empty( $order->billing_postcode ) ) {
            $shipping = $xml->addChild( 'shipping' );
            $shipping->addChild( 'type', 3 );

            // Address infor
            $address = $shipping->addChild( 'address' );
            $node_client_options->addChild( 'rua' )->addCData( $order->billing_address_1 );
            // $address->addChild( 'number', '' );
            if ( ! empty( $order->billing_address_2 ) )
                $node_client_options->addChild( 'complemento' )->addCData( $order->billing_address_2 );
            // $address->addChild( 'district' )->addCData( '' );
            $node_client_options->addChild( 'cep', str_replace( array( '-', ' ' ), '', $order->billing_postcode ) );
            $node_client_options->addChild( 'cidade' )->addCData( $order->billing_city );
            $node_client_options->addChild( 'estado', $order->billing_state );
            $address->addChild( 'pais', 'BRA' );
        }

        //Items.
        $items = $xml->addChild( 'items' );

        // If prices include tax or have order discounts, send the whole order as a single item.
        if ( 'yes' == get_option( 'woocommerce_prices_include_tax' ) || $order->get_order_discount() > 0 ) {

            // Discount.
            if ( $order->get_order_discount() > 0 )
                $xml->addChild( 'extraAmount', '-' . $order->get_order_discount() );

            $item_names = array();

            if ( sizeof( $order->get_items() ) > 0 ) {
                foreach ( $order->get_items() as $order_item ) {
                    if ( $order_item['qty'] )
                        $item_names[] = $order_item['name'] . ' x ' . $order_item['qty'];
                }
            }

            $item = $items->addChild( 'item' );
            $item->addChild( 'id', 1 );
            $item->addChild( 'descricao' )->addCData( substr( sprintf( __( 'Order %s', 'wcgerencianet' ), $order->get_order_number() ) . ' - ' . implode( ', ', $item_names ), 0, 95 ) );
            $item->addChild( 'valor', number_format( $order->get_total() - $order->get_shipping() - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '' ) );
            $item->addChild( 'qtde', 1 );

            if ( ( $order->get_shipping() + $order->get_shipping_tax() ) > 0 )
                $shipping->addChild( 'cost', number_format( $order->get_shipping() + $order->get_shipping_tax(), 2, '.', '' ) );

        } else {

        // TODO: precisa melhorar isso para aceitar taxas e descontos.
        $item_loop = 0;
        if ( sizeof( $order->get_items() ) > 0 ) {

            $node_items = $xml->addChild( 'itens' );

            foreach ( $order->get_items() as $order_item ) {
                if ( $order_item['qty'] ) {
                    $item_name = $order_item['name'];
                    $item_meta = new WC_Order_Item_Meta( $order_item['item_meta'] );

                    if ( $meta = $item_meta->display( true, true ) )
                        $item_name .= ' - ' . $meta;

                    $node_item = $node_items->addChild( 'item' );
                    $item_name = substr( sanitize_text_field( $item_name ), 0, 95 );
                    $item_value = $this->format_money( $order->get_item_total( $order_item, false ) );

                    $node_item->addChild( 'descricao' )->addCData( substr( sanitize_text_field( $item_name ), 0, 95 ) );
                    $node_item->addChild( 'valor', $item_value );
                    $node_item->addChild( 'qtde', $order_item['qty'] );
                }
            }
        }

            // Shipping Cost item.
            if ( $order->get_shipping() > 0 )
                $shipping->addChild( 'cost', number_format( $order->get_shipping(), 2, '.', '' ) );

            // Extras Amount.
            $xml->addChild( 'extraAmount', $order->get_total_tax() );
        }

        // Filter the payment data.
        $xml = apply_filters( 'woocommerce_gerencianet_payment_xml', $xml, $order );

        return $xml->asXML();
    }

    /**
     * Generate Payment URL.
     *
     * @param object $order Order data.
     *
     * @return bool
     */
    public function generate_payment_url( $order ) {
        global $woocommerce;

        // Sets the xml.
        $xml = $this->generate_payment_xml( $order );

        if ( 'yes' == $this->debug )
            $this->log->add( 'gerencianet', 'Requesting token for order ' . $order->get_order_number() . ' with the following data: ' . $xml );

        // Sets the post params.
        $params = array(
            'body'      => array( 'entrada' => $xml ),
            'sslverify' => false,
            'timeout'   => 60,
            'headers'   => array(
                'Content-Type' => 'application/xml;charset=UTF-8',
            )
        );

        // Sets the payment url.
        if ( 'yes' == $this->sandbox )
            $url = $this->dev_boleto;
        else
            $url = $this->prod_boleto;

        $response = wp_remote_post( $url, $params );

        if ( is_wp_error( $response ) ) {
            if ( 'yes' == $this->debug )
                $this->log->add( 'gerencianet', 'WP_Error in generate payment token: ' . $response->get_error_message() );
        } else {

            if ( 'yes' == $this->debug )
                $this->log->add( 'gerencianet', 'Ger&ecirc;nciaNet payment response: ' . print_r( $response, true ) );

            $response_data = new SimpleXmlElement( $response['body'], LIBXML_NOCDATA );

            /**
             * StatusCod 2 - Emissao ocorreu com sucesso
             * StatusCod 1 - Emissao teve erro
             */

            // TODO: Tratar erros
            // TODO: Este codigo abaixo nao funciona para o ambiente de teste, o ambiente de teste nao retorna link na resposta
            if ( 2 == $response_data->statusCod ) {
                $link = $response_data->resposta->cobrancasGeradas->cliente->cobranca->link;
                return $link;
            } else {
                $statusErro = $response_data->resposta->erro->status;

                /**
                 * Cobranca ja foi gerada anteriormente
                 */
                if ( 1012 == $statusErro ) {
                    $link = (string) $response_data->resposta->erro->entrada->emitirCobranca->resposta->cobrancasGeradas->cliente->cobranca->link;
                    return $link;
                }

                /**
                 * Retorno ja utilizado anteriormente
                 */
                if ( 195 == $statusErro ) {
                    $link = (string) $response_data->resposta->erro->entrada;
                    return $link;
                }
            }
        }

        // Added error message.
        $this->add_error( '<strong>Ger&ecirc;nciaNet</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcgerencianet' ) );

        return false;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int    $order_id Order ID.
     *
     * @return array           Redirect.
     */
    public function process_payment( $order_id ) {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        $url = $this->generate_payment_url( $order );

        if ( $url ) {
            // Remove cart.
            $woocommerce->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => esc_url_raw( $url )
            );
        }
    }

    /**
     * Adds error message when not configured the token.
     *
     * @return string Error Mensage.
     */
    public function token_missing_message() {
        echo '<div class="error"><p><strong>' . __( 'Ger&ecirc;nciaNet Disabled', 'wcgerencianet' ) . '</strong>: ' . sprintf( __( 'You should inform your token. %s', 'wcgerencianet' ), '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_GerenciaNet_Gateway' ) . '">' . __( 'Click here to configure!', 'wcgerencianet' ) . '</a>' ) . '</p></div>';
    }
}
