<?php
/**
 * Featherweight engine: converts images to WebP and videos to WebM, serves them on the front
 * end, and never deletes originals. (Formerly "Ahnakot Media Optimizer" 2.0.0.)
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

final class Featherweight {

	const OPT        = 'featherweight_settings';
	const META_IMG   = '_featherweight_webp_done';
	const META_VID   = '_featherweight_webm_done';
	const NONCE      = 'featherweight_bulk';
	const IMG_BATCH  = 5;

	const IMG_EXT = 'jpe?g|jpe|png|gif|bmp|tiff?|heic|heif';
	const VID_EXT = 'mp4|m4v|mov|avi|mkv|wmv|mpe?g|3gp|ogv|flv';

	public static function image_mimes() {
		return array( 'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/tiff', 'image/heic', 'image/heif' );
	}

	public static function video_mimes() {
		return array( 'video/mp4', 'video/x-m4v', 'video/quicktime', 'video/x-msvideo', 'video/avi', 'video/x-matroska', 'video/x-ms-wmv', 'video/mpeg', 'video/3gpp', 'video/ogg', 'video/x-flv' );
	}

	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_upload' ), 20, 2 );
		add_action( 'delete_attachment', array( __CLASS__, 'on_delete' ) );
		if ( is_admin() ) {
			Featherweight_Admin::init();
		}
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_featherweight_batch', array( __CLASS__, 'ajax_batch' ) );
		add_action( 'wp_ajax_featherweight_reset', array( __CLASS__, 'ajax_reset' ) );
		add_filter( 'mime_types', array( __CLASS__, 'mimes' ) );

		if ( ! is_admin() && self::opt( 'serve' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 1 );
		}
	}

	public static function mimes( $m ) {
		$m['webp'] = 'image/webp';
		$m['webm'] = 'video/webm';
		return $m;
	}

	/* ================= settings ================= */

	public static function defaults() {
		return array(
			'quality'     => 80,
			'video_crf'   => 33,
			'serve'       => 1,
			'on_upload'   => 1,
			'skip_bigger' => 1,
			'ffmpeg'      => '',
		);
	}

	public static function opt( $key ) {
		$o = wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
		return $o[ $key ];
	}

	public static function register_settings() {
		register_setting( 'featherweight', self::OPT, array(
			'sanitize_callback' => function ( $in ) {
				return array(
					'quality'     => max( 1, min( 100, (int) ( $in['quality'] ?? 80 ) ) ),
					'video_crf'   => max( 15, min( 50, (int) ( $in['video_crf'] ?? 33 ) ) ),
					'serve'       => empty( $in['serve'] ) ? 0 : 1,
					'on_upload'   => empty( $in['on_upload'] ) ? 0 : 1,
					'skip_bigger' => empty( $in['skip_bigger'] ) ? 0 : 1,
					'ffmpeg'      => preg_replace( '#[^A-Za-z0-9_./-]#', '', (string) ( $in['ffmpeg'] ?? '' ) ),
				);
			},
		) );
	}

	/* ================= engines ================= */

	public static function image_engine() {
		if ( class_exists( 'Imagick' ) && Imagick::queryFormats( 'WEBP' ) ) {
			return 'imagick';
		}
		if ( function_exists( 'imagewebp' ) ) {
			return 'gd';
		}
		return false;
	}

	private static function can_exec() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		return ! in_array( 'exec', $disabled, true );
	}

	/** Path to a working ffmpeg binary, or false. */
	public static function ffmpeg() {
		static $found = null;
		if ( null !== $found ) {
			return $found;
		}
		$found = false;
		if ( ! self::can_exec() ) {
			return $found;
		}
		$candidates = array_filter( array( self::opt( 'ffmpeg' ), 'ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/bin/ffmpeg' ) );
		foreach ( $candidates as $bin ) {
			$out = array();
			$rc  = 1;
			@exec( escapeshellcmd( $bin ) . ' -hide_banner -encoders 2>/dev/null', $out, $rc );
			if ( 0 === $rc && preg_grep( '/libvpx-vp9|libvpx/', $out ) ) {
				$found = $bin;
				break;
			}
		}
		return $found;
	}

	/* ================= images ================= */

	public static function webp_path( $file ) {
		return $file . '.webp'; // photo.jpg -> photo.jpg.webp
	}

	private static function is_stale( $dest, $src ) {
		if ( ! is_file( $dest ) ) {
			return true;
		}
		$force_after = (int) get_option( 'featherweight_force', 0 );
		return filemtime( $dest ) < filemtime( $src ) || filemtime( $dest ) <= $force_after;
	}

	/** Convert one image. Returns bytes saved (int), 0 if skipped, false on error. */
	public static function convert_image( $file ) {
		if ( ! is_file( $file ) || ! preg_match( '/\.(' . self::IMG_EXT . ')$/i', $file ) ) {
			return 0;
		}
		$dest = self::webp_path( $file );
		if ( ! self::is_stale( $dest, $file ) ) {
			return 0;
		}
		$q      = (int) self::opt( 'quality' );
		$engine = self::image_engine();
		$ok     = false;

		try {
			if ( 'imagick' === $engine ) {
				$im = new Imagick( $file );
				if ( $im->getNumberImages() > 1 ) { // animated GIF stays animated
					$im = $im->coalesceImages();
					foreach ( $im as $frame ) {
						$frame->setImageFormat( 'webp' );
						$frame->setImageCompressionQuality( $q );
					}
					$im->setOption( 'webp:method', '4' );
					$ok = $im->writeImages( $dest, true );
				} else {
					$im->autoOrient();
					$im->setImageFormat( 'webp' );
					$im->setImageCompressionQuality( $q );
					$im->setOption( 'webp:method', '6' );
					$im->stripImage();
					$ok = $im->writeImage( $dest );
				}
				$im->clear();
			} elseif ( 'gd' === $engine ) {
				$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
				$img = false;
				if ( in_array( $ext, array( 'jpg', 'jpeg', 'jpe' ), true ) ) {
					$img = @imagecreatefromjpeg( $file );
				} elseif ( 'png' === $ext ) {
					$img = @imagecreatefrompng( $file );
				} elseif ( 'gif' === $ext && ! self::gif_is_animated( $file ) ) { // GD can't keep animation
					$img = @imagecreatefromgif( $file );
				} elseif ( 'bmp' === $ext && function_exists( 'imagecreatefrombmp' ) ) {
					$img = @imagecreatefrombmp( $file );
				}
				if ( ! $img ) {
					return 0; // format not supported by GD: skip, not an error
				}
				imagepalettetotruecolor( $img );
				imagealphablending( $img, false );
				imagesavealpha( $img, true );
				$ok = imagewebp( $img, $dest, $q );
				imagedestroy( $img );
			}
		} catch ( Exception $e ) {
			$ok = false;
		}

		if ( ! $ok || ! is_file( $dest ) ) {
			@unlink( $dest );
			return false;
		}
		return self::keep_if_smaller( $file, $dest );
	}

	private static function gif_is_animated( $file ) {
		$c = @file_get_contents( $file, false, null, 0, 1024 * 1024 );
		return $c && preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $c ) > 1;
	}

	private static function keep_if_smaller( $src, $dest ) {
		clearstatcache();
		$orig = filesize( $src );
		$new  = filesize( $dest );
		if ( self::opt( 'skip_bigger' ) && $new >= $orig ) {
			@unlink( $dest );
			return 0;
		}
		return $orig - $new;
	}

	/** All files (original + every generated size) of an attachment. */
	public static function attachment_files( $id, $meta = null ) {
		$file = get_attached_file( $id );
		if ( ! $file ) {
			return array();
		}
		$meta  = $meta ?: wp_get_attachment_metadata( $id );
		$dir   = trailingslashit( dirname( $file ) );
		$files = array( $file );
		if ( ! empty( $meta['original_image'] ) ) {
			$files[] = $dir . $meta['original_image'];
		}
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $s ) {
				if ( ! empty( $s['file'] ) ) {
					$files[] = $dir . $s['file'];
				}
			}
		}
		return array_unique( $files );
	}

	public static function convert_image_attachment( $id, $meta = null ) {
		$saved = 0;
		$err   = 0;
		foreach ( self::attachment_files( $id, $meta ) as $f ) {
			$r = self::convert_image( $f );
			if ( false === $r ) {
				$err++;
			} else {
				$saved += $r;
			}
		}
		update_post_meta( $id, self::META_IMG, $err ? 'error' : 1 );
		return array( 'saved' => $saved, 'errors' => $err );
	}

	/* ================= videos ================= */

	public static function webm_path( $file ) {
		return $file . '.webm'; // clip.mp4 -> clip.mp4.webm
	}

	private static function job_files( $file ) {
		$base = self::webm_path( $file );
		return array( 'part' => $base . '.part.webm', 'status' => $base . '.status', 'log' => $base . '.log' );
	}

	/** Start ffmpeg in the background for one video attachment. */
	public static function start_video( $id ) {
		$file = get_attached_file( $id );
		$bin  = self::ffmpeg();
		if ( ! $file || ! is_file( $file ) || ! $bin ) {
			update_post_meta( $id, self::META_VID, 'error' );
			return false;
		}
		$dest = self::webm_path( $file );
		if ( ! self::is_stale( $dest, $file ) ) {
			update_post_meta( $id, self::META_VID, 1 );
			return true;
		}
		$j = self::job_files( $file );
		@unlink( $j['status'] );
		@unlink( $j['part'] );

		$crf = (int) self::opt( 'video_crf' );
		$ff  = escapeshellcmd( $bin ) . ' -nostdin -y -i ' . escapeshellarg( $file )
			. ' -map 0:v:0 -map 0:a:0? -c:v libvpx-vp9 -b:v 0 -crf ' . $crf
			. ' -deadline good -cpu-used 4 -row-mt 1 -pix_fmt yuv420p'
			. ' -c:a libopus -b:a 96k -f webm ' . escapeshellarg( $j['part'] );
		$script = '( ' . $ff . ' && mv ' . escapeshellarg( $j['part'] ) . ' ' . escapeshellarg( $dest )
			. ' ; echo $? > ' . escapeshellarg( $j['status'] ) . ' )';
		exec( 'nohup sh -c ' . escapeshellarg( $script ) . ' > ' . escapeshellarg( $j['log'] ) . ' 2>&1 &' );

		update_post_meta( $id, self::META_VID, 'running' );
		update_post_meta( $id, '_featherweight_webm_started', time() );
		return true;
	}

	/** Check a running video job. Returns 'running', 'done' or 'error'. */
	public static function check_video( $id ) {
		$file = get_attached_file( $id );
		$j    = self::job_files( $file );
		if ( ! is_file( $j['status'] ) ) {
			$started = (int) get_post_meta( $id, '_featherweight_webm_started', true );
			if ( $started && time() - $started > 3 * HOUR_IN_SECONDS ) { // give up on hung jobs
				update_post_meta( $id, self::META_VID, 'error' );
				return 'error';
			}
			return 'running';
		}
		$rc = (int) trim( (string) file_get_contents( $j['status'] ) );
		@unlink( $j['status'] );
		@unlink( $j['part'] );
		$dest = self::webm_path( $file );
		if ( 0 !== $rc || ! is_file( $dest ) ) {
			@unlink( $dest );
			update_post_meta( $id, self::META_VID, 'error' );
			return 'error';
		}
		@unlink( $j['log'] );
		$saved = self::keep_if_smaller( $file, $dest );
		update_post_meta( $id, self::META_VID, 1 );
		update_post_meta( $id, '_featherweight_webm_saved', (int) $saved );
		return 'done';
	}

	/* ================= hooks ================= */

	public static function on_upload( $meta, $id ) {
		if ( ! self::opt( 'on_upload' ) ) {
			return $meta;
		}
		$mime = get_post_mime_type( $id );
		if ( in_array( $mime, self::image_mimes(), true ) && self::image_engine() ) {
			self::convert_image_attachment( $id, $meta );
		} elseif ( in_array( $mime, self::video_mimes(), true ) && self::ffmpeg() ) {
			self::start_video( $id ); // runs in the background
		}
		return $meta;
	}

	public static function on_delete( $id ) {
		foreach ( self::attachment_files( $id ) as $f ) {
			foreach ( array( self::webp_path( $f ), self::webm_path( $f ) ) as $out ) {
				if ( is_file( $out ) ) {
					@unlink( $out );
				}
			}
		}
	}

	/* ================= one-click bulk (AJAX) ================= */

	private static function query( $mimes, $meta_key, $compare, $value = null, $limit = 1, $count = false ) {
		$mq = array( 'key' => $meta_key, 'compare' => $compare );
		if ( null !== $value ) {
			$mq['value'] = $value;
		}
		return new WP_Query( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => $mimes,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => ! $count,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array( $mq ),
		) );
	}

	public static function counts() {
		return array(
			'images'        => (int) self::query( self::image_mimes(), self::META_IMG, 'NOT EXISTS', null, 1, true )->found_posts,
			'videos'        => (int) self::query( self::video_mimes(), self::META_VID, 'NOT EXISTS', null, 1, true )->found_posts,
			'videosRunning' => (int) self::query( self::video_mimes(), self::META_VID, '=', 'running', 1, true )->found_posts,
		);
	}

	public static function ajax_batch() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		@set_time_limit( 120 );
		$log   = array();
		$saved = 0;

		// 1) images, a few per request
		if ( self::image_engine() ) {
			foreach ( self::query( self::image_mimes(), self::META_IMG, 'NOT EXISTS', null, self::IMG_BATCH )->posts as $id ) {
				$r      = self::convert_image_attachment( $id );
				$saved += $r['saved'];
				$log[]  = array( 't' => $r['errors'] ? 'warn' : 'ok', 'k' => 'image', 'm' => get_the_title( $id ), 'id' => $id );
			}
		}

		// 2) videos, one ffmpeg job at a time in the background
		if ( self::ffmpeg() ) {
			$running = self::query( self::video_mimes(), self::META_VID, '=', 'running', 1 )->posts;
			if ( $running ) {
				$id    = $running[0];
				$state = self::check_video( $id );
				if ( 'done' === $state ) {
					$s      = (int) get_post_meta( $id, '_featherweight_webm_saved', true );
					$saved += $s;
					$log[]  = array( 't' => 'ok', 'k' => 'video', 'm' => get_the_title( $id ) . ' → WebM', 'id' => $id );
				} elseif ( 'error' === $state ) {
					$log[] = array( 't' => 'warn', 'k' => 'video', 'm' => get_the_title( $id ) . ' — conversion failed', 'id' => $id );
				}
			} else {
				$next = self::query( self::video_mimes(), self::META_VID, 'NOT EXISTS', null, 1 )->posts;
				if ( $next ) {
					self::start_video( $next[0] );
					$log[] = array( 't' => 'run', 'k' => 'video', 'm' => get_the_title( $next[0] ) . ' — converting in background', 'id' => $next[0] );
				}
			}
		}

		$c = self::counts();
		if ( ! self::image_engine() ) {
			$c['images'] = 0;
		}
		if ( ! self::ffmpeg() ) {
			$c['videos'] = 0;
			$c['videosRunning'] = 0;
		}
		wp_send_json_success( array( 'saved' => $saved, 'log' => $log ) + $c );
	}

	public static function ajax_reset() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		delete_post_meta_by_key( self::META_IMG );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> 'running'", self::META_VID ) );
		update_option( 'featherweight_force', time(), false ); // regenerate files older than now
		wp_send_json_success();
	}

	/* ================= front-end serving ================= */

	public static function start_buffer() {
		if ( is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'rewrite_html' ) );
	}

	private static function url_to_path( $url ) {
		$up      = wp_get_upload_dir();
		$baseurl = preg_replace( '#^https?:#', '', $up['baseurl'] );
		$u       = preg_replace( '#^https?:#', '', $url );
		if ( 0 !== strpos( $u, $baseurl ) ) {
			return false;
		}
		return $up['basedir'] . rawurldecode( substr( $u, strlen( $baseurl ) ) );
	}

	public static function rewrite_html( $html ) {
		if ( stripos( $html, '<html' ) === false ) {
			return $html;
		}
		$up      = wp_get_upload_dir();
		$baseurl = preg_quote( preg_replace( '#^https?:#', '', $up['baseurl'] ), '#' );
		$cache   = array();

		// Videos: offer WebM first, keep the original as fallback (Safari/old browsers).
		$html = preg_replace_callback(
			'#<video\b([^>]*?)\ssrc=(["\'])((?:https?:)?' . $baseurl . '/[^"\']+?\.(?:' . self::VID_EXT . '))\2([^>]*)>#i',
			function ( $m ) {
				$path = self::url_to_path( $m[3] );
				if ( ! $path || ! is_file( self::webm_path( $path ) ) ) {
					return $m[0];
				}
				return '<video' . $m[1] . $m[4] . '><source src="' . esc_url( $m[3] . '.webm' ) . '" type="video/webm"><source src="' . esc_url( $m[3] ) . '">';
			},
			$html
		);
		$html = preg_replace_callback(
			'#<source\b[^>]*\ssrc=(["\'])((?:https?:)?' . $baseurl . '/[^"\']+?\.(?:' . self::VID_EXT . '))\1[^>]*>#i',
			function ( $m ) {
				$path = self::url_to_path( $m[2] );
				if ( ! $path || ! is_file( self::webm_path( $path ) ) ) {
					return $m[0];
				}
				return '<source src="' . esc_url( $m[2] . '.webm' ) . '" type="video/webm">' . $m[0];
			},
			$html
		);

		// Images: swap every uploads image URL that has a WebP twin (img, srcset, CSS backgrounds, data attributes).
		$pattern = '#(?:https?:)?' . $baseurl . '/[^"\'\s,)?]+?\.(?:' . self::IMG_EXT . ')(?=[\s"\'),?&]|&quot;)#i';
		return preg_replace_callback( $pattern, function ( $m ) use ( &$cache ) {
			$url = $m[0];
			if ( ! isset( $cache[ $url ] ) ) {
				$path          = self::url_to_path( $url );
				$cache[ $url ] = ( $path && is_file( self::webp_path( $path ) ) ) ? $url . '.webp' : $url;
			}
			return $cache[ $url ];
		}, $html );
	}

	/* ================= admin page ================= */

	/* ================= migration from Ahnakot Media Optimizer ================= */

	const OLD_KEYS = array(
		'_ahnakot_webp_done'    => '_featherweight_webp_done',
		'_ahnakot_webm_done'    => '_featherweight_webm_done',
		'_ahnakot_webm_started' => '_featherweight_webm_started',
		'_ahnakot_webm_saved'   => '_featherweight_webm_saved',
	);

	/**
	 * Carry over settings and "already converted" markers from Ahnakot Media Optimizer, so a site
	 * that used it doesn't convert everything again. The WebP/WebM files are named the same way
	 * (photo.jpg.webp), so they are reused as they are. Runs once.
	 */
	public static function migrate() {
		global $wpdb;
		if ( get_option( 'featherweight_migrated' ) ) {
			return;
		}
		$old = get_option( 'ahnakot_webp_settings' );
		if ( is_array( $old ) && false === get_option( self::OPT ) ) {
			add_option( self::OPT, $old );
		}
		$force = get_option( 'ahnakot_webp_force' );
		if ( $force && ! get_option( 'featherweight_force' ) ) {
			update_option( 'featherweight_force', (int) $force, false );
		}
		foreach ( self::OLD_KEYS as $from => $to ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => $to ), array( 'meta_key' => $from ) ); // phpcs:ignore -- one-off rename.
		}
		wp_cache_flush();
		update_option( 'featherweight_migrated', time(), false );
	}

	public static function activate() {
		// Same plugin under its new name: running both would convert and rewrite pages twice.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
			if ( 'ahnakot-webp.php' === basename( $p ) ) {
				deactivate_plugins( $p, true );
			}
		}
		self::migrate();
	}
}
