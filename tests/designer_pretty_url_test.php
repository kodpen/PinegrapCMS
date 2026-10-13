<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pretty record addresses of a form shown by the visual
 * editor's widgets: the address a record gets (pg_sw_record_url()), the
 * segment a page was asked for (pg_sw_request_segment()), a link bound to
 * the address name opening the record (pg_sw_record_href_bindings()) and the
 * list widget's type names (pg_sw_form_list_types()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// -- the record's address -----------------------------------------------------

// Pretty URLs on and the record has a name: the page, then the name.
function test_designer_pretty_url_named_record()
{
	pg_assert_same('/blog-yazisi/ilk-yazim', pg_sw_record_url('blog-yazisi', 'P8TDKZMZGV', 'ilk-yazim', true, '/'));
}

// Pretty URLs off: the reference code, whatever the name.
function test_designer_pretty_url_off_uses_reference()
{
	pg_assert_same('/blog-yazisi?r=P8TDKZMZGV', pg_sw_record_url('blog-yazisi', 'P8TDKZMZGV', 'ilk-yazim', false, '/'));
}

// Pretty URLs on but the record has no name (no Title field when it was
// sent): the reference code keeps it reachable.
function test_designer_pretty_url_unnamed_record_uses_reference()
{
	pg_assert_same('/blog-yazisi?r=P8TDKZMZGV', pg_sw_record_url('blog-yazisi', 'P8TDKZMZGV', '  ', true, '/'));
}

// The site may live under a folder; the base comes first.
function test_designer_pretty_url_keeps_base()
{
	pg_assert_same('/site/blog-yazisi/ilk-yazim', pg_sw_record_url('blog-yazisi', 'P8TDKZMZGV', 'ilk-yazim', true, '/site/'));
}

// A name with characters that need it is encoded, the reference code too.
function test_designer_pretty_url_encodes_parts()
{
	pg_assert_same('/blog/a%20b', pg_sw_record_url('blog', 'X', 'a b', true, '/'));
	pg_assert_same('/blog?r=A%26B', pg_sw_record_url('blog', 'A&B', '', true, '/'));
}

// -- the segment asked for ----------------------------------------------------

// What follows the page name, up to the next slash.
function test_designer_pretty_url_segment()
{
	pg_assert_same('ilk-yazim', pg_sw_request_segment('blog-yazisi/ilk-yazim'));
	pg_assert_same('ilk-yazim', pg_sw_request_segment('blog-yazisi/ilk-yazim/fazlasi'));
}

// A bare page name has no segment.
function test_designer_pretty_url_no_segment()
{
	pg_assert_same('', pg_sw_request_segment('blog-yazisi'));
	pg_assert_same('', pg_sw_request_segment('blog-yazisi/'));
}

// The segment is decoded the way the catalog widgets decode theirs.
function test_designer_pretty_url_segment_decoded()
{
	pg_assert_same('çay bahçesi', pg_sw_request_segment('blog/%C3%A7ay%20bah%C3%A7esi'));
}

// A segment longer than an address name can be is not looked up.
function test_designer_pretty_url_segment_too_long()
{
	pg_assert_same('', pg_sw_request_segment('blog/' . str_repeat('a', 256)));
}

// -- a link bound to the address name -----------------------------------------

// The href bound to Address Name opens the record, at any depth.
function test_designer_pretty_url_href_binding_rewritten()
{
	$tree = array(
		'type' => 'div',
		'children' => array(
			array('type' => 'a', 'props' => array('_bindings' => array('href' => 'address_name'))),
			array('type' => 'div', 'children' => array(
				array('type' => 'a', 'props' => array('_bindings' => array('href' => ' Address_Name '))),
			)),
		),
	);
	pg_sw_record_href_bindings($tree);
	pg_assert_same('form_item_view', $tree['children'][0]['props']['_bindings']['href']);
	pg_assert_same('form_item_view', $tree['children'][1]['children'][0]['props']['_bindings']['href']);
}

// The name printed as text stays the name; other links stay as they are.
function test_designer_pretty_url_other_bindings_untouched()
{
	$tree = array(
		'type' => 'div',
		'props' => array('_bindings' => array('text' => 'address_name', 'href' => 'website')),
	);
	pg_sw_record_href_bindings($tree);
	pg_assert_same('address_name', $tree['props']['_bindings']['text']);
	pg_assert_same('website', $tree['props']['_bindings']['href']);
}

// -- list widget types --------------------------------------------------------

// The list widget under every name it has been saved with.
function test_designer_pretty_url_list_types()
{
	pg_assert_same(array('form_list_view', 'submitted_forms_list', 'form_list', 'blog_list'), pg_sw_form_list_types());
}
