<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'tour', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The first tour: the card "New here? A one-minute tour" and the tour's
 * stops (a ring round each real control and a bubble beside it). Its pure
 * model (window.PGDesignerTour), then the part. The core loads it only when
 * the card is due or "Take the tour" is chosen.
 *
 * Memory: docs/memory/designer/designer-first-tour-card.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

return array(
	'sources' => array(
		'assets/js/pg_designer_tour.js',
		'assets/js/style_designer_tour.js',
	),
	'when'    => 'always',
);
