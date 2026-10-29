<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for touch-screen tap targets in backend.src.css. Shared
 * panel components that are dense on a desktop grow under a coarse pointer
 * so a finger reaches one target, not its neighbour: the System Status
 * check chips and the remove button of every chips field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The concatenated bodies of every "@media (pointer: coarse)" block.
function panel_tap_targets_coarse_css()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$source = preg_replace('~/\*.*?\*/~s', '', $source);
	$bodies = '';
	$offset = 0;

	while (preg_match('/@media\s*\(pointer:\s*coarse\)\s*\{/', $source, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
		$start = $match[0][1] + strlen($match[0][0]);
		$depth = 1;
		$i = $start;
		$length = strlen($source);

		while ($i < $length && $depth > 0) {
			if ($source[$i] === '{') {
				$depth++;
			} elseif ($source[$i] === '}') {
				$depth--;
			}
			$i++;
		}

		$bodies .= substr($source, $start, $i - 1 - $start) . "\n";
		$offset = $i;
	}

	return $bodies;
}

// The body of the rule in $css whose selector list is exactly $selector.
function panel_tap_targets_rule($css, $selector)
{
	if (preg_match('/(?:^|\}|;)\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $match) !== 1) {
		return '';
	}

	return $match[1];
}

// System Status chips are 19 px tall in rows 22 px apart on a desktop; on a
// touch screen each chip is at least 24 px tall.
function test_panel_tap_targets_status_check_chips()
{
	$body = panel_tap_targets_rule(panel_tap_targets_coarse_css(), '.pg-check');

	pg_assert_true(preg_match('/min-height:\s*1\.5rem/', $body) === 1, 'a .pg-check chip is at least 1.5rem tall under a coarse pointer');
}

// Bootstrap's chip remove button is 16x16 px inside a chip that selects
// itself on a missed click; on a touch screen the button is 24x24 px, the
// icon keeps its size, and the visual designer's keyword box keeps its own.
function test_panel_tap_targets_chip_dismiss()
{
	$css = panel_tap_targets_coarse_css();
	$body = panel_tap_targets_rule($css, '.pg-chips:not(#styleSettingsModal .pg-chips) > .chip > .chip-dismiss');

	pg_assert_true(preg_match('/min-width:\s*1\.5rem/', $body) === 1, 'the chip remove button is at least 1.5rem wide under a coarse pointer');
	pg_assert_true(preg_match('/min-height:\s*1\.5rem/', $body) === 1, 'the chip remove button is at least 1.5rem tall under a coarse pointer');
	pg_assert_true(strpos($css, 'chip-dismiss > svg') === false, 'the remove icon keeps its Bootstrap size');
}
