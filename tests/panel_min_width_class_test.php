<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that panel markup lets a flex column shrink with Bootstrap 6's
 * min-w-0: the class min-width-0 is defined by no stylesheet, so a row's
 * text column kept its content width and a long name (a volunteer shift's
 * place, a queue desk) pushed the screen wider than a phone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_panel_min_width_class_bootstrap_6_defines_min_w_0()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-6/css/bootstrap.min.css');

	pg_assert_true(strpos($css, '.min-w-0{min-width:0}') !== false, 'Bootstrap 6 draws min-w-0');
	pg_assert_true(strpos($css, '.min-width-0') === false, 'Bootstrap 6 has no min-width-0');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'), '.min-width-0') === false, 'the panel sheet has no min-width-0');
}

function test_panel_min_width_class_no_panel_markup_uses_min_width_0()
{
	// Site widgets print Bootstrap 5.3.8 markup, which has neither class;
	// the designer's canvas twins of them print the same.
	$site = array('includes/fn/widgets_cart.php', 'includes/fn/widgets_express_order.php', 'assets/js/style_designer.src.js');
	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));
	foreach ($files as $file) {
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen(PG_FUNCTIONS_DIR) + 1));
		if (!preg_match('/\.(php|js)$/', $path) || preg_match('/\.min\.js$/', $path) || preg_match('#^(data|assets/lib|vendor)/#', $path) || in_array($path, $site, true)) {
			continue;
		}
		if (preg_match('/\bmin-width-0\b/', (string) file_get_contents($file->getPathname()))) {
			$found[] = $path;
		}
	}

	pg_assert_same(array(), $found, 'panel markup writes min-w-0, not the undefined min-width-0');
}
