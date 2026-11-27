<?php
/**
 * Image alt text: find images without it, suggest it from the file name (free), let Smart AI
 * write it from the picture itself (Prasat Premium), fill it into pages, and undo bulk changes.
 *
 * Alt text is stored where WordPress keeps it (_wp_attachment_image_alt). Featherweight also
 * remembers who wrote it (_featherweight_alt_source: ai, file or manual) so bulk changes can be
 * undone without touching text a person typed.
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

class Featherweight_Alt {

	const ALT     = '_wp_attachment_image_alt';
	const SOURCE  = '_featherweight_alt_source';
	const FAILED  = '_featherweight_alt_failed';
	const OPT     = 'featherweight_alt_settings';
	const NONCE   = 'featherweight_alt';
	const CRON    = 'featherweight_alt_ai';
	const BATCH   = 'featherweight_alt_batch_';
	const MAX_LEN = 250;

	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'on_add' ) );
		add_action( self::CRON, array( __CLASS__, 'cron_ai' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		foreach ( array( 'list', 'next', 'save', 'ai', 'files', 'undo' ) as $a ) {
			add_action( 'wp_ajax_featherweight_alt_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
		if ( self::opt( 'fill' ) ) {
			add_filter( 'wp_content_img_tag', array( __CLASS__, 'fill_img_tag' ), 10, 3 );
		}
	}

	/* ================= settings ================= */

	public static function defaults() {
		return array(
			'fill'      => 1, // Put the Media Library alt text into pages that left it out.
			'on_upload' => 1, // Suggest from a meaningful file name when an image is uploaded.
			'ai_upload' => 1, // Premium: let Smart AI write it for each new upload.
		);
	}

	public static function opt( $key ) {
		$o = wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
		return $o[ $key ];
	}

	public static function register_settings() {
		register_setting(
			'featherweight_alt',
			self::OPT,
			array(
				'sanitize_callback' => function ( $in ) {
					$out = array();
					foreach ( array_keys( self::defaults() ) as $k ) {
						$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
					}
					return $out;
				},
			)
		);
	}

	public static function premium() {
		return class_exists( 'Prasat_Hub' ) && method_exists( 'Prasat_Hub', 'has_premium' ) && Prasat_Hub::has_premium();
	}

	/* ================= finding images ================= */

	private static function query_args( array $extra = array() ) {
		return array_merge(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => false,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => self::ALT, 'compare' => 'NOT EXISTS' ),
					array( 'key' => self::ALT, 'value' => '', 'compare' => '=' ),
				),
			),
			$extra
		);
	}

	/** Images (in the Media Library) and how many have no alt text. */
	public static function counts() {
		$all  = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'fields' => 'ids', 'posts_per_page' => 1 ) );
		$miss = new WP_Query( self::query_args( array( 'posts_per_page' => 1 ) ) );
		return array(
			'total'   => (int) $all->found_posts,
			'missing' => (int) $miss->found_posts,
		);
	}

	/** Ids of images without alt text, newest first. */
	public static function missing_ids( $limit = 20, $offset = 0, $skip_failed = false ) {
		$extra = array( 'posts_per_page' => $limit, 'offset' => $offset );
		if ( $skip_failed ) {
			// Leave out images Smart AI could not describe in the last day, so a bulk run moves on.
			$extra['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				self::query_args()['meta_query'],
				array(
					'relation' => 'OR',
					array( 'key' => self::FAILED, 'compare' => 'NOT EXISTS' ),
					array( 'key' => self::FAILED, 'value' => time() - DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ),
				),
			);
		}
		return array_map( 'intval', ( new WP_Query( self::query_args( $extra ) ) )->posts );
	}

	/* ================= suggestions from names (free) ================= */

	/** Turns "koh-kong_smart-city-1024x768.jpg" into "Koh kong smart city". */
	public static function clean_name( $s ) {
		$s = rawurldecode( wp_strip_all_tags( (string) $s ) );
		$s = preg_replace( '/\.(jpe?g|png|gif|webp|avif|bmp|tiff?|heic|heif|svg)$/i', '', $s );
		$s = preg_replace( '/(-scaled|-rotated|-e\d{10,}|-\d+x\d+|-\d{1,2})+$/i', '', $s );
		$s = preg_replace( '/[\s._\-+]+/u', ' ', $s );
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		if ( '' === $s ) {
			return '';
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 ) : ucfirst( $s );
	}

	/** False for camera and app names like "IMG 2041", "image 2025 04 05 11 42 19" or "Screenshot … at 10.11". */
	public static function is_meaningful( $s ) {
		$letters = preg_match_all( '/\p{L}/u', $s );
		$digits  = preg_match_all( '/\d/', $s );
		if ( $letters < 3 || $digits > $letters ) {
			return false;
		}
		// What is left once camera/app words, times and numbers are taken out must still say something.
		$generic = 'img|image|images|dsc|dscn|dscf|pxl|photo|photos|pic|picture|screenshot|screen|shot|capture|scan|scanned|untitled|whatsapp|telegram|signal|output|file|download|copy|of|new|edited|edit|final|version|v|at|am|pm|jpeg|jpg|png';
		$rest    = preg_replace( '/\b(' . $generic . ')\b|\d+/iu', ' ', $s );
		return preg_match_all( '/\p{L}/u', $rest ) >= 3;
	}

	/** A suggestion from the image title or file name, or '' when neither says anything. */
	public static function suggest( $id ) {
		$file = get_attached_file( $id );
		foreach ( array( get_post_field( 'post_title', $id ), $file ? wp_basename( $file ) : '' ) as $raw ) {
			$s = self::clean_name( $raw );
			if ( $s && self::is_meaningful( $s ) ) {
				return mb_substr( $s, 0, self::MAX_LEN );
			}
		}
		return '';
	}

	/* ================= saving ================= */

	public static function get_alt( $id ) {
		return (string) get_post_meta( $id, self::ALT, true );
	}

	public static function set_alt( $id, $alt, $source ) {
		$alt = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $alt ) ) );
		$alt = mb_substr( $alt, 0, self::MAX_LEN );
		update_post_meta( $id, self::ALT, wp_slash( $alt ) );
		if ( '' === $alt ) {
			delete_post_meta( $id, self::SOURCE );
		} else {
			update_post_meta( $id, self::SOURCE, $source );
		}
		delete_post_meta( $id, self::FAILED );
		return $alt;
	}

	/** Remembers the images a bulk run changed, so it can be undone. */
	private static function remember( array $ids ) {
		$token = strtolower( wp_generate_password( 12, false ) );
		update_option( self::BATCH . $token, array_map( 'intval', $ids ), false );
		return $token;
	}

	/** Clears the alt text a bulk run wrote, unless someone has edited it since. */
	public static function undo_batch( $token ) {
		$token = sanitize_key( (string) $token );
		$ids   = (array) get_option( self::BATCH . $token, array() );
		if ( ! $ids ) {
			return new WP_Error( 'featherweight_undo_gone', __( 'The list of changed images is no longer available.', 'featherweight' ) );
		}
		foreach ( $ids as $id ) {
			if ( in_array( get_post_meta( $id, self::SOURCE, true ), array( 'ai', 'file' ), true ) ) {
				self::set_alt( (int) $id, '', '' );
			}
		}
		delete_option( self::BATCH . $token );
		return true;
	}

	/* ================= Smart AI (Premium) ================= */

	/** A small copy of the image for the AI: at most 768 px, as JPEG (PNG/GIF keep transparency). */
	private static function image_payload( $id ) {
		$file = get_attached_file( $id );
		$mime = (string) get_post_mime_type( $id );
		if ( ! $file || ! is_readable( $file ) || 'image/svg+xml' === $mime ) {
			return new WP_Error( 'unsupported', __( 'This image type cannot be read.', 'featherweight' ) );
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return new WP_Error( 'unsupported', __( 'The server cannot open this image.', 'featherweight' ) );
		}
		$size = $editor->get_size();
		if ( $size['width'] > 768 || $size['height'] > 768 ) {
			$editor->resize( 768, 768, false );
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // Not loaded for background (cron) jobs.
		}
		$out_mime = in_array( $mime, array( 'image/png', 'image/gif' ), true ) ? 'image/png' : 'image/jpeg';
		$editor->set_quality( 72 );
		$tmp   = wp_tempnam( 'fw-alt' );
		$saved = $editor->save( $tmp, $out_mime );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return new WP_Error( 'unsupported', __( 'The server cannot open this image.', 'featherweight' ) );
		}
		$data = base64_encode( (string) file_get_contents( $saved['path'] ) ); // phpcs:ignore
		@unlink( $saved['path'] ); // phpcs:ignore
		if ( $saved['path'] !== $tmp ) {
			@unlink( $tmp ); // phpcs:ignore
		}
		return array( 'image' => $data, 'media_type' => $out_mime );
	}

	/** Asks Smart AI to look at the image and write its alt text. Saves nothing. */
	public static function ai_alt( $id ) {
		if ( ! self::premium() ) {
			return new WP_Error( 'no_key', __( 'Writing alt text with AI is part of Prasat Premium.', 'featherweight' ) );
		}
		$img = self::image_payload( $id );
		if ( is_wp_error( $img ) ) {
			update_post_meta( $id, self::FAILED, time() );
			return $img;
		}
		$parent = (int) wp_get_post_parent_id( $id );
		$file   = get_attached_file( $id );
		$res    = Prasat_Hub::ai_request(
			'/v1/alt',
			array_merge(
				$img,
				array(
					'filename' => $file ? wp_basename( $file ) : '',
					'title'    => get_post_field( 'post_title', $id ),
					'context'  => $parent ? wp_strip_all_tags( get_the_title( $parent ) ) : '',
					'locale'   => get_locale(),
				)
			),
			60
		);
		if ( is_wp_error( $res ) ) {
			if ( ! in_array( $res->get_error_code(), array( 'busy', 'unreachable', 'no_key', 'invalid_premium_key', 'relay_not_configured', 'relay_key_invalid' ), true ) ) {
				update_post_meta( $id, self::FAILED, time() ); // Something about this image; don't retry it at once.
			}
			return $res;
		}
		return isset( $res['alt'] ) ? trim( wp_strip_all_tags( (string) $res['alt'] ) ) : '';
	}

	/* ================= uploads ================= */

	public static function on_add( $id ) {
		if ( ! wp_attachment_is_image( $id ) || '' !== self::get_alt( $id ) ) {
			return;
		}
		if ( self::opt( 'on_upload' ) ) {
			$s = self::suggest( $id );
			if ( $s ) {
				self::set_alt( $id, $s, 'file' );
			}
		}
		if ( self::opt( 'ai_upload' ) && self::premium() ) {
			// In the background, so uploading stays fast.
			wp_schedule_single_event( time() + 5, self::CRON, array( (int) $id ) );
		}
	}

	public static function cron_ai( $id ) {
		$id = (int) $id;
		if ( ! wp_attachment_is_image( $id ) || ( '' !== self::get_alt( $id ) && 'file' !== get_post_meta( $id, self::SOURCE, true ) ) ) {
			return; // Someone wrote it already.
		}
		$alt = self::ai_alt( $id );
		if ( ! is_wp_error( $alt ) && '' !== $alt ) {
			self::set_alt( $id, $alt, 'ai' );
		}
	}

	/* ================= pages ================= */

	/**
	 * Images in post content that have no alt text get the one from the Media Library
	 * (pages often keep alt="" from before the image was described).
	 */
	public static function fill_img_tag( $tag, $context, $id ) {
		if ( ! $id || preg_match( '/\salt\s*=\s*(["\'])\s*[^"\'\s][^"\']*\1/i', $tag ) ) {
			return $tag;
		}
		$alt = self::get_alt( $id );
		if ( '' === $alt ) {
			return $tag;
		}
		$attr = 'alt="' . esc_attr( $alt ) . '"';
		if ( preg_match( '/\salt\s*=\s*(["\'])\s*\1/i', $tag ) ) {
			return preg_replace( '/\salt\s*=\s*(["\'])\s*\1/i', ' ' . $attr, $tag, 1 );
		}
		return preg_replace( '/^<img\b/i', '<img ' . $attr, $tag, 1 );
	}

	/* ================= Smart AI Optimize (Prasat hub) ================= */

	/** For Prasat_Hub: the one-click fix for the image_alt check. */
	public static function fixes() {
		return array(
			'image_alt' => array(
				'apply' => array( __CLASS__, 'autofix' ),
				'undo'  => array( __CLASS__, 'undo_batch' ),
			),
		);
	}

	/**
	 * Premium: Smart AI writes alt text for as many images as fit in about 25 seconds.
	 * Without Premium: fills the ones whose file names say something.
	 */
	public static function autofix() {
		$end   = time() + 25;
		$done  = array();
		$ai    = self::premium();
		$stop  = '';
		while ( time() < $end && ! $stop ) {
			$ids = $ai ? self::missing_ids( 5, 0, true ) : self::missing_ids( 2000 );
			if ( ! $ids ) {
				break;
			}
			$progress = false;
			foreach ( $ids as $id ) {
				if ( time() >= $end ) {
					break;
				}
				if ( $ai ) {
					$alt = self::ai_alt( $id );
					if ( is_wp_error( $alt ) ) {
						if ( in_array( $alt->get_error_code(), array( 'busy', 'unreachable', 'no_key', 'invalid_premium_key', 'relay_not_configured', 'relay_key_invalid' ), true ) ) {
							$stop = $alt->get_error_message();
							break;
						}
						continue;
					}
				} else {
					$alt = self::suggest( $id );
				}
				if ( '' !== $alt ) {
					self::set_alt( $id, $alt, $ai ? 'ai' : 'file' );
					$done[]   = $id;
					$progress = true;
				} elseif ( $ai ) {
					update_post_meta( $id, self::FAILED, time() ); // Decorative or blank: let a person decide.
				}
			}
			if ( ! $ai || ! $progress ) {
				break; // File names: one pass is all there is.
			}
		}
		if ( ! $done ) {
			return $stop ? new WP_Error( 'featherweight_alt', $stop ) : array( 'skip' => true );
		}
		$left = self::counts()['missing'];
		$did  = $ai
			/* translators: %s: number of images */
			? sprintf( _n( 'Smart AI wrote alt text for %s image', 'Smart AI wrote alt text for %s images', count( $done ), 'featherweight' ), number_format_i18n( count( $done ) ) )
			/* translators: %s: number of images */
			: sprintf( _n( 'Added alt text from the file name to %s image', 'Added alt text from the file names to %s images', count( $done ), 'featherweight' ), number_format_i18n( count( $done ) ) );
		if ( $left ) {
			/* translators: %s: number of images */
			$did .= sprintf( __( ' (%s still need it: click again, or open Featherweight › Alt text)', 'featherweight' ), number_format_i18n( $left ) );
		}
		return array( 'ok' => true, 'did' => $did, 'undo' => self::remember( $done ) );
	}

	/* ================= the Alt text screen (AJAX) ================= */

	private static function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'featherweight' ) ), 403 );
		}
	}

	private static function item( $id ) {
		$file   = get_attached_file( $id );
		$parent = (int) wp_get_post_parent_id( $id );
		return array(
			'id'      => $id,
			'thumb'   => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
			'file'    => $file ? wp_basename( $file ) : '',
			'alt'     => self::get_alt( $id ),
			'suggest' => self::suggest( $id ),
			'used_on' => $parent ? wp_strip_all_tags( get_the_title( $parent ) ) : '',
			'edit'    => (string) get_edit_post_link( $id, 'raw' ),
			'failed'  => (bool) get_post_meta( $id, self::FAILED, true ),
		);
	}

	public static function ajax_list() {
		self::guard();
		$page = max( 1, (int) ( $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ids  = self::missing_ids( 20, ( $page - 1 ) * 20 );
		wp_send_json_success( array( 'items' => array_map( array( __CLASS__, 'item' ), $ids ), 'counts' => self::counts(), 'page' => $page ) );
	}

	/** For "Write all with AI": the next image Smart AI hasn't failed on in the last day. */
	public static function ajax_next() {
		self::guard();
		$ids = self::missing_ids( 1, 0, true );
		wp_send_json_success( array( 'id' => $ids ? $ids[0] : 0, 'counts' => self::counts() ) );
	}

	public static function ajax_save() {
		self::guard();
		$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Image not found.', 'featherweight' ) ), 404 );
		}
		$alt = self::set_alt( $id, wp_unslash( $_POST['alt'] ?? '' ), 'manual' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		wp_send_json_success( array( 'alt' => $alt, 'counts' => self::counts() ) );
	}

	/** Premium: Smart AI writes the alt text for one image and saves it. */
	public static function ajax_ai() {
		self::guard();
		@set_time_limit( 90 ); // phpcs:ignore
		$id = (int) ( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Image not found.', 'featherweight' ) ), 404 );
		}
		$alt = self::ai_alt( $id );
		if ( is_wp_error( $alt ) ) {
			wp_send_json_error( array( 'code' => $alt->get_error_code(), 'message' => $alt->get_error_message() ) );
		}
		if ( '' === $alt ) {
			update_post_meta( $id, self::FAILED, time() );
			wp_send_json_error( array( 'code' => 'empty', 'message' => __( 'Smart AI thinks this image is decorative. Write the alt text yourself if it shows something.', 'featherweight' ) ) );
		}
		wp_send_json_success( array( 'alt' => self::set_alt( $id, $alt, 'ai' ), 'counts' => self::counts() ) );
	}

	/** Fills every image whose file name or title says something. Free. */
	public static function ajax_files() {
		self::guard();
		$done = array();
		foreach ( self::missing_ids( 2000 ) as $id ) {
			$s = self::suggest( $id );
			if ( $s ) {
				self::set_alt( $id, $s, 'file' );
				$done[] = $id;
			}
		}
		wp_send_json_success( array( 'filled' => count( $done ), 'undo' => $done ? self::remember( $done ) : '', 'counts' => self::counts() ) );
	}

	public static function ajax_undo() {
		self::guard();
		$r = self::undo_batch( sanitize_key( wp_unslash( $_POST['token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'counts' => self::counts() ) );
	}
}
