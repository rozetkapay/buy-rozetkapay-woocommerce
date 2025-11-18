<?php
/**
 * Клас обробника Email
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RP_Payparts_Email {
    
    private $gateway;
    
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }
    
    /**
     * Send pending email
     */
    public function send_pending_email( $order, $payment_url = '' ) {
        // Базова реалізація
        return true;
    }
    
    /**
     * Send success email  
     */
    public function send_success_email( $order, $transaction_id = '' ) {
        // Базова реалізація
        return true;
    }
}
