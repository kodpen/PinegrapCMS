<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's Turkish text fold. pgFoldTR() in backend.src.js
 * folds a capital İ (which toLowerCase() turns into i + U+0307), ı and the
 * other Turkish letters, so the command palette, the Site Settings filter
 * and the API applications filter find "İade", "Denetim İzi" or "İstanbul"
 * typed in lowercase. Comboboxes fold through Bootstrap's searchNormalize.
 * The offer editor lists product groups in the panel language's order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_turkish_fold_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Runs pgFoldTR() from backend.src.js under node on each input; null when
// node is missing or the function is not found.
function panel_turkish_fold_test_run($inputs)
{
	$source = panel_turkish_fold_test_source('assets/js/backend.src.js');

	if (!preg_match('/^window\.pgFoldTR = function \(s\) \{.*?^\};$/ms', $source, $m)) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return false;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgfold');

	if ($file === false) {
		return false;
	}

	file_put_contents($file, 'var window = {};' . "\n" . $m[0] . "\n"
		. 'console.log(JSON.stringify(' . json_encode($inputs) . '.map(window.pgFoldTR)));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

function test_panel_turkish_fold_folds_dotted_capital_i()
{
	$folded = panel_turkish_fold_test_run(array('İade', 'Denetim İzi', 'Çerez İzni', 'ISPARTA', 'ılık', 'Şğüöç', 503));

	pg_assert_true($folded !== null, 'backend.src.js defines window.pgFoldTR');

	if ($folded === false) {
		return;
	}

	pg_assert_same(array('iade', 'denetim izi', 'cerez izni', 'isparta', 'ilik', 'sguoc', '503'), $folded, 'a capital İ loses its dot; ı and the other Turkish letters fold; a number becomes text');
}

function test_panel_turkish_fold_used_by_the_filters()
{
	$backend = panel_turkish_fold_test_source('assets/js/backend.src.js');

	pg_assert_true((bool) preg_match('/function normalizeTR\(s\) \{\s*return window\.pgFoldTR\(s\);/', $backend), 'the command palette folds through pgFoldTR');
	pg_assert_true(strpos($backend, 'var q = query.toLowerCase();') === false, 'the palette query is not lowercased before the fold');
	pg_assert_contains("card.setAttribute('data-pgtext', window.pgFoldTR(", $backend, 'the settings cards are folded');
	pg_assert_contains('var needle = window.pgFoldTR(field.value.trim());', $backend, 'the settings filter folds what is typed');

	$api = panel_turkish_fold_test_source('api_settings.php');

	pg_assert_true(strpos($api, 'this.value.toLowerCase()') === false && strpos($api, 'row.textContent.toLowerCase()') === false, 'the API applications filter does not lowercase on its own');
	pg_assert_contains('fold(this.value).trim()', $api, 'the API applications filter folds what is typed');
	pg_assert_contains('fold(row.textContent)', $api, 'the API applications filter folds the rows');
}

function test_panel_turkish_fold_combobox_search_normalizes()
{
	$js = panel_turkish_fold_test_source('assets/js/pg_fields.js');

	pg_assert_true((bool) preg_match('/pgUI\.combobox\.get\(toggle, \{[^}]*searchNormalize: true/', $js), 'the combobox search drops accents and the dot of İ');
}

function test_panel_turkish_fold_offer_product_groups_sorted_by_label()
{
	$php = panel_turkish_fold_test_source('edit_offer_f.php');

	pg_assert_contains("return pg_geo_label_compare(\$a['label'], \$b['label']);", $php, 'the offer editor\'s product groups follow the panel language\'s order');
	pg_assert_true(strpos($php, "strcasecmp(\$a['label'], \$b['label'])") === false, 'no byte-order sort of the group labels');
}
