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
        $this->method_title   = __( 'Ger&ecirc;ncianet', 'wcgerencianet' );

        // API URLs.
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
        echo '<h3>' . __( 'Ger&ecirc;ncianet standard', 'wcgerencianet' ) . '</h3>';
        echo '<p>' . __( 'Ger&ecirc;ncianet standard works by sending the user to Ger&ecirc;ncianet to enter their payment information.', 'wcgerencianet' ) . '</p>';

        // Checks if is valid for use.
        if ( ! $this->is_valid_for_use() ) {
            echo '<div class="inline error"><p><strong>' . __( 'Ger&ecirc;ncianet Disabled', 'wcgerencianet' ) . '</strong>: ' . __( 'Works only with Brazilian Real.', 'wcgerencianet' ) . '</p></div>';
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
                'label' => __( 'Enable Ger&ecirc;ncianet standard', 'wcgerencianet' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcgerencianet' ),
                'desc_tip' => true,

                'default' => __( 'Ger&ecirc;ncianet', 'wcgerencianet' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcgerencianet' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcgerencianet' ),
                'default' => __( 'Pay via Ger&ecirc;ncianet', 'wcgerencianet' )
            ),
            'token' => array(
                'title' => __( 'Ger&ecirc;ncianet Token', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'Please enter your Ger&ecirc;ncianet token. This is needed to process the payment and notifications', 'wcgerencianet' ),
                'default' => ''
            ),
            'invoice_prefix' => array(
                'title' => __( 'Invoice Prefix', 'wcgerencianet' ),
                'type' => 'text',
                'description' => __( 'Please enter a prefix for your invoice numbers. If you use your Ger&ecirc;ncianet account for multiple stores ensure this prefix is unqiue as Ger&ecirc;ncianet will not allow orders with the same invoice number.', 'wcgerencianet' ),
                'desc_tip' => true,
                'default' => 'WC-'
            ),
            'testing' => array(
                'title' => __( 'Gateway Testing', 'wcgerencianet' ),
                'type' => 'title',
                'description' => ''
            ),
            'sandbox' => array(
                'title' => __( 'Ger&ecirc;ncianet Sandbox', 'wcgerencianet' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Ger&ecirc;ncianet sandbox', 'wcgerencianet' ),
                'default' => 'no',
                'description' => __( 'Ger&ecirc;ncianet sandbox can be used to test payments.', 'wcgerencianet' ),
            ),
            'debug' => array(
                'title' => __( 'Debug Log', 'wcgerencianet' ),
                'type' => 'checkbox',
                'label' => __( 'Enable logging', 'wcgerencianet' ),
                'default' => 'no',
                'description' => sprintf( __( 'Log Ger&ecirc;ncianet events, such as API requests, inside %s', 'wcgerencianet' ), '<code>woocommerce/logs/gerencianet-' . sanitize_file_name( wp_hash( 'gerencianet' ) ) . '.txt</code>' )
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

        $xml = new WC_GerenciaNet_SimpleXML( '<?xml version="1.0" encoding="utf-8"  ?><cobrancaonline></cobrancaonline>' );

        $xml->addChild( 'token', $this->token );
        $node_clients = $xml->addChild( 'clientes' );
        $node_client = $node_clients->addChild( 'cliente' );

        $node_client->addChild( 'email' )->addCData( $order->billing_email );
        $node_client_options = $node_client->addChild( 'opcionais' );
        $return = 'woocommerce_' . $order->id;

        $node_client_options->addChild( 'nomeRazaoSocial' )->addCData( $order->billing_first_name . ' ' . $order->billing_last_name );
        $node_client_options->addChild( 'retorno', $return );

        // Shipping info.
        if ( isset( $order->billing_postcode ) && ! empty( $order->billing_postcode ) ) {
            // Address info
            $node_client_options->addChild( 'rua' )->addCData( $order->billing_address_1 );
            if ( ! empty( $order->billing_address_2 ) )
                $node_client_options->addChild( 'complemento' )->addCData( $order->billing_address_2 );
            $node_client_options->addChild( 'cep', str_replace( array( '-', ' ' ), '', $order->billing_postcode ) );
            $node_client_options->addChild( 'cidade' )->addCData( $order->billing_city );
            $node_client_options->addChild( 'estado', $order->billing_state );
        }

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

            // Tax
            if ( $order->get_total_tax() > 0 ) {
                $tax_item = $node_items->addChild( 'item' );
                $tax_item->addChild( 'descricao', 'Taxa' );
                $tax_item->addChild( 'valor', $this->format_money( $order->get_total_tax() ) );
                $tax_item->addChild(  'qtde', 1 );
            }
        }

        $node_xml_options = $xml->addChild( 'opcionais' );

        // Shipping Costs
        $shipping_value = $this->format_money( $order->get_shipping() );
        $shipping_tax   = $this->format_money( $order->get_shipping_tax() );
        $shipping_total = $shipping_value + $shipping_tax;

        $node_shipping = $node_xml_options->addChild( 'frete' );
        $node_shipping->addChild( 'tipo', 'fixo' );
        $node_shipping->addChild( 'pesoOuValor', $shipping_total );

        // Discount.
        if ( $order->get_order_discount() > 0 )
            $node_xml_options->addChild( 'descontoSobreTotal', $this->format_money( $order->get_order_discount() ) );

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
            'method'    => 'POST',
            'sslverify' => false,
            'timeout'   => 30
        );

        // Sets the payment url.
        if ( 'yes' == $this->sandbox )
            $url = $this->dev_cobranca;
        else
            $url = $this->prod_cobranca;

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
				$chave = $response_data->resposta->cobrancasGeradas->cliente->cobranca->chave;
				$retorno = $response_data->resposta->cobrancasGeradas->cliente->cobranca->retorno;
				$valor = $response_data->resposta->cobrancasGeradas->cliente->cobranca->valor;
				$token = $this->token;
				$versao = '1.0';
				$metodo = 'cobrancaonline';
				
				// TODO: Definir URL de Callback
				//$callback = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_GerenciaNet_Gateway', home_url( '/' ) ) );
				
				// Sets the post params.
				$params = array(
					'body'      => array( 
						'chave'    => $chave,
						'retorno'  => $retorno,
						'valor'    => $valor,
						'versao'   => $versao,
						'link'     => $link,
						'callback' => $callback,
						'token'    => $token,
						'metodo'   => $metodo
					),
					'method'    => 'POST',
					'sslverify' => false,
					'timeout'   => 30
				);
				
				$urlArmazenarDadosParaCallback = 'https://integracao.gerencianet.com.br/callback/armazenar/woocommerce';
				if ( 'yes' != $this->sandbox ) {
					wp_remote_post( $url, $params );					
				}
				
                return $link;
            } else {
                $statusErro = $response_data->resposta->erro->status;

                // Cobranca ja foi gerada anteriormente
                if ( 1012 == $statusErro ) {
                    $link = (string) $response_data->resposta->erro->entrada->emitirCobranca->resposta->cobrancasGeradas->cliente->cobranca->link;
                    return $link;
                }

                // Retorno ja utilizado anteriormente
                if ( 195 == $statusErro ) {
                    $this->add_error( '<strong>Ger&ecirc;ncianet</strong>: ' . __( 'Já foi gerado uma cobrança com este número de pedido. Por favor avisar ao responsável pelo sistema.', 'wcgerencianet' ) );

                    return false;
                }

                // Conta nao possui permissao para emitir com cartao de credito
                if ( 1106 == $statusErro ) {
                    // Added error message.
                    $this->add_error( '<strong>Ger&ecirc;ncianet</strong>: ' . __( 'Por favor, entre em contato com o responsável pelo sistema pedindo liberação para compras com cartão de crédito.', 'wcgerencianet' ) );

                    return false;
                }

                // Email enviado eh invalido
                if ( 124 == $statusErro ) {
                    $this->add_error( '<strong>Ger&ecirc;ncianet</strong>: ' . __( 'O email utilizado para gerar a cobrança é inválido.', 'wcgerencianet' ) );

                    return false;
                }
            }
        }

        // Added error message.
        $this->add_error( '<strong>Ger&ecirc;ncianet</strong>: ' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcgerencianet' ) );

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
        echo '<div class="error"><p><strong>' . __( 'Ger&ecirc;ncianet Disabled', 'wcgerencianet' ) . '</strong>: ' . sprintf( __( 'You should inform your token. %s', 'wcgerencianet' ), '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_GerenciaNet_Gateway' ) . '">' . __( 'Click here to configure!', 'wcgerencianet' ) . '</a>' ) . '</p></div>';
    }
}
