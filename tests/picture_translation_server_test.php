<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a translated Design Canvas picture drawn by the server with
 * nobody in the panel (includes/fn/picture_server_draw.php): the language's
 * document and the fit as twins of the editor's M.languageDoc() and
 * M.languageFit() on fixtures both run, each changed text fitted to its
 * source's box, what the server draws whole and what it leaves to a panel,
 * the time budget and the waiting mark, and the places that call it.
 * Drawing itself is tested only where Imagick is loaded.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (is_file(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php');
}

/**
 * A fixture of tests/fixtures/picture_language, decoded as arrays.
 *
 * @param string $name
 * @return array
 */
function picture_server_fixture($name)
{
	$list = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/picture_language/' . $name . '.json'), true);

	return is_array($list) ? $list : array();
}

/**
 * Runs $script under Node with the editor's model as M and the fixture
 * folder as F, and answers its JSON; null without Node. The script goes
 * through a file: on Windows the shell's quoting spoils an inline one.
 *
 * @param string $script
 * @return array|null
 */
function picture_server_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpicsrv');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var F = ' . json_encode(dirname(__FILE__) . '/fixtures/picture_language/') . ";\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

/**
 * What a server that draws every part answers.
 *
 * @return array
 */
function picture_server_can()
{
	return array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png', 'jpeg', 'webp'), 'paused' => 0, 'engine' => 'test');
}

/**
 * A canvas document of a sale banner: a background shape and a text in the
 * bundled Roboto, plus the layers given.
 *
 * @param array $more
 * @return array
 */
function picture_server_doc($more = array())
{
	return array('v' => 1, 'artboard' => array('width' => 400, 'height' => 200, 'background' => '#ffffff'), 'layers' => array_merge(array(
		array('id' => 'r1', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 400, 'h' => 200, 'color' => '#ffeecc', 'fill' => 'solid', 'stroke' => 0),
		array('id' => 't1', 'type' => 'text', 'text' => 'Sale', 'font' => 'g:Roboto', 'weight' => 700, 'size' => 60, 'align' => 'center',
			'color' => '#222222', 'x' => 120, 'y' => 60, 'w' => 160, 'h' => 75),
	), $more));
}

/**
 * Each layer's words: a text's text, a sticker's label, '' for any other.
 *
 * @param array $doc
 * @return array
 */
function picture_server_words($doc)
{
	return array_map(function ($l) {
		return isset($l['text']) ? $l['text'] : (isset($l['label']) ? $l['label'] : '');
	}, $doc['layers']);
}

/**
 * A document's layers without their words, to see that nothing else moved.
 *
 * @param array $doc
 * @return string JSON
 */
function picture_server_rest($doc)
{
	foreach ($doc['layers'] as $i => $l) {
		unset($doc['layers'][$i]['text'], $doc['layers'][$i]['label']);
	}

	return json_encode($doc);
}

// The module is loaded where it is used, not by functions.php, and the job
// draws only after the translation jobs ran.
function test_picture_server_module_and_job()
{
	pg_assert_true(function_exists('pg_picture_tr_draw_due'), 'the module is there');

	$manifest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_false(strpos($manifest, 'picture_server_draw.php') !== false, 'not loaded by functions.php');

	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translation_job.php');
	$jobs = strpos($job, 'pg_tr_jobs_run_due(');
	$draw = strpos($job, 'pg_picture_tr_draw_due(');

	pg_assert_true(($jobs !== false) && ($draw !== false) && ($draw > $jobs), 'the pictures are drawn after the translation jobs');
	pg_assert_contains("/includes/fn/picture_server_draw.php'", $job, 'the job loads the module');
	pg_assert_true(strpos($job, 'pg_picture_tr_marked()') !== false, 'only while the mark is set');
}

// M.languageFit() and its PHP twin answer every fixture alike.
function test_picture_server_fit_twins()
{
	$fixtures = picture_server_fixture('fit');

	pg_assert_true(count($fixtures) >= 10, 'fixtures read');

	if (!function_exists('pg_picture_tr_language_fit')) {
		pg_assert_true(false, 'pg_picture_tr_language_fit() is missing');

		return;
	}

	foreach ($fixtures as $case) {
		$got = call_user_func_array('pg_picture_tr_language_fit', $case['args']);

		pg_assert_true(abs($got['scale'] - $case['scale']) < 1e-9, 'PHP scale: ' . $case['about']);
		pg_assert_same($case['mark'], $got['mark'], 'PHP mark: ' . $case['about']);
	}

	$r = picture_server_node('var list = require(F + "fit.json");'
		. 'console.log(JSON.stringify(list.map(function (c) { return M.languageFit.apply(null, c.args); })));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($fixtures as $i => $case) {
		pg_assert_true(abs($r[$i]['scale'] - $case['scale']) < 1e-9, 'script scale: ' . $case['about']);
		pg_assert_same($case['mark'], $r[$i]['mark'], 'script mark: ' . $case['about']);
	}
}

// M.languageDoc() and its PHP twin put the same words in, fit the same
// layers and leave everything else as it was.
function test_picture_server_doc_twins()
{
	$fixtures = picture_server_fixture('doc');

	pg_assert_true(count($fixtures) >= 5, 'fixtures read');

	if (!function_exists('pg_picture_tr_language_doc')) {
		pg_assert_true(false, 'pg_picture_tr_language_doc() is missing');

		return;
	}

	foreach ($fixtures as $case) {
		$put = pg_picture_tr_language_doc($case['doc'], $case['texts']);

		if (!empty($case['none'])) {
			pg_assert_same(null, $put, 'PHP: ' . $case['about']);
			continue;
		}

		pg_assert_same($case['words'], picture_server_words($put['doc']), 'PHP words: ' . $case['about']);
		pg_assert_same($case['fitted'], array_map('strval', array_keys($put['source'])), 'PHP fitted: ' . $case['about']);
		pg_assert_same(picture_server_rest($case['doc']), picture_server_rest($put['doc']), 'PHP rest kept: ' . $case['about']);

		foreach ($put['source'] as $id => $layer) {
			pg_assert_same(picture_server_words(array('layers' => array($layer))), picture_server_words(array('layers' => array_values(array_filter($case['doc']['layers'], function ($l) use ($id) {
				return (string) $l['id'] === (string) $id;
			})))), 'PHP source layer as it was: ' . $case['about']);
		}
	}

	// An object decode reads the same.
	$object = pg_picture_tr_language_doc(json_decode(json_encode($fixtures[0]['doc'])), $fixtures[0]['texts']);
	pg_assert_same($fixtures[0]['words'], picture_server_words($object['doc']), 'objects read as arrays');

	$r = picture_server_node('var list = require(F + "doc.json");'
		. 'console.log(JSON.stringify(list.map(function (c) {'
		. ' var put = M.languageDoc(c.doc, c.texts);'
		. ' return put ? { words: put.doc.layers.map(function (l) { return l.text || l.label || ""; }), fitted: Object.keys(put.source),'
		. '   kept: c.doc.layers.map(function (l) { return l.text || l.label || ""; }) } : null;'
		. '})));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($fixtures as $i => $case) {
		if (!empty($case['none'])) {
			pg_assert_same(null, $r[$i], 'script: ' . $case['about']);
			continue;
		}

		pg_assert_same($case['words'], $r[$i]['words'], 'script words: ' . $case['about']);
		pg_assert_same($case['fitted'], $r[$i]['fitted'], 'script fitted: ' . $case['about']);
		pg_assert_same(picture_server_words($case['doc']), $r[$i]['kept'], 'script source kept: ' . $case['about']);
	}
}

// Each changed text is fitted as the Design Canvas fits it: the size
// shrunk to the source's box, never under 70% (then marked), the aligned
// side kept across and the middle kept upright.
function test_picture_server_fitted()
{
	if (!function_exists('pg_picture_tr_language_fitted')) {
		pg_assert_true(false, 'pg_picture_tr_language_fitted() is missing');

		return;
	}

	// Ten pixels a letter at size 20, one line of 1.25.
	$layout = function ($l) {
		$size = (float) $l['size'];

		return ($l['text'] === 'no font') ? null : array('w' => round(mb_strlen($l['text']) * $size / 2, 2), 'h' => round($size * 1.25, 2));
	};
	$doc = array('v' => 1, 'artboard' => array('width' => 400, 'height' => 200), 'layers' => array(
		array('id' => 'c', 'type' => 'text', 'text' => 'Sale', 'size' => 20, 'align' => 'center', 'x' => 100, 'y' => 50, 'w' => 40, 'h' => 25),
		array('id' => 'r', 'type' => 'text', 'text' => 'Sale', 'size' => 20, 'align' => 'right', 'x' => 100, 'y' => 90, 'w' => 40, 'h' => 25),
		array('id' => 'l', 'type' => 'text', 'text' => 'Sale', 'size' => 20, 'x' => 100, 'y' => 130, 'w' => 40, 'h' => 25),
		array('id' => 'k', 'type' => 'text', 'text' => 'Kept', 'size' => 20, 'x' => 0, 'y' => 0, 'w' => 40, 'h' => 25),
	));
	$put = pg_picture_tr_language_doc($doc, array(
		array('id' => 'c', 'field' => 'text', 'text' => 'Angebot'),
		array('id' => 'r', 'field' => 'text', 'text' => 'Sonderangebot'),
		array('id' => 'l', 'field' => 'text', 'text' => 'Neu'),
	));
	$fitted = pg_picture_tr_language_fitted($put, $layout);
	$by = array();

	foreach ($fitted['doc']['layers'] as $layer) {
		$by[$layer['id']] = $layer;
	}

	// "Angebot": 70 wide at 20 for a box of 40: 0.5714 is under the floor.
	pg_assert_same(14.0, (float) $by['c']['size'], 'shrunk to the floor');
	pg_assert_same(49.0, (float) $by['c']['w'], 'its new width');
	pg_assert_same(95.5, (float) $by['c']['x'], 'centred text keeps its middle');
	pg_assert_same(53.75, (float) $by['c']['y'], 'the box\'s middle stays upright');
	pg_assert_same(1, $fitted['fit'], 'a text under the floor marks the picture');

	pg_assert_same(14.0, (float) $by['r']['size'], 'a longer one, also at the floor');
	pg_assert_same(140.0 - (float) $by['r']['w'], (float) $by['r']['x'], 'right aligned text keeps its right side');

	pg_assert_same(20.0, (float) $by['l']['size'], 'a shorter text keeps its size');
	pg_assert_same(100.0, (float) $by['l']['x'], 'left aligned text keeps its left side');
	pg_assert_same(30.0, (float) $by['l']['w'], 'its box is its new width');
	pg_assert_same($doc['layers'][3], $by['k'], 'an untouched layer is left as it was');

	$one = pg_picture_tr_language_doc($doc, array(array('id' => 'l', 'field' => 'text', 'text' => 'Neue')));
	pg_assert_same(0, pg_picture_tr_language_fitted($one, $layout)['fit'], 'every text fits: no mark');

	$lost = pg_picture_tr_language_doc($doc, array(array('id' => 'l', 'field' => 'text', 'text' => 'no font')));
	pg_assert_same(null, pg_picture_tr_language_fitted($lost, $layout), 'a text the server cannot lay out leaves the picture');
}

// What the server draws whole is planned with the language's words fitted;
// a sticker, an effect, a font it has no file of, a picture layer, a photo
// and a text not translated are left to a panel.
function test_picture_server_plan()
{
	if (!function_exists('pg_picture_tr_server_plan')) {
		pg_assert_true(false, 'pg_picture_tr_server_plan() is missing');

		return;
	}

	$can = picture_server_can();
	$texts = array(array('id' => 't1', 'field' => 'text', 'text' => 'Sonderangebot'));
	$plain = pg_picture_tr_server_plan(picture_server_doc(), $texts, $can);

	pg_assert_same('', $plain['error'], 'a plain document is drawn');
	pg_assert_same(array('rect', 'text'), array_column($plain['plan']['items'], 'kind'), 'its layers');
	pg_assert_same('Sonderangebot', $plain['plan']['items'][1]['text'], 'in the language\'s words');
	pg_assert_true($plain['plan']['items'][1]['size'] < 60, 'the longer words are made smaller');
	pg_assert_true($plain['plan']['items'][1]['size'] >= 42, 'never under 70%');
	pg_assert_same(400, $plain['plan']['width'], 'the artboard\'s size');

	$short = pg_picture_tr_server_plan(json_decode(json_encode(picture_server_doc())), array(array('id' => 't1', 'field' => 'text', 'text' => 'Saldo')), $can);
	pg_assert_same('', $short['error'], 'objects read as arrays');
	pg_assert_same(0, $short['fit'], 'a text that fits leaves no mark');

	$sticker = array('id' => 's1', 'type' => 'sticker', 'kind' => 'burst', 'label' => 'New', 'x' => 10, 'y' => 10, 'w' => 60, 'h' => 60);
	pg_assert_same('kind', pg_picture_tr_server_plan(picture_server_doc(array($sticker)), $texts, $can)['error'], 'a sticker waits for a panel');

	$shadow = picture_server_doc();
	$shadow['layers'][1]['shadow'] = array('color' => '#000000', 'blur' => 0.1, 'dx' => 0.05, 'dy' => 0.05);
	pg_assert_same('text', pg_picture_tr_server_plan($shadow, $texts, $can)['error'], 'an effect waits for a panel');

	$font = picture_server_doc();
	$font['layers'][1]['font'] = 'g:No Such Family';
	pg_assert_same('font', pg_picture_tr_server_plan($font, $texts, $can)['error'], 'a font the server has no file of waits for a panel');

	pg_assert_same('font', pg_picture_tr_server_plan(picture_server_doc(), array(array('id' => 't1', 'field' => 'text', 'text' => 'Sale →')), $can)['error'],
		'a letter the font lacks waits for a panel');

	$image = array('id' => 'i1', 'type' => 'image', 'src' => '/banner.png', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100);
	pg_assert_same('picture', pg_picture_tr_server_plan(picture_server_doc(array($image)), $texts, $can)['error'], 'a picture layer waits for a panel');

	$photo = array('v' => 1, 'source' => array('fileId' => 3, 'width' => 800, 'height' => 600, 'base' => 2), 'geometry' => array('rot' => 0),
		'layers' => array(array('id' => 't1', 'type' => 'text', 'text' => 'Sale', 'font' => 'g:Roboto', 'size' => 40, 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 50)));
	pg_assert_same('kind', pg_picture_tr_server_plan($photo, $texts, $can)['error'], 'a photo waits for a panel');

	pg_assert_same('untranslated', pg_picture_tr_server_plan(picture_server_doc(), array(array('id' => 't1', 'field' => 'text', 'text' => null)), $can)['error'],
		'a text not translated is not drawn');
	pg_assert_same('text', pg_picture_tr_server_plan(picture_server_doc(), $texts, array('text' => false) + $can)['error'], 'a server that draws no text');
}

// The run: nothing waiting clears the mark; the rows are drawn one after
// another while a drawing's time is left in the budget; once something is
// drawn the queue's query is asked again and clears the mark when nothing
// is left; a row the server leaves keeps the mark for a panel.
function test_picture_server_run_budget_and_mark()
{
	if (!function_exists('pg_picture_tr_draw_due_with')) {
		pg_assert_true(false, 'pg_picture_tr_draw_due_with() is missing');

		return;
	}

	$mark = tempnam(sys_get_temp_dir(), 'pgpicmark');
	$rows = array();

	for ($i = 1; $i <= 6; $i++) {
		$rows[] = array('file_id' => $i, 'language' => 'de', 'state' => 0, 'name' => 'banner' . $i . '.png', 'folder' => 1);
	}

	// Nothing waiting: the mark is cleared, nothing is drawn.
	pg_picture_tr_mark_set($mark);
	$asked = 0;
	$summary = pg_picture_tr_draw_due_with(array('de'), 20, function ($sql) use (&$asked) {
		$asked++;

		return array();
	}, function () {
		pg_assert_true(false, 'nothing is drawn');

		return '';
	}, $mark);

	pg_assert_same(1, $asked, 'one query');
	pg_assert_same(0, $summary['drawn'], 'nothing drawn');
	pg_assert_false(pg_picture_tr_marked($mark), 'the mark is cleared');

	// Six rows of five seconds each in a budget of twenty: a row starts only
	// while one drawing's time is left.
	pg_picture_tr_mark_set($mark);
	$now = 1000.0;
	$drawn = array();
	$sqls = array();
	$summary = pg_picture_tr_draw_due_with(array('de'), 20, function ($sql) use (&$sqls, $rows, &$drawn) {
		$sqls[] = $sql;

		// The second query, after the drawing: the rows not drawn are left.
		return (count($sqls) === 1) ? $rows : array_slice($rows, count($drawn));
	}, function ($row, $until) use (&$now, &$drawn) {
		pg_assert_true($until > $now, 'each drawing gets its own time');
		$now += 5;
		$drawn[] = $row['file_id'];

		return '';
	}, $mark, function () use (&$now) {
		return $now;
	});

	$limit = pg_image_draw_limits()['seconds'];
	$expected = (int) floor((20 - $limit) / 5) + 1;

	pg_assert_same(range(1, $expected), $drawn, 'drawn in order while the budget lasts');
	pg_assert_same($expected, $summary['drawn'], 'counted');
	pg_assert_true($summary['stopped'], 'the budget ended first');
	pg_assert_same(2, count($sqls), 'the queue is asked again after a drawing');
	pg_assert_contains('ft.state IN (0, 2)', $sqls[0], 'the queue\'s own query');
	pg_assert_true(pg_picture_tr_marked($mark), 'rows left keep the mark');

	// Every row drawn: the second query finds nothing and clears the mark.
	pg_picture_tr_mark_set($mark);
	$calls = 0;
	$summary = pg_picture_tr_draw_due_with(array('de'), 60, function ($sql) use (&$calls, $rows) {
		$calls++;

		return ($calls === 1) ? array_slice($rows, 0, 2) : array();
	}, function () {
		return '';
	}, $mark);

	pg_assert_same(2, $summary['drawn'], 'both drawn');
	pg_assert_false($summary['stopped'], 'inside the budget');
	pg_assert_false(pg_picture_tr_marked($mark), 'nothing left: the mark is cleared');

	// A row the server cannot draw whole is left to a panel, with the mark.
	pg_picture_tr_mark_set($mark);
	$calls = 0;
	$summary = pg_picture_tr_draw_due_with(array('de'), 60, function ($sql) use (&$calls, $rows) {
		$calls++;

		return array_slice($rows, 0, 2);
	}, function ($row) {
		return ($row['file_id'] === 1) ? 'kind' : '';
	}, $mark);

	pg_assert_same(array(1, 1), array($summary['drawn'], $summary['left']), 'one drawn, one left');
	pg_assert_true(pg_picture_tr_marked($mark), 'a row left keeps the mark');

	// A failed query tells nothing: the mark stays and nothing is drawn.
	$summary = pg_picture_tr_draw_due_with(array('de'), 60, function () {
		return false;
	}, function () {
		pg_assert_true(false, 'nothing is drawn');

		return '';
	}, $mark);

	pg_assert_same(0, $summary['rows'], 'no rows');
	pg_assert_true(pg_picture_tr_marked($mark), 'the mark stays');

	@unlink($mark);
}

// The run itself does nothing while the mark is clear: no query, no probe.
function test_picture_server_run_needs_the_mark()
{
	if (!function_exists('pg_picture_tr_draw_due')) {
		pg_assert_true(false, 'pg_picture_tr_draw_due() is missing');

		return;
	}

	$body = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/picture_server_draw.php');
	$start = strpos($body, "\nfunction pg_picture_tr_draw_due(");
	$body = ($start === false) ? '' : substr($body, $start, 1600);
	$marked = strpos($body, 'pg_picture_tr_marked()');
	$can = strpos($body, 'pg_image_draw_can()');

	pg_assert_true(($marked !== false) && ($can !== false) && ($marked < $can), 'the mark is read before anything else');
	pg_assert_contains("empty(\$can['text'])", $body, 'only where the server draws text');
	pg_assert_contains('pg_picture_tr_draw_due_with(', $body, 'the queue over every folder');
}

// System Status shows the Canvas Drawing row also for a site with a target
// language, with words for the pictures.
function test_picture_server_system_status_row()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/system_status.php');
	$start = strpos($source, '// 🎨 Canvas drawing');
	$end = ($start === false) ? false : strpos($source, '$group = ', $start);
	$block = (($start === false) || ($end === false)) ? '' : substr($source, $start, $end - $start);

	pg_assert_true(strpos($block, 'pg_tr_target_languages()') !== false, 'a target language shows the row');

	// The row's sentences are picked from a list, so the $makeIcon() literal
	// check does not see them: each one is a Turkish key here.
	require_once(__DIR__ . '/helpers/language_map.php');
	$map = pg_test_language_map('tr');
	$sentences = 0;

	foreach (token_get_all('<?php ' . $block) as $token) {
		if (!is_array($token) || ($token[0] !== T_CONSTANT_ENCAPSED_STRING) || (strpos($token[1], ' draw') === false)) {
			continue;
		}

		$sentence = stripcslashes(substr($token[1], 1, -1));
		$sentences++;
		pg_assert_true(isset($map[$sentence]), 'a Turkish key for "' . $sentence . '"');
	}

	pg_assert_same(9, $sentences, 'three states, each for agents, pictures or both');
}

// Where Imagick is loaded: a plain document is drawn at the artboard's size.
function test_picture_server_paint_with_imagick()
{
	if (!class_exists('Imagick') || !function_exists('pg_picture_tr_server_plan')) {
		pg_assert_true(true, 'Imagick is not loaded here');

		return;
	}

	$drawn = pg_picture_tr_server_plan(picture_server_doc(), array(array('id' => 't1', 'field' => 'text', 'text' => 'Sonderangebot')), picture_server_can());
	$png = pg_image_draw_paint($drawn['plan']);
	$size = getimagesizefromstring($png);

	pg_assert_same(array(400, 200, IMAGETYPE_PNG), array($size[0], $size[1], $size[2]), 'the artboard\'s size');
}
