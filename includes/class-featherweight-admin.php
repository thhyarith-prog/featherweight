<?php
/**
 * Featherweight admin screen (Prasat Digital UI kit): Media → Featherweight.
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

class Featherweight_Admin {

	const SLUG = 'featherweight';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FEATHERWEIGHT_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function menu() {
		add_submenu_page( Prasat_Hub::SLUG, 'Featherweight', 'Featherweight', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Optimize', 'featherweight' ) . '</a>' );
		return $links;
	}

	private static function on_page() {
		return isset( $_GET['page'] ) && self::SLUG === $_GET['page']; // phpcs:ignore
	}

	public static function body_class( $c ) {
		return self::on_page() ? $c . ' pd-body' : $c;
	}

	public static function total( $mimes ) {
		$q = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => $mimes, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return (int) $q->found_posts;
	}

	/** Numbers for the screen: what is left, the totals and the overall percentage. */
	public static function state() {
		$img     = Featherweight::image_engine();
		$ff      = Featherweight::ffmpeg();
		$c       = Featherweight::counts();
		$img_tot = self::total( Featherweight::image_mimes() );
		$vid_tot = self::total( Featherweight::video_mimes() );
		$img_left = $img ? $c['images'] : 0;
		$vid_left = $ff ? $c['videos'] + $c['videosRunning'] : 0;
		$all     = max( 1, ( $img ? $img_tot : 0 ) + ( $ff ? $vid_tot : 0 ) );
		return array(
			'img'      => $img,
			'ff'       => $ff,
			'img_tot'  => $img_tot,
			'vid_tot'  => $vid_tot,
			'img_left' => $img_left,
			'vid_left' => $vid_left,
			'todo'     => $img_left + $vid_left,
			'all'      => $all,
			'pct'      => (int) round( ( 1 - ( $img_left + $vid_left ) / $all ) * 100 ),
		);
	}

	public static function assets( $hook ) {
		if ( ! self::on_page() ) {
			return;
		}
		$v = FEATHERWEIGHT_VERSION . '.' . (int) @filemtime( FEATHERWEIGHT_DIR . 'assets/admin.js' ) . (int) @filemtime( FEATHERWEIGHT_DIR . 'assets/admin.css' ); // phpcs:ignore
		wp_enqueue_style( 'featherweight-ui', FEATHERWEIGHT_URL . 'assets/prasat-ui.css', array(), $v );
		wp_enqueue_style( 'featherweight-admin', FEATHERWEIGHT_URL . 'assets/admin.css', array( 'featherweight-ui' ), $v );
		wp_enqueue_script( 'featherweight-ui', FEATHERWEIGHT_URL . 'assets/prasat-ui.js', array(), $v, true );
		wp_enqueue_script( 'featherweight-admin', FEATHERWEIGHT_URL . 'assets/admin.js', array( 'featherweight-ui' ), $v, true );
		$st = self::state();
		wp_localize_script(
			'featherweight-admin',
			'Featherweight',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( Featherweight::NONCE ),
				'total' => $st['all'],
				'i18n'  => array(
					'running'      => __( 'Optimizing… keep this page open', 'featherweight' ),
					'videoRunning' => __( 'Converting a video in the background…', 'featherweight' ),
					'paused'       => __( 'Paused', 'featherweight' ),
					'done'         => __( 'All done', 'featherweight' ),
					'allDone'      => __( 'Everything is optimized', 'featherweight' ),
					'doneLead'     => __( 'New uploads are converted automatically. Use Re-optimize all after changing the quality settings.', 'featherweight' ),
					'savedTotal'   => __( 'All media optimized. Saved %s.', 'featherweight' ),
					'lost'         => __( 'Lost connection to the server. Press Optimize everything to resume.', 'featherweight' ),
					'error'        => __( 'Error:', 'featherweight' ),
					'image'        => __( 'Image', 'featherweight' ),
					'video'        => __( 'Video', 'featherweight' ),
					'optimized'    => __( '%s%% of media optimized', 'featherweight' ),
					'confirmReset' => __( 'Re-optimize every image and video with the current settings? Originals are kept.', 'featherweight' ),
					'leave'        => __( 'Optimization is still running.', 'featherweight' ),
					'expired'      => __( 'Your session has expired. Reload the page and press Optimize everything to continue.', 'featherweight' ),
					'statusOk'     => __( 'Done', 'featherweight' ),
					'statusRun'    => __( 'Working', 'featherweight' ),
					'statusWarn'   => __( 'Problem', 'featherweight' ),
				),
			)
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'featherweight' ) );
		}
		$st = self::state();
		$o  = wp_parse_args( (array) get_option( Featherweight::OPT, array() ), Featherweight::defaults() );
		include FEATHERWEIGHT_DIR . 'views/page.php';
	}

	/** A setting row with a switch (Prasat kit). */
	public static function switch_row( $key, $title, $desc, $on ) {
		printf(
			'<div class="pd-row"><div class="pd-row__text"><label class="pd-row__title" for="fw-%1$s">%2$s</label><p class="pd-row__desc" id="fw-%1$s-h">%3$s</p></div><div class="pd-row__control"><span class="pd-switch"><input type="checkbox" role="switch" id="fw-%1$s" name="%4$s[%1$s]" value="1" aria-describedby="fw-%1$s-h"%5$s><span></span></span></div></div>',
			esc_attr( $key ),
			esc_html( $title ),
			esc_html( $desc ),
			esc_attr( Featherweight::OPT ),
			checked( (bool) $on, true, false )
		);
	}

	/**
	 * Findings for the Prasat Digital one-click site check (Prasat_Hub::run_check).
	 */
	public static function health() {
		$st   = self::state();
		$page = admin_url( 'admin.php?page=featherweight' );
		$out  = array();
		$out[] = array( 'id' => 'webp_engine', 'status' => $st['img'] ? 'pass' : 'warn', 'severity' => 'medium', 'title' => __( 'The server can make WebP images', 'featherweight' ), 'detail' => $st['img'] ? '' : __( 'PHP has no WebP support (GD or Imagick). Ask your host to enable it.', 'featherweight' ) );
		if ( $st['img'] ) {
			$out[] = array( 'id' => 'images_optimized', 'status' => $st['img_left'] ? 'warn' : 'pass', 'severity' => 'low', 'title' => __( 'Images are optimized', 'featherweight' ), 'detail' => $st['img_left'] ? sprintf( __( '%d images are still in their original, heavier format.', 'featherweight' ), $st['img_left'] ) : sprintf( __( 'All %d images have a lighter WebP copy.', 'featherweight' ), $st['img_tot'] ), 'fix_url' => $page, 'fix_label' => __( 'Optimize', 'featherweight' ) );
		}
		if ( $st['vid_tot'] && ! $st['ff'] ) {
			$out[] = array( 'id' => 'video_engine', 'status' => 'info', 'severity' => 'low', 'title' => __( 'Videos stay in their original format', 'featherweight' ), 'detail' => sprintf( __( 'Your server has no ffmpeg, so %d videos can\'t be converted to WebM. Ask your host if you want lighter videos.', 'featherweight' ), $st['vid_tot'] ) );
		}
		return $out;
	}
}
