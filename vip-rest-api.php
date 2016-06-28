<?php
/*
 Plugin Name: VIP REST API Endpoints
 Plugin URI: https://vip.wordpress.com/
 Description: Add custom REST API endpoints for VIP requests
 Author: Erick Hitter, Automattic
 Version: 0.1
 */

class WPCOM_VIP_REST_API_Endpoints {
	/**
	 * SINGLETON
	 */
	private static $__instance = null;

	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
		}

		return self::$__instance;
	}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */
	private $namespace = 'wpcom-vip/v1';

	private $cached_sites_list = 'wpcom-vip-sites-list';

	/**
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Register API routes
	 */
	public function rest_api_init() {
		register_rest_route( $this->namespace, '/sites/', array(
			'methods' => 'GET',
			'callback' => array( $this, 'list_sites' ),
		) );
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Build list of sites on a multisite network
	 *
	 * For consistency, will also return result on single-site
	 */
	public function list_sites() {
		$sites = array();

		if ( is_multisite() ) {
			// `get_sites()` won't be in Core until at least 4.6
			if ( function_exists( 'get_sites' ) ) {
				$_sites = get_sites( array(
					'public'   => 1,
					'archived' => 0,
					'spam'     => 0,
					'deleted'  => 0,
					'fields'   => 'ids',
				) );
			} else {
				// Add support for 4.4 and 4.5, as `get_sites()` wasn't introduced until 4.6
				$_sites = get_site_transient( $this->cached_sites_list );

				if ( ! is_array( $_sites ) ) {
					global $wpdb;

					$_sites = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} WHERE `public` = 1 AND `archived` = 0 AND `spam` = 0 AND `deleted` = 0 LIMIT 1000;" );

					if ( is_array( $_sites ) ) {
						set_site_transient( $this->cached_sites_list, $_sites, 30 * MINUTE_IN_SECONDS );
					} else {
						$_sites = false;
					}
				}
			}

			// Inflate raw list of site IDs, if available
			if ( is_array( $_sites ) ) {
				// Switch to the blog to ensure certain domain filtering is respected
				foreach ( $_sites as $_site ) {
					switch_to_blog( $_site );

					$sites[] = array(
						'domain_name' => parse_url( home_url(), PHP_URL_HOST ),
					);

					restore_current_blog();
				}
			} else {
				$sites = new WP_Error( 'no-sites-found', 'Failed to retrieve any sites for this multisite network.' );
			}
		} else {
			// Provided for consistency, even though this provides no insightful response
			$sites[] = array(
				'domain_name' => parse_url( home_url(), PHP_URL_HOST ),
			);
		}

		return new WP_REST_Response( $this->format_response( $sites ) );
	}

	/**
	 * Mimic response format from VIP Go API
	 *
	 * Consistency simplifies incorporating data from the VIP Go API with this API
	 */
	private function format_response( $data ) {
		$response = array();

		if ( is_wp_error( $data ) ) {
			$response['status'] = 'error';
			$response['data']   = false;
		} else {
			$response['status'] = 'success';
			$response['page']      = 1;
			$response['pagesize']  = 1000;
			$response['totalrecs'] = count( $data );
			$response['result']    = $response['totalrecs'];

			$response['data'] = $data;
		}

		return $response;
	}
}

WPCOM_VIP_REST_API_Endpoints::instance();
