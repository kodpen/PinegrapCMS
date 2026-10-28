<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the SEO score figures and points in the front-end editing
 * controls: the page bar and Page Panel in the toolbar, and the button box
 * and SEO panel over a site page. The score's hex is a fill (ring, dots);
 * the figure takes a band text shade that reads 4.5:1 in both themes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function seo_score_contrast_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function seo_score_contrast_luminance($hex)
{
	$hex = ltrim($hex, '#');
	$hex = (strlen($hex) === 3) ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex;
	$sum = 0;

	foreach (array(0 => 0.2126, 2 => 0.7152, 4 => 0.0722) as $offset => $weight) {
		$c = hexdec(substr($hex, $offset, 2)) / 255;
		$c = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		$sum += $weight * $c;
	}

	return $sum;
}

function seo_score_contrast_ratio($a, $b)
{
	$la = seo_score_contrast_luminance($a);
	$lb = seo_score_contrast_luminance($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// The value of a --pg-sp-* name in the CSS block that follows $selector.
function seo_score_contrast_var($source, $selector, $name)
{
	$start = strpos($source, $selector);

	if ($start === false) {
		return '';
	}

	$block = substr($source, $start, strpos($source, '}', $start) - $start);

	return (preg_match('/--pg-sp-' . $name . ':\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?);/i', $block, $m) === 1) ? $m[1] : '';
}

// The site sets (seo.php): every band and the muted points on their ground.
function test_seo_score_site_palette_reads_in_both_themes()
{
	$seo = seo_score_contrast_source('seo.php');
	$sets = array(
		'light' => "#software_pinegrap_button_container,\n            #software_seo_panel{",
		'dark' => "#software_pinegrap_button_container.pg_seo_tb_dark,\n            #software_seo_panel.pg_seo_tb_dark{",
	);

	foreach ($sets as $theme => $selector) {
		$bg = seo_score_contrast_var($seo, $selector, 'bg');
		pg_assert_true($bg !== '', 'the ' . $theme . ' set has a ground');

		foreach (array('success', 'warning', 'orange', 'danger', 'muted') as $name) {
			$fg = seo_score_contrast_var($seo, $selector, $name);
			pg_assert_true($fg !== '', 'the ' . $theme . ' set names --pg-sp-' . $name);
			pg_assert_true(seo_score_contrast_ratio($fg, $bg) >= 4.5,
				'--pg-sp-' . $name . ' reads 4.5:1 on the ' . $theme . ' ground');
		}
	}
}

// No figure is painted with the band's fill colour any more.
function test_seo_score_figures_use_band_classes()
{
	$seo = seo_score_contrast_source('seo.php');
	$bar = seo_score_contrast_source('includes/fn/output.php');

	pg_assert_true(strpos($seo, 'pg_seo_tb_total" style="color:') === false, 'the Page Panel total has no inline colour');
	pg_assert_true(strpos($seo, 'class="pg_seo_tb_total pg_seo_band_') !== false, 'the Page Panel total carries its band');
	pg_assert_true(strpos($seo, 'font-weight="700" fill="currentColor"') !== false, 'the ring figure follows its band colour');
	pg_assert_true(strpos($seo, "'band' => \$band") !== false, 'the score hands its band to the page bar');
	pg_assert_true(strpos($bar, '<span style="color:') === false, 'the page bar score has no inline colour');
	pg_assert_true(strpos($bar, '#pg_page_bar .pg-pbar-band-warning{ color: var(--pg-warning-fg-emphasis); }') !== false,
		'the page bar reads the panel warning ink');
}

// Points and weights were the half-transparent tertiary colour (3.08:1).
function test_seo_score_points_use_the_muted_colour()
{
	$seo = seo_score_contrast_source('seo.php');

	pg_assert_true(strpos($seo, '.pg_seo_tb_points{ flex: 0 0 auto; color: var(--pg-sp-muted); }') !== false, 'item points are muted, not faint');
	pg_assert_true(strpos($seo, 'text-align: right; color: var(--pg-sp-muted); }') !== false, 'group weights are muted, not faint');
}

// In the toolbar the bands come from the panel's accent inks.
function test_seo_score_toolbar_maps_bands_to_panel_tokens()
{
	$toolbar = seo_score_contrast_source('toolbar.php');

	foreach (array('success', 'warning', 'danger') as $band) {
		pg_assert_true(strpos($toolbar, '--pg-sp-' . $band . ': var(--pg-' . $band . '-fg-emphasis);') !== false,
			'the toolbar maps --pg-sp-' . $band);
	}
	pg_assert_true(strpos($toolbar, '--pg-sp-orange: color-mix(') !== false, 'the toolbar maps --pg-sp-orange');
}

// The button box follows the panel theme like the SEO panel does.
function test_seo_score_button_box_follows_the_panel_theme()
{
	$page = seo_score_contrast_source('get_page.php');

	pg_assert_true(strpos($page, 'background: var(--pg-sp-bg, #fff);') !== false, 'the box ground reads the palette');
	pg_assert_true(strpos($page, 'button_container.classList.toggle("pg_seo_tb_dark"') !== false, 'the box takes the dark set');
	pg_assert_true(strpos($page, 'background: #fff url(') === false, 'no button keeps a fixed white ground');
}
