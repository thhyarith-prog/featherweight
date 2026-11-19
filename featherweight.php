<?php
/**
 * Plugin Name:       Featherweight
 * Plugin URI:        https://www.prasatdigital.com
 * Description:       Lighter media in one click: converts every image (JPG, PNG, GIF, BMP, TIFF, HEIC…) to WebP and every video (MP4, MOV, AVI, MKV, WMV…) to WebM, and serves the optimized files to visitors. Originals are never deleted. Formerly Ahnakot Media Optimizer.
 * Version:           2.1.5
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/thhyarith-prog/featherweight
 * Author:            Prasat Digital
 * Author URI:        https://www.prasatdigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       featherweight
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

define( 'FEATHERWEIGHT_VERSION', '2.1.5' );
define( 'FEATHERWEIGHT_FILE', __FILE__ );
define( 'FEATHERWEIGHT_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEATHERWEIGHT_URL', plugin_dir_url( __FILE__ ) );

// Updates come from the GitHub releases of this plugin.
require_once FEATHERWEIGHT_DIR . 'includes/prasat-updater.php';
Prasat_Updater::register( __FILE__, 'thhyarith-prog/featherweight' );

require_once FEATHERWEIGHT_DIR . 'includes/class-featherweight.php';
if ( is_admin() ) {
	require_once FEATHERWEIGHT_DIR . 'includes/class-featherweight-admin.php';
	require_once FEATHERWEIGHT_DIR . 'includes/prasat-hub.php';
	Prasat_Hub::register( 'featherweight', array( 'assets' => FEATHERWEIGHT_URL . 'assets/', 'health' => array( 'Featherweight_Admin', 'health' ), 'dashboard' => array( 'Featherweight_Admin', 'dashboard' ) ) );
}

register_activation_hook( __FILE__, array( 'Featherweight', 'activate' ) );
add_action( 'admin_init', array( 'Featherweight', 'migrate' ) );
Featherweight::init();
