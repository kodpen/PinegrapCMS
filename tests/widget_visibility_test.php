<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visibility spans a system widget resolves after it has drawn
 * its tree (_pg_sw_resolve_visibility(), includes/fn/widgets.php): a span is
 * kept or cut by its flag, and the spans inside a kept span are resolved too,
 * so a button only a record's editors see stays hidden inside the part shown
 * when the record is found.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A span is kept when its flag is on and cut when it is off.
function test_widget_visibility_flat_spans()
{
	$html = '<!--pg-vis:record_found:1-->A<!--/pg-vis:1--><!--pg-vis:record_not_found:2-->B<!--/pg-vis:2-->';
	pg_assert_same('A', _pg_sw_resolve_visibility($html, array('record_found' => true, 'record_not_found' => false)));
}

// A span inside a kept span follows its own flag.
function test_widget_visibility_nested_span_cut()
{
	$html = '<!--pg-vis:record_found:1--><p>Record</p><!--pg-vis:can_edit:2--><a>Edit</a><!--/pg-vis:2--><!--/pg-vis:1-->';
	pg_assert_same('<p>Record</p>', _pg_sw_resolve_visibility($html, array('record_found' => true, 'can_edit' => false)));
	pg_assert_same('<p>Record</p><a>Edit</a>', _pg_sw_resolve_visibility($html, array('record_found' => true, 'can_edit' => true)));
}

// A cut span takes the spans inside it along.
function test_widget_visibility_nested_span_inside_cut()
{
	$html = 'x<!--pg-vis:record_found:1--><!--pg-vis:can_edit:2--><a>Edit</a><!--/pg-vis:2--><!--/pg-vis:1-->y';
	pg_assert_same('xy', _pg_sw_resolve_visibility($html, array('record_found' => false, 'can_edit' => true)));
}

// Markup without spans comes back as it was.
function test_widget_visibility_no_spans()
{
	pg_assert_same('<p>Plain</p>', _pg_sw_resolve_visibility('<p>Plain</p>', array()));
}
