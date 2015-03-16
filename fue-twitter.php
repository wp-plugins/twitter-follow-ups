<?php
 /**
  * Plugin Name: Twitter Add-on for Follow-up Emails
  * Plugin URI: http://www.woothemes.com/products/follow-up-emails/
  * Description: Twitter Integration for Follow-Up marketing plugin for WooCommerce. Don't just email. Tweet your customers!
  * Version: 1.1.1
  * Author: 75nineteen Media LLC
  * Author URI: http://www.75nineteen.com
  */

/**
 * Class FUE_Twitter
 */
class FUE_Twitter {

    /**
     * Plugin version
     */
    const VERSION = '1.1.1';

    /**
     * Plugin file path
     */
    const PLUGIN_FILE = __FILE__;

    /**
     * @var array Twitter settings
     */
    public $settings;

    /**
     * @var string OAUth callback URL
     */
    private $callback;

    /**
     * Class constructor
     */
    public function __construct() {
        $this->settings = $this->get_settings();
        $this->callback = $this->get_callback_url();

        $this->register_hooks();
        $this->include_files();

        $this->register_autoloader();

        if ( is_admin() ) {
            $this->twitter_admin = new FUE_Twitter_Admin( $this );
        }

        $this->twitter_tweet    = new FUE_Twitter_Tweeter( $this );
        $this->twitter_frontend = new FUE_Twitter_Frontend( $this );
    }

    public function show_unsupported_version_notice() {
        ?>
        <div class="error">
            <p><?php _e( 'FUE-Twitter requires Follow-Up Emails version 4.1+', 'follow_up_emails' ); ?></p>
        </div>
    <?php
    }

    /**
     * Register hooks
     */
    public function register_hooks() {
        add_filter( 'fue_addons', array($this, 'register_addon') );
        add_filter( 'fue_email_types', array($this, 'register_email_types'), 20 );
        add_filter( 'plugins_loaded', array($this, 'include_fue_extensions') );
        add_filter( 'plugins_loaded', array($this, 'check_fue_version') );
    }

    public function check_fue_version() {
        if ( !defined( 'FUE_VERSION' ) || version_compare( FUE_VERSION, '4.1', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'show_unsupported_version_notice' ) );
        }
    }

    public function include_fue_extensions() {
        $this->twitter_scheduler    = new FUE_Twitter_Scheduler( $this );
    }

    /**
     * Load the settings data from the database
     * @return array
     */
    public function get_settings() {
        $settings = get_option( 'fue_twitter' );
        $default    = array(
            'consumer_key'          => '',
            'consumer_secret'       => '',
            'access_token'          => ''
        );
        $settings = wp_parse_args( $settings, $default );

        return apply_filters( 'fue_twitter_settings', $settings );
    }

    /**
     * Get the callback URL for OAuth
     *
     * @return string
     */
    public function get_callback_url() {
        $url = admin_url('admin.php?page=followup-emails-settings&tab=twitter&oauth=1');

        return apply_filters( 'fue_twitter_callback_url', $url );
    }

    /**
     * Load Twitter libraries
     */
    public function include_files() {
        require_once 'includes/twitter/twitteroauth/autoload.php';
    }

    /**
     * Register the autoloader method
     */
    public function register_autoloader() {
        // Auto-load classes on demand
        if ( function_exists( "__autoload" ) ) {
            spl_autoload_register( "__autoload" );
        }

        spl_autoload_register( array( $this, 'autoload' ) );
    }

    /**
     * Auto-load classes
     *
     * @param string $class
     */
    public function autoload( $class ) {
        $path  = null;
        $class = strtolower( $class );
        $file = 'class-' . str_replace( '_', '-', $class ) . '.php';

        if ( strpos( $class, 'fue_twitter_' ) === 0 ) {
            $path = trailingslashit( dirname(__FILE__) .'/includes' );
        }

        if ( $path && is_readable( $path . $file ) ) {
            include_once( $path . $file );
            return;
        }
    }

    /**
     * Register as an add-on for FUE
     * @param array $addons
     * @return array
     */
    public function register_addon( $addons ) {
        $addons['twitter'] = array(
            'name'          => 'Twitter',
            'installed'     => true,
            'settings'      => true,
            'url'           => '#',
            'description'   => __('@todo twitter description', 'follow_up_emails')
        );

        return $addons;
    }

    /**
     * Register the Twitter email type
     *
     * @hook fue_email_types
     * @param array $types
     * @return array $types
     */
    public function register_email_types( $types ) {
        $triggers = array();

        // copy the triggers for the storewide email type
        foreach ( $types as $type ) {
            if ( $type->id == 'storewide' ) {
                $triggers = $type->triggers;
                break;
            }
        }

        if ( empty( $triggers ) ) {
            // storewide email type not found. perhaps WC is not installed
            // twitter needs WC to be installed
            return $types;
        }

        $props = array(
            'priority'              => 10,
            'label'                 => __('Twitter Messages', 'follow_up_emails'),
            'singular_label'        => __('Twitter Message', 'follow_up_emails'),
            'triggers'              => $triggers,
            'durations'             => Follow_Up_Emails::$durations,
            'long_description'      => __('@todo', 'follow_up_emails'),
            'short_description'     => __('@todo', 'follow_up_emails'),
            'list_template'         => dirname(__FILE__) . '/templates/email-list.php'
        );
        $types[] = new FUE_Email_Type( 'twitter', $props );

        return $types;

    }

}

$GLOBALS['fue_twitter'] = new FUE_Twitter();