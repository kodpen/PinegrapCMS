<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Page Design Studio's frame on a narrow window
 * (assets/css/style_designer.css): from a 1024 px window the bar's Add Page,
 * Preview and Publish are their icons; on a phone the lines around Undo and
 * Redo go and the status bar's Help is its icon; the shut docked bottom
 * panel is one strip of three headers; the rails carry icons only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The bodies of every top-level @media block of style_designer.css whose
// query is exactly $query, joined.
function designer_frame_narrow_media($query)
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$out = '';
	$head = '@media ' . $query . ' {';
	$at = 0;
	while (($start = strpos($css, $head, $at)) !== false) {
		$i = $start + strlen($head);
		$depth = 1;
		$len = strlen($css);
		while (($i < $len) && ($depth > 0)) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;
			}
			$i++;
		}
		$out .= substr($css, $start + strlen($head), $i - $start - strlen($head)) . "\n";
		$at = $i;
	}

	return $out;
}

function test_designer_bar_words_go_from_1024()
{
	$at1024 = designer_frame_narrow_media('(max-width: 1024px)');

	foreach (array('.sd-tabs-add-txt', '#sd-preview-menu-btn .sd-preview-txt', '#sd-ajax-save .sd-publish-txt', '#sd-publish-page .sd-publish-txt') as $sel) {
		pg_assert_contains($sel, $at1024, $sel . ' is hidden from a 1024 px window');
	}
}

function test_designer_phone_bar_and_status_bar()
{
	$phone = designer_frame_narrow_media('(max-width: 575.98px)');

	pg_assert_contains('.sd-toolbar-actions .sd-bar-line { display: none; }', $phone, 'no lines around Undo and Redo on a phone');
	pg_assert_contains('#sd-status-shortcuts .sd-status-txt { display: none; }', $phone, 'Help is its icon on a phone');
}

function test_designer_docked_bottom_panel_shut_is_one_strip()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_true(preg_match('/\.sd-panel-bottom\.sd-docked:not\(\.sd-dock-open\) \.sd-bp-grid \{[^}]*flex-direction: row;[^}]*height: 28px;/', $css) === 1, 'the shut docked panel is one 28 px row');
	pg_assert_contains(".sd-panel-bottom.sd-docked .sd-bp-quad.sd-dock-open .sd-bp-quad-body { display: block; }", $css, 'an open part still shows its body');
}

function test_designer_rails_have_no_label()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_true(strpos($js, 'sd-rail-label') === false, 'the rail draws no vertical word');
}
