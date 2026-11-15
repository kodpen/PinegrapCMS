<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Comments widget's slots opened in the editor: the page
 * links as a list of the paging family (source paging.pages, shared with
 * the search results), drawn by the widget with its links ending at the
 * comments' anchor and the built-in pagination left out, the same links as
 * that pagination prints; and the editor's twins (the list offered in the
 * widget, the section's convert action, the true samples of the CAPTCHA,
 * star rating input and publish options).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page links as the editor's starter builds them (_sdPagingListStarter()):
// previous, the list of the links, next.
function comments_tree_slots_test_pager()
{
	$sem = function ($tag, $cls, $props = array(), $kids = array()) {
		return array('type' => 'semantic', 'props' => array('tag' => $tag, 'cssClass' => $cls) + $props, 'children' => $kids);
	};
	$when = function ($token) {
		return array('_bindings' => array('show_if' => $token), '_bindParams' => array('show_if' => array('op' => 'not_empty')));
	};
	$step = function ($flag, $token, $text) use ($sem) {
		return $sem('li', 'page-item', array('_bindings' => array('eo_visible_if' => $flag)), array(
			$sem('a', 'page-link', array('text' => $text, 'href' => '#', '_bindings' => array('href' => $token))),
		));
	};

	return $sem('nav', 'mt-3', array(), array(
		$sem('ul', 'pagination pagination-sm justify-content-center mb-0', array(), array(
			$step('has_prev_page', '__prev_page_url', 'prev'),
			array('type' => 'loop_area', 'props' => array('source' => 'paging.pages'), 'children' => array(
				$sem('li', 'page-item', $when('__pager_url'), array(
					$sem('a', 'page-link', array('text' => '1', 'href' => '#', '_bindings' => array('href' => '__pager_url', 'text' => '__pager_number'))),
				)),
				$sem('li', 'page-item active', $when('__pager_is_current'), array(
					$sem('span', 'page-link', array('text' => '2', '_bindings' => array('text' => '__pager_number'))),
				)),
				$sem('li', 'page-item disabled', $when('__pager_is_gap'), array(
					$sem('span', 'page-link', array('text' => '…')),
				)),
			)),
			$step('has_next_page', '__next_page_url', 'next'),
		)),
	));
}

// The default layout with its comment_pagination section swapped for $with.
function comments_tree_slots_test_tree($with)
{
	$tree = comments_widget_test_stamped_tree();
	$swap = function (&$node) use (&$swap, $with) {
		if (!is_array($node) || empty($node['children'])) {
			return false;
		}
		foreach ($node['children'] as $i => $child) {
			if (isset($child['props']['_bindings']['section']) && $child['props']['_bindings']['section'] === 'comment_pagination') {
				$node['children'][$i] = $with;
				return true;
			}
			if ($swap($node['children'][$i])) {
				return true;
			}
		}
		return false;
	};
	pg_assert_true($swap($tree), 'the default layout has a comment_pagination section');
	return $tree;
}

// Draws $tree in a widget scope of the comments kind, as the page does.
function comments_tree_slots_test_draw($tree, $data)
{
	// A scope of its own each time: the lists a widget drew are kept for
	// the request under its id.
	static $sid = 960;
	$scope = pg_sw_finish_open(array('sid' => ++$sid, 'kind' => 'comments', 'email' => false));
	try {
		$html = pg_sw_finish_widget_html(pg_comments_widget_draw($tree, $data), $scope);
	} finally {
		pg_sw_finish_close();
	}
	return $html;
}

function test_comments_tree_slots_paging_offered()
{
	pg_assert_true(isset(pg_sw_kind_lists('comments')['paging.pages']), 'the comments widget offers the page links');
	pg_assert_same(array('paging.pages'), array_keys(pg_sw_kind_lists('comments')), 'and no other list');
	pg_assert_same(array(), pg_sw_kind_lists('comments_recent'), 'a list of recent comments has no pages');
}

// The links of the list and of the tokens end at the comments' anchor when
// asked, as the comments' pagination does; without a fragment as before.
function test_comments_tree_slots_paging_fragment()
{
	$saved_get = $_GET;
	$_GET = array('r' => '4', 'comments_page' => '2');

	$rows = pg_sw_list_paging_rows('comments_page', 2, 3, 'software_comments');
	pg_assert_same('?r=4&comments_page=1#software_comments', $rows['paging.pages'][2][0]['__pager_url']);
	pg_assert_same('', $rows['paging.pages'][2][1]['__pager_url'], 'the page shown has no address');
	pg_assert_same('?r=4&comments_page=3', pg_sw_list_paging_rows('comments_page', 2, 3)['paging.pages'][2][2]['__pager_url'], 'no fragment: as before');

	$paging = pg_sw_list_paging('comments_page', 2, 3, 'software_comments');
	pg_assert_same('?r=4&amp;comments_page=1#software_comments', $paging['tokens']['__prev_page_url'], 'escaped once');
	pg_assert_same('?r=4&amp;comments_page=3', pg_sw_list_paging('comments_page', 2, 3)['tokens']['__next_page_url']);

	$widget = pg_comments_widget_paging(2, 3);
	pg_assert_same(array('tokens', 'flags', 'rows'), array_keys($widget));
	pg_assert_same('?r=4&amp;comments_page=3#software_comments', $widget['tokens']['__next_page_url']);
	pg_assert_same(array('has_prev_page' => true, 'has_next_page' => true, 'has_more_pages' => true), $widget['flags']);
	pg_assert_same($rows, $widget['rows']);
	$_GET = $saved_get;
}

// The page links built from the tree print the links the built-in
// pagination printed, and the built-in one is left out.
function test_comments_tree_slots_tree_prints_the_classic_links()
{
	$saved_get = $_GET;
	$_GET = array('r' => '4', 'comments_page' => '2');

	comments_widget_test_with_session(function () {
		$classic_html = pg_sw_pagination_html('comments_page', 2, 3, 'software_comments');
		$data = comments_widget_test_draw_data();
		$data['sections'] = array('comment_pagination' => $classic_html);
		$data['paging'] = pg_comments_widget_paging(2, 3);

		$html = comments_tree_slots_test_draw(comments_tree_slots_test_tree(comments_tree_slots_test_pager()), $data);
		pg_assert_false(strpos($html, 'pg-sw-pagination'), 'no built-in pagination beside the designed links');
		pg_assert_false(strpos($html, '<!--pg-'), 'no marker left');
		pg_assert_false(strpos($html, '^^'), 'no token left');

		preg_match_all('#<a class="page-link" href="([^"]*)"#', $classic_html, $classic);
		preg_match_all('#<a class="page-link" href="([^"]*)"#', $html, $tree);
		pg_assert_same(array('?r=4&amp;comments_page=1#software_comments', '?r=4&amp;comments_page=1#software_comments',
			'?r=4&amp;comments_page=3#software_comments', '?r=4&amp;comments_page=3#software_comments'), $classic[1], 'the classic links');
		pg_assert_same($classic[1], $tree[1], 'the tree prints the same links in the same order');
		pg_assert_true((bool) preg_match('#<li class="page-item active">\s*<span class="page-link"[^>]*>2</span>#', $html), 'the page shown, not linked');
		pg_assert_true(strpos($html, 'id="c-7"') < strpos($html, 'class="pagination'), 'after the comments');

		// The first page: no previous link, as the classic one links none.
		$data['paging'] = pg_comments_widget_paging(1, 3);
		$first = comments_tree_slots_test_draw(comments_tree_slots_test_tree(comments_tree_slots_test_pager()), $data);
		pg_assert_false(strpos($first, '>prev<'), 'no previous page on the first');
		pg_assert_contains('>next<', $first);

		// One page: the classic section prints nothing, nor do the links.
		$data['sections'] = array('comment_pagination' => '');
		$data['paging'] = pg_comments_widget_paging(1, 1);
		$one = comments_tree_slots_test_draw(comments_tree_slots_test_tree(comments_tree_slots_test_pager()), $data);
		pg_assert_false(strpos($one, 'page-link'), 'no page link on a single page');
	});
	$_GET = $saved_get;
}

// A design without the list prints what it printed: the section filled,
// or the links after the comments when the design placed no section.
function test_comments_tree_slots_classic_output_kept()
{
	comments_widget_test_with_session(function () {
		$nav = '<nav class="pg-sw-pagination">Pages</nav>';
		$data = comments_widget_test_draw_data();
		$data['sections'] = array('comment_pagination' => $nav);
		$before = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data);
		$data['paging'] = pg_comments_widget_paging(1, 2);
		$after = comments_tree_slots_test_draw(comments_widget_test_stamped_tree(), $data);
		pg_assert_same($before, $after, 'the section as before');
		pg_assert_same(1, substr_count($after, $nav), 'once');

		$bare = array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'article', '_bindings' => array('text' => '__comment_name')), 'children' => array()),
			)),
		));
		pg_assert_contains('</article>' . $nav, comments_tree_slots_test_draw($bare, $data), 'the fallback after the comments');

		// A designed list is never the comments' loop.
		pg_assert_false(_pg_comments_tree_has_loop(array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'loop_area', 'props' => array('source' => 'paging.pages'), 'children' => array()),
		))), 'a page list is not the comments list');
	});
}

// The editor offers the list in the widget, turns either pagination section
// into it, and draws the server-built parts of the form as true samples.
function test_comments_tree_slots_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("kinds: ['form_list_view', 'catalog_listing', 'search_results', 'comments'],", $js);
	pg_assert_contains('function _sdSectionOpensAsList(kind, section) {', $js);
	pg_assert_contains('function _sdSectionToList(node, bs) {', $js);
	pg_assert_contains('function _sdCommentsSectionSample(section, doc) {', $js);
	pg_assert_contains("['__prev_page_url',             _sdT('Previous page link (ends at the comments)')]", $js);
	pg_assert_contains("['has_prev_page',            _sdT('A previous page of comments exists')]", $js);
	pg_assert_contains("if (p.type === 'loop_area' && !_sdListIsSourced(p)) place.inComment = true;", $js);
}

// The star rating input as review sites draw it: five stars side by side, no
// number shown, filled up to the chosen one and the rest in outline; still a
// radio group named rating with the ids comment_rating_1..5, no inline style.
function test_comments_tree_slots_rating_stars()
{
	$html = pg_comments_rating_input_html(3);
	pg_assert_contains('role="radiogroup" aria-label="Rating" data-pg-comment-stars', $html, 'a labelled radio group');
	pg_assert_same(5, substr_count($html, 'type="radio" class="btn-check" name="rating"'), 'five radios named rating');
	for ($i = 1; $i <= 5; $i++) {
		pg_assert_contains('id="comment_rating_' . $i . '" value="' . $i . '"', $html, 'radio ' . $i);
	}
	pg_assert_contains('value="3" autocomplete="off" checked', $html, 'three chosen');
	pg_assert_same(5, substr_count($html, 'data-pg-star-on'), 'a filled star per radio');
	pg_assert_same(5, substr_count($html, 'data-pg-star-off'), 'an outline star per radio');
	pg_assert_same(3, substr_count($html, '<span class="text-warning" data-pg-star-on>'), 'filled up to the chosen one');
	pg_assert_same(2, substr_count($html, '<span class="text-body-tertiary" data-pg-star-off>'), 'the rest in outline');
	pg_assert_false((bool) preg_match('#<span aria-hidden="true">\d</span>#', $html), 'no number shown');
	pg_assert_contains('<span class="visually-hidden">3 out of 5</span>', $html, 'each star said in words');
	pg_assert_false(strpos($html, 'style='), 'no inline style');
	pg_assert_same(5, substr_count(pg_comments_rating_input_html(null), 'data-pg-star-on>'), 'five filled by default');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	pg_assert_contains("form.find('[data-pg-comment-stars]')", $js, 'the stars refill on a change');
	pg_assert_contains('[data-pg-comment-stars]', (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js'), 'and in the minified twin');
}
