<?php
/**
 * Uninstall: removes Featherweight's settings. Converted .webp / .webm files and the originals
 * stay in the uploads folder.
 *
 * @package Featherweight
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'featherweight_settings', 'featherweight_force', 'featherweight_migrated' ) as $featherweight_option ) {
	delete_option( $featherweight_option );
}
