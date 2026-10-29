<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rating of a product in the catalog widgets
 * (includes/fn/widgets_catalog.php): the __rating_avg and __rating_count
 * tokens, the columns they are read from before and after the review columns
 * exist, the has_rating / no_rating flags of a listing card and of the
 * product page, and what the visual designer offers for them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function of a file: from its declaration to the closing
// brace at the start of a line.
function _catalog_rating_function_source($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}", $start);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

// The SELECT statements of a piece of source that read the given column.
function _catalog_rating_selects($source, $column)
{
	preg_match_all('/"SELECT\b.*?"/s', $source, $m);

	return array_values(array_filter($m[0], function ($select) use ($column) {
		return strpos($select, $column) !== false;
	}));
}

// No verified, starred review: both tokens are empty, so a bound element
// collapses and no rating is shown without a review behind it.
function test_catalog_rating_values_without_reviews()
{
	$empty = array('__rating_avg' => '', '__rating_count' => '');

	pg_assert_same($empty, _pg_catalog_rating_values('0.00', '0'), 'as read from the products row');
	pg_assert_same($empty, _pg_catalog_rating_values(0, 0), 'numbers');
	pg_assert_same($empty, _pg_catalog_rating_values('4.50', '0'), 'an average without a review behind it');
	pg_assert_same($empty, _pg_catalog_rating_values(null, null), 'no columns at all');
}

// The average with one decimal in the site's number format, the count as a
// plain number.
function test_catalog_rating_values_one_decimal()
{
	$separators = pg_number_separators();
	$decimal = $separators['decimal'];

	pg_assert_same(array('__rating_avg' => '4' . $decimal . '3', '__rating_count' => '3'), _pg_catalog_rating_values(4.333, 3));
	pg_assert_same(array('__rating_avg' => '4' . $decimal . '3', '__rating_count' => '3'), _pg_catalog_rating_values('4.33', '3'), 'strings from MySQL');
	pg_assert_same(array('__rating_avg' => '5' . $decimal . '0', '__rating_count' => '1'), _pg_catalog_rating_values('5.00', '1'), 'a whole number keeps its decimal');
	pg_assert_same('4' . $decimal . '8', _pg_catalog_rating_values('4.75', '12')['__rating_avg'], 'rounded, not cut');
}

// The columns exist from 11.0.14 on; before that the query reads zeros, so it
// does not fail and the listing does not turn into "No products found".
function test_catalog_rating_columns()
{
	pg_assert_same('products.rating_avg AS rating_avg, products.rating_count AS rating_count', _pg_catalog_rating_columns(true));
	pg_assert_same('0 AS rating_avg, 0 AS rating_count', _pg_catalog_rating_columns(false));
	pg_assert_same('products.rating_avg AS product_rating_avg, products.rating_count AS product_rating_count', _pg_catalog_rating_columns(true, 'product_'));
	pg_assert_same('0 AS product_rating_avg, 0 AS product_rating_count', _pg_catalog_rating_columns(false, 'product_'));
}

// The listing's card is drawn once for each rating state: an element shown
// only with a rating is in the rated card alone, one shown only without it in
// the other, a group row (no rating state of its own) draws neither unless
// that leaves nothing visible (a token only in an attribute, or the
// add-to-cart button every group row loses, does not count), and a flag no
// catalog evaluates leaves its element drawn.
function test_catalog_rating_card_templates()
{
	$paragraph = function ($text, $flag) {
		$props = array('contentType' => 'paragraph', 'text' => $text);
		if ($flag !== '') $props['_bindings'] = array('eo_visible_if' => $flag);
		return array('type' => 'content', 'props' => $props, 'children' => array());
	};
	$card = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card'), 'children' => array(
		$paragraph('Always', ''),
		$paragraph('Stars', 'has_rating'),
		$paragraph('No reviews yet', 'no_rating'),
		$paragraph('Pasted', 'record_found'),
	));

	$templates = _pg_catalog_card_templates(array($card));

	pg_assert_contains('<p>Stars</p>', $templates['rated']);
	pg_assert_false(strpos($templates['rated'], 'No reviews yet') !== false, 'the rated card has no "no reviews" text');
	pg_assert_contains('<p>No reviews yet</p>', $templates['unrated']);
	pg_assert_false(strpos($templates['unrated'], 'Stars') !== false, 'the card without a rating has no stars');
	pg_assert_true(isset($templates['group']), 'a card for group rows');
	$group = isset($templates['group']) ? $templates['group'] : '';
	pg_assert_false(strpos($group, 'Stars') !== false, 'a group card has no stars');
	pg_assert_false(strpos($group, 'No reviews yet') !== false, 'a group card has no "no reviews" text');

	pg_assert_contains('pg-vis', $templates['marked'], 'the marked card keeps its markers for a bundle\'s states');
	unset($templates['marked']);
	foreach ($templates as $state => $html) {
		pg_assert_contains('<p>Always</p>', $html, $state . ': an element without a flag');
		pg_assert_contains('<p>Pasted</p>', $html, $state . ': a flag of another widget');
		pg_assert_false(strpos($html, 'pg-vis') !== false, $state . ': no marker is left');
	}

	// A card that exists only with a rating is empty for a product without one.
	$templates = _pg_catalog_card_templates(array($paragraph('Stars', 'has_rating')));

	pg_assert_same('<p>Stars</p>', $templates['rated']);
	pg_assert_same('', $templates['unrated']);
	pg_assert_same('', isset($templates['group']) ? $templates['group'] : null, 'nor for a group');

	// Two alternative cards side by side, one per rating state: nothing is left
	// without a flag, so a group row takes the card without a rating and the
	// child groups (categories) are still drawn.
	$col = function ($flag, $children) {
		return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col', '_bindings' => array('eo_visible_if' => $flag)), 'children' => $children);
	};
	$templates = _pg_catalog_card_templates(array(
		$col('has_rating', array($paragraph('Name', ''), $paragraph('Stars', ''))),
		$col('no_rating', array($paragraph('Name', ''), $paragraph('Be the first to review', ''))),
	));

	pg_assert_contains('<p>Stars</p>', $templates['rated']);
	pg_assert_false(strpos($templates['rated'], 'Be the first') !== false, 'the rated card has no "be the first" text');
	pg_assert_contains('<p>Be the first to review</p>', $templates['unrated']);
	pg_assert_false(strpos($templates['unrated'], 'Stars') !== false, 'the card without a rating has no stars');
	pg_assert_true($templates['group'] !== '', 'group rows still have a card');
	pg_assert_same($templates['unrated'], $templates['group'], 'group rows take the card without a rating');

	// The same two cards inside one column without a flag (the starter's
	// col > card shape): the column alone is left, with nothing a group row
	// can show, so group rows take the card without a rating too.
	$card_flagged = function ($flag, $children) {
		return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card', '_bindings' => array('eo_visible_if' => $flag)), 'children' => $children);
	};
	$alternatives = array(
		$card_flagged('has_rating', array($paragraph('^^__name^^', ''), $paragraph('Stars', ''))),
		$card_flagged('no_rating', array($paragraph('^^__name^^', ''), $paragraph('Be the first to review', ''))),
	);
	$wrappers = array(
		'a grid column' => array('type' => 'col', 'props' => array('xs' => '12', 'md' => '4', 'cssClass' => 'mb-4'), 'children' => $alternatives),
		'a plain div'   => array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col'), 'children' => $alternatives),
	);
	foreach ($wrappers as $label => $wrapper) {
		$templates = _pg_catalog_card_templates(array($wrapper));

		pg_assert_contains('<p>Stars</p>', $templates['rated'], $label);
		pg_assert_contains('<p>Be the first to review</p>', $templates['unrated'], $label);
		pg_assert_contains('^^__name^^', $templates['group'], $label . ': group rows draw a name');
		pg_assert_same($templates['unrated'], $templates['group'], $label . ': group rows take the card without a rating');
	}

	// A wrapper whose only token sits in an attribute (a card-as-link bound to
	// the detail page, a class naming the row type) shows nothing by itself,
	// so group rows take the card without a rating as well.
	$link = array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'd-block text-decoration-none', '_bindings' => array('href' => '__detail_url')), 'children' => $alternatives);
	$wrappers = array(
		'a link to the detail page' => array('type' => 'col', 'props' => array('xs' => '12', 'md' => '4'), 'children' => array($link)),
		'a row type class'          => array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col card-^^__row_type^^'), 'children' => $alternatives),
	);
	foreach ($wrappers as $label => $wrapper) {
		$templates = _pg_catalog_card_templates(array($wrapper));

		pg_assert_contains('^^__name^^', $templates['group'], $label . ': group rows draw a name');
		pg_assert_same($templates['unrated'], $templates['group'], $label . ': group rows take the card without a rating');
	}

	// Every group row loses the add-to-cart button, so a column that keeps
	// only that button beside the two alternative cards shows nothing on a
	// group row either.
	$atc = array('type' => 'component', 'props' => array(
		'componentType' => 'btn', 'btnElement' => 'button', 'btnType' => 'submit', 'text' => 'Add to Cart',
		'_attrs' => array(array('name' => 'data-pg-catalog-atc', 'value' => '1')),
	), 'children' => array());
	$wrapper = array('type' => 'col', 'props' => array('xs' => '12', 'md' => '4'), 'children' => array_merge($alternatives, array($atc)));
	$templates = _pg_catalog_card_templates(array($wrapper));

	pg_assert_contains('data-pg-catalog-atc="1"', $templates['unrated'], 'a product card keeps its add-to-cart button');
	pg_assert_same($templates['unrated'], $templates['group'], 'a column left with only the add-to-cart button takes the card without a rating');

	// An image left beside the two cards is seen, so the group card keeps it.
	$image = array('type' => 'content', 'props' => array('contentType' => 'image', 'src' => '^^__image_url^^'), 'children' => array());
	$wrapper = array('type' => 'col', 'props' => array('xs' => '12'), 'children' => array_merge(array($image), $alternatives));
	$templates = _pg_catalog_card_templates(array($wrapper));

	pg_assert_contains('<img', $templates['group'], 'the unflagged image stays');
	pg_assert_false(strpos($templates['group'], 'Be the first') !== false, 'a group card with an image of its own has no "be the first" text');

	// A wrapper that keeps a token in its text, or text of its own, is a card
	// for group rows.
	$wrapper = array('type' => 'col', 'props' => array('xs' => '12'), 'children' => array_merge(array($paragraph('^^__name^^', '')), $alternatives));
	$templates = _pg_catalog_card_templates(array($wrapper));

	pg_assert_contains('<p>^^__name^^</p>', $templates['group'], 'the unflagged name stays');
	pg_assert_false(strpos($templates['group'], 'Stars') !== false, 'a group card with a name of its own has no stars');
	pg_assert_false(strpos($templates['group'], 'Be the first') !== false, 'nor the "be the first" text');
}

// What a group card shows once both rating states are cut: text and media
// count, a token in an attribute and the body of a style block do not.
function test_catalog_html_shows()
{
	$seen = array(
		'<div class="col"><p>^^__name^^</p></div>',
		'<div>Categories</div>',
		'<div><img src="^^__image_url^^" alt=""></div>',
		'<span><svg viewBox="0 0 1 1"><path d="M0 0"/></svg></span>',
	);
	foreach ($seen as $html) {
		pg_assert_true(_pg_catalog_html_shows($html), $html);
	}

	$unseen = array(
		'',
		"<div class=\"col-12\">\n    \n</div>",
		'<a class="d-block" href="^^__detail_url^^" data-pg-bind="href:detail_url"> </a>',
		'<div class="col card-^^__row_type^^"></div>',
		'<div><style>.card{color:red}</style><!-- ^^__name^^ --></div>',
		'<div>&nbsp;</div>',
	);
	foreach ($unseen as $html) {
		pg_assert_false(_pg_catalog_html_shows($html), $html);
	}
}

// The listing reads the columns behind the readiness check, fills both tokens
// from the helper, gives each product row the card of its state and each
// group row the card with neither state.
function test_catalog_rating_listing_source()
{
	$listing = _catalog_rating_function_source('includes/fn/widgets_catalog.php', '_render_system_widget_catalog_listing');

	pg_assert_true($listing !== '', 'the listing renderer is found');
	pg_assert_contains('$rating_cols = _pg_catalog_rating_columns(pg_reviews_ready());', $listing);

	$selects = _catalog_rating_selects($listing, 'products.seo_score');

	pg_assert_same(1, count($selects), 'one product query');
	pg_assert_contains('$rating_cols', isset($selects[0]) ? $selects[0] : '', 'the product query reads the rating');

	pg_assert_contains("'__rating_avg'          => \$rating['__rating_avg'],", $listing);
	pg_assert_contains("'__rating_count'        => \$rating['__rating_count'],", $listing);
	pg_assert_contains("\$loop_template       = \$_pg_cards['unrated'];", $listing, 'the card without a rating');
	pg_assert_contains("\$rendered = _pg_catalog_card_for(\$_pg_cards, \$rating['__rating_count'] !== '', _pg_catalog_bundle_flags(\$bundle));", $listing, 'the card of the rating and bundle state');
	pg_assert_contains("if (trim(\$_pg_cards['marked']) === '') {", $listing, 'a card shown only with a rating is still a card');
	pg_assert_contains("\$loop_template_group = \$_pg_cards['group'];", $listing);
	pg_assert_contains("if (\$group_navigation !== 'none' && \$loop_template_group !== '') {", $listing, 'child groups need a group card');
	pg_assert_contains('$g_rendered = $loop_template_group;', $listing, 'a group row draws neither rating state');
	pg_assert_false(strpos($listing, '$g_rendered = $loop_template;') !== false, 'no group row takes the card without a rating');
	pg_assert_same(2, substr_count($listing, '_pg_catalog_card_templates('), 'with and without a loop area');
}

// The product page reads the columns in its three product queries and the
// variant query, fills the same keys found or not, passes the rating to the
// variant swap, and resolves the flags once the product is known (both
// states kept for the variant script when the variants differ in rating).
function test_catalog_rating_item_view_source()
{
	$view = _catalog_rating_function_source('includes/fn/widgets_catalog.php', '_render_system_widget_catalog_item_view');

	pg_assert_true($view !== '', 'the item view renderer is found');
	pg_assert_contains('$_civ_rating_cols = _pg_catalog_rating_columns(pg_reviews_ready());', $view);
	pg_assert_contains("\$_civ_var_rating_cols = _pg_catalog_rating_columns(pg_reviews_ready(), 'product_');", $view);

	$selects = _catalog_rating_selects($view, 'products.seo_score');

	pg_assert_same(3, count($selects), 'three product queries');

	foreach ($selects as $i => $select) {
		pg_assert_contains('$_civ_rating_cols', $select, 'product query ' . ($i + 1));
	}

	$variants = _catalog_rating_selects($view, 'AS product_cf4');

	pg_assert_same(1, count($variants), 'one variant query');
	pg_assert_contains('$_civ_var_rating_cols', isset($variants[0]) ? $variants[0] : '', 'the variant query reads the rating');

	// Found and not found carry the same keys.
	pg_assert_same(2, substr_count($view, "'__rating_avg'           => \$_civ_rating['__rating_avg'],"), 'both maps carry the average');
	pg_assert_same(2, substr_count($view, "'__rating_count'         => \$_civ_rating['__rating_count'],"), 'both maps carry the count');

	// The variant swap writes the fields it is given, by their bare names.
	pg_assert_contains("'rating_avg'      => \$_p_rating['__rating_avg'],", $view);
	pg_assert_contains("'rating_count'    => \$_p_rating['__rating_count'],", $view);

	// Marked before the tree is drawn, resolved once the product is read.
	$mark = strpos($view, "_pg_sw_mark_visibility(\$tree_decoded, array('has_rating', 'no_rating', 'can_notify', 'is_bundle', 'has_bundle_saving'));");
	$split = strpos($view, '$split = _split_widget_tree($tree_decoded);');
	$resolve = strpos($view, '$loop_template = _pg_sw_resolve_visibility($loop_template, $_civ_flags);');

	pg_assert_true($mark !== false && $split !== false && $mark < $split, 'marked before the split');
	pg_assert_true($resolve !== false && $resolve > strpos($view, '$_civ_var_rating_cols'), 'resolved after the product and its variants are read');
	pg_assert_contains("'has_rating' => \$_civ_rating['__rating_count'] !== '',", $view, 'a product with reviews has a rating');
	pg_assert_contains("'no_rating'  => \$_civ_rating['__rating_count'] === '',", $view, 'a product without reviews has none');
	pg_assert_contains('$static_html   = _pg_sw_resolve_visibility($static_html, $_civ_flags);', $view);

	// A variant group whose variants differ in rating keeps both states for
	// the variant script, on the card and the surround, and ships the rule
	// that keeps a hidden state hidden; the script switches them.
	pg_assert_contains('_pg_catalog_rating_states_differ($variant_data[\'products\'])', $view, 'only when the variants differ');
	pg_assert_contains('_pg_catalog_variant_flags_keep($loop_template, $_civ_flags, $_civ_switched)', $view);
	pg_assert_contains('_pg_catalog_variant_flags_keep($static_html, $_civ_flags, $_civ_switched)', $view);
	pg_assert_contains('[data-pg-variant-flag][hidden]{display:none!important}', $view);
	$keep = strpos($view, '_pg_catalog_variant_flags_keep($loop_template');
	pg_assert_true($keep !== false && $keep > strpos($view, '$variant_data = array('), 'decided once the variant payload is built');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	$apply = substr($js, (int) strpos($js, 'function applyVariant('));

	pg_assert_contains("querySelectorAll('[data-pg-variant-flag]')", $apply, 'the script switches the flagged elements');
	pg_assert_contains('p.rating_count', $apply, 'from the chosen variant\'s count');
}

// Whether a select group's variants differ in rating: only then does the
// product page keep both has_rating / no_rating states for the script.
function test_catalog_rating_states_differ()
{
	$rated = array('id' => 1, 'rating_avg' => '4.6', 'rating_count' => '12');
	$unrated = array('id' => 2, 'rating_avg' => '', 'rating_count' => '');

	pg_assert_true(_pg_catalog_rating_states_differ(array($rated, $unrated)), 'one with and one without');
	pg_assert_true(_pg_catalog_rating_states_differ(array($unrated, $unrated, $rated)), 'in any order');
	pg_assert_false(_pg_catalog_rating_states_differ(array($rated, $rated)), 'all with a rating');
	pg_assert_false(_pg_catalog_rating_states_differ(array($unrated, $unrated)), 'none with a rating');
	pg_assert_false(_pg_catalog_rating_states_differ(array($rated)), 'one variant');
	pg_assert_false(_pg_catalog_rating_states_differ(array()), 'no variants');
	pg_assert_true(_pg_catalog_rating_states_differ(array($rated, array('id' => 3))), 'a variant without the field has no rating');
}

// Each top-level element of a has_rating / no_rating span carries the flag in
// its own opening tag, with `hidden` for the state that is off; a run of text
// goes into a span of its own; comments and whitespace stay as they are. Null
// when the end of an element cannot be found.
function test_catalog_rating_flag_mark()
{
	$a = ' data-pg-variant-flag="has_rating"';

	pg_assert_same("\n  <div" . $a . " class=\"a\"><div>x</div></div>\n", _pg_catalog_variant_flag_mark("\n  <div class=\"a\"><div>x</div></div>\n", 'has_rating', false), 'nested tags of the same name');
	pg_assert_same('<P' . $a . ' hidden>x</p>', _pg_catalog_variant_flag_mark('<P>x</p>', 'has_rating', true), 'any letter case');
	pg_assert_same('<img' . $a . ' src="a.png" alt="">', _pg_catalog_variant_flag_mark('<img src="a.png" alt="">', 'has_rating', false), 'an element without a closing tag');
	pg_assert_same('<i' . $a . ' class="bi"/>', _pg_catalog_variant_flag_mark('<i class="bi"/>', 'has_rating', false), 'a self-closed tag');
	pg_assert_same('<span' . $a . ' title="a > b">x</span>', _pg_catalog_variant_flag_mark('<span title="a > b">x</span>', 'has_rating', false), 'a quoted value may hold ">"');
	pg_assert_same('<p' . $a . '><svg><path d="M0"/></svg></p>', _pg_catalog_variant_flag_mark('<p><svg><path d="M0"/></svg></p>', 'has_rating', false), '<path> is not <p>');

	// Several elements: each one carries the flag, the space between stays.
	pg_assert_same(
		'<i' . $a . ' hidden class="bi bi-star-fill"></i> <i' . $a . ' hidden class="bi bi-star"></i>',
		_pg_catalog_variant_flag_mark('<i class="bi bi-star-fill"></i> <i class="bi bi-star"></i>', 'has_rating', true),
		'two icons'
	);

	// Text has no tag to carry the flag: it goes into a span.
	pg_assert_same('<span data-pg-variant-flag="no_rating">Be the first</span>', _pg_catalog_variant_flag_mark('Be the first', 'no_rating', false), 'text');
	pg_assert_same(
		'<i' . $a . ' class="bi bi-star-fill"></i><span' . $a . '> 4,6 (a < b)' . "\n" . '</span>',
		_pg_catalog_variant_flag_mark('<i class="bi bi-star-fill"></i> 4,6 (a < b)' . "\n", 'has_rating', false),
		'an icon and text; a "<" that opens no tag is text'
	);

	// A comment stays as it is and the element inside the pair is marked.
	pg_assert_same(
		'<!--pg-active:begin--><a' . $a . ' href="/r">Reviews</a><!--pg-active:end-->',
		_pg_catalog_variant_flag_mark('<!--pg-active:begin--><a href="/r">Reviews</a><!--pg-active:end-->', 'has_rating', false),
		'comments around an element'
	);
	pg_assert_same('', _pg_catalog_variant_flag_mark('', 'has_rating', true), 'nothing');
	pg_assert_same("\n  ", _pg_catalog_variant_flag_mark("\n  ", 'has_rating', true), 'whitespace');

	// Markup that does not balance.
	pg_assert_same(null, _pg_catalog_variant_flag_mark('<div><div>a</div>', 'has_rating', false), 'not closed');
	pg_assert_same(null, _pg_catalog_variant_flag_mark('<p>a</p></div>', 'has_rating', false), 'a closing tag without an opening one');
	pg_assert_same(null, _pg_catalog_variant_flag_mark('a <!-- b', 'has_rating', false), 'a comment that does not end');
	pg_assert_same(null, _pg_catalog_variant_flag_mark('<a href="x>y</a>', 'has_rating', false), 'a quote that does not end');
}

// Both rating states kept: each flagged element carries its flag, the state
// that does not fit the product drawn first is hidden, no wrapper is added
// around an element and no marker is left. Text goes into a span of its own.
// Markup that does not balance is kept or cut as without variants, and so is
// a span of another flag.
function test_catalog_rating_flags_keep()
{
	$span = function ($flag, $n, $html) {
		return '<!--pg-vis:' . $flag . ':' . $n . '-->' . $html . '<!--/pg-vis:' . $n . '-->';
	};
	$rated = array('has_rating' => true, 'no_rating' => false);
	$unrated = array('has_rating' => false, 'no_rating' => true);

	$html = '<div class="row">'
		. $span('has_rating', 1, "\n  <div class=\"col d-flex\">&#9733; <span data-pg-bind=\"text:rating_avg\">4,6</span></div>\n")
		. $span('no_rating', 2, '<p class="small">Be the first to review</p>')
		. '</div>';

	pg_assert_same(
		'<div class="row">'
		. "\n  <div data-pg-variant-flag=\"has_rating\" class=\"col d-flex\">&#9733; <span data-pg-bind=\"text:rating_avg\">4,6</span></div>\n"
		. '<p data-pg-variant-flag="no_rating" hidden class="small">Be the first to review</p>'
		. '</div>',
		_pg_catalog_variant_flags_keep($html, $rated),
		'a product with a rating: the no_rating element is hidden'
	);
	pg_assert_same(
		'<div class="row">'
		. "\n  <div data-pg-variant-flag=\"has_rating\" hidden class=\"col d-flex\">&#9733; <span data-pg-bind=\"text:rating_avg\">4,6</span></div>\n"
		. '<p data-pg-variant-flag="no_rating" class="small">Be the first to review</p>'
		. '</div>',
		_pg_catalog_variant_flags_keep($html, $unrated),
		'a product without a rating: the has_rating element is hidden'
	);

	// One element without a closing tag, and nested elements of the same name.
	pg_assert_same('<img data-pg-variant-flag="has_rating" src="stars.svg" alt="">', _pg_catalog_variant_flags_keep($span('has_rating', 3, '<img src="stars.svg" alt="">'), $rated));
	pg_assert_same(
		'<div data-pg-variant-flag="has_rating" hidden><div>4,6</div><div>(12)</div></div>',
		_pg_catalog_variant_flags_keep($span('has_rating', 4, '<div><div>4,6</div><div>(12)</div></div>'), $unrated)
	);

	// Spans inside a kept span are switched on their own.
	pg_assert_same(
		'<div data-pg-variant-flag="has_rating" hidden><span data-pg-variant-flag="no_rating">none</span></div>',
		_pg_catalog_variant_flags_keep($span('has_rating', 5, '<div>' . $span('no_rating', 6, '<span>none</span>') . '</div>'), $unrated)
	);

	// Plain text goes into a span of its own, and each of several elements
	// carries the flag, so both switch with the variant too.
	pg_assert_same('<span data-pg-variant-flag="no_rating">Be the first</span>', _pg_catalog_variant_flags_keep($span('no_rating', 7, 'Be the first'), $unrated));
	pg_assert_same('<span data-pg-variant-flag="no_rating" hidden>Be the first</span>', _pg_catalog_variant_flags_keep($span('no_rating', 8, 'Be the first'), $rated));
	pg_assert_same(
		'<b data-pg-variant-flag="has_rating">4,6</b><i data-pg-variant-flag="has_rating">(12)</i>',
		_pg_catalog_variant_flags_keep($span('has_rating', 9, '<b>4,6</b><i>(12)</i>'), $rated)
	);
	pg_assert_same(
		'<div data-pg-variant-flag="has_rating" hidden>4,6</div><div data-pg-variant-flag="has_rating" hidden>(12)</div>',
		_pg_catalog_variant_flags_keep($span('has_rating', 10, '<div>4,6</div><div>(12)</div>'), $unrated)
	);

	// Markup that does not balance has no end to mark: kept or cut for the
	// product drawn first.
	pg_assert_same('<div>4,6', _pg_catalog_variant_flags_keep($span('has_rating', 13, '<div>4,6'), $rated));
	pg_assert_same('', _pg_catalog_variant_flags_keep($span('has_rating', 14, '<div>4,6'), $unrated));

	// Another flag is resolved as before.
	pg_assert_same('<p>Edit</p>', _pg_catalog_variant_flags_keep($span('record_found', 11, '<p>Edit</p>'), array('record_found' => true)));
	pg_assert_same('', _pg_catalog_variant_flags_keep($span('record_found', 12, '<p>Edit</p>'), array()));

	// From a designer tree, as the product page draws it.
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'd-flex gap-2', '_bindings' => array('eo_visible_if' => 'has_rating')), 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Stars'), 'children' => array()),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'No reviews yet', '_bindings' => array('eo_visible_if' => 'no_rating')), 'children' => array()),
		array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<i class="bi bi-star-fill"></i> 4,6', '_bindings' => array('eo_visible_if' => 'has_rating')), 'children' => array()),
	));
	_pg_sw_mark_visibility($tree, array('has_rating', 'no_rating'));
	$html = '';
	foreach ($tree['children'] as $child) {
		$html .= _render_tree_node($child, 0, 0);
	}
	$kept = _pg_catalog_variant_flags_keep($html, $unrated);

	pg_assert_false(strpos($kept, 'pg-vis') !== false, 'no marker is left');
	pg_assert_true((bool) preg_match('/<div data-pg-variant-flag="has_rating" hidden class="d-flex gap-2">\s*<p>Stars<\/p>\s*<\/div>/', $kept), 'the stars row is kept, hidden');
	pg_assert_contains('<p data-pg-variant-flag="no_rating">No reviews yet</p>', $kept, 'the text without a rating is shown');
	pg_assert_contains('<i data-pg-variant-flag="has_rating" hidden class="bi bi-star-fill"></i><span data-pg-variant-flag="has_rating" hidden> 4,6', $kept, 'custom HTML with an icon and text is kept, hidden');
}

// The designer offers both tokens in both catalog widgets, the two flags in
// "Show only when", and canvas samples that look like a rating.
function test_catalog_rating_designer_offers()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	foreach (array('SW_CATALOG_TOKEN_GROUPS', 'SW_CATALOG_ITEM_TOKEN_GROUPS') as $palette) {
		$start = strpos($js, 'var ' . $palette . ' = [');
		pg_assert_true($start !== false, $palette . ' is found');
		$body = substr($js, $start, strpos($js, "\n    ];", $start) - $start);
		pg_assert_contains("['__rating_avg',", $body, $palette);
		pg_assert_contains("['__rating_count',", $body, $palette);
	}

	$start = strpos($js, 'var SW_VISIBILITY_FLAGS = {');
	pg_assert_true($start !== false, 'SW_VISIBILITY_FLAGS is found');
	$flags = substr($js, $start, strpos($js, "\n    };", $start) - $start);

	foreach (array('catalog_listing', 'catalog_item_view') as $type) {
		pg_assert_true((bool) preg_match('/\n\s+' . $type . ': \[(.*?)\n\s+\]/s', $flags, $m), $type . ' has flags');
		pg_assert_contains("['has_rating',", isset($m[1]) ? $m[1] : '', $type);
		pg_assert_contains("['no_rating',", isset($m[1]) ? $m[1] : '', $type);
	}

	// The file is too long to print when an assertion fails.
	pg_assert_true(strpos($js, "'__rating_avg': '4.6'") !== false, 'a canvas sample for the average');
	pg_assert_true(strpos($js, "'__rating_count': '12'") !== false, 'a canvas sample for the count');
}
