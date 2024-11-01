<?php

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$api_key = get_option('zonify_api_key');
$error = get_option('zonify_error');
$error_message = get_option('zonify_error_message');

?>

<div class="zonify-page">
    <div class="zonify-logo">
        <a href="https://help.zonifyapp.com/" target="_blank"> <img
                    style="max-width:none; width:345px; border:0; text-decoration:none; outline:none"
                    src="<?php echo esc_url(ZONIFY_URL).'/assets/images/zonify-logo.png'?>"/></a>
    </div>

    <?php

    if (!$api_key || ($api_key && strlen($api_key) < 1) || $api_key == null) {
        ?>
        <div class="zonify-alert">Invalid API key!</div>
        <?php
    } else {

        $url = ZONIFY_API_URL . '/site/login-woo?token=' . $api_key;

        if ($error && $error == "yes") {
            ?>
            <div class="zonify-alert"><?php echo esc_html($error_message); ?></div>
            <div class="zonify-clearfix"></div><br/>
            <?php
        }

        ?>
        <div class="zonify-login-url" style="margin-top:-40px; line-height: 30px;">
            <h3>You're All Set!</h3>
            Zonify is installed on your website <br>
            Click on the button below to login to your dashboard
            <div class="zonify-clearfix"></div>
            <button id="zonifyLoginBtn" class="zonify-btn-login-me"
                    style="text-transform: none; font-size: 25px;" onclick="window.open('<?php echo esc_html($url); ?>')">Go To Dashboard
            </button>

            <div class="zonify-clearfix"></div>
        </div>
        <div class="zonify-clearfix"></div>
        <?php
    }
    ?>
    <div class="zonify-clearfix"></div>
</div>

<style>
    .notice, div.error, div.updated {
        display: none !important;
    }

    .zonify-page {
        box-shadow: -7px 5px 16px 0px rgb(4 4 4 / 35%) !important;
        margin: 51px auto 20px;
    }
</style>
