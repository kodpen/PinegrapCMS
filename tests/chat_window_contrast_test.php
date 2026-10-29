<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the panel chat window: own messages and their read ticks on a
 * gradient fixed in both themes, the active tab and the switched-on emoji
 * button on the primary emphasis ink, the send button on the accent ink.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chat_window_contrast_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/chat.php');
}

// The rule body of one #pg-chat-root selector, or '' when missing.
function chat_window_contrast_rule($source, $selector)
{
	if (preg_match('/^\s*#pg-chat-root ' . preg_quote($selector, '/') . ' \{([^}]*)\}/m', $source, $match) !== 1) {
		return '';
	}

	return $match[1];
}

function chat_window_contrast_rgb($hex)
{
	$hex = ltrim($hex, '#');

	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function chat_window_contrast_luminance(array $rgb)
{
	$l = array();
	foreach ($rgb as $c) {
		$c /= 255;
		$l[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
	}

	return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

function chat_window_contrast_ratio(array $a, array $b)
{
	$la = chat_window_contrast_luminance($a);
	$lb = chat_window_contrast_luminance($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// White at the given alpha laid over a ground.
function chat_window_contrast_white_over(array $ground, $alpha)
{
	$out = array();
	foreach ($ground as $c) {
		$out[] = $alpha * 255 + (1 - $alpha) * $c;
	}

	return $out;
}

// Own messages: white text, ticks and seen ticks reach 4.5:1 at both ends
// of a gradient that does not follow the theme's pastel primary.
function test_chat_window_contrast_own_bubble()
{
	$rule = chat_window_contrast_rule(chat_window_contrast_source(), '.pg-chat-bubble-me');
	pg_assert_true(preg_match('/background: linear-gradient\(135deg, (#[0-9a-f]{6}), (#[0-9a-f]{6})\)/i', $rule, $m) === 1,
		'own bubble is a gradient of two fixed colours');
	pg_assert_true(strpos($rule, 'color: #fff') !== false, 'own bubble text is white');
	if (empty($m)) {
		return;
	}

	$tick = chat_window_contrast_rule(chat_window_contrast_source(), '.pg-chat-tick');
	pg_assert_true(preg_match('/color: rgba\(255,\s*255,\s*255,\s*([0-9.]+)\)/', $tick, $alpha) === 1,
		'read tick is translucent white');
	$seen = chat_window_contrast_rule(chat_window_contrast_source(), '.pg-chat-tick-seen');
	pg_assert_true(preg_match('/color: (#[0-9a-f]{6})/i', $seen, $seenColour) === 1, 'seen tick has a colour');

	foreach (array($m[1], $m[2]) as $end) {
		$ground = chat_window_contrast_rgb($end);
		pg_assert_true(chat_window_contrast_ratio(array(255, 255, 255), $ground) >= 4.5, 'white text on ' . $end);
		pg_assert_true(chat_window_contrast_ratio(chat_window_contrast_white_over($ground, (float) $alpha[1]), $ground) >= 4.5,
			'read tick on ' . $end);
		pg_assert_true(chat_window_contrast_ratio(chat_window_contrast_rgb($seenColour[1]), $ground) >= 4.5,
			'seen tick on ' . $end);
	}
}

// The active tab and the switched-on emoji button read the emphasis ink,
// the send button the accent ink.
function test_chat_window_contrast_inks()
{
	$source = chat_window_contrast_source();

	foreach (array('.pg-chat-tabs .nav-link.active', '.pg-chat-tool.pg-chat-tool-on') as $selector) {
		pg_assert_true(strpos(chat_window_contrast_rule($source, $selector), 'color: var(--pg-primary-fg-emphasis)') !== false,
			$selector . ' reads the primary emphasis ink');
	}

	$send = chat_window_contrast_rule($source, '.pg-chat-send');
	pg_assert_true(strpos($send, 'background: var(--pg-primary)') !== false, 'send button keeps the primary fill');
	pg_assert_true(strpos($send, 'color: var(--pg-on-accent)') !== false, 'send icon reads the accent ink');
}
