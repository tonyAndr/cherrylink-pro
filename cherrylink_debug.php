<?php

// Disable direct access
defined('ABSPATH') || exit;
// Define lib name
define('LINKATE_DEBUG', true);

add_action('wp_ajax_output_admin_debug_env', array('CHERRYLINK_DEBUGGER', '_admin_debug_env'));
add_action('wp_ajax_output_admin_debug_links', array('CHERRYLINK_DEBUGGER', '_admin_debug_links'));
add_action('wp_ajax_output_admin_debug_wpdb', array('CHERRYLINK_DEBUGGER', '_admin_debug_wpdb'));

class CHERRYLINK_DEBUGGER
{

    public static function deprecated_cherry_debug($func, $variable, $description = '')
    {
        // $options = get_option('linkate-posts', []); 
        // if ($options['debug_enabled'] === "true") {
        //     echo "FUNC: " . $func . PHP_EOL;
        //     if ($description)
        //         echo $description . PHP_EOL;
        //     echo '<pre>';
        //     var_dump($variable);
        //     echo '</pre>';
        // }
    }

    public static function _admin_debug_env()
    {
        $environment = new \W18T();
        $theme = wp_get_theme();

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $plugins_output = '';
        if ($all_plugins) {
            $plugins_output = implode("\n", array_keys($all_plugins));
        }

        $dmt = "==================================" . PHP_EOL;
        $output = $dmt . "> Enviroment" . PHP_EOL;
        $output .= "OS: " . $environment->operating_system . " - " . $environment->operating_system->version . PHP_EOL;
        $output .= "PHP: " . $environment->interpreter->version . PHP_EOL;
        $output .= "WEB: " . $environment->web_server->version . PHP_EOL;
        $output .= "DB: " . $environment->database_server->version . PHP_EOL;
        $output .= "WP: " . $environment->platform->version . PHP_EOL;
        $output .= $dmt . "> Theme" . PHP_EOL;
        $output .= "Name: " . $theme->get('Name') . " - " . $theme->get('Version') . PHP_EOL;
        $output .= $dmt . "> Plugins" . PHP_EOL;
        $output .= $plugins_output . PHP_EOL;
        $output .= $dmt . "> CherryLink" . PHP_EOL;
        $output .= "Version: " . LinkatePosts::get_linkate_version() . PHP_EOL;
        $output .= $dmt . "> Links output " . PHP_EOL;

        echo $output;
        wp_die();
    }
    public static function _admin_debug_links()
    {
        $post_id = $_POST['post_id'];

        $is_term = 0;
        $offset = 0;
        $custom_text = '';
        $mode = 'gutenberg';

        $data = '';
        $data =  linkate_posts("manual_ID=" . $post_id . "&is_term=" . $is_term . "&offset=" . $offset . "&mode=" . $mode . "&custom_text=" . $custom_text . "&");
        // try {

        // } catch (Exception $e) {
        //     $data = $e->getMessage();
        //     $data .= PHP_EOL . $e->getTraceAsString();
        // }

        wp_send_json($data);
    }

    public static function _admin_debug_wpdb()
    {
        $dmt = PHP_EOL . PHP_EOL . "==================================" . PHP_EOL;
        $output = $dmt . "> WPDB" . PHP_EOL;
        

        $db_info = get_option('cherry_debug_info');

        if ($db_info) {
            $error = $db_info['error'] ? $db_info['error'] : 'none';
            $output .= "WPDB Last Query: " . $db_info['query'] . PHP_EOL;
            $output .= "WPDB Last Error: " . $error . PHP_EOL;
        } else {
            $output .= "Not found";
        }

        echo $output;
        wp_die();
    }
}
