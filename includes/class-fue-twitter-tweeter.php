<?php

/**
 * Class FUE_Twitter_Tweeter
 *
 * Takes care of posting tweets to Twitter
 */
class FUE_Twitter_Tweeter {

    /**
     * Class constructor
     * @param FUE_Twitter $fue_twitter
     */
    public function __construct( FUE_Twitter $fue_twitter ) {
        $this->fue_twitter = $fue_twitter;

        // override to post a tweet instead of sending an email
        add_filter( 'fue_send_queue_item', array( $this, 'tweet' ), 10, 4 );

        // variable replacements
        add_action( 'fue_before_variable_replacements', array( $this, 'register_variable_replacements' ), 10, 4 );
    }

    /**
     * Register additional variables to be replaced
     *
     * @param FUE_Sending_Email_Variables $var
     * @param array                 $email_data
     * @param FUE_Email             $email
     * @param object                $queue_item
     *
     * @return void
     */
    public function register_variable_replacements( $var, $email_data, $email, $queue_item ) {
        $variables = array(
            'twitter_handle' => '',
            'order_items'    => '',
            'store_url'      => ''
        );

        // use test data if the test flag is set
        if ( isset( $email_data['test'] ) && $email_data['test'] ) {
            $variables['twitter_handle']    = '@johndoe';
            $variables['order_items']       = 'Woo Logo, Round Shirt';
            $variables['store_url']         = get_bloginfo('url');
        } else {
            if ( !empty( $queue_item->order_id ) ) {
                $variables['twitter_handle']    = $this->get_twitter_handle_from_order( $queue_item->order_id );
                $variables['order_items']       = $this->get_order_items_csv( $queue_item->order_id );
                $variables['store_url']         = get_bloginfo('url');
            }
        }

        $var->register( $variables );

    }

    /**
     * Get the Twitter handle stored in the given order.
     *
     * @param int $order_id
     * @return string
     */
    public function get_twitter_handle_from_order( $order_id ) {
        $handle_meta    = get_post_meta( $order_id, '_twitter_handle', true );
        $handle         = '';

        if ( $handle_meta ) {
            $handle = '@'.$handle_meta;
        }

        return $handle;
    }

    public function get_order_items_csv( $order_id ) {
        $order          = WC_FUE_Compatibility::wc_get_order( $order_id );
        $items          = $order->get_items();
        $items_array    = array();

        foreach ( $items as $item ) {
            $product = WC_FUE_Compatibility::wc_get_product( $item['product_id'] );
            $items_array[] = $product->get_title();
        }

        return implode( ', ', $items_array );
    }

    /**
     * Backwards-compatible way of getting the available order statuses
     *
     * @return array
     */
    public function get_order_statuses() {
        $order_statuses = array();

        if ( WC_FUE_Compatibility::is_wc_version_gte_2_2() ) {
            $statuses = wc_get_order_statuses();

            foreach ( $statuses as $key => $status ) {
                $order_statuses[] = str_replace( 'wc-', '', $key );
            }

        } else {
            $terms = get_terms( 'shop_order_status', array('hide_empty' => 0, 'orderby' => 'id') );

            if (! isset($terms->errors) ) {
                foreach ( $terms as $status ) {
                    $order_statuses[] = $status->slug;
                }
            }

        }

        return $order_statuses;
    }

    /**
     * Post a tweet
     *
     * @hook fue_send_queue_item
     * @param bool $sent
     * @param FUE_Sending_Queue_Item $item
     * @param array $data
     * @param array $headers
     * @return bool
     */
    public function tweet( $sent, $item, $data, $headers = null ) {

        $email = new FUE_Email( $item->email_id );
        $settings = $this->fue_twitter->get_settings();

        if ( $email->get_type() != 'twitter' ) {
            return $sent;
        }

        if ( empty( $settings['access_token']) || empty( $settings['consumer_key'] ) ) {
            // not configured
            return $sent;
        }

        $connection = new \Abraham\TwitterOAuth\TwitterOAuth(
            $settings['consumer_key'],
            $settings['consumer_secret'],
            $settings['access_token']['oauth_token'],
            $settings['access_token']['oauth_token_secret']
        );

        if ( $connection ) {

            try {
                $message = $this->sanitize_message( $data['message'] );
                $status = $connection->post( 'statuses/update', array(
                    'status'    => wp_kses( $message, array() )
                ) );

                if ( isset( $status->errors ) ) {
                    $sent = new WP_Error( 'fue_twitter_tweet_error', 'Twitter Error: ' . $status->errors[0]->message );
                } else {
                    $sent = true;
                }
            } catch ( Exception $e ) {
                $logger = new WC_Logger();
                $logger->add( 'fue-twitter', print_r($e, true) );
            }

        }

        return $sent;
    }

    /**
     * Make $message valid for Twitter's status requirements
     *
     * @param string $message
     * @return string
     */
    private function sanitize_message( $message ) {
        // strip all HTML
        $message = htmlspecialchars_decode( wp_kses( $message , array() ) );

        // trim to a max for 140 chars
        $message = substr( $message, 0, 139 );

        return $message;
    }

}