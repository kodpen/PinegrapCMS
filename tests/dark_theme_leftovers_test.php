<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for panel places that hard-coded black text or light fills
 * and so stayed light in the dark theme: theme badges let the theme set
 * their text colour, and inline styles read the panel's --pg-* tokens.
 * Fixed fills (bg-warning, theme-warning, bg-info) may keep fg-black; the
 * barcode label canvas and its print document stay white as the paper.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dark_theme_leftovers_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// A subtle theme badge sets its own text colour; fg-black is black in both themes.
function test_dark_theme_theme_badges_have_no_black_text()
{
	pg_assert_contains(
		'<span class="badge badge-subtle theme-secondary border fw-light" title="',
		dark_theme_leftovers_source('includes/fn/signin.php'),
		'sign-in identity badges');
	pg_assert_contains(
		'translate-middle badge-subtle theme-secondary border-top fw-light"><?=$number?></span>',
		dark_theme_leftovers_source('includes/templates/duplicate_folder.php'),
		'find/replace row number badge');
	pg_assert_contains(
		'<span class="menu-text badge fg-body fw-light py-3" >',
		dark_theme_leftovers_source('includes/fn/user_list.php'),
		'access menu names follow the theme');

	// text-dark is a Bootstrap 5 class with no rule in the panel's Bootstrap 6.
	pg_assert_false(
		strpos(dark_theme_leftovers_source('includes/dashboard/widgets/widget_21.php'), 'text-dark') !== false,
		'the firewall widget carries no v5 text-dark');
}

// The label designer's boxes and fields read panel tokens; the canvas stays white.
function test_dark_theme_label_designer_uses_tokens()
{
	$js = dark_theme_leftovers_source('assets/js/backend.src.js');
	$start = strpos($js, 'LabelEditor');
	pg_assert_true($start !== false, 'PgBarcode.LabelEditor is found');
	$editor = substr($js, $start);

	foreach (array('#f8f9fa', '#dee2e6', '#ced4da', '#6c757d') as $colour) {
		pg_assert_false(strpos($editor, $colour) !== false, 'the label designer has no fixed ' . $colour);
	}

	pg_assert_contains('background:var(--pg-bg-1);border:1px solid var(--pg-border-color);', $editor);
	pg_assert_contains('border:1px solid var(--pg-border-color);border-radius:4px;padding:0 4px;font-size:12px;background:var(--pg-bg-body);color:var(--pg-fg-body);', $editor);
	pg_assert_contains('color:var(--pg-fg-2);', $editor);
}

// The order export overlay dims the page in the panel's own background colour.
function test_dark_theme_please_wait_overlay_uses_tokens()
{
	$js = dark_theme_leftovers_source('assets/js/backend.src.js');
	pg_assert_contains(
		'background: color-mix(in srgb, var(--pg-bg-body) 70%, transparent);color: var(--pg-fg-body);',
		$js);
	pg_assert_false(strpos($js, '#e3e3e3ab') !== false, 'no fixed light overlay');
}

function test_dark_theme_fixed_fills_follow_tokens()
{
	$widget = dark_theme_leftovers_source('includes/dashboard/widgets/widget_14.php');
	pg_assert_contains('height:4px;background:var(--pg-bg-2);overflow:hidden', $widget);
	pg_assert_false(strpos($widget, '#e5e7eb') !== false, 'hosting widget track');

	$form = dark_theme_leftovers_source('includes/forms/field_form.php');
	pg_assert_contains('<div style="background: var(--pg-border-color);width: 3px;height: 14px;', $form);
	pg_assert_contains('<div style="background: var(--pg-border-color);width: 14px;height: 3px;', $form);
	pg_assert_false(strpos($form, '#dee2e6') !== false, 'field form corner ticks');
}

// var(--bs-orange) is not defined in the panel, so the 30-54 band drew nothing.
function test_dark_theme_seo_orange_band_is_a_literal()
{
	$seo = dark_theme_leftovers_source('seo.php');
	pg_assert_false(strpos($seo, '--bs-orange') !== false, 'seo.php reads no undefined --bs-orange');
	pg_assert_contains("return array('class' => '', 'style' => '#fd7e14');", $seo);
	pg_assert_contains(
		'<span class="badge" style="background-color:\' . $color[\'style\'] . \';color:var(--pg-on-accent)" title="',
		$seo,
		'the orange badge carries dark ink');
}

// A black 6% track vanishes on the dark card; the panel's second background shows in both themes.
function test_dark_theme_dashboard_tracks_use_tokens()
{
	foreach (array(18, 21, 22, 23) as $id) {
		$widget = dark_theme_leftovers_source('includes/dashboard/widgets/widget_' . $id . '.php');
		pg_assert_false(strpos($widget, 'rgba(0,0,0,.06)') !== false, 'widget ' . $id . ' has no black 6% fill');
		pg_assert_contains('style="height:4px;background:var(--pg-bg-2)', $widget, 'widget ' . $id . ' track');
	}

	pg_assert_contains(
		'style="background:var(--pg-bg-2);color:inherit;font-size:10px"',
		dark_theme_leftovers_source('includes/dashboard/widgets/widget_21.php'),
		'firewall widget hit count pill');
}

// The AI row's presence dot is built with border-white; its ring follows the window background.
function test_dark_theme_chat_dot_ring_follows_background()
{
	pg_assert_contains(
		'#pg-chat-root .pg-chat-dot { border-color: var(--pg-bg-body); }',
		dark_theme_leftovers_source('chat.php'));
}

// Theme badges set their own text colour: fg-white on a pastel fill reads at about 2:1.
function test_dark_theme_badges_keep_their_ink()
{
	pg_assert_contains(
		'translate-middle badge rounded-pill badge-subtle theme-success">',
		dark_theme_leftovers_source('view_orders.php'),
		'tracking count badge');

	// The profiles list's rows are built in includes/fn/commission_list.php.
	$profiles = dark_theme_leftovers_source('includes/fn/commission_list.php');
	$profiles = substr($profiles, strpos($profiles, 'function pg_commission_profile_list_row('));
	$profiles = substr($profiles, 0, strpos($profiles, "\n}\n"));
	pg_assert_false(strpos($profiles, 'fg-white') !== false, 'recurring commission badges');
	pg_assert_contains(
		'<span class="badge theme-primary fw-light">\' . pg_format_money($profile[\'amount\'] / 100, BASE_CURRENCY_SYMBOL) . \'</span>\'',
		$profiles,
		'the amount badge is closed');

	pg_assert_contains(
		'<span class="badge fw-light theme-secondary">',
		dark_theme_leftovers_source('includes/templates/view_key_codes.php'));

	$offer = dark_theme_leftovers_source('assets/js/offer_editor.js');
	pg_assert_contains('<a href="#" class="fg-reset text-decoration-none" data-on="', $offer, 'offer tag remove link');

	// A black fill needs white ink; the badge's default text is dark in the light theme.
	pg_assert_contains("'ban'         => array('bg-black fg-white', lang('Banned')),", dark_theme_leftovers_source('includes/fn/waf_log_list.php'));
	pg_assert_contains("'deleted'      => ['bg-black fg-white',", dark_theme_leftovers_source('view_parasut_inbox.php'));
}

// The users list is a scrollX DataTable (.dt-scroll-body overflow:auto): a menu
// placed absolutely inside it is clipped, so the access menu opens fixed.
function test_dark_theme_user_list_access_menu_opens_fixed()
{
	pg_assert_contains(
		'data-bs-auto-close="outside" data-bs-strategy="fixed" data-bs-toggle="menu"',
		dark_theme_leftovers_source('includes/fn/user_list.php'),
		'access menu toggle in the users list');
}
