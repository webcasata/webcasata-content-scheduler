<?php
/**
 * Central orchestrator: instantiates the loader, i18n, engine, and
 * admin classes, then registers all of their hooks in one place.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Core.
 */
class Webcasata_CS_Core {

	/**
	 * Hook loader.
	 *
	 * @var Webcasata_CS_Loader
	 */
	protected $loader;

	/**
	 * Scheduling engine, shared between define_engine_hooks() and
	 * define_admin_hooks() so the admin class can queue/cancel Action
	 * Scheduler actions through the same instance.
	 *
	 * @var Webcasata_CS_Engine
	 */
	protected $engine;

	/**
	 * Constructor. Builds collaborators and queues their hooks with
	 * the loader; nothing actually registers with WordPress until
	 * run() is called.
	 */
	public function __construct() {
		$this->loader = new Webcasata_CS_Loader();

		$this->define_i18n_hooks();
		$this->define_engine_hooks();
		$this->define_admin_hooks();
		$this->define_block_hooks();
	}

	/**
	 * Translation loading.
	 *
	 * @return void
	 */
	private function define_i18n_hooks() {
		$i18n = new Webcasata_CS_I18n();
		$this->loader->add_action( 'init', $i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Scheduling engine (Action Scheduler callbacks).
	 *
	 * @return void
	 */
	private function define_engine_hooks() {
		$engine = new Webcasata_CS_Engine();
		$engine->register_hooks();

		// Stash the engine on the container so admin hooks can reuse
		// the same instance instead of constructing a second one.
		$this->engine = $engine;
	}

	/**
	 * Admin menu, assets, and form handling.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$admin = new Webcasata_CS_Admin( $this->engine );

		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
	}

	/**
	 * Gutenberg block registration.
	 *
	 * @return void
	 */
	private function define_block_hooks() {
		$blocks = new Webcasata_CS_Blocks();
		$this->loader->add_action( 'init', $blocks, 'register_blocks' );
	}

	/**
	 * Fire all registered hooks.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}
}
