<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the BlogPosting author of a record page in includes/fn/seo.php:
 * pg_submitted_form_author_name() names the sending account's contact and
 * never its login username, and pg_build_blogposting_jsonld() prints no
 * person when that name is blank.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The lines of a function, to read which columns it queries.
function seo_author_name_test_function_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A login username is half of a credential: the author name comes from the
// contact only, so an account without a contact name gives '' and the
// structured data names the site instead.
function test_seo_author_name_never_reads_the_username()
{
	$source = seo_author_name_test_function_source('pg_submitted_form_author_name');

	pg_assert_contains('FROM contacts', $source, 'the contact is read');
	pg_assert_false(strpos($source, 'user_username') !== false, 'the login username is not read');
	pg_assert_false(preg_match('/\bFROM\s+user\b/i', $source) === 1, 'the user table is not queried');
}

// A blank author gives no Person: the site's Organization (when the site has
// a title) or no author at all.
function test_seo_author_name_blank_author_is_not_a_person()
{
	foreach (array('', '   ', null) as $author) {
		$data = pg_build_blogposting_jsonld(array('headline' => 'Post', 'url' => 'https://example.com/post', 'author' => $author));
		$type = isset($data['author']['@type']) ? $data['author']['@type'] : '';
		pg_assert_false($type === 'Person', 'blank author ' . var_export($author, true));
	}

	$data = pg_build_blogposting_jsonld(array('headline' => 'Post', 'url' => 'https://example.com/post', 'author' => ' Ada Lovelace '));
	pg_assert_same(array('@type' => 'Person', 'name' => 'Ada Lovelace'), $data['author'], 'a contact name is the person');
}

// The form's stored choice: contact (the rule above), organization, or a
// field of the form by id. Anything else, '' from a row older than the
// column included, reads as contact.
function test_seo_author_choice_is_read_from_the_stored_word()
{
	$contact = array('source' => 'contact', 'field_id' => 0);

	pg_assert_same($contact, pg_blogposting_author_choice('contact'), 'contact');
	pg_assert_same($contact, pg_blogposting_author_choice(''), 'nothing stored');
	pg_assert_same($contact, pg_blogposting_author_choice(null), 'null');
	pg_assert_same($contact, pg_blogposting_author_choice('admin'), 'an unknown word');
	pg_assert_same($contact, pg_blogposting_author_choice('field:'), 'a field without an id');
	pg_assert_same($contact, pg_blogposting_author_choice('field:0'), 'a field id of zero');
	pg_assert_same($contact, pg_blogposting_author_choice('field:abc'), 'a field id that is no number');
	pg_assert_same(array('source' => 'organization', 'field_id' => 0), pg_blogposting_author_choice(' organization '), 'the site');
	pg_assert_same(array('source' => 'field', 'field_id' => 12), pg_blogposting_author_choice('field:12'), 'a field');
}

// What the settings screen stores for a posted choice, and that the stored
// word reads back as the same choice.
function test_seo_author_setting_value_round_trips()
{
	pg_assert_same('contact', pg_blogposting_author_setting_value('contact', 0), 'contact');
	pg_assert_same('contact', pg_blogposting_author_setting_value('', 5), 'nothing posted');
	pg_assert_same('contact', pg_blogposting_author_setting_value('person', 5), 'an unknown word');
	pg_assert_same('contact', pg_blogposting_author_setting_value('field', 0), 'a field choice without a field');
	pg_assert_same('organization', pg_blogposting_author_setting_value('organization', 5), 'the site, the field id dropped');
	pg_assert_same('field:5', pg_blogposting_author_setting_value('field', '5'), 'a field');

	foreach (array('contact', 'organization', 'field:5') as $stored) {
		$choice = pg_blogposting_author_choice($stored);
		pg_assert_same($stored, pg_blogposting_author_setting_value($choice['source'], $choice['field_id']), 'round trip of ' . $stored);
	}
}

// The name the BlogPosting prints under each choice; '' names the site.
function test_seo_author_name_follows_the_form_choice()
{
	pg_assert_same('Ada Lovelace', pg_blogposting_author_name_for('contact', ' Ada Lovelace ', 'Kodpen Team'), 'contact: the contact name, the field is not read');
	pg_assert_same('Ada Lovelace', pg_blogposting_author_name_for('', 'Ada Lovelace', 'Kodpen Team'), "nothing stored: today's rule");
	pg_assert_same('Ada Lovelace', pg_blogposting_author_name_for('nonsense', 'Ada Lovelace', 'Kodpen Team'), "an unknown word: today's rule");
	pg_assert_same('', pg_blogposting_author_name_for('contact', '', 'Kodpen Team'), 'contact without a name: the site');
	pg_assert_same('', pg_blogposting_author_name_for('organization', 'admin', 'Kodpen Team'), 'organization: no person, whatever the contact is called');
	pg_assert_same('Kodpen Team', pg_blogposting_author_name_for('field:3', 'admin', ' Kodpen Team '), 'a field: its value');
	pg_assert_same('Kodpen & Team', pg_blogposting_author_name_for('field:3', 'admin', "<p>Kodpen &amp;\n Team</p>"), 'a rich text value: its text on one line');
	pg_assert_same('admin', pg_blogposting_author_name_for('field:3', 'admin', ''), 'a field left blank: the contact');
	pg_assert_same('', pg_blogposting_author_name_for('field:3', '', '<br />'), 'a field left blank and no contact name: the site');
}

// A person for a name; the site as an Organization with its address
// otherwise; nothing when the site has no title either.
function test_seo_author_node_names_the_site_with_its_address()
{
	pg_assert_same(array('@type' => 'Person', 'name' => 'Ada Lovelace'), pg_blogposting_author_node(' Ada Lovelace ', 'Kodpen', 'https://kodpen.com/'), 'a name is a person');
	pg_assert_same(array('@type' => 'Organization', 'name' => 'Kodpen', 'url' => 'https://kodpen.com/'), pg_blogposting_author_node('', 'Kodpen', 'https://kodpen.com/'), 'no name: the site with its address');
	pg_assert_same(array('@type' => 'Organization', 'name' => 'Kodpen'), pg_blogposting_author_node('', 'Kodpen', ' '), 'no address known');
	pg_assert_same(null, pg_blogposting_author_node('', '', 'https://kodpen.com/'), 'no name and no site title: no author');
}

// The BlogPosting takes its author from that node, and the record's name
// is read through the form's choice behind the column's readiness check.
function test_seo_author_name_reads_the_form_choice_behind_its_readiness_check()
{
	pg_assert_contains('pg_blogposting_author_node(', seo_author_name_test_function_source('pg_build_blogposting_jsonld'), 'the builder uses the node');

	$source = seo_author_name_test_function_source('pg_submitted_form_author_name');

	pg_assert_contains('pg_blogposting_author_setting_ready()', $source, 'the column is probed before it is read');
	pg_assert_contains('pg_blogposting_author_name_for(', $source, 'the name follows the choice');
	pg_assert_contains("pg_schema_has('custom_form_pages', 'schema_author')", seo_author_name_test_function_source('pg_blogposting_author_setting_ready'), 'the readiness check names the column');
}
