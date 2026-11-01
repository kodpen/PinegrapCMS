<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A design template's comments label is a singular noun: the platform
 * lowercases it into sentences, puts it after a count and into "Add
 * {var:1}". The shared "Reply" key is the Reply button, a verb in Turkish
 * ("Yanıtla"), so a label takes the lower-case noun key "reply" ("yanıt").
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

function test_design_template_comment_labels_no_verb_reply_label()
{
	$paths = glob(dirname(__DIR__) . '/pinegrap/includes/design_templates/*.php');

	pg_assert_true(count($paths) > 0, 'the design templates are found');

	foreach ($paths as $path) {
		$source = (string) file_get_contents($path);

		pg_assert_true(!preg_match("/'label'\\s*=>\\s*lang\\(\\s*'Reply'\\s*\\)/", $source), basename($path) . ': no comments label on the verb key Reply');
	}
}

function test_design_template_comment_labels_turkish_noun_and_verb()
{
	$map = pg_test_language_map('tr');

	pg_assert_true(isset($map['reply']) && $map['reply'] === 'yanıt', 'Turkish reply is the noun yanıt');
	pg_assert_true(isset($map['Reply']) && $map['Reply'] === 'Yanıtla', 'Turkish Reply stays the verb Yanıtla');
}

// Every template's conversation page counts its answers as replies: the
// Comments widget under it says "Write a reply" and "Send reply", so a
// "Message" label would print "1 Message" above a reply form.
function test_design_template_comment_labels_conversation_page_takes_reply()
{
	$found = 0;
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
		$tpl = _pg_tpl_include($file);
		foreach (isset($tpl['pages']) && is_array($tpl['pages']) ? $tpl['pages'] : array() as $page) {
			if (isset($page['key']) && $page['key'] === 'conversation' && isset($page['comments']['label'])) {
				$found++;
				pg_assert_same(lang('reply'), $page['comments']['label'], basename($file) . ': conversation page label is reply');
			}
		}
	}
	pg_assert_true($found > 0, 'conversation pages are found');
}

// The texts of a tree, in document order.
function _test_comment_labels_texts($node, &$out)
{
	if (!is_array($node)) {
		return;
	}
	if (isset($node['props']['text']) && is_string($node['props']['text'])) {
		$out[] = $node['props']['text'];
	}
	foreach (!empty($node['children']) && is_array($node['children']) ? $node['children'] : array() as $child) {
		_test_comment_labels_texts($child, $out);
	}
}

// The newsroom's "Live now" list opens the public story, so its count is
// the readers' comments (letters in the magazine), never "messages": that
// word stays for the desk conversation counted on the other desk lists.
function test_design_template_comment_labels_live_now_count_names_reader_comments()
{
	$cases = array(
		'pinegrap-news' => array('live' => lang('reader comments'), 'desk' => 'staff_all'),
		'pinegrap-magazine' => array('live' => lang('letters'), 'desk' => 'staff_all'),
	);
	foreach ($cases as $id => $case) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');

		$live = array();
		_test_comment_labels_texts($tpl['widgets']['staff_published']['tree'], $live);
		pg_assert_true(!in_array(lang('messages'), $live, true), $id . ': Live now does not call reader comments messages');
		pg_assert_true(in_array($case['live'], $live, true), $id . ': Live now names the reader comments');

		$desk = array();
		_test_comment_labels_texts($tpl['widgets'][$case['desk']]['tree'], $desk);
		pg_assert_true(in_array(lang('messages'), $desk, true), $id . ': a desk list still counts messages');
	}
}

// The article form sends the byline as the structured data's author
// (schema_author field:byline), so no staff or contributor note may say
// search engines get the account's name instead.
function test_design_template_comment_labels_no_stale_author_note()
{
	foreach (array('pinegrap-news', 'pinegrap-magazine') as $id) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
		$json = json_encode(array($tpl['pages'], $tpl['widgets'], isset($tpl['shared']) ? $tpl['shared'] : array()));
		foreach (array('account that sent it', 'name on your account', 'name on their account') as $stale) {
			pg_assert_same(false, strpos($json, $stale), $id . ': no note says "' . $stale . '"');
		}
	}
}
