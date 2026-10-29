<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comment stars default part (includes/fn/widgets_parts.php):
 * a layout binding the old ^^__comment_stars_html^^ shows the visitor the
 * same stars, words and classes as the span the renderer built before, an
 * e-mail the same characters; the editor builds the same part.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The stars as the comments widget drew them before the part: one span,
// the rating said once, five drawings without white space between them.
function _parts_cs_old_html($n, $email = false)
{
	$label = h(lang(array('string' => 'Rated {var:1} out of 5', 'vars' => $n)));
	if ($email) {
		return '<span class="pg-comment-stars" role="img" aria-label="' . $label . '">'
			. str_repeat('&#9733;', $n) . str_repeat('&#9734;', 5 - $n) . '</span>';
	}
	$html = '<span class="pg-comment-stars" role="img" aria-label="' . $label . '">';
	for ($i = 1; $i <= 5; $i++) {
		$html .= _pg_comments_star_svg($i <= $n);
	}
	return $html . '</span>';
}

// The host the starters bound: a div with the rating flag.
function _parts_cs_tree($host = array())
{
	return array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'semantic', 'props' => $host + array(
		'tag' => 'div', 'cssClass' => 'text-warning mb-1', 'customName' => 'Stars',
		'_bindings' => array('text' => '__comment_stars_html', 'eo_visible_if' => 'comment_has_rating'),
	), 'children' => array())));
}

// $tree given its parts at the widget door, drawn and finished as one
// comment with $rating (0: none) in a comments widget.
function _parts_cs_render($tree, $rating, $email = false)
{
	$tree = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'comments', array(), $email), true);
	$scope = pg_sw_finish_open(array('sid' => 45, 'kind' => 'comments', 'email' => $email));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		$values = array(
			'__comment_rating' => $rating ? (string)$rating : '',
			'__comment_rating_label' => pg_comments_rating_label($rating),
			'__comment_stars_text' => pg_comments_stars_text($rating),
		);
		$html = pg_sw_sweep_tokens(pg_sw_fill_longest($html, pg_comments_widget_escape($values)));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function _parts_cs_visible($html)
{
	$html = str_replace('<', ' <', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

function _parts_cs_classes($html)
{
	preg_match_all('/\sclass="([^"]*)"/', $html, $m);
	return array_values(array_filter(array_map('trim', $m[1]), 'strlen'));
}

function test_widget_parts_comment_stars_match_the_old_span()
{
	for ($n = 1; $n <= 5; $n++) {
		$new = _parts_cs_render(_parts_cs_tree(), $n);
		$old = '<div class="text-warning mb-1">' . _parts_cs_old_html($n) . '</div>';
		pg_assert_same(_parts_cs_visible($old), _parts_cs_visible($new), $n . ': same words (none)');
		pg_assert_same(_parts_cs_classes($old), _parts_cs_classes($new), $n . ': same classes in the same order');
		pg_assert_contains('<span class="pg-comment-stars" role="img" aria-label="' . h(pg_comments_rating_label($n)) . '">', $new, $n . ': the rating said once');
		pg_assert_same($n, substr_count($new, 'bi-star-fill'), $n . ': filled');
		pg_assert_same(5, substr_count($new, 'aria-hidden="true"'), $n . ': five drawings, each hidden');
		pg_assert_true(strpos($new, '^^') === false && strpos($new, '<!--pg-') === false && strpos($new, '{{') === false, $n . ': no token or marker left');
	}
	pg_assert_same('', _parts_cs_visible(_parts_cs_render(_parts_cs_tree(array('_bindings' => array('text' => '__comment_stars_html'))), 0)), 'no rating: nothing');
	pg_assert_true(strpos(_parts_cs_render(_parts_cs_tree(array('_bindings' => array('text' => '__comment_stars_html'))), 0), 'pg-comment-stars') === false, 'no rating: no span');

	// A literal token, as older layouts wrote it.
	$literal = _parts_cs_render(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'cssClass' => 'small', 'text' => '^^__comment_stars_html^^'), 'children' => array()),
	)), 3);
	pg_assert_same(array('small', 'pg-comment-stars', 'bi bi-star-fill', 'bi bi-star-fill', 'bi bi-star-fill', 'bi bi-star', 'bi bi-star'), _parts_cs_classes($literal), 'a literal token: the paragraph holds the stars');
}

function test_widget_parts_comment_stars_in_an_email()
{
	for ($n = 1; $n <= 5; $n++) {
		$mail = _parts_cs_render(_parts_cs_tree(), $n, true);
		$old = '<div class="text-warning mb-1">' . _parts_cs_old_html($n, true) . '</div>';
		pg_assert_same(_parts_cs_visible($old), _parts_cs_visible($mail), $n . ': the same characters');
		pg_assert_same(_parts_cs_classes($old), _parts_cs_classes($mail), $n . ': same classes');
		pg_assert_true(strpos($mail, '<svg') === false, $n . ': no drawing');
		pg_assert_contains('aria-label="' . h(pg_comments_rating_label($n)) . '"', $mail, $n . ': the rating said once');
	}
	// The design's own child of the span stays.
	$tree = json_decode(pg_sw_default_parts_apply(json_encode(_parts_cs_tree()), 'comments'), true);
	$tree['children'][0]['children'][0]['children'][] = array('type' => 'semantic', 'props' => array('tag' => 'small', 'text' => 'own'), 'children' => array());
	pg_assert_contains("\u{2605}\u{2605}\u{2606}\u{2606}\u{2606} own", _parts_cs_visible(_parts_cs_render($tree, 2, true)), 'own text after the stars');
	pg_assert_same(json_encode($tree), pg_sw_default_parts_apply(json_encode($tree), 'comments'), 'a page leaves the part as it is');
}

function test_widget_parts_comment_stars_css()
{
	pg_assert_same('', pg_sw_parts_css('<div>no stars</div>'), 'no stars, no stylesheet');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_carousel_layout.css');
	pg_assert_contains('.pg-comment-stars{display:inline-flex}', $css, 'the stars side by side');
}

// The starters and the forum template build the part the door gives.
function test_widget_parts_comment_stars_starters()
{
	$part = pg_sw_part_comment_stars();
	$found = 0;
	$walk = function ($node) use (&$walk, &$found, $part) {
		if (!is_array($node)) return;
		if (isset($node['props']['cssClass']) && $node['props']['cssClass'] === 'pg-comment-stars') {
			pg_assert_same($part[0], $node, 'the part');
			$found++;
		}
		foreach (isset($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) $walk($child);
	};
	$walk(pg_comments_default_tree());
	$walk(pg_comments_recent_default_tree());
	pg_assert_same(2, $found, 'both starters');
	$tpl = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-forum.php');
	pg_assert_same(3, substr_count($tpl, 'pg_sw_part_comment_stars()'), 'the forum template');
	pg_assert_true(strpos($tpl, '__comment_stars_html') === false, 'no old token there');
}

// The editor's part (style_designer.js) is the server's.
function test_widget_parts_comment_stars_editor_twin()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$cut = function ($from, $to) use ($js) {
		$a = strpos($js, $from);
		pg_assert_true($a !== false, 'found: ' . trim($from));
		$b = strpos($js, $to, $a);
		return substr($js, $a, $b + strlen($to) - $a);
	};
	$src = $cut("\n    var SW_DEFAULT_PARTS = {", "\n    };\n");
	foreach (array('_sdDefaultParts', '_sdPartTokenOf', '_sdPartHost', '_sdPartEl', '_sdPartWhen', '_sdPartCommentStars', '_sdPartCommentStar') as $fn) {
		$src .= $cut("\n    function " . $fn . '(', "\n    }\n");
	}
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		_parts_cs_tree()['children'][0],
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'lead' => true, 'text' => '^^__comment_stars_html^^'), 'children' => array()),
	));
	$stubs = "var _n = 0; function gid() { return 'n' + (++_n); }\nfunction _sdT(s) { return s; }\n"
		. "function createNode(type, props, children) { return { _id: gid(), type: type, props: props || {}, children: children || [] }; }\n";
	$script = 'var tree = ' . json_encode($tree) . ";\n"
		. "var recent = JSON.parse(JSON.stringify(tree));\n"
		. "var changed = _sdDefaultParts(tree, 'comments', {}) && _sdDefaultParts(recent, 'comments_recent', {});\n"
		. "console.log(JSON.stringify({ changed: changed, again: _sdDefaultParts(tree, 'comments', null), tree: tree, recent: recent, starter: _sdPartCommentStars() }));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgpartscs');
	if ($file === false) return;
	file_put_contents($file, $stubs . $src . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);
	pg_assert_true($answer['changed'], 'the editor gave the parts');
	pg_assert_false($answer['again'], 'a tree with its part has nothing left to give');

	$server = json_decode(pg_sw_default_parts_apply(json_encode($tree), 'comments'), true);
	pg_assert_same(_parts_cs_norm($server), _parts_cs_norm($answer['tree']), 'the same tree');
	pg_assert_same(_parts_cs_norm($server), _parts_cs_norm($answer['recent']), 'a list of recent comments too');
	pg_assert_same(array_map('_parts_cs_norm', pg_sw_part_comment_stars()), array_map('_parts_cs_norm', $answer['starter']), 'the starters\' part');
}

// A tree without what only one side writes: the editor's ids and names,
// and the sample text under a text binding.
function _parts_cs_norm($node)
{
	if (!is_array($node)) return $node;
	unset($node['_id']);
	if (isset($node['props']) && is_array($node['props'])) {
		unset($node['props']['customName']);
		if (isset($node['props']['_bindings']['text'])) unset($node['props']['text']);
		ksort($node['props']);
		foreach ($node['props'] as $k => $v) {
			if (is_array($v)) ksort($node['props'][$k]);
		}
		if (!$node['props']) $node['props'] = array();
	}
	if (isset($node['children']) && is_array($node['children'])) {
		$node['children'] = array_map('_parts_cs_norm', $node['children']);
	}
	ksort($node);
	return $node;
}
