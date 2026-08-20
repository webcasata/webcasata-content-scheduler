<?php
/**
 * Registers this plugin's Gutenberg blocks.
 *
 * @package Webcasata_Content_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webcasata_CS_Blocks.
 */
class Webcasata_CS_Blocks {

	/**
	 * Register every block that ships with this plugin.
	 *
	 * The register_block_type() function reads block.json directly —
	 * including its "render" (server-side render.php), "editorScript",
	 * "style", and "viewScript" fields — so there's nothing else to
	 * wire up here as
	 * more blocks are added in later phases; each just needs its own
	 * build/ directory registered on this same hook.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$block_path = WEBCASATA_CS_PATH . 'blocks/scheduled-content/build';

		if ( ! file_exists( $block_path . '/block.json' ) ) {
			return;
		}

		register_block_type( $block_path );
	}
}
