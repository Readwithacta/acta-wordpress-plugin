<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all options and transients created by Acta.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Tell Acta this site is disconnecting, before the credentials are deleted.
 *
 * Acta marks the publisher inactive so it stops serving the paywall and stops
 * taking payments it can no longer fulfil — once this plugin is gone, article
 * content can no longer be fetched from the site.
 *
 * Nothing is deleted on Acta's side. The account, Stripe details and any
 * balance owed are kept, so reinstalling reconnects to the same publisher.
 *
 * Best-effort: uninstall must never be blocked by a network problem, so
 * failures are ignored. Acta also detects removal on its own.
 *
 * This file runs standalone — the main plugin file is not loaded, so the
 * backend URL cannot come from ACTA_BACKEND_URL and is repeated here.
 */
function acta_uninstall_notify_backend() {
	$publisher_id = get_option( 'acta_publisher_id', '' );
	$secret       = get_option( 'acta_secret_key', '' );

	if ( empty( $publisher_id ) || empty( $secret ) ) {
		return;
	}

	wp_remote_post( 'https://api.readwithacta.com/api/v1/public/disconnect-wordpress', array(
		'timeout'  => 10,
		'blocking' => false,
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode( array(
			'publisherId'     => $publisher_id,
			'siteUrl'         => home_url(),
			'pluginSecretKey' => $secret,
		) ),
	) );
}

$options = array(
	'acta_secret_key',
	'acta_publisher_id',
	'acta_stripe_url',
	'acta_connection_status',
	'acta_subscribe_override',
	'acta_do_activation_redirect',
);

// Per-blog transients.
$transients = array(
	'acta_default_rev_share',
);

// Network-wide (site) transients — stored once, not per-blog.
$site_transients = array(
	'acta_update_data',
);

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		// Must run before the options below are deleted — it needs them.
		acta_uninstall_notify_backend();
		foreach ( $options as $option ) {
			delete_option( $option );
		}
		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}
		restore_current_blog();
	}
	foreach ( $site_transients as $transient ) {
		delete_site_transient( $transient );
	}
} else {
	// Must run before the options below are deleted — it needs them.
	acta_uninstall_notify_backend();
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}
	foreach ( $site_transients as $transient ) {
		delete_site_transient( $transient );
	}
}
