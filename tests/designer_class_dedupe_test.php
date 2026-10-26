<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the class dedupe of the visual designer (deduplicateCssClass()
 * in assets/js/style_designer.js): a new class replaces the one of the same
 * family at the same breakpoint and leaves the other breakpoints alone, so
 * d-none and d-md-block can sit together. The function and its prefix list
 * are cut out of the script and run under Node (skipped without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs each [cssClass, newClass] through deduplicateCssClass(); null without Node.
function designer_class_dedupe_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$list_from = strpos($source, '    var CLASS_MUTEX_PREFIXES = [');
	$from = strpos($source, '    function deduplicateCssClass(');
	pg_assert_true($list_from !== false && $from !== false, 'the designer has the prefix list and deduplicateCssClass()');

	if ($list_from === false || $from === false) {
		return null;
	}

	$list = substr($source, $list_from, strpos($source, '];', $list_from) + 2 - $list_from);
	$function = substr($source, $from, strpos($source, "\n    function ", $from + 10) - $from);
	$file = tempnam(sys_get_temp_dir(), 'pgdedupe');
	file_put_contents($file, $list . "\n" . $function . "\n"
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) { return deduplicateCssClass(c[0], c[1]); })));');
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_designer_class_dedupe_by_breakpoint()
{
	$cases = array(
		// A responsive class leaves the base and the other breakpoints.
		array('d-none', 'd-md-block'),
		array('d-none d-md-block', 'd-lg-flex'),
		array('d-none d-md-block', 'd-md-flex'),
		array('d-none d-md-block', 'd-flex'),
		array('p-3 p-md-5', 'p-4'),
		array('p-3 p-md-5', 'p-md-2'),
		array('flex-column flex-md-row', 'flex-row'),
		array('justify-content-center', 'justify-content-lg-between'),
		array('text-center fw-bold', 'fw-normal'),
		array('shadow shadow-sm', 'shadow-lg'),
		array('bg-primary text-white', 'bg-body'),
		array('card my-class', 'col-6'),
		array('', 'd-md-block'),
	);

	$result = designer_class_dedupe_test_run($cases);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		'd-none',
		'd-none d-md-block',
		'd-none',
		'd-md-block',
		'p-md-5',
		'p-3',
		'flex-md-row',
		'justify-content-center',
		'text-center',
		'',
		'text-white',
		'card my-class',
		'',
	), $result);
}
