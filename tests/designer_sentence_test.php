<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a sentence with values (includes/fn/designer.php): a semantic
 * element whose props._sentence is on draws its words as one text and its
 * N-th child where {var:N} stands, on one line; the editor's twin
 * (_sdSentenceLayout() in style_designer.js) lays out the same pieces; a
 * translation that drops a value is refused; an element without the prop
 * draws as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');

// Text => child count => the layout the server and the editor give.
function _sentence_vectors()
{
	return array(
		array('Rated {var:1} out of 5', 1, array('Rated ', 1, ' out of 5')),
		array('5 üzerinden {var:1} puan', 1, array('5 üzerinden ', 1, ' puan')),
		array('{var:1}', 1, array(1)),
		array('{var:2} before {var:1}', 2, array(2, ' before ', 1)),
		// A child once, at its first placeholder.
		array('{var:1} and {var:1}', 1, array(1, ' and ')),
		// A placeholder without a child draws nothing.
		array('Ends {var:3}.', 1, array('Ends ', '.', ' ', 1)),
		// A child no placeholder names follows after a space.
		array('Words only', 2, array('Words only', ' ', 1, ' ', 2)),
		array('', 1, array(1)),
		array('', 0, array()),
		// Not placeholders: no number, zero, three digits, spaces.
		array('{var:0} {var} {var:100} { var:1 }', 0, array('{var:0} {var} {var:100} { var:1 }')),
	);
}

function test_designer_sentence_layout()
{
	foreach (_sentence_vectors() as $v) {
		pg_assert_same($v[2], pg_sentence_layout($v[0], $v[1]), json_encode(array($v[0], $v[1])));
	}
}

// A paragraph with the value as a span of its own, as the panel builds it.
function _sentence_node($text, $children, $sentence = true)
{
	$props = array('tag' => 'p', 'cssClass' => 'small', 'text' => $text);
	if ($sentence) $props['_sentence'] = true;
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

function _sentence_value($token, $class = 'fw-bold')
{
	return array('type' => 'semantic', 'props' => array('tag' => 'span', 'cssClass' => $class, 'text' => '', '_bindings' => array('text' => $token)), 'children' => array());
}

function test_designer_sentence_renders_inline()
{
	$html = _render_tree_node(_sentence_node('Rated {var:1} out of 5 & more', array(_sentence_value('__comment_rating'))), 1, 0);
	pg_assert_same('    <p class="small">Rated <span class="fw-bold" data-pg-bind="text:comment_rating">^^__comment_rating^^</span> out of 5 &amp; more</p>' . "\n", $html);

	$two = _render_tree_node(_sentence_node('{var:2} / {var:1}', array(_sentence_value('a', 'x'), _sentence_value('b', 'y'))), 0, 0);
	pg_assert_same('<p class="small"><span class="y" data-pg-bind="text:b">^^b^^</span> / <span class="x" data-pg-bind="text:a">^^a^^</span></p>' . "\n", $two);

	// A value with children of its own keeps them.
	$nested = _sentence_value('a');
	$nested['children'] = array(array('type' => 'semantic', 'props' => array('tag' => 'i', 'cssClass' => 'bi bi-star'), 'children' => array()));
	$out = _render_tree_node(_sentence_node('By {var:1}', array($nested)), 0, 0);
	pg_assert_true(strpos($out, '<p class="small">By <span class="fw-bold" data-pg-bind="text:a">^^a^^') === 0, $out);
	pg_assert_contains('<i class="bi bi-star"></i>', $out);
	pg_assert_true(substr($out, -5) === "</p>\n", $out);
}

// Without the prop, the same tree draws as it always did: the words first,
// then each child on its own line, the placeholder as written.
function test_designer_sentence_without_the_prop_is_unchanged()
{
	$html = _render_tree_node(_sentence_node('Rated {var:1} out of 5', array(_sentence_value('__comment_rating')), false), 0, 0);
	pg_assert_same('<p class="small">Rated {var:1} out of 5' . "\n"
		. '    <span class="fw-bold" data-pg-bind="text:comment_rating">^^__comment_rating^^</span>' . "\n"
		. '</p>' . "\n", $html);
}

// The translation check keeps the value in the sentence.
function test_designer_sentence_translation_keeps_the_value()
{
	pg_assert_true(pg_tr_translatable('Rated {var:1} out of 5'), 'the words are a text');
	pg_assert_false(pg_tr_translatable('{var:1}'), 'a value alone is not');
	$ok = pg_tr_result_check('Rated {var:1} out of 5', '5 üzerinden {var:1} puan', 'text');
	pg_assert_true($ok['ok'], 'moved value');
	$dropped = pg_tr_result_check('Rated {var:1} out of 5', '5 üzerinden puan', 'text');
	pg_assert_same('placeholders', $dropped['error'], 'dropped value');
}

// The editor lays out the same pieces (run under Node when it is there).
function test_designer_sentence_editor_twin()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$fn = '';
	foreach (array('_sdIsSentence', '_sdSentenceLayout') as $name) {
		$from = strpos($js, "\n    function " . $name . '(');
		pg_assert_true($from !== false, $name . ' is found');
		$to = strpos($js, "\n    }\n", $from);
		$fn .= substr($js, $from, $to + 7 - $from);
	}
	$vectors = array();
	foreach (_sentence_vectors() as $v) $vectors[] = array($v[0], $v[1]);
	$script = $fn . "\nvar v = " . json_encode($vectors) . ";\n"
		. "console.log(JSON.stringify({ layouts: v.map(function (x) { return _sdSentenceLayout(x[0], x[1]); }),"
		. " is: [_sdIsSentence({ type: 'semantic', props: { _sentence: true } }), _sdIsSentence({ type: 'content', props: { _sentence: true } }), _sdIsSentence({ type: 'semantic', props: {} })] }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgsen');
	if ($file === false) return;
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$answer = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($answer), implode("\n", $out));
	foreach (_sentence_vectors() as $i => $v) {
		pg_assert_same($v[2], $answer['layouts'][$i], 'editor: ' . json_encode(array($v[0], $v[1])));
	}
	pg_assert_same(array(true, false, false), $answer['is']);
}
