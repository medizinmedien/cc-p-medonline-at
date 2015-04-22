<?php
/**
 * Plugin Name: Custom Code for p.medonline.at
 * Description: Essentielle Funktionalit&auml;t f&uuml;r p.medonline.at. Betrifft vor allem den Seitenschutz, falls Besucher nicht eingeloggt sind. HTTPS wird grunds&auml;tzlich erzwungen. Fullstory-Einbindung. Verhinderung des Einbettens von p.medonline.at-Inhalten in externe Frames.
 * Author: Frank St&uuml;rzebecher
 * Version: 0.4.1
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
	ob_end_flush();
}
add_action('headway_html_close', 'cc_pmed_headway_ob_end_flush');


/**
 * Handler for content access when visitors are not logged in.
 *
 * By default all pages redirect to the login page. Excluded from this rule are
 * the front page and a very small list of excluded pages. Logged in users have
 * access to all content. A post password protected page will not redirect.
 */
add_action( 'template_redirect', 'cc_pmed_not_logged_in', 1 );
function cc_pmed_not_logged_in() {
	global $post;

	$slug = $post->post_name;

	$redirect_excludes = array(
		'impressum',
		'kontakt',
		'gesdine'
	);

	if ( ! is_front_page() && ! is_user_logged_in() && ! in_array( $slug, $redirect_excludes )
	&& empty( $post->post_password ) && empty( get_metadata( 'post', $post->ID, 'is_public', true ) ) ) {

		if ( function_exists( 'is_otat_protected_post' ) && is_otat_protected_post() ) {

			// Hand access to plugin "One-time access tokens".
			return;

		} else {
			wp_redirect( 'https://medonline.at/wp-login.php' );
		}

	}
}


/**
 * Force SSL for p.medonline.at.
 */
add_action( 'template_redirect', 'cc_pmed_force_ssl' );
function cc_pmed_force_ssl() {
	global $post;

	if ( $_SERVER['SERVER_PORT'] == 80 ) {
		wp_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		exit();
	}
}


/**
 * Add X-Frame-Options directive to secure pages from being embedded.
 */
function cc_pmed_add_xframeoptions() {
	if ( ! is_admin() )
	?><meta http-equiv="X-Frame-Options" content="deny" /><?php
}
add_action( 'wp_head', 'cc_pmed_add_xframeoptions', 5 );


/**
 * Force the "One time access tokens" plugin to create SSL cookies.
 */
add_filter( 'otat_force_https_cookie', '__return_true' );


/**
 * Avoid autocomplete in password fields of protected posts.
 */
function cc_pmed_secure_postpass_form ( $form ) {

	$needed_form = str_replace(
		array(
			'class="post-password-form"',
			'name="post_password"',
		),
		array(
			'class="post-password-form" autocomplete="off"',
			'name="post_password" autocomplete="off"',
		),
		$form
	);
	return $needed_form;
}
add_filter( 'the_password_form', 'cc_pmed_secure_postpass_form');


/**
 * Provide pluggable WP function to create the post password cookie
 * with secure attributes. Works only when a password field will be used.
 * So NO effect on tokenized posts handled by plugin "Post Password Token"!
 */
if ( !function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect($location, $status = 302) {
		// Added part: make the hardcoded WP cookie "secure" and "httponly".
		// Fires only when the password field is used (Post PW Token circumvents this!).
		if ( isset($_GET['action']) && $_GET['action'] == 'postpass' ) { // set in wp-login.php
			global $hasher, $expire;
			$ssl = true;

			setcookie( 'wp-postpass_' . COOKIEHASH,
				$hasher->HashPassword( wp_unslash( $_POST['post_password'] ) ),
				$expire,
				COOKIEPATH,
				'',   // actual domain
				$ssl, // secure
				true  // httponly
			);
			// Since we die here, the cookie has to be renewed already.
			if( strlen($location) == 0 )
				wp_die( 'Ihr Webbrowser scheint keinen Referer zu senden.<br/>Bitte verwenden Sie daher jetzt die Zur&uuml;ck-Schaltfl&auml;che Ihres Browsers und dr&uuml;cken Sie dann die Taste F5 auf Ihrer Tastatur ("Aktualisieren").' );

		} // End of added part - what follows is WP stuff.

		// Need to look at the URL the way it will end up in wp_redirect()
		$location = wp_sanitize_redirect($location);
		$location = wp_validate_redirect($location, admin_url());
		wp_redirect($location, $status);
	}
}

// Just a note:
////////////////////
// To change hardcoded cookie attributes of the plugin "Post Password Token"
// to more secure ones you would have to do additionally:
//   remove_action( 'template_redirect', 'ppt_template_redirect' );
// and then hijack cookie creation by a custom cc_pmed_replace_ppt_cookie()
// function. Finally do a:
//   add_action( 'template_redirect', 'cc_pmed_replace_ppt_cookie', 5 );


