<?php
/**
 * Media → Featherweight.
 *
 * @var array $st State from Featherweight_Admin::state().
 * @var array $o  Settings.
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

$name = Featherweight::OPT;
?>
<div class="wrap pd-app featherweight">
	<?php include FEATHERWEIGHT_DIR . 'views/header.php'; ?>
	<hr class="wp-header-end">
	<?php settings_errors(); // "Settings saved." after the form below is submitted. ?>

	<section class="pd-card fw-hero" aria-labelledby="fw-hero-t">
		<div class="fw-ring" role="img" id="fw-ring" aria-label="<?php echo esc_attr( sprintf( __( '%s%% of media optimized', 'featherweight' ), $st['pct'] ) ); ?>">
			<svg viewBox="0 0 148 148" aria-hidden="true"><defs><linearGradient id="fw-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#818cf8"/><stop offset="1" stop-color="#2dd4bf"/></linearGradient></defs><circle class="bg" cx="74" cy="74" r="64"/><circle class="fg" id="fw-ring-fg" cx="74" cy="74" r="64" stroke-dasharray="402.1" stroke-dashoffset="<?php echo esc_attr( 402.1 * ( 1 - $st['pct'] / 100 ) ); ?>"/></svg>
			<div class="fw-ring__v"><strong id="fw-pct"><?php echo (int) $st['pct']; ?>%</strong><span><?php esc_html_e( 'optimized', 'featherweight' ); ?></span></div>
		</div>
		<div>
			<h2 id="fw-hero-t"><?php echo $st['todo'] ? esc_html__( 'Optimize your media library', 'featherweight' ) : esc_html__( 'Everything is optimized', 'featherweight' ); ?></h2>
			<p class="pd-lead" id="fw-hero-p">
				<?php
				echo $st['todo']
					? esc_html(
						sprintf(
							/* translators: 1: "3 images", 2: "1 video" */
							__( '%1$s and %2$s are waiting. Keep this page open while it runs — videos convert in the background and can take a few minutes each.', 'featherweight' ),
							sprintf( _n( '%d image', '%d images', $st['img_left'], 'featherweight' ), $st['img_left'] ),
							sprintf( _n( '%d video', '%d videos', $st['vid_left'], 'featherweight' ), $st['vid_left'] )
						)
					)
					: esc_html__( 'New uploads are converted automatically. Use Re-optimize all after changing the quality settings.', 'featherweight' );
				?>
			</p>
			<div class="pd-actions">
				<button type="button" class="button button-primary button-hero" id="fw-start" <?php echo $st['todo'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Optimize everything', 'featherweight' ); ?></button>
				<button type="button" class="button button-large" id="fw-reset"><?php esc_html_e( 'Re-optimize all', 'featherweight' ); ?></button>
				<span class="fw-status" id="fw-status" role="status" aria-live="polite"></span>
			</div>
		</div>
	</section>

	<div class="pd-grid pd-grid--4">
		<div class="pd-stat"><span class="pd-stat__num" id="fw-img"><?php echo (int) $st['img_left']; ?></span><span class="pd-stat__label"><?php esc_html_e( 'Images left', 'featherweight' ); ?></span><span class="pd-stat__sub"><?php echo esc_html( sprintf( __( 'of %d in the library', 'featherweight' ), $st['img_tot'] ) ); ?></span></div>
		<div class="pd-stat"><span class="pd-stat__num" id="fw-vid"><?php echo (int) $st['vid_left']; ?></span><span class="pd-stat__label"><?php esc_html_e( 'Videos left', 'featherweight' ); ?></span><span class="pd-stat__sub"><?php echo esc_html( sprintf( __( 'of %d in the library', 'featherweight' ), $st['vid_tot'] ) ); ?></span></div>
		<div class="pd-stat"><span class="pd-stat__num" id="fw-saved">0 KB</span><span class="pd-stat__label"><?php esc_html_e( 'Saved this run', 'featherweight' ); ?></span><span class="pd-stat__sub"><?php esc_html_e( 'Since this page was opened', 'featherweight' ); ?></span></div>
		<div class="pd-stat"><span class="pd-stat__label"><?php esc_html_e( 'Server engines', 'featherweight' ); ?></span>
			<span class="fw-engines">
				<span class="pd-badge pd-badge--<?php echo $st['img'] ? 'ok' : 'bad'; ?>"><?php echo $st['img'] ? '&#10003;' : '!'; ?> WebP · <?php echo $st['img'] ? esc_html( strtoupper( $st['img'] ) ) : esc_html__( 'missing', 'featherweight' ); ?></span>
				<span class="pd-badge pd-badge--<?php echo $st['ff'] ? 'ok' : 'warn'; ?>"><?php echo $st['ff'] ? '&#10003;' : '!'; ?> WebM · <?php echo $st['ff'] ? 'ffmpeg' : esc_html__( 'needs ffmpeg', 'featherweight' ); ?></span>
			</span>
		</div>
	</div>

	<div class="pd-grid pd-grid--wide">
		<section class="pd-card" aria-labelledby="fw-act-t">
			<h2 id="fw-act-t"><?php esc_html_e( 'Activity', 'featherweight' ); ?></h2>
			<ul class="fw-log" id="fw-log" aria-live="polite" aria-relevant="additions"><li class="pd-empty" id="fw-empty"><?php echo wp_kses( __( 'Nothing yet. Press <strong>Optimize everything</strong> to start.', 'featherweight' ), array( 'strong' => array() ) ); ?></li></ul>
		</section>

		<section class="pd-card" aria-labelledby="fw-set-t">
			<h2 id="fw-set-t"><?php esc_html_e( 'Settings', 'featherweight' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'featherweight' ); ?>
				<div class="fw-range">
					<label for="fw-q"><?php esc_html_e( 'Image quality', 'featherweight' ); ?> <output id="fw-q-o" for="fw-q"><?php echo (int) $o['quality']; ?></output></label>
					<input type="range" id="fw-q" min="40" max="100" name="<?php echo esc_attr( $name ); ?>[quality]" value="<?php echo (int) $o['quality']; ?>" aria-describedby="fw-q-h">
					<p class="description" id="fw-q-h"><?php esc_html_e( '80 keeps photos sharp at about a third of the size.', 'featherweight' ); ?></p>
				</div>
				<div class="fw-range">
					<label for="fw-c"><?php esc_html_e( 'Video compression (CRF)', 'featherweight' ); ?> <output id="fw-c-o" for="fw-c"><?php echo (int) $o['video_crf']; ?></output></label>
					<input type="range" id="fw-c" min="15" max="50" name="<?php echo esc_attr( $name ); ?>[video_crf]" value="<?php echo (int) $o['video_crf']; ?>" aria-describedby="fw-c-h">
					<p class="description" id="fw-c-h"><?php esc_html_e( 'Lower = better quality, bigger file. 33 is a good balance.', 'featherweight' ); ?></p>
				</div>
				<div class="pd-rows">
					<?php
					Featherweight_Admin::switch_row( 'on_upload', __( 'Convert on upload', 'featherweight' ), __( 'Optimize new images and videos automatically.', 'featherweight' ), $o['on_upload'] );
					Featherweight_Admin::switch_row( 'serve', __( 'Serve optimized files', 'featherweight' ), __( 'Show WebP / WebM to visitors. Videos keep the original as a fallback.', 'featherweight' ), $o['serve'] );
					Featherweight_Admin::switch_row( 'skip_bigger', __( 'Skip if larger', 'featherweight' ), __( 'Keep the original when the converted file would be bigger.', 'featherweight' ), $o['skip_bigger'] );
					?>
				</div>
				<p class="fw-field">
					<label class="pd-label" for="fw-ff"><?php esc_html_e( 'ffmpeg path', 'featherweight' ); ?></label>
					<input type="text" id="fw-ff" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[ffmpeg]" value="<?php echo esc_attr( $o['ffmpeg'] ); ?>" placeholder="<?php esc_attr_e( 'Auto-detect', 'featherweight' ); ?>" aria-describedby="fw-ff-h">
					<span class="description" id="fw-ff-h"><?php esc_html_e( 'Only needed if your host installed ffmpeg somewhere unusual.', 'featherweight' ); ?></span>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'featherweight' ); ?></button></p>
			</form>
		</section>
	</div>
</div>
