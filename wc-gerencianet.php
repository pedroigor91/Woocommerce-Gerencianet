<?php
/**
 * Plugin Name: WooCommerce Gerêncianet Gateway
 * Plugin URI: https://github.com/pedroigor91/Woocommerce-Gerencianet
 * Description: Gateway de pagamento Gerêncianet para WooCommerce.
 * Author: Pedro Igor, Claudio Sanches, Alessandro Alcantara
 * Author URI: https://github.com/pedroigor91/Woocommerce-Gerencianet/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: wcgerencianet
 * Domain Path: /languages/
 */

define( 'WOO_GERENCIANET_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_GERENCIANET_URL', plugin_dir_url( __FILE__ ) );

/**
 * WooCommerce fallback notice.
 */
function wcgerencianet_woocommerce_fallback_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Ger&ecirc;ncianet Gateway depends on the last version of %s to work!', 'wcgerencianet' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
}

/**
 * Load functions.
 */
function wcgerencianet_gateway_load() {

    // Checks with WooCommerce is installed.
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcgerencianet_woocommerce_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'wcgerencianet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to WooCommerce.
     *
     * @param  array $methods WooCommerce payment methods.
     *
     * @return array          Payment methods with Ger&ecirc;ncianet.
     */
    function wcgerencianet_add_gateway( $methods ) {
        $methods[] = 'WC_Gerencianet_Gateway';

        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'wcgerencianet_add_gateway' );

    // Include the WC_Gerencianet_Gateway class.
    require_once WOO_GERENCIANET_PATH . 'includes/class-wc-gerencianet-gateway.php';
}

add_action( 'plugins_loaded', 'wcgerencianet_gateway_load', 0 );

/**
 * Hides the Ger&ecirc;ncianet with payment method with the customer lives outside Brazil
 *
 * @param  array $available_gateways Default Available Gateways.
 *
 * @return array                    New Available Gateways.
 */
function wcgerencianet_hides_when_is_outside_brazil( $available_gateways ) {

    // Remove standard shipping option.
    if ( isset( $_REQUEST['country'] ) && 'BR' != $_REQUEST['country'] )
        unset( $available_gateways['gerencianet'] );

    return $available_gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'wcgerencianet_hides_when_is_outside_brazil' );

/**
 * Adds custom settings url in plugins page.
 *
 * @param  array $links Default links.
 *
 * @return array        Default links and settings link.
 */
function wcgerencianet_action_links( $links ) {

    $settings = array(
        'settings' => sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Gerencianet_Gateway' ),
            __( 'Settings', 'wcgerencianet' )
        )
    );

    return array_merge( $settings, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcgerencianet_action_links' );
