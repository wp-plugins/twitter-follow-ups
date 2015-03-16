<?php

/**
 * FUE_Twitter_Admin class
 */
class FUE_Twitter_Admin {

    /**
     * @var FUE_Twitter
     */
    private $fue_twitter;

    /**
     * Class constructor
     *
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
        // settings
        add_action( 'fue_settings_integration', array($this, 'settings_form') );
        add_action( 'fue_settings_save', array($this, 'save_settings') );


        // OAuth Callback
        add_action( 'admin_post_twitter-oauth', array( $this, 'oauth_callback' ) );

        // API Test
        add_action( 'admin_post_fue_twitter_api_test', array( $this, 'test_api' ) );

        // Variables
        add_action( 'fue_email_variables_list', array( $this, 'twitter_variables' ) );

        // Email Form
        add_action( 'fue_email_form_scripts', array( $this, 'email_form_scripts' ) );
        add_action( 'edit_form_after_title', array( $this, 'add_twitter_content' ), 101 );
        add_filter( 'fue_save_email_data', array( $this, 'save_email' ) );
    }

    /**
     * Content for the Twitter settings page
     * @hook fue_addon_settings_twitter
     */
    public function settings_form() {
        include dirname(FUE_Twitter::PLUGIN_FILE) .'/templates/settings.php';
    }

    /**
     * Save data from the settings page
     *
     * @hook fue_settings_save
     * @param array $data
     */
    public function save_settings( $data ) {
        if ( $data['section'] == 'integration' ) {
            $settings = $this->fue_twitter->get_settings();
            $settings['consumer_key']       = sanitize_text_field( $data['twitter_consumer_key'] );
            $settings['consumer_secret']    = sanitize_text_field( $data['twitter_consumer_secret'] );

            update_option( 'fue_twitter', $settings );
        }
    }

    /**
     * Store OAuth tokens after Twitter App Authorization
     *
     * @throws \Abraham\TwitterOAuth\TwitterOAuthException
     */
    public function oauth_callback() {
        $this->fue_twitter->include_files();

        $request_token  = get_transient( 'fue_twitter_request_token' );

        if ( isset($_REQUEST['oauth_token']) && $request_token['oauth_token'] !== $_REQUEST['oauth_token']) {
            // Abort! Something is wrong.
            wp_die( _e('The tokens do not match!', 'follow_up_emails') );
        }

        $connection = new \Abraham\TwitterOAuth\TwitterOAuth(
            $this->settings['consumer_key'],
            $this->settings['consumer_secret'],
            $request_token['oauth_token'],
            $request_token['oauth_token_secret']
        );

        $access_token = $connection->oauth("oauth/access_token", array("oauth_verifier" => $_REQUEST['oauth_verifier']));

        $this->settings['access_token'] = $access_token;
        update_option( 'fue_twitter', $this->fue_twitter->get_settings() );

        $message = urlencode( __('Successfully connected your Twitter account!', 'follow_up_emails') );
        wp_redirect( admin_url('admin.php?page=followup-emails-settings&tab=twitter&message='. $message) );
        exit;

    }

    /**
     * Test the FUE API by creating a Twitter message and adding
     * it to the queue to be posted automatically
     */
    public function test_api() {
        require_once dirname( FUE_Twitter::PLUGIN_FILE ) .'/includes/api-client/class-fue-api-client.php';

        $user_id        = get_current_user_id();
        $consumer_key   = get_user_meta( $user_id, 'fue_api_consumer_key', true );
        $consumer_secret= get_user_meta( $user_id, 'fue_api_consumer_secret', true );

        if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
            wp_die( 'FUE API keys are missing. Please generate them first in your profile page.' );
        }

        try {
            $fue_api = new FUE_API_Client( $consumer_key, $consumer_secret, get_bloginfo('url'), false );

            $data = array(
                'type'      => 'twitter',
                'name'      => 'Twitter API Test',
                'subject'   => '',
                'message'   => $_REQUEST['message'],
                'status'    => 'active',
                'trigger'   => 'processing',
                'interval'  => '1',
                'duration'  => 'minutes'
            );

            echo '<h3>Creating the email</h3>';
            echo '<pre>'. print_r($data, true) .'</pre>';

            $response = $fue_api->create_email( $data );

            echo '<h3>Response</h3>';
            echo '<pre>'. print_r($response, true) .'</pre>';
            $email_id = $response->email->id;

            $data = array(
                'email_id'      => $response->email->id,
                'user_id'       => get_current_user_id(),
                'user_email'    => 'dummy@email.com',
                'send_date'     => get_gmt_from_date( date('Y-m-d H:i:s') ),
                'is_sent'       => 0,
                'status'        => 1
            );
            echo '<h3>Creating the queue item</h3>';
            echo '<pre>'. print_r($data, true) .'</pre>';

            $response = $fue_api->create_queue_item( $data );

            echo '<h3>Response</h3>';
            echo '<pre>'. print_r($response, true) .'</pre>';

            echo '<h3>Sending the Queue Item</h3>';
            $item = new FUE_Sending_Queue_Item( $response->item->id );
            $status = Follow_Up_Emails::instance()->mailer->send_queue_item( $item );
            echo '<pre>'. print_r($status, true) .'</pre>';

            echo '<h3>Cleaning up</h3>';
            $fue_api->delete_queue_item( $response->item->id );
            echo '<p>Queue item deleted</p>';

            $fue_api->delete_email( $email_id );
            echo '<p>Email deleted</p>';

            echo '<p>All done! Back to the <a href="admin.php?page=followup-emails-settings&tab=integration">settings page</a></p>';
        } catch ( Exception $e ) {
            wp_die( $e->getMessage() );
        }



    }

    /**
     * Add twitter variables to the email form
     *
     * @param FUE_Email $email
     */
    public function twitter_variables( $email ) {
        if ($email->type !== 'twitter') {
            return;
        }
        ?>
        <li class="var var_twitter var_twitter_handle"><strong>{twitter_handle}</strong> <img class="help_tip" title="<?php _e('The customer\'s Twitter handle (e.g. @johndoe)', 'follow_up_emails'); ?>" src="<?php echo FUE_TEMPLATES_URL; ?>/images/help.png" width="16" height="16" /></li>
        <li class="var var_twitter var_order_items"><strong>{order_items}</strong> <img class="help_tip" title="<?php _e('Displays a list of purchased items.', 'follow_up_emails'); ?>" src="<?php echo FUE_TEMPLATES_URL; ?>/images/help.png" width="16" height="16" /></li>
        <?php
    }

    /**
     * Enqueue JS for the email form
     */
    public function email_form_scripts() {
        wp_enqueue_script( 'fue-form-twitter', plugins_url( 'templates/js/email-form-twitter.js', FUE_Twitter::PLUGIN_FILE ), array('jquery'), FUE_Twitter::VERSION );
        wp_enqueue_style( 'fue-form-twitter', plugins_url( 'templates/css/email-form-twitter.css', FUE_Twitter::PLUGIN_FILE ) );
    }

    /**
     * Content for the Twitter content box
     */
    public static function add_twitter_content() {
        global $post;

        if ( $post->post_type != 'follow_up_email' ) {
            return;
        }

        ?>
        <div id="fue-twitter-content" style="display: none; margin-top: 20px;">
            <label for="twitter_content" class="fue-label"><?php _e('Twitter Message', 'follow_up_emails'); ?></label>
            <textarea name="twitter_content" id="twitter_content" rows="5" cols="80"><?php echo esc_attr( $post->post_content ); ?></textarea>
            <div id="fue-twitter-content-character-count-container">
                <div id="fue-twitter-content-character-count">
                    <?php _e('Character Count:', 'follow_up_emails'); ?> <span id="fue-twitter-count">0</span> <?php _e('of 140', 'follow_up_emails'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save the twitter_content field as the post_content if the email type is twitter
     *
     * @param array $data
     * @return array
     */
    public function save_email( $data ) {
        if ( $data['type'] == 'twitter' ) {
            $data['message']    = $_POST['twitter_content'];
        }

        return $data;
    }

}