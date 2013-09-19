<?php
/**
 * WC Gerencianet Helpers Class.
 *
 * Gerêncianet payment helpers.
 *
 * @since 0.1
 */
class WC_Gerencianet_Helpers {

    /**
     * Payment type name.
     *
     * @param  int    $value Type number.
     *
     * @return string        Type name.
     */
    public function payment_type( $value ) {
        switch ( $value ) {
            case 1:
                $type = __( 'Credit Card', 'wcgerencianet' );
                break;
            case 2:
                $type = __( 'Billet', 'wcgerencianet' );
                break;
            case 3:
                $type = __( 'Online Debit', 'wcgerencianet' );
                break;
            default:
                $type = __( 'Unknown', 'wcgerencianet' );
                break;
        }

        return $type;
    }

    /**
     * Payment method name.
     *
     * @param  int    $value Method number.
     *
     * @return string        Method name.
     */
    public function payment_method( $value ) {
        $credit_card = __( 'Credit Card', 'wcpagseguro' );
        $billet = __( 'Billet', 'wcpagseguro' );
        $online_debit = __( 'Online Debit', 'wcpagseguro' );

        switch ( $value ) {
            case 101:
                $method = $credit_card . ' ' . 'Elo';
                break;
            case 102:
                $method = $credit_card . ' ' . 'Visa';
                break;
            case 103:
                $method = $credit_card . ' ' . 'MasterCard';
                break;
            case 104:
                $method = $credit_card . ' ' . 'American Express';
                break;
            case 105:
                $method = $credit_card . ' ' . 'Diners';
                break;
            case 106:
                $method = $credit_card . ' ' . 'Aura';
                break;
            case 107:
                $method = $credit_card . ' ' . 'Elo';
                break;
            case 108:
                $method = $credit_card . ' ' . 'Discover';
                break;
            case 109:
                $method = $credit_card . ' ' . 'JCB';
                break;
            default:
                $method = __( 'Unknown', 'wcpagseguro' );
                break;
        }

        return $method;
    }

    /**
     * Error messages.
     *
     * @param  int    $code Error code.
     *
     * @return string       Error message.
     */
    public function error_message( $code ) {
        switch ( $code ) {
            case 11013:
            case 11014:
                $message = __( 'Please enter a valid phone number with DDD. Example: (11) 5555-5555.', 'wcgerencianet' );
                break;
            case 11017:
                $message = __( 'Please enter a valid zip code number.', 'wcgerencianet' );
                break;
            case 11164:
                $message = __( 'Please enter a valid CPF number.', 'wcgerencianet' );
                break;

            default:
                $message = __( 'An error has occurred while processing your payment, please review your data and try again. Or contact us for assistance.', 'wcgerencianet' );
                break;
        }

        return $message;
    }
}