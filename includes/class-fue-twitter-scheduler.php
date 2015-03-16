<?php

/**
 * Class FUE_Twitter_Scheduler
 */
class FUE_Twitter_Scheduler extends FUE_Addon_Woocommerce_Scheduler {

    /**
     * @var FUE_Twitter
     */
    private $fue_twitter;

    /**
     * Class constructor
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
        // @since 2.2.1 support custom order statuses
        add_action( 'plugins_loaded', array($this, 'hook_statuses'), 20 );
        add_action( 'woocommerce_checkout_order_processed', array($this, 'order_status_updated') );

        // use the twitter handle as the email address
        add_filter( 'fue_insert_email_order', array( $this, 'use_twitter_handle_as_email'), 100 );
    }

    /**
     * For twitter types, use the twitter handle in place of the email address
     *
     * @param array $data
     * @return array
     */
    public function use_twitter_handle_as_email( $data ) {

        $email = new FUE_Email( $data['email_id'] );

        if ( $email->type == 'twitter' && !empty( $data['order_id'] ) ) {
            $handle = get_post_meta( $data['order_id'], '_twitter_handle', true );

            if ( $handle ) {
                $data['user_email'] = '@'.$handle;
            }
        }

        return $data;
    }

    /**
     * Register order statuses to trigger follow-up emails
     */
    function hook_statuses() {
        $statuses = $this->fue_twitter->twitter_tweet->get_order_statuses();

        foreach ( $statuses as $status ) {
            add_action('woocommerce_order_status_'. $status, array($this, 'order_status_updated'), 100);
        }

    }

    /**
     * When an order gets updated, queue emails that match the new status
     *
     * @param int $order_id
     */
    function order_status_updated( $order_id ) {

        $order = WC_FUE_Compatibility::wc_get_order($order_id);

        $queued         = array();
        $triggers       = $this->get_order_triggers( $order, Follow_Up_Emails::get_email_type( 'twitter' ) );

        $product_emails = $this->get_matching_product_emails( $order, $triggers, false );
        $queued         = array_merge( $queued, $this->queue_product_emails( $product_emails, $order ) );

        $product_always_send_emails = $this->get_matching_product_emails( $order, $triggers, true );
        $queued         = array_merge( $queued, $this->queue_always_send_product_emails( $product_always_send_emails, $order ) );

        $category_emails    = $this->get_matching_category_emails( $order, $triggers, false );
        $queued             = array_merge( $queued, $this->queue_category_emails( $category_emails, $order ) );

        $category_always_send_emails    = $this->get_matching_category_emails( $order, $triggers, true );
        $queued         = array_merge( $queued, $this->queue_always_send_category_emails( $category_always_send_emails, $order ) );

        $storewide_always_send_emails   = $this->get_matching_storewide_emails( $order, $triggers, true );
        $queued         = array_merge( $queued, $this->queue_storewide_emails( $storewide_always_send_emails, $order ) );

        if ( count( $queued ) == 0 ) {
            $storewide_emails = $this->get_matching_storewide_emails( $order, $triggers );
            $this->queue_storewide_emails( $storewide_emails, $order );
        }

        $this->add_order_notes_to_queued_emails( $queued );

    }

    /**
     * Get all matching product emails against the provided $order and $triggers and sort by priority
     *
     * @param WC_Order  $order
     * @param array     $triggers
     * @param bool      $always_send
     * @return array    Array of matched FUE_Email
     */
    protected function get_matching_product_emails( $order, $triggers, $always_send = false ) {
        $item_ids       = $this->get_product_ids_from_order( $order );
        $product_ids    = array();
        $variation_ids  = array();

        foreach ( $item_ids as $item_id ) {
            $product_ids[] = $item_id['product_id'];

            if ( $item_id['variation_id'] ) {
                $variation_ids[] = $item_id['variation_id'];
            }
        }

        $product_ids    = array_unique( $product_ids );
        $variation_ids  = array_unique( $variation_ids );

        // product match
        $always_send_value = ( $always_send ) ? 1 : 0;
        $args = array(
            'meta_query'    => array(
                'relation'  => 'AND',
                array(
                    'key'       => '_interval_type',
                    'value'     => $triggers,
                    'compare'   => 'IN'
                ),
                array(
                    'key'       => '_product_id',
                    'value'     => 0,
                    'compare'   => '!='
                ),
                array(
                    'key'       => '_product_id',
                    'value'     => array_merge( $product_ids, $variation_ids ),
                    'compare'   => 'IN'
                ),
                array(
                    'key'       => '_always_send',
                    'value'     => $always_send_value
                )
            )
        );

        $product_emails = fue_get_emails( 'twitter', FUE_Email::STATUS_ACTIVE, $args );

        // Loop through the product matches and queue the top result
        $matched_product_emails = array();
        foreach ( $product_emails as $email ) {

            $meta               = maybe_unserialize($email->meta);
            $include_variations = isset($meta['include_variations']) && $meta['include_variations'] == 'yes';

            if ( $this->exclude_customer_based_on_purchase_history( fue_get_customer_from_order( $order ), $email ) ) {
                continue;
            }

            // exact product match
            if ( in_array( $email->product_id, $product_ids ) ) {
                $matched_product_emails[] = $email;
            } elseif ( $include_variations && in_array( $email->product_id, $variation_ids ) ) {
                $matched_product_emails[] = $email;
            }

        }

        return $matched_product_emails;
    }

    /**
     * Get all matching category emails against the provided $order and $triggers and sort by priority
     *
     * @param WC_Order  $order
     * @param array     $triggers
     * @param bool      $always_send
     * @return array    Array of matched FUE_Email
     */
    protected function get_matching_category_emails( $order, $triggers, $always_send = false ) {
        $matching_emails    = array();
        $category_ids       = $this->get_category_ids_from_order( $order );

        $always_send_value = ( $always_send ) ? 1 : 0;
        $args = array(
            'meta_query'    => array(
                'relation'  => 'AND',
                array(
                    'key'       => '_interval_type',
                    'value'     => $triggers,
                    'compare'   => 'IN'
                ),
                array(
                    'key'       => '_category_id',
                    'value'     => 0,
                    'compare'   => '!='
                ),
                array(
                    'key'       => '_category_id',
                    'value'     => $category_ids,
                    'compare'   => 'IN'
                ),
                array(
                    'key'       => '_always_send',
                    'value'     => $always_send_value
                )
            )
        );

        $category_emails = fue_get_emails( 'twitter', FUE_Email::STATUS_ACTIVE, $args );

        foreach ( $category_emails as $email ) {

            if ( $this->exclude_customer_based_on_purchase_history( fue_get_customer_from_order( $order ), $email ) ) {
                continue;
            }

            $matching_emails[] = $email;

        }

        return $matching_emails;
    }

    /**
     * Get all matching storewide emails against the provided $order and $triggers and sort by priority
     *
     * @param WC_Order  $order
     * @param array     $triggers
     * @param bool      $always_send
     * @return array    Array of matched FUE_Email
     */
    protected function get_matching_storewide_emails( $order, $triggers, $always_send = false ) {

        $matched_emails = array();
        $category_ids   = $this->get_category_ids_from_order( $order );

        $emails = fue_get_emails( 'twitter', FUE_Email::STATUS_ACTIVE, array(
            'meta_query' => array(
                'relation'  => 'AND',
                array(
                    'key'       => '_interval_type',
                    'value'     => $triggers,
                    'compare'   => 'IN'
                ),
                array(
                    'key'       => '_product_id',
                    'value'     => 0
                ),
                array(
                    'key'       => '_category_id',
                    'value'     => 0
                )
            )
        ) );

        foreach ( $emails as $email ) {
            // excluded categories
            $meta = maybe_unserialize($email->meta);
            $excludes = (isset($meta['excluded_categories'])) ? $meta['excluded_categories'] : array();

            if ( count($excludes) > 0 ) {
                foreach ( $category_ids as $cat_id ) {
                    if ( in_array( $cat_id, $excludes ) )
                        continue 2;
                }
            }

            if ( $this->exclude_customer_based_on_purchase_history( fue_get_customer_from_order( $order ), $email ) ) {
                continue;
            }

            $matched_emails[] = $email;

        }

        return $matched_emails;
    }

}