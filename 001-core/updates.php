<?php

namespace Automattic\VIP\Core\Updates;

/**
 * Show plugin update notices on the plugins page.
 *
 * This is basically a copy of Core's wp_plugin_update_rows() function.
 * The only difference is that we use the activate_plugins cap instead of update_plugins.
 * Because no one on VIP has update_plugins, core's notices are never displayed.
 * With this change, we make them visible.
 */
function show_plugin_update_notices() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$plugins = get_site_transient( 'update_plugins' );

	if ( isset( $plugins->response ) && is_array( $plugins->response ) ) {
		$plugins = array_keys( $plugins->response );
		foreach ( $plugins as $plugin_file ) {
			add_action( "after_plugin_row_{$plugin_file}", 'wp_plugin_update_row', 10, 2 );
		}
	}
}
add_action( 'load-plugins.php', __NAMESPACE__ . '\show_plugin_update_notices', 20 ); // After wp_update_plugins() is called.
