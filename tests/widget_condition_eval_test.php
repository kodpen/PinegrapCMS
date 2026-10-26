<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the editor's twin of the value condition test (_sdCondEval()
 * and its helpers in style_designer.js, run under Node) against the
 * server's pg_sw_cond_eval(): the shared vectors, a grid of values and
 * operands both sides answer, and the single separator with three digits
 * after it, which the site's decimal point decides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whether Node can be run here; the tests pass quietly without it.
function _cond_eval_node_ok()
{
	if (!function_exists('exec')) return false;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	return $code === 0;
}

// The twin's source, cut out of style_designer.js.
function _cond_eval_js_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SD_COND_WS = ", ";\n");
	foreach (array('_sdCondPlain', '_sdCondText', '_sdCondFold', '_sdCondFigureOk', '_sdCondFigureNumber', '_sdCondAmount',
		'_sdCondNumber', '_sdCondComparable', '_sdCondIsWhole', '_sdCondEval') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	return $src;
}

// The twin's answers to $cases (rows of op, value, operand, decimal), in order.
function _cond_eval_js(array $cases)
{
	$script = _cond_eval_js_source()
		. 'var cases = ' . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return _sdCondEval(c[0], c[1], c[2], c[3]); })));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return null;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer) && count($answer) === count($cases), 'Node printed the answers: ' . substr($printed, 0, 300));
	return $answer;
}

// The site's decimal point, as pg_parse_amount() reads it here.
function _cond_eval_decimal()
{
	$s = pg_number_separators();
	return $s['decimal'];
}

// Every shared vector gives the same answer in the editor.
function test_widget_condition_eval_vectors()
{
	if (!_cond_eval_node_ok()) return;
	$rows = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/widget_condition_vectors.json'), true);
	pg_assert_true(is_array($rows) && count($rows) >= 60, 'the vectors file holds its rows');
	$cases = array();
	foreach ($rows as $row) {
		foreach (array('.', ',') as $decimal) {
			$cases[] = array($row['op'], $row['value'], $row['operand'], $decimal);
		}
	}
	$answer = _cond_eval_js($cases);
	foreach ($cases as $i => $c) {
		$row = $rows[intdiv($i, 2)];
		pg_assert_same($row['expect'], $answer[$i],
			'#' . intdiv($i, 2) . ' (' . $c[3] . ') ' . $c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($c[2], JSON_UNESCAPED_UNICODE));
	}
}

// A grid of values and operands under every operator: the editor answers
// as the server does, with the server's decimal point.
function test_widget_condition_eval_grid()
{
	if (!_cond_eval_node_ok()) return;
	$values = array('', ' ', "\xC2\xA0", '0', '1', '-1', '12', '12,50', '12.50', '₺12,50', '-₺12,50', '€12,50 EUR', '12,50 TL',
		'10%', '1.234,56', '1,234.56', '1 234,56', '1.500', '1,500', '24.10.2026', '1.2,5', 'Kat 2', 'KAT 2', 'ODA 2',
		'US$12', 'R$ 3', 'İstanbul', 'ISTANBUL', 'ırmak', 'IRMAK', 'complete', 'Complete', 'pending', 'a  b', "a\u{2028}b",
		'9007199254740993', '1e3', '0x10', '12 tl', 'Ünye', 'şeker', '½');
	$operands = array('0', '1', '12.5', '12,5', '1500', '1.5', '3', 'istanbul', 'irmak', 'complete', 'kat', 'a b', 'EUR', '%', 'ün', '-1');
	$cases = array();
	$decimal = _cond_eval_decimal();
	foreach (array_keys(pg_sw_cond_ops()) as $op) {
		foreach ($values as $value) {
			foreach ($operands as $operand) {
				$cases[] = array($op, $value, $operand, $decimal);
			}
		}
	}
	$cases[] = array('bogus', 'x', 'x', $decimal);
	$answer = _cond_eval_js($cases);
	foreach ($cases as $i => $c) {
		pg_assert_same(pg_sw_cond_eval($c[0], $c[1], $c[2]), $answer[$i],
			$c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($c[2], JSON_UNESCAPED_UNICODE));
	}
}

// One separator with three digits after it: a decimal point only where
// the site writes one so.
function test_widget_condition_eval_three_digits_follow_the_site()
{
	if (!_cond_eval_node_ok()) return;
	$answer = _cond_eval_js(array(
		array('eq', '1.500', '1500', ','),
		array('eq', '1.500', '1.5', '.'),
		array('eq', '1,500', '1.5', ','),
		array('eq', '1,500', '1500', '.'),
		array('gt', '₺1.500', '100', ','),
		array('gt', '₺1.500', '100', '.'),
	));
	pg_assert_same(array(true, true, true, true, true, false), $answer);
}

// What a visitor reads in a value: tags and comments go, spaces collapse.
function test_widget_condition_eval_text()
{
	if (!_cond_eval_node_ok()) return;
	$script = _cond_eval_js_source()
		. "console.log(JSON.stringify([_sdCondText('<b>12</b> <!-- x -->\\u00a0TL '), _sdCondText(null), _sdCondPlain(' a \\t b ')]));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgcond');
	if ($file === false) return;
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(array(pg_sw_cond_text("<b>12</b> <!-- x -->\xC2\xA0TL "), '', 'a b'), json_decode(implode("\n", $out), true));
}
