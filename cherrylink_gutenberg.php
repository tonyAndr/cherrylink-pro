<?php

/*
 * CherryLink Plugin
 */

// Disable direct access
defined( 'ABSPATH' ) || exit;
// Define lib name
define('LINKATE_GUTENBERG_ASSETS', true);

/**
 * Registers all block assets so that they can be enqueued through Gutenberg in
 * the corresponding context.
 *
 * Passes translations to JavaScript.
 */
function register_cherrylink_gutenberg_meta() {

    
	// CRB META 
	register_post_meta( '', 'crb-meta-links', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
	) );

	register_post_meta( '', 'crb-meta-show', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
	) );

	register_post_meta( '', 'crb-meta-show-edited', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
	) );

	register_post_meta( '', 'crb-meta-use-manual', array(
		'show_in_rest' => true,
		'single' => true,
		'type' => 'string',
    ) );
    
}

function register_cherrylink_gutenberg_scripts() {
    	// automatically load dependencies and version
    // $asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');

    $js_path = '';
    if (($_SERVER['HTTP_HOST']) === 'seoplugs') {
        //DEBUG ONLY 
        $js_path = plugins_url( '../gutenberg-src/build/cherry-gutenberg.js', __FILE__ );
    } else {
        //RELEASE
        $js_path = plugins_url( 'js/cherry-gutenberg.js', __FILE__ );
    }
	wp_register_script(
		'cherrylink-gutenberg',
		$js_path,
		array('react', 'wp-blocks', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-polyfill'),
		LinkatePosts::get_linkate_version()
    );

	wp_localize_script( 'cherrylink-gutenberg', 'ajax_var', array(
        'url'    => 'admin-ajax.php',
        'nonce'  => wp_create_nonce( 'cherry_nonce' ),
    ) );
    wp_enqueue_script( 'cherrylink-gutenberg' );

}

add_action( 'init', 'register_cherrylink_gutenberg_meta' );