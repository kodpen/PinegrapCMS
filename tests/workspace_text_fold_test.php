<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish-aware text fold of the workspace searches
 * (ws_fold(), ws_text_position() and ws_text_contains() in
 * includes/workspace/commands.php): İ, I, ı and i are one letter, the rest
 * is lower cased, character positions stay those of the original text, and
 * the palette excerpt finds a match written with other capitals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/palette.php');

// The dotted and dotless i, small and capital, fold to one letter, so a
// search typed either way finds the name written the other way.
function test_workspace_text_fold_turkish_letters()
{
	$rows = array(
		array('İsmail', 'ismail'),
		array('Can Yıldız', 'YILDIZ'),
		array('IŞIK', 'ışık'),
		array('İstanbul', 'ISTANBUL'),
		array('Kırıkkale Şubesi', 'KIRIKKALE'),
		array('İzmir', 'izmir'),
		array('AYDIN', 'Aydın'),
	);

	foreach ($rows as $row) {
		pg_assert_true(ws_text_contains($row[0], $row[1]), $row[0] . ' contains ' . $row[1]);
	}

	pg_assert_true(ws_text_contains('YILDIZ', 'yıldız'), 'YILDIZ contains yıldız');
	pg_assert_same(ws_fold('İSMAİL'), ws_fold('ismail'), 'İSMAİL folds as ismail');
	pg_assert_same(ws_fold('IŞIK'), ws_fold('ışık'), 'IŞIK folds as ışık');
	pg_assert_same(ws_fold('YILDIZ'), ws_fold('Yıldız'), 'YILDIZ folds as Yıldız');
	pg_assert_same(ws_fold('ISTANBUL'), ws_fold('İstanbul'), 'ISTANBUL folds as İstanbul');
	pg_assert_false(ws_text_contains('Ankara', 'izmir'), 'a different word is not found');
}

// Plain ASCII and other letters lower case as before; an empty needle is
// found at the start.
function test_workspace_text_fold_plain_text()
{
	pg_assert_same('kraft ambalaj', ws_fold('KRAFT Ambalaj'), 'ASCII lower cased');
	pg_assert_same('çğöşü', ws_fold('ÇĞÖŞÜ'), 'other Turkish capitals lower cased');
	pg_assert_true(ws_text_contains('ANKARA', 'ankara'), 'ASCII contains');
	pg_assert_same(0, ws_text_position('abc', ''), 'empty needle at the start');
	pg_assert_false(ws_text_position('', 'a'), 'nothing in an empty text');
}

// A position counts characters of the original text, so a caller can cut
// it there.
function test_workspace_text_fold_position()
{
	pg_assert_same(4, ws_text_position('Can Yıldız', 'YILDIZ'), 'position after the first name');
	pg_assert_same(8, ws_text_position('İİİ ışı İstanbul', 'istanbul'), 'capital İ counts one character');
	pg_assert_same('İstanbul', mb_substr('İİİ ışı İstanbul', ws_text_position('İİİ ışı İstanbul', 'ISTANBUL'), 8), 'cut at the position');

	$long = str_repeat('başta duran sözcükler ', 10) . 'İSMAİL KARAKAŞ burada ' . str_repeat('sonra gelen sözcükler ', 10);
	$piece = ws_palette_snippet($long, 'ismail', 60);

	pg_assert_contains('İSMAİL', $piece, 'the excerpt holds the match written in capitals');
	pg_assert_same('…', mb_substr($piece, 0, 1), 'the excerpt is cut at the front');
}
