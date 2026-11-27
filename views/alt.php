<?php
/**
 * Featherweight → Alt text.
 *
 * @var array  $st          State from Featherweight_Admin::state().
 * @var string $tab         Current tab.
 * @var int    $alt_missing Images without alt text.
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;

$counts  = Featherweight_Alt::counts();
$premium = Featherweight_Alt::premium();
$aopt    = wp_parse_args( (array) get_option( Featherweight_Alt::OPT, array() ), Featherweight_Alt::defaults() );
$pct     = $counts['total'] ? (int) floor( 100 * ( $counts['total'] - $counts['missing'] ) / $counts['total'] ) : 100;
$switch  = function ( $key, $title, $desc, $badge = '' ) use ( $aopt ) {
	printf(
		'<div class="pd-row"><div class="pd-row__text"><label class="pd-row__title" for="fwa-%1$s">%2$s%5$s</label><p class="pd-row__desc" id="fwa-%1$s-h">%3$s</p></div><div class="pd-row__control"><span class="pd-switch"><input type="checkbox" role="switch" id="fwa-%1$s" name="%4$s[%1$s]" value="1" aria-describedby="fwa-%1$s-h"%6$s><span></span></span></div></div>',
		esc_attr( $key ),
		esc_html( $title ),
		esc_html( $desc ),
		esc_attr( Featherweight_Alt::OPT ),
		$badge ? ' <span class="pd-badge">' . esc_html( $badge ) . '</span>' : '',
		checked( (bool) $aopt[ $key ], true, false )
	);
};
?>
<div class="wrap pd-app featherweight fw-alt">
	<?php include FEATHERWEIGHT_DIR . 'views/header.php'; ?>
	<hr class="wp-header-end">
	<?php settings_errors(); ?>

	<section class="pd-card fw-hero" aria-labelledby="fwa-hero-t">
		<div class="fw-ring" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: percent */ __( '%d%% of images have alt text', 'featherweight' ), $pct ) ); ?>">
			<svg viewBox="0 0 148 148" aria-hidden="true"><defs><linearGradient id="fwa-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#818cf8"/><stop offset="1" stop-color="#2dd4bf"/></linearGradient></defs><circle class="bg" cx="74" cy="74" r="64"/><circle class="fg" id="fwa-ring-fg" cx="74" cy="74" r="64" stroke="url(#fwa-grad)" stroke-dasharray="402.1" stroke-dashoffset="<?php echo esc_attr( 402.1 * ( 1 - $pct / 100 ) ); ?>"/></svg>
			<div class="fw-ring__v"><strong id="fwa-pct"><?php echo (int) $pct; ?>%</strong><span><?php esc_html_e( 'described', 'featherweight' ); ?></span></div>
		</div>
		<div>
			<h2 id="fwa-hero-t"><?php esc_html_e( 'Alt text for your images', 'featherweight' ); ?></h2>
			<p class="pd-lead">
				<span id="fwa-missing"><?php echo esc_html( number_format_i18n( $counts['missing'] ) ); ?></span>
				<?php
				/* translators: %s: number of images */
				echo esc_html( sprintf( __( 'of %s images have no alt text. Screen readers read it aloud to blind visitors, and Google uses it to understand and rank your images.', 'featherweight' ), number_format_i18n( $counts['total'] ) ) );
				?>
			</p>
			<div class="pd-actions">
				<?php if ( $premium ) : ?>
					<button type="button" class="button button-primary button-hero" id="fwa-ai-all" <?php disabled( ! $counts['missing'] ); ?>><?php esc_html_e( 'Write all with AI', 'featherweight' ); ?></button>
				<?php endif; ?>
				<button type="button" class="button button-large<?php echo $premium ? '' : ' button-primary'; ?>" id="fwa-files" <?php disabled( ! $counts['missing'] ); ?>><?php esc_html_e( 'Fill from file names', 'featherweight' ); ?></button>
				<span class="fw-status" id="fwa-status" role="status" aria-live="polite"></span>
			</div>
			<?php if ( ! $premium ) : ?>
				<p class="fwa-premium"><span class="pd-badge"><?php esc_html_e( 'Premium', 'featherweight' ); ?></span> <?php esc_html_e( 'With Prasat Premium, Smart AI looks at each picture and writes the alt text for you, in English or Khmer to match your site.', 'featherweight' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=prasat-digital' ) ); ?>"><?php esc_html_e( 'Add your Premium key', 'featherweight' ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Smart AI sees a small copy of each picture with its file name, title and the page it is on. Nothing else about your site is sent.', 'featherweight' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="pd-card" aria-labelledby="fwa-list-t">
		<div class="pd-card__head"><h2 id="fwa-list-t"><?php esc_html_e( 'Images without alt text', 'featherweight' ); ?></h2><span class="fwa-pager" id="fwa-pager"></span></div>
		<ul class="fwa-list" id="fwa-list" aria-live="polite" aria-busy="true"><li class="pd-empty"><?php esc_html_e( 'Loading images…', 'featherweight' ); ?></li></ul>
	</section>

	<section class="pd-card" aria-labelledby="fwa-set-t">
		<h2 id="fwa-set-t"><?php esc_html_e( 'Settings', 'featherweight' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'featherweight_alt' ); ?>
			<div class="pd-rows">
				<?php
				$switch( 'fill', __( 'Fill alt text into pages', 'featherweight' ), __( 'When a page shows an image without alt text, use the one from the Media Library.', 'featherweight' ) );
				$switch( 'on_upload', __( 'Use the file name on upload', 'featherweight' ), __( 'New images get alt text from a descriptive file name, like "koh-kong-smart-city.jpg". Names like IMG_2041 are skipped.', 'featherweight' ) );
				$switch( 'ai_upload', __( 'Write with AI on upload', 'featherweight' ), __( 'Smart AI describes each new image in the background a few seconds after upload.', 'featherweight' ), __( 'Premium', 'featherweight' ) );
				?>
			</div>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'featherweight' ); ?></button></p>
		</form>
	</section>
</div>
