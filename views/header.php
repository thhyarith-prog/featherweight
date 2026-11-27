<?php
/**
 * Featherweight brand header and section tabs (shared by both screens).
 *
 * @var array  $st  State from Featherweight_Admin::state().
 * @var string $tab Current tab: optimize or alt.
 *
 * @package Featherweight
 */

defined( 'ABSPATH' ) || exit;
?>
	<header class="pd-brand">
		<div class="pd-brand__id">
			<span class="pd-brand__mark pd-brand__mark--icon" aria-hidden="true"><img src="<?php echo esc_url( FEATHERWEIGHT_URL . 'assets/img/icon-featherweight.png' ); ?>" alt="" width="48" height="48"></span>
			<div><h1>Featherweight</h1><p class="pd-brand__tag"><?php esc_html_e( 'WebP · WebM · Lighter pages', 'featherweight' ); ?></p></div>
		</div>
		<div class="pd-brand__side">
			<span class="pd-brand__status"><span class="pd-dot<?php echo $st['todo'] ? ' pd-dot--warn' : ''; ?>" aria-hidden="true"></span><span id="fw-head-pct"><?php echo (int) $st['pct']; ?>%</span> <small><?php esc_html_e( 'of media optimized', 'featherweight' ); ?></small></span>
			<a class="pd-credit" href="https://www.prasatdigital.com" target="_blank" rel="noopener noreferrer" title="prasatdigital.com"><img src="<?php echo esc_url( FEATHERWEIGHT_URL . 'assets/img/prasat-mark-white.svg' ); ?>" alt="" width="20" height="20"><span><?php printf( /* translators: %s: Prasat Digital */ esc_html__( 'by %s', 'featherweight' ), '<b>Prasat Digital</b>' ); ?></span></a>
		</div>
	</header>
	<nav class="pd-tabs" aria-label="<?php esc_attr_e( 'Featherweight sections', 'featherweight' ); ?>">
		<?php foreach ( array( 'optimize' => __( 'Optimize', 'featherweight' ), 'alt' => __( 'Alt text', 'featherweight' ) ) as $k => $label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=featherweight' . ( 'optimize' === $k ? '' : '&tab=' . $k ) ) ); ?>" class="<?php echo $k === $tab ? 'is-active' : ''; ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?><?php if ( 'alt' === $k && ! empty( $alt_missing ) ) : ?> <span class="pd-count"><?php echo esc_html( number_format_i18n( $alt_missing ) ); ?></span><?php endif; ?></a>
		<?php endforeach; ?>
	</nav>
