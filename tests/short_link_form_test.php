<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short link form shared by the create and the edit screens
 * (includes/forms/short_link_form.php): which option rows a destination
 * opens with, the column a destination is saved in, and that both screens
 * use the shared form and the token, and the rule a URL destination is
 * checked with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');

function short_link_form_test_shown($destination_type)
{
	return array_keys(array_filter(pg_short_link_form_row_styles($destination_type), function ($style) {
		return $style === '';
	}));
}

function test_short_link_form_row_styles()
{
	pg_assert_same(array('page_id', 'tracking_code'), short_link_form_test_shown('page'), 'page');
	pg_assert_same(array('catalog_page_id', 'or', 'catalog_detail_page_id', 'product_group_id', 'tracking_code'), short_link_form_test_shown('product_group'), 'product group');
	pg_assert_same(array('catalog_detail_page_id', 'product_id', 'tracking_code'), short_link_form_test_shown('product'), 'product');
	pg_assert_same(array('url'), short_link_form_test_shown('url'), 'url');
	pg_assert_same(array('file'), short_link_form_test_shown('file'), 'file');
	pg_assert_same(array(), short_link_form_test_shown(''), 'none chosen yet');
	pg_assert_same(array(), short_link_form_test_shown('workspace_guest'), 'a type the form does not offer');

	$styles = pg_short_link_form_row_styles('url');

	pg_assert_same(9, count($styles), 'every row has a style');
	pg_assert_same(' style="display: none"', $styles['page_id'], 'hidden rows');
}

function test_short_link_form_destination_column()
{
	pg_assert_same(array('product_group_id', 'product_group_id'), pg_short_link_form_destination_column('product_group'), 'product group');
	pg_assert_same(array('product_id', 'product_id'), pg_short_link_form_destination_column('product'), 'product');
	pg_assert_same(array('url', 'url'), pg_short_link_form_destination_column('url'), 'url');
	pg_assert_same(array('file_id', 'file'), pg_short_link_form_destination_column('file'), 'file: the field is named file');
	pg_assert_same(null, pg_short_link_form_destination_column('page'), 'a page keeps its target in page_id');
	pg_assert_same(null, pg_short_link_form_destination_column(''), 'none');
}

function test_short_link_form_screens_use_the_shared_form()
{
	foreach (array('add_short_link.php', 'edit_short_link.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');", $source, $file . ': the form');
		pg_assert_contains('pg_short_link_form_cards($liveform, ', $source, $file . ': the cards');
		pg_assert_contains('pg_short_link_form_validate_destination($liveform, ', $source, $file . ': the destination check');
		pg_assert_contains('pg_short_link_form_answer($liveform, ', $source, $file . ': the answer check');
		pg_assert_contains('get_token_field()', $source, $file . ': the token field');
		pg_assert_contains('validate_token_field();', $source, $file . ': the token check');
	}

	// A one-time link has no name to check; an edited link may keep its own.
	pg_assert_contains("if (\$link_mode !== 'once') {\n        \$name = pg_short_link_form_validate_name(\$liveform, null);", (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_short_link.php'), 'create');
	pg_assert_contains("pg_short_link_form_validate_name(\$liveform, \$liveform->get_field_value('id'))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_short_link.php'), 'edit');
}

/**
 * A stand-in for the live form: the destination check reads values and
 * marks errors only.
 */
class short_link_form_test_form
{
	public $values;
	public $errors = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($field)
	{
		return isset($this->values[$field]) ? $this->values[$field] : '';
	}

	public function check_field_error($field)
	{
		return isset($this->errors[$field]);
	}

	public function mark_error($field, $message = '')
	{
		$this->errors[$field] = $message;
	}

	public function validate_required_field($field, $message = '')
	{
		if (trim((string) $this->get_field_value($field)) === '') {
			$this->mark_error($field, $message);
		}
	}
}

function short_link_form_test_url_error($url)
{
	$form = new short_link_form_test_form(array('destination_type' => 'url', 'url' => $url));

	pg_short_link_form_validate_destination($form, null);

	return isset($form->errors['url']) ? $form->errors['url'] : '';
}

function test_short_link_form_url_destination_uses_the_shared_rule()
{
	pg_assert_same('', short_link_form_test_url_error('https://example.com/a?b=1'), 'web address');
	pg_assert_same('', short_link_form_test_url_error('/yeni/sayfa'), 'path on this site');

	foreach (array('mailto:info@example.com', 'tel:+905551112233', 'ftp://example.com/x', 'javascript:alert(1)', 'data:text/html,x', '//cdn.example.com/x', '/\\other.com') as $url) {
		pg_assert_same(pg_short_link_url_problem($url), short_link_form_test_url_error($url), $url);
		pg_assert_true(short_link_form_test_url_error($url) !== '', $url . ' refused');
	}

	pg_assert_true(short_link_form_test_url_error('') !== '', 'empty');
	pg_assert_true(short_link_form_test_url_error('/a b') !== '', 'a space inside');
	pg_assert_true(short_link_form_test_url_error(' /a') !== '', 'a leading space is never stored');
	pg_assert_true(short_link_form_test_url_error("/a\t") !== '', 'a trailing tab is never stored');
	pg_assert_true(short_link_form_test_url_error('/' . str_repeat('a', pg_short_link_url_max())) !== '', 'longer than the column');
}
