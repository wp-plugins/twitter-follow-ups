<h3><?php _e('Twitter Application Access Keys', 'follow_up_emails'); ?></h3>

<?php if (isset($_GET['message'])): ?>
    <div id="message" class="updated"><p><?php echo wp_kses_post( urldecode( $_GET['message'] ) ); ?></p></div>
<?php endif; ?>

<a href="#" class="toggle-guide"><?php _e('Guide to getting your API keys', 'follow_up_emails'); ?></a>

<div id="guide" class="updated" style="display: none;">
    <p>
        <?php _e('To get your API Keys, create a <a href="https://apps.twitter.com/app/new">new Twitter App</a> and set the following values:', 'follow_up_emails'); ?>
    </p>
    <ul>
        <li><strong>Name:</strong> <?php _e('Your app\'s name', 'follow_up_emails'); ?></li>
        <li><strong>Description:</strong> <?php _e('Your application description, which will be shown in user-facing authorization screens', 'follow_up_emails'); ?></li>
        <li><strong>Website:</strong> <?php _e('Your application\'s publicly accessible home page, where users can go to download, make use of, or find out more information about your application', 'follow_up_emails'); ?></li>
        <li><strong>Callback URL:</strong> <?php printf( __('Set to <code>%s</code>', 'follow_up_emails'), admin_url('admin-post.php?action=twitter-oauth') ); ?></li>
        <li><strong>Permissions:</strong> <?php _e('Set to <code>Read and Write</code>', 'follow_up_emails'); ?></li>
    </ul>

    <p><?php _e('After creating your app, click on the Keys and Access Tokens tab to get your Consumer Key and Consumer Secret.', 'follow_up_emails'); ?></p>
</div>


<table class="form-table">
    <tbody>
    <tr valign="top">
        <th><label for="twitter_consumer_key"><?php _e('Consumer Key', 'follow_up_emails'); ?></label></th>
        <td>
            <input type="text" name="twitter_consumer_key" id="twitter_consumer_key" value="<?php echo esc_attr( $this->fue_twitter->settings['consumer_key'] ); ?>" size="50" />
        </td>
    </tr>
    <tr valign="top">
        <th><label for="twitter_consumer_secret"><?php _e('Consumer Secret', 'follow_up_emails'); ?></label></th>
        <td>
            <input type="text" name="twitter_consumer_secret" id="twitter_consumer_secret" value="<?php echo esc_attr( $this->fue_twitter->settings['consumer_secret'] ); ?>" size="50" />
        </td>
    </tr>
    <?php
    if ( empty( $this->fue_twitter->settings['access_token'] ) && ( !empty( $this->fue_twitter->settings['consumer_key'] ) && !empty( $this->fue_twitter->settings['consumer_secret'] ) ) ):
        $connection     = new \Abraham\TwitterOAuth\TwitterOAuth( $this->fue_twitter->settings['consumer_key'], $this->fue_twitter->settings['consumer_secret'] );
        $request_token  = $connection->oauth('oauth/request_token');
        $auth_url       = $connection->url('oauth/authorize', array('oauth_token' => $request_token['oauth_token']));

        // store the token for 10 minutes
        set_transient( 'fue_twitter_request_token', $request_token, 600 );
        ?>
        <tr valign="top">
            <th><label for="twitter_signin"><?php _e('Grant API Access', 'follow_up_emails'); ?></label></th>
            <td>
                <a href="<?php echo $auth_url; ?>"><img src="<?php echo plugins_url( 'templates/images/sign-in-with-twitter.png', FUE_Twitter::PLUGIN_FILE ); ?>" alt="<?php _e('Sign In with Twitter', 'follow_up_emails'); ?>" /></a>
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

    <p class="submit">
        <?php if ( !empty( $this->fue_twitter->settings['access_token'] ) ): ?>
        <input type="button" name="test" class="button-secondary toggle-test" value="<?php _e('Test API Keys', 'follow_up_emails'); ?>" class="button-primary" />
        <?php endif; ?>
    </p>

<div id="test-form" style="display: none;">
    <h3><?php _e('API Test', 'follow_up_emails'); ?></h3>

        <table class="form-table">
            <tr>
                <th><label for="twitter_message"><?php _e('Tweet', 'follow_up_emails'); ?></label></th>
                <td>
                    <textarea id="twitter_message" cols="50" rows="5"></textarea>
                    <p class="description"><?php _e('Max of 140 characters', 'follow_up_emails'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="button" id="twitter_test_api" class="button-primary" value="Tweet!" />
        </p>

</div>
<script>
    jQuery(".toggle-guide").click(function(e) {
        e.preventDefault();

        jQuery("#guide").slideToggle();
    });

    jQuery(".toggle-test").click(function() {
        jQuery("#test-form").toggle();
    });

    jQuery("#twitter_test_api").click(function() {
        window.location.href = "admin-post.php?action=fue_twitter_api_test&message="+ encodeURIComponent(jQuery("#twitter_message").val());
    });
</script>