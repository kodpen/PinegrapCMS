<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the visual editor's notice (sdToast() in
 * style_designer.js): Bootstrap 6 Toast markup shown and hidden through
 * pgUI.toast, an accessible close button, the live region roles, removal
 * on hidden, and its rules in style_designer.css on --pg-* tokens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The sdToast() and _dismissToast() source, from the function to the next
// section banner.
function bs6_designer_toast_source()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.src.js');
	$start = strpos($source, 'function sdToast(msg, type, duration)');
	pg_assert_true($start !== false, 'sdToast() keeps its signature');
	$end = strpos($source, '// =========================', (int) $start);
	return (string) substr($source, (int) $start, $end === false ? 4000 : $end - $start);
}

function bs6_designer_toast_css()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/style_designer.css');
	$start = strpos($css, 'TOAST NOTIFICATIONS');
	pg_assert_true($start !== false, 'toast section in style_designer.css');
	$end = strpos($css, '/* =========================', (int) $start + 30);
	return (string) substr($css, (int) $start, $end === false ? 3000 : $end - $start);
}

function test_bs6_designer_toast_markup()
{
	$js = bs6_designer_toast_source();

	pg_assert_contains("'toast sd-toast theme-' + ", $js);
	pg_assert_contains("'toast-body ", $js);
	pg_assert_true(strpos($js, 'sd-toast-visible') === false, 'own show class left');
	pg_assert_true(strpos($js, 'sd-toast-dismiss') === false, 'own close glyph left');
}

function test_bs6_designer_toast_uses_pgui()
{
	$js = bs6_designer_toast_source();

	pg_assert_contains('pgUI.toast.get(toast, { autohide: dur > 0, delay: dur', $js);
	pg_assert_contains("pgUI.toast.on(toast, 'hidden'", $js);
	pg_assert_contains('toast.parentNode.removeChild(toast)', $js);
	pg_assert_contains('pgUI.toast.find(toast)', $js);
	pg_assert_contains('pgUI.hostLayer(container)', $js);
}

function test_bs6_designer_toast_accessible()
{
	$js = bs6_designer_toast_source();

	pg_assert_contains("'btn-close", $js);
	pg_assert_contains("close.setAttribute('aria-label', _sdT('Close'))", $js);
	pg_assert_contains("toast.setAttribute('role', type === 'error' ? 'alert' : 'status')", $js);
	pg_assert_contains("toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite')", $js);
	pg_assert_contains("toast.setAttribute('aria-atomic', 'true')", $js);
}

function test_bs6_designer_toast_css_tokens()
{
	$css = bs6_designer_toast_css();

	pg_assert_true(!preg_match('/#[0-9a-f]{3,6}\b/i', $css), 'fixed colours in the toast rules');
	pg_assert_true(!preg_match('/var\(\s*--bs-(?!toast-)/', $css), 'toast rules read a --bs-* variable');
	pg_assert_true(strpos($css, 'sd-toast-visible') === false, 'own show class rule left');
	foreach (array('success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info') as $type => $tone) {
		pg_assert_contains('.sd-toast.theme-' . $tone, $css, $type);
		pg_assert_contains('var(--pg-' . $tone . '-bg-subtle)', $css, $type);
		pg_assert_contains('var(--pg-' . $tone . '-fg-emphasis)', $css, $type);
	}
}
