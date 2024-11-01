<?php

/**
 * @package: zonify-plugin
 */

/**
 * Plugin Name: Zonify
 * Description: Earn Amazon affiliate commissions, or start a dropshipping business by importing Amazon products into WooCommerce
 * Version: 1.0.2
 * Author: Zonify
 * Author URI: https://help.zonifyapp.com/
 * License: GPLv3 or later
 * Text Domain: zonify-plugin
 */

if (!defined('ABSPATH')) {
    die;
}

define("ZONIFY_API_URL", "https://app.zonifyapp.com/dashboard");
define('ZONIFY_VERSION', '1.0');
define('ZONIFY_PATH', dirname(__FILE__));
define('ZONIFY_FOLDER', basename(ZONIFY_PATH));
define('ZONIFY_URL', plugins_url() . '/' . ZONIFY_FOLDER);
define('ZONIFY_API_KEY', get_option('zonifyapp_api_key'));
define("ZONIFY_DEVELOPMENT", (stripos(ZONIFY_API_URL, "dev.zonify") !== false ? "dev" : ""));
define("ZONIFY_DEBUG", true);

register_activation_hook(__FILE__, 'zonify_activation_hook');
register_deactivation_hook(__FILE__, 'zonify_deactivation_hook');
register_uninstall_hook(__FILE__, 'zonify_uninstall_hook');
add_action('admin_enqueue_scripts', 'zonify_add_admin_css_js');
add_action('admin_menu', 'zonify_admin_menu');
add_action('wp_head', 'zonify_script');
add_action("wp_ajax_nopriv_zonify_installation", "zonify_installation");
add_action("wp_ajax_zonify_installation", "zonify_installation");

function zonify_activation_hook()
{
    $data = array(
        'store' => get_site_url(),
        'email' => get_option('admin_email'),
        'event' => 'install'
    );

    $response = zonify_send_request('/woocommerce/activate', $data);

    if ($response) {

        if ($response['success'] > 0) {

            zonify_log('api key: ' . $response['api_key'], get_site_url());
            zonify_log((!get_option('zonify_api_key') ? "yes" : "no"), get_site_url());

            if (!get_option('zonify_api_key')) {
                add_option('zonify_api_key', $response['api_key']);

                zonify_log($response['app_name'] . " " . $response['user_id'] . " " . $response['scope'], get_site_url());

                if (class_exists("WC_Auth")) {
                    class Zonify_AuthCustom extends WC_Auth
                    {
                        public function getKeys($app_name, $user_id, $scope)
                        {
                            return parent::create_keys($app_name, $user_id, $scope);
                        }
                    }

                    $auth = new Zonify_AuthCustom();
                    $keys = $auth->getKeys($response['app_name'], $response['user_id'], $response['scope']);
                    $data = array(
                        'store' => get_site_url(),
                        'keys' => $keys,
                        'user_id' => $response['user_id'],
                        'event' => 'update_keys'
                    );


                    $keys_response = zonify_send_request('/woocommerce/activate', $data);

                    if ($keys_response && $keys_response['success'] == 0) {
                        add_option('zonify_error', 'yes');
                        add_option('zonify_error_message', $keys_response['message']);
                    }
                }

                zonify_log('after auth');
            } else {
                update_option('zonify_api_key', $response['api_key']);
            }
        } else {
            zonify_log('invalid response - api key', get_site_url());

            if (!get_option('zonify_error')) {
                add_option('zonify_error', 'yes');
                add_option('zonify_error_message', 'Error activation plugin!');
            }
        }
    } else {
        zonify_log('error getting response - api key', get_site_url());

        if (!get_option('zonify_error')) {
            add_option('zonify_error', 'yes');
            add_option('zonify_error_message', 'Error activation plugin!');
        }
    }
}

function zonify_deactivation_hook()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }
    $data = array(
        'store' => get_site_url(),
        'event' => 'deactivated',
    );
    return zonify_send_request('/woocommerce/deactivate', $data);
}

function zonify_uninstall_hook()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    delete_option('zonify_api_key');

    if (get_option('zonify_error')) {
        delete_option('zonify_error');
    }

    if (get_option('zonify_error_message')) {
        delete_option('zonify_error_message');
    }

    zonify_clear_all_caches();

    $data = array(
        'store' => get_site_url(),
        'event' => 'uninstall',
    );
    return zonify_send_request('/woocommerce/deactivate', $data);
}

function zonify_script()
{

    $attributes = array(
        'id' => ZONIFY_DEVELOPMENT . 'zonifyScript',
        'async' => true,
        'src' => esc_url("https://app.zonifyapp.com/dashboard/js/affiliate-woo.js"),
    );
    wp_print_script_tag($attributes);
}

function zonify_add_admin_css_js()
{
    wp_register_style('zonify_style', ZONIFY_URL . '/assets/css/style.css');
    wp_enqueue_style('zonify_style');
    wp_register_script('zonify-admin', ZONIFY_URL . '/assets/js/script.js', array('jquery'), '1.0.0');
    wp_enqueue_script('zonify-admin');
}

function zonify_admin_menu()
{
    add_menu_page('Zonify Settings', 'Zonify', 'manage_options', 'Zonify', 'zonify_admin_menu_page_html', ZONIFY_URL . '/assets/images/zonify_icon.png');
}

function zonify_has_woocommerce()
{
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

function zonify_admin_menu_page_html()
{
    include_once ZONIFY_PATH . '/views/zonify_admin_page.php';
}

function zonify_send_request($path, $data)
{
    try {
        $headers = array(
            'Content-Type' => 'application/json',
            'x-plugin-version' => ZONIFY_VERSION,
            'x-site-url' => get_site_url(),
            'x-wp-version' => get_bloginfo('version'),
        );

        if (zonify_has_woocommerce()) {
            $headers['x-woo-version'] = WC()->version;
        }

        $url = ZONIFY_API_URL . $path;
        $data = array(
            'headers' => $headers,
            'body' => json_encode($data),
        );
        zonify_log('sending request', $url);
        $response = wp_remote_post($url, $data);
        zonify_log('got response', $url);

        if (!is_wp_error($response)) {

            $decoded_response = json_decode(wp_remote_retrieve_body($response), true);

            return $decoded_response;
        }
        return 0;
    } catch (Exception $err) {
        zonify_handle_error('failed sending request', $err, $data);
    }
}

function zonify_log($message, $data = null)
{
    $log = null;

    if (isset($data)) {
        $log = "\n[Zonify] " . $message . ":\n" . print_r($data, true);
    } else {
        $log = "\n[Zonify] " . $message;
    }
    error_log($log);

    if (ZONIFY_DEBUG) {
        $plugin_log_file = plugin_dir_path(__FILE__) . 'debug.log';
        error_log($log . "\n", 3, $plugin_log_file);
    }
}

function zonify_handle_error($message, $err, $data = null)
{
    zonify_log($message, $err);
}

function zonify_plugin_redirect()
{
    exit(wp_redirect("admin.php?page=Zonify"));
}

function zonify_clear_all_caches()
{
    try {
        global $wp_fastest_cache;

        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix, $supercachedir;
            if (empty($supercachedir) && function_exists('get_supercache_dir')) {
                $supercachedir = get_supercache_dir();
            }
            wp_cache_clean_cache($file_prefix);
        }
        if (method_exists('WpFastestCache', 'deleteCache') && !empty($wp_fastest_cache)) {
            $wp_fastest_cache->deleteCache();
        }

        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            // Preload cache.
            if (function_exists('run_rocket_sitemap_preload')) {
                run_rocket_sitemap_preload();
            }
        }
        if (class_exists("autoptimizeCache") && method_exists("autoptimizeCache", "clearall")) {
            autoptimizeCache::clearall();
        }
        if (class_exists("LiteSpeed_Cache_API") && method_exists("autoptimizeCache", "purge_all")) {
            LiteSpeed_Cache_API::purge_all();
        }

        if (class_exists('\Hummingbird\Core\Utils')) {
            $modules = \Hummingbird\Core\Utils::get_active_cache_modules();
            foreach ($modules as $module => $name) {
                $mod = \Hummingbird\Core\Utils::get_module($module);

                if ($mod->is_active()) {
                    if ('minify' === $module) {
                        $mod->clear_files();
                    } else {
                        $mod->clear_cache();
                    }
                }
            }
        }
    } catch (Exception $e) {
        return 1;
    }
}
function zonify_installation()
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !get_option('editorify_error')) {
        $json['success'] = 1;
        $json['api_key'] = get_option('zonify_api_key');
    } else {
        $json["success"] = 0;
    }

    wp_send_json($json);
    wp_die();
}

?>