<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the QR code wrapper in includes/fn/qr.php: version choice from
 * the block table (past the generator's own 1-10 limit), the matrix shape and
 * finder patterns, UTF-8 input and the SVG markup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A key URI the size the two-step setup screen produces (146 bytes).
function qr_test_otpauth_uri()
{
	return 'otpauth://totp/Pinegrap%20Demo:erdal%40kodpen.com?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Pinegrap%20Demo&algorithm=SHA1&digits=6&period=30';
}

// True when the 7x7 block at ($top, $left) is a finder pattern: dark outer
// ring, light ring inside it, dark 3x3 centre.
function qr_test_is_finder($matrix, $top, $left)
{
	for ($row = 0; $row < 7; $row++) {
		for ($column = 0; $column < 7; $column++) {
			$ring = max(abs($row - 3), abs($column - 3));
			$expected = ($ring === 2) ? 0 : 1;

			if (!isset($matrix[$top + $row][$left + $column]) || ($matrix[$top + $row][$left + $column] !== $expected)) {
				return false;
			}
		}
	}

	return true;
}

function test_qr_empty_text_gives_nothing()
{
	pg_assert_same(array(), pg_qr_matrix(''));
	pg_assert_same('', pg_qr_svg(''));
	pg_assert_same('', pg_qr_svg_data_uri(''));
}

function test_qr_length_limit()
{
	pg_assert_same(1000, pg_qr_max_length());
	pg_assert_same(array(), pg_qr_matrix(str_repeat('q', 1001)), '1001 bytes');
	pg_assert_same('', pg_qr_svg(str_repeat('q', 1001)), '1001 bytes svg');
	pg_assert_same(121, count(pg_qr_matrix(str_repeat('q', 1000))), '1000 bytes');
}

// Module count is 17 + 4 * version. The expected versions are the smallest
// that hold the text in byte mode at each level.
function test_qr_version_choice()
{
	pg_assert_same(21, count(pg_qr_matrix('A')), "'A' is version 1");

	$uri = qr_test_otpauth_uri();
	pg_assert_same(146, strlen($uri));
	pg_assert_same(45, count(pg_qr_matrix($uri, 'L')), 'otpauth L: version 7');
	pg_assert_same(49, count(pg_qr_matrix($uri, 'M')), 'otpauth M: version 8');
	pg_assert_same(57, count(pg_qr_matrix($uri, 'Q')), 'otpauth Q: version 10');
	pg_assert_same(65, count(pg_qr_matrix($uri, 'H')), 'otpauth H: version 12');

	pg_assert_same(69, count(pg_qr_matrix(str_repeat('abc123XYZ-', 30), 'M')), '300 bytes M: version 13');
	pg_assert_same(121, count(pg_qr_matrix(str_repeat('q', 1000), 'M')), '1000 bytes M: version 26');
}

function test_qr_unknown_level_is_m()
{
	$uri = qr_test_otpauth_uri();
	pg_assert_same(pg_qr_matrix($uri, 'M'), pg_qr_matrix($uri, 'x'));
	pg_assert_same(pg_qr_matrix($uri, 'M'), pg_qr_matrix($uri, 'm'));
}

// Square, 0/1 cells, finder patterns in three corners and not in the fourth.
function test_qr_matrix_shape_and_finders()
{
	foreach (array('A', qr_test_otpauth_uri(), str_repeat('q', 1000)) as $text) {
		$matrix = pg_qr_matrix($text);
		$count = count($matrix);
		$label = strlen($text) . ' bytes';

		$square = true;
		$binary = true;

		foreach ($matrix as $cells) {
			$square = $square && (count($cells) === $count);

			foreach ($cells as $cell) {
				$binary = $binary && (($cell === 0) || ($cell === 1));
			}
		}

		pg_assert_true($square, $label . ': square');
		pg_assert_true($binary, $label . ': 0/1 cells');
		pg_assert_true(qr_test_is_finder($matrix, 0, 0), $label . ': top left finder');
		pg_assert_true(qr_test_is_finder($matrix, 0, $count - 7), $label . ': top right finder');
		pg_assert_true(qr_test_is_finder($matrix, $count - 7, 0), $label . ': bottom left finder');
		pg_assert_false(qr_test_is_finder($matrix, $count - 7, $count - 7), $label . ': no bottom right finder');
	}
}

function test_qr_utf8_text_is_encoded()
{
	$text = 'Türkçe çay ☕';
	$matrix = pg_qr_matrix($text);

	pg_assert_same(17, strlen($text));
	pg_assert_same(25, count($matrix), 'UTF-8 text, 17 bytes: version 2');
	pg_assert_true(qr_test_is_finder($matrix, 0, 0));
}

function test_qr_svg_markup()
{
	$svg = pg_qr_svg('A', 200, array('label' => 'a"b<c'));

	pg_assert_same(0, strpos($svg, '<svg'), 'starts with <svg');
	pg_assert_contains('viewBox="0 0 29 29"', $svg);
	pg_assert_contains('width="200"', $svg);
	pg_assert_contains('height="200"', $svg);
	pg_assert_contains('shape-rendering="crispEdges"', $svg);
	pg_assert_contains('role="img"', $svg);
	pg_assert_contains('aria-label="a&quot;b&lt;c"', $svg);
	pg_assert_contains('<rect width="29" height="29" fill="#ffffff"/>', $svg);
	// The top left finder's first row: seven dark modules from (4, 4).
	pg_assert_contains('<path d="M4 4h7v1h-7z', $svg);
	pg_assert_same('</svg>', substr($svg, -6));

	$bare = pg_qr_svg('A', 0);
	$bare_tag = substr($bare, 0, strpos($bare, '>') + 1);
	pg_assert_false(strpos($bare_tag, ' width=') !== false, 'size 0: no width');
	pg_assert_false(strpos($bare_tag, ' height=') !== false, 'size 0: no height');
	pg_assert_false(strpos($bare_tag, 'role=') !== false, 'no label: no role');
	pg_assert_contains('aria-hidden="true"', $bare_tag);

	$styled = pg_qr_svg('A', 120, array('id' => 'k"ey', 'class' => 'qr', 'level' => 'H'));
	pg_assert_contains('id="k&quot;ey"', $styled);
	pg_assert_contains('class="qr"', $styled);
}

function test_qr_svg_data_uri()
{
	$prefix = 'data:image/svg+xml;base64,';
	$uri = pg_qr_svg_data_uri('A', 0, array('label' => 'QR'));

	pg_assert_same(0, strpos($uri, $prefix));
	pg_assert_same(pg_qr_svg('A', 0, array('label' => 'QR')), base64_decode(substr($uri, strlen($prefix))));
}

// The generator runs under pg_qr_error_handler(); the caller's handler is
// back in place afterwards, after a code and after an empty result alike.
function test_qr_restores_error_handler()
{
	$current = function () {
		$handler = set_error_handler('var_dump');
		restore_error_handler();

		return $handler;
	};

	$before = $current();
	pg_qr_matrix(qr_test_otpauth_uri());
	pg_assert_true($before === $current(), 'after a code');
	pg_qr_matrix(str_repeat('q', 1001));
	pg_assert_true($before === $current(), 'after an empty result');
}

// Only the generator's E_USER_ERROR becomes an exception; a notice is left
// to PHP (false) so it cannot cost the code.
function test_qr_error_handler_converts_only_user_error()
{
	$thrown = null;

	try {
		pg_qr_error_handler(E_USER_ERROR, 'code length overflow.', 'qrcode.php', 354);
	} catch (ErrorException $exception) {
		$thrown = $exception;
	}

	pg_assert_true($thrown instanceof ErrorException, 'E_USER_ERROR throws');
	pg_assert_same(E_USER_ERROR, ($thrown !== null) ? $thrown->getSeverity() : null, 'severity kept');
	pg_assert_same('code length overflow.', ($thrown !== null) ? $thrown->getMessage() : null, 'message kept');

	foreach (array(E_USER_NOTICE, E_USER_WARNING, E_USER_DEPRECATED, E_NOTICE, E_WARNING, E_DEPRECATED) as $severity) {
		pg_assert_false(pg_qr_error_handler($severity, 'notice', 'qrcode.php', 1), 'severity ' . $severity . ' passes through');
	}
}
