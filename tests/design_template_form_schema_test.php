<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the structured data settings a design's export carries in its
 * page forms (includes/fn/design_templates_custom.php): the BlogPosting
 * author as a template form key (pg_dtc_schema_author_key(): organization,
 * field:<name>, nothing for the contact) and the schema type, which comes
 * with the editor's form settings (pg_cf_load_page_form_settings()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_template_schema_author_key()
{
	$names = array(12 => 'byline', 13 => 'title');

	pg_assert_same('', pg_dtc_schema_author_key('contact', $names), 'the contact is the default and is left out');
	pg_assert_same('', pg_dtc_schema_author_key('', $names), 'a row older than the setting');
	pg_assert_same('organization', pg_dtc_schema_author_key('organization', $names), 'the site');
	pg_assert_same('field:byline', pg_dtc_schema_author_key('field:12', $names), 'a field by its name, which Publish turns into the new id');
	pg_assert_same('', pg_dtc_schema_author_key('field:99', $names), 'a field the form no longer has');
	pg_assert_same('', pg_dtc_schema_author_key('field:12', array(12 => ' ')), 'a field without a name');

	// What the export writes is what Publish reads back.
	pg_assert_same('field:12', pg_cf_template_schema_author(pg_dtc_schema_author_key('field:12', $names), array('byline' => 12)));
	pg_assert_same('organization', pg_cf_template_schema_author(pg_dtc_schema_author_key('organization', $names), array()));
}

function test_design_template_export_carries_the_form_schema_settings()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/design_templates_custom.php');
	pg_assert_contains("\$author = pg_dtc_form_schema_author((int)\$p['page_id']);", $source, 'the export reads the author of each page form');
	pg_assert_contains("if (\$author !== '') \$fs['schema_author'] = \$author;", $source, 'and writes it as a form key');

	$custom_form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');
	pg_assert_contains("'schema_type'          => pg_record_schema_type(\$row['schema_type'] ?? ''),", $custom_form, 'the schema type travels with the editor settings the export reads');
}
