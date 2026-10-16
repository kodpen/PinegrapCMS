<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SEO screen's save module (includes/settings/seo.save.php): a
 * site-wide JSON-LD that is not valid JSON is refused the way every other
 * field the operator has to correct is, before anything is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The field is marked, so the dialog answers status "invalid" with the
// sentence; output_error() answered it with a 500 after the IndexNow file had
// been written and the old one deleted.
function test_settings_seo_save_jsonld_marked_before_writes()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/settings/seo.save.php');

	$mark = strpos($source, "mark_error('custom_jsonld'");

	pg_assert_true($mark !== false, 'invalid JSON-LD marks its field');
	pg_assert_false(strpos($source, 'output_error(') !== false, 'the module does not stop the request');

	foreach (array('@unlink(', '@fopen(', 'db("DELETE', 'db("UPDATE config') as $write) {
		$at = strpos($source, $write);

		pg_assert_true(($at !== false) && ($mark < $at), 'marked before ' . $write);
	}
}
