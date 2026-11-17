<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the parts of a Workspace message in a narrow column (the
 * discussion panel on a wide window, a phone): each follows the width of
 * the column it stands in, never the window's alone. The message head
 * wraps, the file card and the voice player give their buttons the next
 * line, and the poll box reads its narrow layout from its own width
 * through the ws-poll container.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The declarations of the first rule written at the line's start whose
// selector is exactly $selector, or '' when there is none.
function workspace_narrow_rule($css, $selector)
{
	if (!preg_match('/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $css, $match)) {
		return '';
	}

	return $match[1];
}

// The body of the first @container block with this prelude, or ''.
function workspace_narrow_container($css, $prelude)
{
	$at = strpos($css, '@container ' . $prelude . ' {');

	if ($at === false) {
		return '';
	}

	$start = strpos($css, '{', $at) + 1;
	$depth = 1;
	$length = strlen($css);

	for ($i = $start; $i < $length; $i++) {
		if ($css[$i] === '{') {
			$depth++;
		} elseif ($css[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($css, $start, $i - $start);
			}
		}
	}

	return '';
}

function workspace_narrow_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

function test_workspace_narrow_message_head_wraps()
{
	$css = workspace_narrow_css();

	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-msg-meta'), 'flex-wrap: wrap') !== false, 'the head line wraps by its column');
	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-msg-meta > .badge'), 'text-overflow: ellipsis') !== false, 'a mark wider than the column is cut');
}

function test_workspace_narrow_file_and_voice_wrap()
{
	$css = workspace_narrow_css();

	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-file-card'), 'flex-wrap: wrap') !== false, 'the file card wraps');
	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-file-card > .ws-grow'), 'flex: 1 1 8rem') !== false, 'the file name keeps 8rem');
	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-voice'), 'flex-wrap: wrap') !== false, 'the voice player wraps');
	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-voice-main'), 'flex: 1 1 7rem') !== false, 'the voice name keeps 7rem');
}

function test_workspace_narrow_poll_reads_its_own_width()
{
	$css = workspace_narrow_css();
	$narrow = workspace_narrow_container($css, 'ws-poll (max-width: 30rem)');

	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-poll'), 'container: ws-poll / inline-size') !== false, 'the poll box is its own container');
	pg_assert_true($narrow !== '', 'a narrow poll box has its own layout');
	pg_assert_true(strpos($narrow, '.ws-poll-meta') !== false, 'the counts go under the option');
	// After the base .ws-poll-split rule in the file, so it needs the
	// higher specificity to win.
	pg_assert_true(strpos($narrow, '.ws-poll .ws-poll-split') !== false, 'a picture beside the options goes above them');
	pg_assert_true(strpos(workspace_narrow_rule($css, '.ws-poll-text'), 'overflow-wrap: break-word') !== false, 'a long word in an option wraps');
}
