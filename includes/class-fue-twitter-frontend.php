<?php

/**
 * FUE_Twitter_Frontend class
 */
class FUE_Twitter_Frontend {

    /**
     * @var FUE_Twitter
     */
    private $fue_twitter;

    /**
     * @param FUE_Twitter $fue_twitter
     */
    public function __construct( FUE_Twitter $fue_twitter ) {
        $this->fue_twitter = $fue_twitter;

        $this->register_hooks();
    }

    /**
     * Register hooks
     */
    public function register_hooks() {
        // checkout form
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'checkout_form_fields' ) );

        // store the twitter handle
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'store_twitter_handle' ) );
    }

    /**
     * Render the checkout fields
     */
    public function checkout_form_fields() {
        include dirname( FUE_Twitter::PLUGIN_FILE ) .'/templates/checkout-form.php';
    }

    /**
     * Store the passed twitter handle from the checkout form
     *
     * @param int $order_id
     */
    public function store_twitter_handle( $order_id ) {
        if ( !empty( $_POST['twitter_handle'] ) ) {
            $handle = ltrim( sanitize_text_field( $_POST['twitter_handle'] ), '@' );
            update_post_meta( $order_id, '_twitter_handle', $handle );
        }
    }

}