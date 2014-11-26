<?php
/**
 * Plugin Name: Custom Code for p.medonline.at
 * Description: Site-specific functionality for p.medonline.at
 * Author: Frank St&uuml;rzebecher
 * Version: 0.2
 * Plugin URI: https://github.com/medizinmedien/allgemein/cc-p-medonline-at
 */

defined( 'ABSPATH' ) || exit();

/**
 * Load Fullstory from Shared Includes.
 */
function cc_pmed_load_fullstory() {

	$fullstory_file = WP_PLUGIN_DIR . '/Shared-Includes/inc/track/fullstory-tracking.php';

	if( file_exists( $fullstory_file ) )
		include( $fullstory_file );

}
add_action( 'wp_footer',    'cc_pmed_load_fullstory' );
add_action( 'login_footer', 'cc_pmed_load_fullstory' );

/**
* Embed Groove code into page footers to avoid anonymous support requests.
*/
function cc_pmed_add_groove() {

	// No include on special pages.
	if(( defined( 'DOING_CRON' ) && DOING_CRON )
	|| ( defined( 'XMLRPC_REQUEST') && XMLRPC_REQUEST )
	|| ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE )
	|| ( defined( 'DOING_AJAX' ) && DOING_AJAX)
	|| is_page( array('impressum', 'kontakt') ))
		return;

	$groove_include = WP_PLUGIN_DIR . '/Shared-Includes/inc/groove/groove-help-widget.php';

	if( file_exists( $groove_include ) )
		include( $groove_include );

}
//add_action( 'wp_footer',  'cc_pmed_add_groove' );
//add_action( 'login_head', 'cc_pmed_add_groove' );


/**
 * Make Headway links https when needed.
 */
 // Callback function for ob_start.
function cc_pmed_headway_replace_https( $buffer ){
	$scheme = is_ssl() ? 'https://' : 'http://';
	return str_replace( 'http://p.medonline.at', $scheme . 'p.medonline.at', $buffer );
}
// Start buffering.
function cc_pmed_begin_headway_obstart() {
	ob_start('cc_pmed_headway_replace_https');
}
add_action('headway_html_open', 'cc_pmed_begin_headway_obstart');
// Finish buffering.
function cc_pmed_headway_ob_end_flush() {
	ob_end_flush;
}
add_action('headway_html_close', 'cc_pmed_headway_ob_end_flush');
