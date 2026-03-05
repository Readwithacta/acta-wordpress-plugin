<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all options and transients created by Acta.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'acta_secret_key',
	'acta_publisher_id',
	'acta_stripe_url',
	'acta_connection_status',
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
