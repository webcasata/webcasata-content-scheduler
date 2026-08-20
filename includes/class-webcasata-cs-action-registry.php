<?php
/**
 * Central registry of available Action types.
 *
 * New action types (taxonomies, featured image, custom fields/ACF,
 * WooCommerce price...) register themselves here via the
 * `webcasata_cs_register_actions` filter. Nothing else in the plugin
 * needs to change when a new action type is added in a later phase.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Action_Registry.
 */
class Webcasata_CS_Action_Registry {

	/**
	 * Cached instances, keyed by slug.
	 *
	 * @var Webcasata_CS_Action[]|null
	 */
	private static $actions = null;

	/**
	 * Build (once) and return every registered action instance, keyed
	 * by slug.
	 *
	 * @return Webcasata_CS_Action[]
	 */
	public static function all() {
		if ( null !== self::$actions ) {
			return self::$actions;
		}

		/**
		 * Filter the list of available Action class names.
		 *
		 * Core Phase 1 registers only the Post Status action here.
		 * Later phases (and third-party extensions) add their own
		 * classes to this same array instead of modifying core files.
		 *
		 * @param string[] $classes Fully-qualified class names, each extending Webcasata_CS_Action.
		 */
		$classes = apply_filters(
			'webcasata_cs_register_actions',
			array(
				'Webcasata_CS_Action_Post_Status',
			)
		);

		$actions = array();

		foreach ( $classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			$instance = new $class_name();

			if ( ! $instance instanceof Webcasata_CS_Action ) {
				continue;
			}

			$actions[ $instance->get_slug() ] = $instance;
		}

		self::$actions = $actions;

		return self::$actions;
	}

	/**
	 * Get a single action instance by slug.
	 *
	 * @param string $slug Action slug.
	 * @return Webcasata_CS_Action|null
	 */
	public static function get( $slug ) {
		$actions = self::all();

		return isset( $actions[ $slug ] ) ? $actions[ $slug ] : null;
	}

	/**
	 * Get slug => label pairs for populating the admin "What do you
	 * want to change?" dropdown.
	 *
	 * @return array
	 */
	public static function get_choices() {
		$choices = array();

		foreach ( self::all() as $slug => $action ) {
			$choices[ $slug ] = $action->get_label();
		}

		return $choices;
	}
}
