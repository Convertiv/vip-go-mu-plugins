<?php

namespace Automattic\VIP\Search;

use \ElasticPress\Indexable as Indexable;
use \ElasticPress\Indexables as Indexables;

use \WP_Error as WP_Error;

class Versioning {
	const INDEX_VERSIONS_OPTION = 'vip_search_index_versions';
	
	/**
	 * The currently used index version, by type. This lets us override the active version for indexing while another index is active
	 */
	private $current_index_version_by_type = array();

	/**
	 * An internal record of every object that has been queued up (via Queue::queue_object()) so that we can replicate those jobs
	 * to the other index versions at the end of the request
	 */
	private $queued_objects_by_type_and_version = array();

	public function __construct() {
		// When objects are added to the queue, we want to replicate that out to all index versions, to keep them in sync
		add_action( 'vip_search_indexing_object_queued', [ $this, 'action__vip_search_indexing_object_queued' ], 10, 4 );
	}

	/**
	 * Set the current (not active) version for a given Indexable. This allows us to work on other index versions without making
	 * that index active
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to temporarily set the current index version
	 * @return bool|WP_Error True on success, or WP_Error on failure
	 */
	public function set_current_version_number( Indexable $indexable, int $version_number ) {
		// Validate that the requested version is known
		$versions = $this->get_versions( $indexable );

		if ( ! isset( $versions[ $version_number ] ) ) {
			return new WP_Error( 'invalid-index-version', sprintf( 'The requested index version %d does not exist', $version_number ) );
		}

		$this->current_index_version_by_type[ $indexable->slug ] = $version_number;

		return true;
	}

	/**
	 * Reset the current version for a given Indexable. This will default back to the active index, with no override
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to reset the current index version
	 * @return bool|WP_Error True on success
	 */
	public function reset_current_version_number( Indexable $indexable ) {
		unset( $this->current_index_version_by_type[ $indexable->slug ] );

		return true;
	}

	/**
	 * Get the current index version number
	 * 
	 * The current index number is the index that should be used for requests. It is different than the active index, which is the index
	 * that has been designated as the default for all requests. The current index can be overridden to make requests to other indexs, such as
	 * for indexing content on them while they are still inactive
	 * 
	 * This defaults to the active index, but can be overridden by calling Versioning::set_current_version_number()
	 * 
	 * NOTE - purposefully not adding a typehint due to a warning emitted by our very old version of PHPUnit on PHP 7.4 
	 * (Function ReflectionType::__toString() is deprecated), because we mock this function, which causes __toString() to be called for params
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to get the current version number
	 * 
	 * @return int The current version number
	 */
	public function get_current_version_number( $indexable ) {
		$override = isset( $this->current_index_version_by_type[ $indexable->slug ] ) ? $this->current_index_version_by_type[ $indexable->slug ] : null;

		if ( is_int( $override ) ) {
			return $override;
		}

		return $this->get_active_version_number( $indexable );
	}

	/**
	 * Retrieve the active index version for a given Indexable
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to get the active index version
	 * @return int The currently active index version
	 */
	public function get_active_version( Indexable $indexable ) {
		$versions = $this->get_versions( $indexable );

		$active_statuses = wp_list_pluck( $versions, 'active' );

		$array_index = array_search( true, $active_statuses, true );

		if ( false === $array_index ) {
			return null;
		}

		return $versions[ $array_index ];
	}

	/**
	 * Grab just the version number for the active version
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable to get the active version number for
	 * @return int The currently active version number
	 */
	public function get_active_version_number( Indexable $indexable ) {
		$active_version = $this->get_active_version( $indexable );

		if ( ! $active_version ) {
			return 1;
		}

		return $active_version['number'];
	}

	public function get_inactive_versions( Indexable $indexable ) {
		$versions = $this->get_versions( $indexable );
		$active_version_number = $this->get_active_version_number( $indexable );

		unset( $versions[ $active_version_number ] );

		return $versions;
	}

	/**
	 * Retrieve details about available index versions
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to retrieve index versions
	 * @return array Array of index versions
	 */
	public function get_versions( Indexable $indexable ) {
		if ( Search::is_network_mode() ) {
			$versions = get_site_option( self::INDEX_VERSIONS_OPTION, array() );
		} else {
			$versions = get_option( self::INDEX_VERSIONS_OPTION, array() );
		}

		$slug = $indexable->slug;

		if ( ! isset( $versions[ $slug ] ) || ! is_array( $versions[ $slug ] ) || empty( $versions[ $slug ] ) ) {
			return array(
				1 => array(
					'number' => 1,
					'active' => true,
					'created_time' => null, // We don't know when it was actually created
					'activated_time' => null,
				),
			);
		}

		// Normalize the versions to ensure consistency (have all fields, etc)
		return array_map( array( $this, 'normalize_version' ), $versions[ $slug ] );
	}

	/**
	 * Normalize the fields of a version, to handle old or incomplete data
	 * 
	 * This is important to keep the data stored in the option consistent and current when changes to the structure are needed
	 * 
	 * @param array The index version to normalize
	 * @return array The index version, with all data normalized
	 */
	public function normalize_version( $version ) {
		$version_fields = array(
			'number',
			'active',
			'created_time',
			'activated_time',
		);

		if ( ! is_array( $version ) ) {
			$version = array();
		}

		$keys = array_keys( $version );

		$missing_keys = array_diff( $version_fields, $keys );

		foreach ( $missing_keys as $key ) {
			$version[ $key ] = null;
		}

		return $version;
	}

	/**
	 * Retrieve details about a given index version
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to retrieve the index version
	 * @return array Array of index versions
	 */
	public function get_version( Indexable $indexable, int $version_number ) {
		$slug = $indexable->slug;
	
		$versions = $this->get_versions( $indexable );

		if ( ! isset( $versions[ $version_number ] ) ) {
			return null;
		}

		return $versions[ $version_number ];
	}

	/**
	 * Retrieve details about available index versions
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to create a new version
	 * @return bool Boolean indicating if the new version was successfully added or not
	 */
	public function add_version( Indexable $indexable ) {
		$slug = $indexable->slug;
	
		$versions = $this->get_versions( $indexable );

		$new_version_number = $this->get_next_version_number( $versions );

		if ( ! $new_version_number ) {
			return new WP_Error( 'unable-to-get-next-version', 'Unable to determine next index version' );
		}

		$new_version = array(
			'number' => $new_version_number,
			'active' => false,
			'created_time' => time(),
			'activated_time' => null,
		);

		$versions[ $new_version_number ] = $new_version;

		$result = $this->update_versions( $indexable, $versions );

		if ( true !== $result ) {
			return $result;
		}

		return $new_version;
	}

	/**
	 * Save details about available index versions
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to update versions
	 * @param array Array of version information for the given Indexable
	 * @return bool Boolean indicating if the version information was saved successfully or not
	 */
	public function update_versions( Indexable $indexable, $versions ) {
		if ( Search::is_network_mode() ) {
			$current_versions = get_site_option( self::INDEX_VERSIONS_OPTION, array() );
		} else {
			$current_versions = get_option( self::INDEX_VERSIONS_OPTION, array() );
		}

		$current_versions[ $indexable->slug ] = $versions;
	
		if ( Search::is_network_mode() ) {
			return update_site_option( self::INDEX_VERSIONS_OPTION, $current_versions, 'no' );
		}

		return update_option( self::INDEX_VERSIONS_OPTION, $current_versions, 'no' );
	}

	/**
	 * Determine what the next index version number is, based on an array of existing index versions
	 * 
	 * Versions start at 1
	 * 
	 * @param array $versions Array of existing versions from which to calculate the next version

	 */
	public function get_next_version_number( $versions ) {
		$new_version = null;


		if ( ! empty( $versions ) && is_array( $versions ) ) {
			$new_version = max( array_keys( $versions ) );
		}

		// If site has no versions yet (1 version), the next version is 2
		if ( ! is_int( $new_version ) || $new_version < 2 ) {
			$new_version = 2;
		} else {
			$new_version++;
		}

		return $new_version;
	}

	/**
	 * Activate a new version of an index
	 * 
	 * Verifies that the new target index does in-fact exist, then marks it as active
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to activate the new index
	 * @param int $version_number The new index version to activate
	 * @return bool|WP_Error Boolean indicating success, or WP_Error on error 
	 */
	public function activate_version( Indexable $indexable, int $version_number ) {
		$versions = $this->get_versions( $indexable );

		// If this wasn't a valid version, abort with error
		if ( ! isset( $versions[ $version_number ] ) ) {
			return new WP_Error( 'invalid-index-version', sprintf( 'The index version %d was not found', $version_number ) );
		}

		// Mark all others as inactive, activate the new one
		foreach ( $versions as &$version ) {
			if ( $version_number === $version['number'] ) {
				$version['active'] = true;
				$version['activated_time'] = time();
			} else {
				$version['active'] = false;
			}
		}

		if ( ! $this->update_versions( $indexable, $versions ) ) {
			return new WP_Error( 'failed-activating-version', sprintf( 'The index version %d failed to activate', $version_number ) );
		}

		return true;
	}

	/**
	 * Get stats for a given index version, such as how many documents it contains
	 * 
	 * @param \ElasticPress\Indexable $indexable The Indexable type for which to activate the new index
	 * @param int The index version to get stats for
	 * @return array Array of index stats
	 */
	public function get_version_stats( Indexable $indexable, $version ) {
		// Need helper function in \ElasticPress\Elasticsearch
	}

	/**
	 * Implements the vip_search_indexing_object_queued action to keep track of queued objects so that we can transparently
	 * replicate the queued job to the non-active index versions
	 * 
	 *
	 * @param int $object_id Object id
	 * @param string $object_type Object type (the Indexable slug)
	 * @param array $options The options passed to queue_object()
	 * @param int $index_version The index version that was used when queuing the object
	 */
	public function action__vip_search_indexing_object_queued( $object_id, $object_type, $options, $index_version ) {
		// Each time an object is queued, we keep track of that by version + type, so on shutdown, we can process them all in bulk
		if ( ! isset( $this->queued_objects_by_type_and_version[ $object_type ] ) ) {
			$this->queued_objects_by_type_and_version[ $object_type ] = array();
		}

		if ( ! isset( $this->queued_objects_by_type_and_version[ $object_type ][ $index_version ] ) ) {
			$this->queued_objects_by_type_and_version[ $object_type ][ $index_version ] = array();
		}

		$this->queued_objects_by_type_and_version[ $object_type ][ $index_version ][] = array( $object_id, $options );
	}

	/**
	 * When the request finishes, find all items that had been queued up on the active index and replicate those jobs out to each non-active index version
	 * 
	 * This ensures that the active index version is treated as The Truth, and non-active index versions follow it (and not the other way around)
	 */
	public function action__shutdown() {
		$this->replicate_queued_objects_to_other_versions( $this->queued_objects_by_type_and_version );
	}

	/**
	 * Given an array of object types and the objects queued by version, replicate those jobs to the
	 * _other_ index versions to keep them in sync
	 * 
	 * @param $queued_objects Multidimensional array of queued objects, keyed first by type, then index version
	 */
	public function replicate_queued_objects_to_other_versions( $queued_objects ) {
		if ( ! is_array( $queued_objects ) || empty( $queued_objects ) ) {
			return;
		}

		// Loop over every type of object that was changed
		foreach ( $queued_objects as $object_type => $objects_by_version ) {
			$indexable = \ElasticPress\Indexables::factory()->get( $object_type );

			// If it's not a valid indexable, just skip
			if ( ! $indexable ) {
				continue;
			}

			$versions = $this->get_versions( $indexable );

			// Do we have any other index versions for this type? If not, nothing to do.
			if ( ! $versions || count( $versions ) <= 1 ) {
				continue;
			}

			$active_version_number = $this->get_active_version_number( $indexable );

			// Were there any changes to the active version? If not, we skip - we don't keep replicate non-active indexes to others
			if ( ! isset( $objects_by_version[ $active_version_number ] ) || empty( $objects_by_version[ $active_version_number ] ) ) {
				continue;
			}

			// Other index versions, besides active
			$inactive_versions = $this->get_inactive_versions( $indexable );

			// There were changes for active version - now we need to loop over every object that was queued for the active version and replicate that job to the other versions
			foreach ( $inactive_versions as $version ) {
				$this->set_current_version_number( $indexable, $version['number'] );

				foreach ( $objects_by_version[ $active_version_number ] as $entry ) {
					$object_id = $entry[0];
					$options = $entry[1];

					// Override the index version in the options
					$options['index_version'] = $version['number'];

					\Automattic\VIP\Search::instance()->queue->queue_object( $object_id, $object_type, $options );
				}

				$this->reset_current_version_number( $indexable );
			}
		}
	}
}
