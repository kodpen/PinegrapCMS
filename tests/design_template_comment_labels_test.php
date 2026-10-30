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
