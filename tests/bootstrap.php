<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

define( 'VIP_GO_MUPLUGINS_TESTS__DIR__', __DIR__ );
define( 'WPMU_PLUGIN_DIR', getcwd() );

// Constant configs
// Ideally we'd have a way to mock these
define( 'FILES_CLIENT_SITE_ID', 123 );
define( 'WPCOM_VIP_MAIL_TRACKING_KEY', 'key' );
define( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING', true );

function _manually_load_plugin() {
	require_once( __DIR__ . '/../000-vip-init.php' );
	require_once( __DIR__ . '/../001-core.php' );
	require_once( __DIR__ . '/../a8c-files.php' );

	require_once( __DIR__ . '/../async-publish-actions.php' );
	require_once( __DIR__ . '/../performance.php' );
	require_once( __DIR__ . '/../security.php' );

	require_once( __DIR__ . '/../schema.php' );

	require_once( __DIR__ . '/../vip-jetpack/vip-jetpack.php' );

	// Proxy lib
	require_once( __DIR__ . '/../lib/proxy/ip-forward.php' );
	require_once( __DIR__ . '/../lib/proxy/class-iputils.php' );

	require_once( __DIR__ . '/../vip-cache-manager.php' );
	require_once( __DIR__ . '/../vip-mail.php' );
	require_once( __DIR__ . '/../vip-rest-api.php' );
	require_once( __DIR__ . '/../vip-plugins.php' );

	require_once( __DIR__ . '/../wp-cli.php' );

	require_once( __DIR__ . '/../z-client-mu-plugins.php' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
