<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 3 to 5.3.8 converter on a page type's markup
 * (includes/fn/legacy_bootstrap.php): the rules of a class list where no
 * Legacy theme styles the markup, the pass over rendered markup and over a
 * layout file's text, the starting sites' layouts and templates, the
 * names the site's own scripts and sheets use, and where a page drawn by
 * get_page_content.php runs the pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');

// The class names a stylesheet defines.
function _test_lbs_layout_sheet_classes($file)
{
	preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($file)), $m);
	return array_flip($m[1]);
}

// Whether the layout pass rewrites a class of this name.
function _test_lbs_layout_converts($name)
{
	if ($name === 'in' || $name === 'glyphicon' || pg_lbs_is_visibility($name)) return true;
	return (bool)pg_lbs_layout_classes($name, true)['converted'];
}

// The starting sites' layout files and the templates, path => text.
function _test_lbs_layout_files()
{
	$files = array();
	foreach (array('/data/backups/english_default/layouts', '/data/backups/turkish_default/layouts', '/includes/templates') as $dir) {
		foreach ((array)glob(PG_FUNCTIONS_DIR . $dir . '/*.php') as $file) {
			$files[substr($file, strlen(PG_FUNCTIONS_DIR) + 1)] = (string)file_get_contents($file);
		}
	}
	return $files;
}

// The PHP of a file, without the text between its blocks.
function _test_lbs_layout_php($code)
{
	$php = '';
	foreach (token_get_all($code) as $t) {
		if (!is_array($t)) {
			$php .= $t;
		} elseif ($t[0] !== T_INLINE_HTML) {
			$php .= $t[1];
		}
	}
	return $php;
}

// Every name the pass writes into a piece of markup is one Bootstrap 5.3.8
// defines.
function _test_lbs_layout_writes_5_3_8_names($in, $out)
{
	static $bs5 = null;
	if ($bs5 === null) $bs5 = _test_lbs_layout_sheet_classes(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	$names = function ($html) {
		preg_match_all('~\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~', $html, $m);
		return array_flip(preg_split('/\s+/', implode(' ', array_merge($m[1], $m[2], $m[3])), -1, PREG_SPLIT_NO_EMPTY));
	};
	foreach (array_keys(array_diff_key($names($out), $names($in))) as $name) {
		pg_assert_true(isset($bs5[$name]), $in . ' writes ' . $name);
	}
}

function test_lbs_layout_rules_of_a_class_list()
{
	// Without a Legacy theme the names 5.3.8 owns are its own utilities.
	pg_assert_same('legacy-bg-primary text-end', pg_lbs_classes('bg-primary text-right')['class']);
	$r = pg_lbs_classes('bg-primary text-right overflow-hidden', array('theme_names' => false));
	pg_assert_same('bg-primary text-end overflow-hidden', $r['class']);
	pg_assert_same(array('text-right' => 1), $r['converted']);

	// A kept name stays as written and is counted; the rest is converted.
	$r = pg_lbs_classes('label pull-right hidden', array('keep' => array('label', 'hidden')));
	pg_assert_same('label float-end hidden', $r['class']);
	pg_assert_same(array('label' => 1, 'hidden' => 1), $r['kept']);
	pg_assert_same(array('pull-right' => 1), $r['converted']);
	// A kept visibility name is left out of the element's display set.
	pg_assert_same('hide d-none d-sm-block', pg_lbs_classes('hide hidden-xs', array('keep' => array('hide')))['class']);
	pg_assert_same('glyphicon bi bi-check-lg', pg_lbs_classes('glyphicon glyphicon-ok', array('keep' => array('glyphicon')))['class']);

	// A name written beside its 5.3.8 names: found by both, once.
	$r = pg_lbs_classes('form-group row', array('beside' => array('form-group')));
	pg_assert_same('mb-3 form-group row', $r['class']);
	pg_assert_same(array('form-group' => 1), $r['converted']);
	$again = pg_lbs_classes($r['class'], array('beside' => array('form-group')));
	pg_assert_same($r['class'], $again['class']);
	pg_assert_same(array(), $again['converted']);
	$icon = pg_lbs_classes('glyphicon-ok glyphicon', array('beside' => array('glyphicon-ok', 'glyphicon')));
	pg_assert_same('bi bi-check-lg glyphicon-ok glyphicon', $icon['class']);
	pg_assert_same(array('glyphicon-ok' => 1), $icon['converted']);
	pg_assert_same(array(), pg_lbs_classes($icon['class'], array('beside' => array('glyphicon-ok', 'glyphicon')))['converted']);
}

// The templates write the 5.3.8 colour beside btn-default and a theme writes
// its own margin beside form-group: neither may lose to the map's name.
function test_lbs_layout_a_name_the_element_already_says_wins()
{
	pg_assert_same('btn btn-light', pg_lbs_classes('btn btn-default')['class']);
	pg_assert_same('btn btn-primary btn-thin', pg_lbs_classes('btn btn-default btn-primary btn-thin')['class']);
	$r = pg_lbs_classes('btn btn-default btn-secondary btn-xs');
	pg_assert_same('btn btn-secondary btn-sm', $r['class']);
	pg_assert_same(array('btn-default' => 1, 'btn-xs' => 1), $r['converted']);
	pg_assert_same('btn btn-outline-secondary', pg_lbs_classes('btn btn-default btn-outline-secondary')['class']);
	// btn-lg and btn-block are no colour.
	pg_assert_same('btn btn-light btn-lg w-100', pg_lbs_classes('btn btn-default btn-lg btn-block')['class']);

	pg_assert_same('mb-3', pg_lbs_classes('form-group')['class']);
	pg_assert_same('mb48', pg_lbs_classes('form-group mb48')['class']);
	pg_assert_same('form-inline mb0', pg_lbs_classes('form-group form-inline mb0')['class']);
	pg_assert_same('mb-0', pg_lbs_classes('form-group mb-0')['class']);
	pg_assert_same('my-2', pg_lbs_classes('form-group my-2')['class']);
	// Another side, or one width only, is no bottom margin of its own.
	pg_assert_same('mb-3 mt32', pg_lbs_classes('form-group mt32')['class']);
	pg_assert_same('mb-3 mb-sm-0', pg_lbs_classes('form-group mb-sm-0')['class']);
}

// An element hidden at one width is shown again as what its tag is: a table
// cell shown as a block leaves its row.
function test_lbs_layout_visibility_knows_the_tag()
{
	pg_assert_same(array('d-none', 'd-sm-table-cell'), pg_lbs_visibility(array('hidden-xs'), 'table-cell'));
	pg_assert_same(array('d-none', 'd-md-inline'), pg_lbs_visibility(array('hidden-xs', 'hidden-sm'), 'inline'));
	// A bare visible-* is a block, but on a table, a row or a cell.
	pg_assert_same(array('d-block', 'd-sm-none'), pg_lbs_visibility(array('visible-xs'), 'inline'));
	pg_assert_same(array('d-table-cell', 'd-sm-none'), pg_lbs_visibility(array('visible-xs'), 'table-cell'));
	pg_assert_same(array('d-none', 'd-print-table-row'), pg_lbs_visibility(array('visible-print'), 'table-row'));
	// A named display is what it says on any tag.
	pg_assert_same(array('d-inline', 'd-sm-none'), pg_lbs_visibility(array('visible-xs-inline'), 'table-cell'));

	pg_assert_same('table-cell', pg_lbs_layout_display('td'));
	pg_assert_same('table-row', pg_lbs_layout_display('tr'));
	pg_assert_same('inline', pg_lbs_layout_display('span'));
	pg_assert_same('inline-block', pg_lbs_layout_display('img'));
	pg_assert_same('block', pg_lbs_layout_display('div'));

	$html = '<table><tr class="visible-md visible-lg"><th class="hidden-xs">Price</th><td class="hidden-xs hidden-sm"><span class="hidden-xs">x</span><img class="hidden-lg" src="a.png"></td></tr></table><div class="hidden-xs">y</div>';
	$want = '<table><tr class="d-none d-md-table-row"><th class="d-none d-sm-table-cell">Price</th><td class="d-none d-md-table-cell"><span class="d-none d-sm-inline">x</span><img class="d-lg-none" src="a.png"></td></tr></table><div class="d-none d-sm-block">y</div>';
	pg_assert_same($want, pg_lbs_layout_html($html)['html']);

	// Every name written is one 5.3.8 defines.
	$bs5 = _test_lbs_layout_sheet_classes(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	foreach (array('table', 'table-row', 'table-cell', 'inline', 'inline-block', 'block') as $display) {
		foreach (array('hidden-xs', 'hidden-sm hidden-md', 'visible-lg', 'visible-print', 'hidden-xs visible-print') as $set) {
			foreach (pg_lbs_visibility(explode(' ', $set), $display) as $c) {
				pg_assert_true(isset($bs5[$c]), $set . ' on ' . $display . ' writes ' . $c);
			}
		}
	}
}

// Tags only: text between tags, comments, the inside of script, style,
// textarea and pre, and every other attribute's value stay byte for byte.
function test_lbs_layout_html_changes_tags_only()
{
	$html = '<div class="form-group text-right" title=\'write class="hidden" here\'>'
		. 'a visitor wrote &lt;b class=&quot;hidden&quot;&gt; and class="pull-right" 1 < 2 <3'
		. '<!-- <p class="text-right"> -->'
		. '<script>var a = \'<p class="text-right">\';</script>'
		. '<style>.text-right > a[class="hidden"] { color: red }</style>'
		. '<textarea class="form-control input-lg" name="t"><p class="text-right"></textarea>'
		. '<pre class="pre-scrollable"><span class="text-right">x</span></pre>'
		. '<input value="class=&quot;hidden&quot; <b class=hidden>" class="input-sm">'
		. '</div>';
	$want = '<div class="mb-3 text-end" title=\'write class="hidden" here\'>'
		. 'a visitor wrote &lt;b class=&quot;hidden&quot;&gt; and class="pull-right" 1 < 2 <3'
		. '<!-- <p class="text-right"> -->'
		. '<script>var a = \'<p class="text-right">\';</script>'
		. '<style>.text-right > a[class="hidden"] { color: red }</style>'
		. '<textarea class="form-control form-control-lg" name="t"><p class="text-right"></textarea>'
		. '<pre class="pre-scrollable"><span class="text-right">x</span></pre>'
		. '<input value="class=&quot;hidden&quot; <b class=hidden>" class="form-control-sm">'
		. '</div>';
	$r = pg_lbs_layout_html($html);
	pg_assert_same($want, $r['html']);
	pg_assert_true($r['done']);
	pg_assert_same(array('form-group' => 1, 'text-right' => 1, 'input-lg' => 1, 'input-sm' => 1), $r['converted']);
	pg_assert_same(array('pre-scrollable' => 1), $r['by_hand']);

	// No theme's names: 5.3.8's own utilities stay.
	pg_assert_same('<section class="bg-primary overflow-hidden text-end">', pg_lbs_layout_html('<section class="bg-primary overflow-hidden text-right">')['html']);
	pg_assert_same('', pg_lbs_layout_html('')['html']);
	pg_assert_same('no tag at all', pg_lbs_layout_html('no tag at all')['html']);
}

// A tag is read as a browser reads it.
function test_lbs_layout_html_reads_a_tag_as_a_browser_does()
{
	$cases = array(
		// A value may hold ">", in either quote.
		'<a title="a > b" class="pull-right" data-x=\'{"a":">"}\'>' => '<a title="a > b" class="float-end" data-x=\'{"a":">"}\'>',
		// Bare values, a value that becomes two names gets its quotes.
		'<p class=text-right id=a>' => '<p class=text-end id=a>',
		'<img class=center-block src=a.png>' => '<img class="d-block mx-auto" src=a.png>',
		"<p class='text-right'>" => "<p class='text-end'>",
		// Any case, any white space, a self-closing slash.
		"<DIV\n\tCLASS = \"col-xs-6\n\t\tcol-sm-offset-2\"\n>" => "<DIV\n\tCLASS = \"col-6 offset-sm-2\"\n>",
		'<br class="hidden-xs"/>' => '<br class="d-none d-sm-block"/>',
		// Class names are case sensitive.
		'<p class="Text-Right text-right">' => '<p class="Text-Right text-end">',
		// The first of a repeated attribute is the one that counts.
		'<p class="text-right" class="pull-left">' => '<p class="text-end" class="pull-left">',
		// No white space between attributes, an attribute without a value.
		'<input disabled class="input-lg"required>' => '<input disabled class="form-control-lg"required>',
		// What is no tag is text.
		'a < b, a <3 b, <> and </> <p class="text-right">' => 'a < b, a <3 b, <> and </> <p class="text-end">',
		// A comment that never closes runs to the end.
		'<p class="text-right"><!-- <p class="text-right">' => '<p class="text-end"><!-- <p class="text-right">',
		// So does a script without its end tag.
		'<p class="text-right"><script>x = "<p class=\'text-right\'>"' => '<p class="text-end"><script>x = "<p class=\'text-right\'>"',
	);
	foreach ($cases as $html => $want) {
		$r = pg_lbs_layout_html($html);
		pg_assert_same($want, $r['html']);
		pg_assert_true($r['done'], $html);
	}
	// A list with nothing to convert keeps its bytes, white space and all.
	$plain = "<div class=\" card  card-body\n\tmy-own \" id=x><p class=''>";
	pg_assert_same($plain, pg_lbs_layout_html($plain)['html']);
}

// It fails open: a tag that never ends returns the markup as it came.
function test_lbs_layout_html_broken_tag_returns_the_markup_as_given()
{
	foreach (array(
		'<p class="text-right">a</p><div class="pull-left',
		'<p class="text-right">a</p><div class="pull-left" title="never closed>b</div>',
		'<p class="text-right">a</p><div',
		'<p class="text-right">a</p></div',
	) as $html) {
		$r = pg_lbs_layout_html($html);
		pg_assert_same($html, $r['html']);
		pg_assert_false($r['done'], $html);
		pg_assert_same(array(), $r['converted']);
	}
	// A quote that closes later makes one long value, as in a browser: the
	// title runs to the quote before text-right, the tag ends at the next
	// ">", and what follows is text.
	$r = pg_lbs_layout_html('<a title="x>y</a><p class="text-right"> " class="pull-left">');
	pg_assert_true($r['done']);
	pg_assert_same('<a title="x>y</a><p class="text-right"> " class="pull-left">', $r['html']);
	pg_assert_same(array(), $r['converted']);
}

// An ambiguous name is converted only in Bootstrap 3's shape.
function test_lbs_layout_ambiguous_names_need_bootstrap_3_s_shape()
{
	$cases = array(
		// A partner name on the element itself.
		'<span class="label label-success">ok</span>' => '<span class="badge text-bg-success">ok</span>',
		'<div class="panel panel-default"><div class="panel-body">x</div></div>' => '<div class="card"><div class="card-body">x</div></div>',
		'<div class="well well-sm">x</div>' => '<div class="card card-body">x</div>',
		// A bare one is the site's own: the starting sites' form views date
		// their posts with the theme's .label.
		'<span class="label">^^publish-date^^</span>' => '<span class="label">^^publish-date^^</span>',
		'<div class="panel"><div class="media"><a class="thumbnail"><b class="caret"></b></a></div></div>' => '<div class="panel"><div class="media"><a class="thumbnail"><b class="caret"></b></a></div></div>',
		'<div class="well pull-right">x</div>' => '<div class="well float-end">x</div>',
		// Bootstrap 3's checkbox and radio blocks: an input of the type, or
		// a label that opens with one. The input is 5.3.8's form-check-input.
		'<div class="checkbox"><label><input type="checkbox" name="a"> A</label></div>' => '<div class="form-check"><label><input type="checkbox" name="a" class="form-check-input"> A</label></div>',
		"<div class=\"radio mb24\">\n\t<label class=\"x\">\n\t\t<input\n\t\t\tname=\"a\"\n\t\t\ttype=\"radio\"> A</label></div>" => "<div class=\"form-check mb24\">\n\t<label class=\"x\">\n\t\t<input\n\t\t\tname=\"a\"\n\t\t\ttype=\"radio\" class=\"form-check-input\"> A</label></div>",
		'<div class="radio"><input type=radio id=a><label for=a>A</label></div>' => '<div class="form-check"><input type=radio id=a class="form-check-input"><label for=a>A</label></div>',
		// An input's own class, a wrapper around other markup, the other
		// type's input.
		'<input type="checkbox" class="checkbox check">' => '<input type="checkbox" class="checkbox check">',
		'<div class="radio"><div class="radio-option"><div class="inner"></div><input type="radio" name="a"></div></div>' => '<div class="radio"><div class="radio-option"><div class="inner"></div><input type="radio" name="a"></div></div>',
		'<div class="checkbox"><label>A <input type="checkbox"></label></div>' => '<div class="checkbox"><label>A <input type="checkbox"></label></div>',
		'<div class="checkbox"><label><input type="radio"> A</label></div>' => '<div class="checkbox"><label><input type="radio"> A</label></div>',
		// The inline ones are nobody else's name.
		'<label class="checkbox-inline"><input type="checkbox"> A</label>' => '<label class="form-check form-check-inline"><input type="checkbox" class="form-check-input"> A</label>',
	);
	foreach ($cases as $html => $want) {
		pg_assert_same($want, pg_lbs_layout_html($html)['html']);
	}
	$r = pg_lbs_layout_html('<span class="label">a</span><span class="label label-info">b</span><b class="caret"></b>');
	pg_assert_same(array('label' => 1, 'caret' => 1), $r['kept']);
	pg_assert_same(array('label' => 1, 'label-info' => 1), $r['converted']);
	// Every ambiguous name is one the map knows.
	foreach (pg_lbs_layout_ambiguous_names() as $name) {
		pg_assert_true(isset(pg_lbs_class_map()[$name]), $name);
	}
}

// The data-toggle family is renamed where Bootstrap's scripts read the tag:
// other libraries and a site's own scripts use the same attribute names.
function test_lbs_layout_data_family_only_where_bootstrap_reads_the_tag()
{
	$cases = array(
		'<a data-toggle="collapse" data-target="#a" data-parent="#b" data-id="7">x</a>' => '<a data-bs-toggle="collapse" data-bs-target="#a" data-bs-parent="#b" data-id="7">x</a>',
		'<a href="#" data-toggle=tooltip data-placement=top title="t">x</a>' => '<a href="#" data-bs-toggle=tooltip data-bs-placement=top title="t">x</a>',
		'<button class="close" data-dismiss="alert">x</button>' => '<button class="close" data-bs-dismiss="alert">x</button>',
		'<div class="carousel slide" data-ride="carousel" data-interval="3000"><a data-slide="prev"></a><li data-target="#c" data-slide-to="0"></li></div>' => '<div class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000"><a data-bs-slide="prev"></a><li data-bs-target="#c" data-bs-slide-to="0"></li></div>',
		// The modal carries its own options.
		'<div class="modal fade" data-backdrop="static" data-keyboard="false">' => '<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false">',
		// Another library's toggle, a site's own target, offset and interval.
		'<a data-toggle="lightbox" data-target="#a">x</a>' => '<a data-toggle="lightbox" data-target="#a">x</a>',
		'<div data-target="#a" data-offset="10" data-interval="5" data-parent="x">x</div>' => '<div data-target="#a" data-offset="10" data-interval="5" data-parent="x">x</div>',
		'<nav data-spy="affix" data-offset-top="60">' => '<nav data-spy="affix" data-offset-top="60">',
		// Written for 5.3.8 already, in whole or beside the old name.
		'<a data-bs-toggle="tab" data-bs-target="#a">x</a>' => '<a data-bs-toggle="tab" data-bs-target="#a">x</a>',
		'<a data-toggle="tab" data-bs-toggle="tab">x</a>' => '<a data-toggle="tab" data-bs-toggle="tab">x</a>',
	);
	foreach ($cases as $html => $want) {
		pg_assert_same($want, pg_lbs_layout_html($html)['html']);
	}
	// Written twice with no white space before the next attribute: each
	// attribute stays whole.
	pg_assert_same('<a data-toggle="tab" data-bs-toggle="tab"data-target="#a" data-bs-target="#a">x</a><script>$("[data-toggle], [data-target]")</script>',
		pg_lbs_layout_html('<a data-toggle="tab"data-target="#a">x</a><script>$("[data-toggle], [data-target]")</script>')['html']);
	$r = pg_lbs_layout_html('<a data-toggle="tab" data-target="#a">x</a><a data-toggle="tab">y</a>');
	pg_assert_same(array('data-toggle' => 2, 'data-target' => 1), $r['converted']);
	// The triggers are names of the one family.
	foreach (pg_lbs_layout_triggers() as $name => $values) {
		pg_assert_same('data-bs-' . substr($name, 5), pg_lbs_data_attribute($name));
	}
}

// No script of the markup may stop finding its elements.
function test_lbs_layout_names_a_script_uses_stay_findable()
{
	$script = '<script>'
		. '$(".help-block").text("x"); $("#f .form-group > label, td.text-right").addClass("on");'
		. '$("#more").removeClass("hidden"); el.classList.toggle(\'in\', open); $("#b").toggleClass("hide btn-default");'
		. '$(\'[data-toggle="tab"]\').on("shown", f); var t = $(this).data("target");'
		. 'var hidden = el.hidden; $("#x").hide(); for (k in o) {} var url = "file.label";'
		. '</script>';
	$names = pg_lbs_layout_script_names($script);
	foreach (array('help-block', 'form-group', 'text-right', 'hidden', 'in', 'hide', 'btn-default') as $name) {
		pg_assert_true(isset($names['classes'][$name]), $name);
	}
	pg_assert_same(array('data-toggle' => true, 'data-target' => true), $names['attributes']);
	pg_assert_same(array('classes' => array(), 'attributes' => array()), pg_lbs_layout_script_names('<p class="hidden">no script</p>'));

	$html = '<div class="form-group"><p class="help-block">h</p></div>'
		. '<div id="more" class="hidden">m</div><div class="collapse in">c</div><a id="b" class="btn btn-default hide">b</a>'
		. '<a data-toggle="tab" data-target="#p">t</a><span class="label pull-right">l</span>'
		. $script;
	// A name a script selects is written beside its 5.3.8 names; a state it
	// changes stays alone, or the 5.3.8 name would stay behind when the
	// script takes the old one off; an attribute it reads is written twice.
	$want = '<div class="mb-3 form-group"><p class="form-text help-block">h</p></div>'
		. '<div id="more" class="hidden">m</div><div class="collapse in">c</div><a id="b" class="btn btn-light btn-default hide">b</a>'
		. '<a data-toggle="tab" data-bs-toggle="tab" data-target="#p" data-bs-target="#p">t</a><span class="label float-end">l</span>'
		. $script;
	$r = pg_lbs_layout_html($html);
	pg_assert_same($want, $r['html']);
	pg_assert_same(array('hidden' => 1, 'in' => 1, 'hide' => 1, 'label' => 1), $r['kept']);
	// A second pass changes nothing and converts nothing.
	$again = pg_lbs_layout_html($r['html']);
	pg_assert_same($r['html'], $again['html']);
	pg_assert_same(array(), $again['converted']);

	// Without the script the same markup is converted whole.
	pg_assert_same('<div class="mb-3"><p class="form-text">h</p></div><div id="more" class="d-none">m</div><div class="collapse show">c</div>',
		pg_lbs_layout_html('<div class="form-group"><p class="help-block">h</p></div><div id="more" class="hidden">m</div><div class="collapse in">c</div>')['html']);
}

// 5.3.8 markup passes untouched, and what the pass writes it never reads
// again.
function test_lbs_layout_5_3_8_markup_passes_untouched()
{
	$html = '<form class="row g-3 needs-validation"><div class="col-md-6 mb-3"><label class="form-label" for="a">A</label>'
		. '<div class="input-group has-validation"><span class="input-group-text">@</span><input class="form-control form-control-lg is-invalid" id="a">'
		. '<div class="invalid-feedback">x</div></div><div class="form-text">h</div></div>'
		. '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c"><label class="form-check-label" for="c">C</label></div>'
		. '<div class="card text-bg-primary border-0 d-none d-md-block float-end"><div class="card-body"><span class="badge text-bg-success visually-hidden">n</span></div></div>'
		. '<button class="btn btn-outline-secondary btn-sm w-100 collapsed" data-bs-toggle="collapse" data-bs-target="#x">b</button>'
		. '<div class="collapse show bg-secondary bg-dark overflow-hidden align-top" id="x"><i class="bi bi-check-lg"></i><img class="img-fluid rounded-circle d-block mx-auto"></div>'
		. '<div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></form>'
		. '<ul class="nav nav-pills flex-column"><li class="nav-item"><a class="nav-link active" href="#">a</a></li></ul>'
		. '<nav><ol class="breadcrumb"><li class="breadcrumb-item active">b</li></ol></nav><ul class="pagination"><li class="page-item"><a class="page-link">1</a></li></ul>'
		. '<ul class="list-inline"><li class="list-inline-item">c</li></ul><dl class="row"><dt class="col-sm-3">d</dt><dd class="col-sm-9">e</dd></dl>'
		. '<div class="input-group"><select class="form-select"></select><button class="btn btn-outline-secondary">f</button></div>'
		. '<div class="row"><div class="col-sm-9 order-sm-1">g</div><div class="col-sm-3 order-sm-0">h</div></div>';
	$r = pg_lbs_layout_html($html);
	pg_assert_same($html, $r['html']);
	pg_assert_same(array(), $r['converted']);

	// No name the layout pass writes is one it converts, and none is a name
	// 5.3.8 gives another meaning: an old name kept beside the new is safe.
	$bs5 = _test_lbs_layout_sheet_classes(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	foreach (pg_lbs_class_map() as $from => $to) {
		if (in_array($from, pg_lbs_theme_names(), true)) continue;
		pg_assert_false(isset($bs5[$from]), $from . ' is a 5.3.8 name');
		foreach (preg_split('/\s+/', $to, -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false(_test_lbs_layout_converts($c), $from . ' writes ' . $c);
		}
	}
	foreach (pg_lbs_icon_map() as $from => $to) {
		pg_assert_false(isset($bs5[$from]), $from . ' is a 5.3.8 name');
	}
}

// A layout file's text: PHP stays byte for byte, a tag that holds PHP is
// read like any other, and a class list PHP writes in part is counted.
function test_lbs_layout_source_keeps_php_and_counts_what_it_writes()
{
	$code = "<?php if (\$items): ?>\n"
		. "<div class=\"form-group <?=\$error ? 'has-error' : ''?>\" id=\"row_<?=\$id?>\">\n"
		. "\t<a href=\"<?=h(\$url)?>\" class=\"btn btn-default btn-xs\" <?=\$attributes?>><?=h(lang('Edit'))?></a>\n"
		. "\t<td class=\"<?=\$cell_class?>\"><span class=\"col-xs-<?=\$n?> text-right\">x</span></td>\n"
		. "\t<div class=\"checkbox\"><?php if (\$label): ?><label><input type=\"checkbox\" <?=\$checked?>> <?=h(\$label)?></label><?php endif ?></div>\n"
		. "<?php endif; echo '<p class=\"text-right\">'; ?>\n";
	$want = "<?php if (\$items): ?>\n"
		. "<div class=\"mb-3 <?=\$error ? 'has-error' : ''?>\" id=\"row_<?=\$id?>\">\n"
		. "\t<a href=\"<?=h(\$url)?>\" class=\"btn btn-light btn-sm\" <?=\$attributes?>><?=h(lang('Edit'))?></a>\n"
		. "\t<td class=\"<?=\$cell_class?>\"><span class=\"col-xs-<?=\$n?> text-end\">x</span></td>\n"
		. "\t<div class=\"form-check\"><?php if (\$label): ?><label><input type=\"checkbox\" <?=\$checked?>> <?=h(\$label)?></label><?php endif ?></div>\n"
		. "<?php endif; echo '<p class=\"text-right\">'; ?>\n";
	$r = pg_lbs_layout_source($code);
	pg_assert_same($want, $r['code']);
	pg_assert_true($r['done']);
	pg_assert_same(3, $r['php']);
	pg_assert_same(array('form-group' => 1, 'btn-default' => 1, 'btn-xs' => 1, 'text-right' => 1, 'checkbox' => 1), $r['converted']);
	pg_assert_same($want, pg_lbs_layout_source($want)['code']);

	// A closing tag inside a PHP string ends no block, as PHP reads it.
	$r = pg_lbs_layout_source("<p class=\"text-right\"><?php echo '?><b class=\"pull-left\">'; ?><i class=\"pull-left\">");
	pg_assert_same("<p class=\"text-end\"><?php echo '?><b class=\"pull-left\">'; ?><i class=\"float-start\">", $r['code']);
	pg_assert_same(array('text-right' => 1, 'pull-left' => 1), $r['converted']);

	// A file that ends inside PHP, a file with no PHP, a broken tag.
	pg_assert_same('<p class="text-end"><?php echo 1;', pg_lbs_layout_source('<p class="text-right"><?php echo 1;')['code']);
	pg_assert_same('<p class="text-end">', pg_lbs_layout_source('<p class="text-right">')['code']);
	$r = pg_lbs_layout_source('<p class="text-right"><?=$a?><div class="pull-left');
	pg_assert_same('<p class="text-right"><?=$a?><div class="pull-left', $r['code']);
	pg_assert_false($r['done']);
}

// Every layout of both starting sites and every template: PHP untouched, the
// text between tags untouched, no name the pass converts left in a written
// class list, a second pass changes nothing.
function test_lbs_layout_starting_site_layouts_and_templates()
{
	$files = _test_lbs_layout_files();
	pg_assert_true(count($files) > 200, 'layouts and templates read');
	$results = array();
	$left = array();
	foreach ($files as $path => $code) {
		$r = pg_lbs_layout_source($code);
		pg_assert_true($r['done'], $path . ': the pass finishes');
		$results[] = $r;
		pg_assert_same(_test_lbs_layout_php($code), _test_lbs_layout_php($r['code']), $path . ': the same PHP');
		$text = preg_replace('/<\?.*?(?:\?>|\z)/s', '', $code);
		$converted = preg_replace('/<\?.*?(?:\?>|\z)/s', '', $r['code']);
		pg_assert_same(strip_tags($text), strip_tags($converted), $path . ': the same text between tags');

		$again = pg_lbs_layout_source($r['code']);
		pg_assert_same($r['code'], $again['code'], $path . ': a second pass');
		pg_assert_same(array(), $again['converted'], $path . ': a second pass converts nothing');

		// The class lists as written, outside scripts and comments.
		$markup = preg_replace(array('~<(script|style|textarea|pre)\b.*?</\1~is', '~<!--.*?-->~s'), '', $converted);
		preg_match_all('~<[a-zA-Z][^<>]*?\sclass\s*=\s*(["\'])(.*?)\1~s', $markup, $m);
		foreach ($m[2] as $list) {
			foreach (preg_split('/\s+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (_test_lbs_layout_converts($c)) $left[$c] = true;
			}
		}
	}
	$report = pg_lbs_report($results);
	pg_assert_true($report['totals']['converted'] > 4000, 'the layouts print Bootstrap 3');
	foreach (array('form-group', 'text-right', 'btn-default', 'checkbox', 'radio', 'input-group-addon', 'help-block', 'glyphicon', 'visible-xs-inline') as $name) {
		pg_assert_true(isset($report['converted'][$name]), $name . ' is converted');
	}
	// The ambiguous names the layouts use are checkbox and radio. What stays
	// is the theme's own .radio around its payment options, one input's own
	// class, and the icons the theme draws in its own widgets: the chevron of
	// each .select-option and the tick of each .check-box.
	ksort($left);
	pg_assert_same(array('checkbox', 'glyphicon', 'glyphicon-ok', 'radio', 'ti-angle-down'), array_keys($left));
	$kept = array_keys($report['kept']);
	sort($kept);
	pg_assert_same(array('checkbox', 'glyphicon', 'glyphicon-ok', 'radio', 'ti-angle-down'), $kept);
	pg_assert_true($report['kept']['radio'] < $report['converted']['radio'] && $report['kept']['checkbox'] < 5, 'the blocks are converted');
	pg_assert_true($report['kept']['ti-angle-down'] > 250 && $report['kept']['glyphicon-ok'] > 80, 'the theme widgets keep their icons');
	pg_assert_true(isset($report['converted']['ti-close'], $report['converted']['glyphicon-remove']), 'an icon of the layout\'s own is converted');
	// The names Bootstrap 3 gave through a tag or a parent. What a designer
	// finishes by hand is the inline form, whose parts are laid out in more
	// than one way.
	foreach (array('input-group-btn', 'dl-horizontal', 'pager', 'form-control', 'col-sm-push-3', 'col-sm-pull-9', 'nav') as $name) {
		pg_assert_true(isset($report['converted'][$name]), $name . ' is converted');
	}
	pg_assert_same(array('form-inline'), array_keys($report['by_hand']));
}

// The count behind the pass: no script and no sheet of the site side names a
// class the pass converts, so none stops finding its elements. A script or
// sheet that starts to name one fails here first.
function test_lbs_layout_site_scripts_and_sheets_name_no_converted_class()
{
	$sources = array();
	foreach (array('frontend.src.js', 'add_to_cart.src.js', 'cross_sell.src.js', 'chat_site.src.js') as $file) {
		$sources[$file] = '<script>' . file_get_contents(PG_FUNCTIONS_DIR . '/' . $file) . '</script>';
	}
	// The page types' own files with their inline scripts, and each script
	// file they name.
	foreach ((array)glob(PG_FUNCTIONS_DIR . '/get_*.php') as $file) {
		$code = (string)file_get_contents($file);
		$sources[basename($file)] = $code;
		preg_match_all('~assets/js/[a-z0-9_]+\.js~', $code, $m);
		foreach (array_unique($m[0]) as $script) {
			$sources[$script] = '<script>' . file_get_contents(PG_FUNCTIONS_DIR . '/' . $script) . '</script>';
		}
	}
	$sources += _test_lbs_layout_files();
	pg_assert_true(count($sources) > 280, 'scripts, page types, layouts and templates read');
	$scripts = 0;
	foreach ($sources as $path => $text) {
		$names = pg_lbs_layout_script_names($text);
		pg_assert_true(is_array($names), $path . ': the scripts are read');
		$scripts += (stripos($text, '<script') !== false) ? 1 : 0;
		foreach (array_keys($names['classes']) as $name) {
			pg_assert_false(_test_lbs_layout_converts($name), $path . ' names the class ' . $name);
		}
		pg_assert_same(array(), $names['attributes'], $path . ' reads a Bootstrap data attribute');
	}
	pg_assert_true($scripts > 20, 'sources with a script');

	// A rule on a renamed class would stop matching.
	$stay = array_merge(pg_lbs_theme_names(), pg_lbs_layout_ambiguous_names());
	foreach (array('frontend.src.css', 'livesite.src.css', 'pinegrap.src.css', 'responsive.src.css') as $file) {
		$r = pg_lbs_css(file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
		pg_assert_same(array(), array_values(array_diff(array_keys($r['converted']), $stay)), $file . ' selects a converted class');
		pg_assert_same(array(), $r['kept'], $file . ' selects a class the pass rewrites');
	}
}

function test_lbs_layout_theme_widgets_keep_the_icon_their_theme_draws()
{
	$cases = array(
		// The theme lays the chevron over its select and shows the tick only
		// in a ticked box.
		"<div class=\"select-option\">\n\t<i class=\"ti-angle-down\"></i>\n\t<select name=\"a\"></select>\n</div>"
			=> "<div class=\"select-option\">\n\t<i class=\"ti-angle-down\"></i>\n\t<select name=\"a\"></select>\n</div>",
		'<label class="check-box"><input type="checkbox" name="b" value="1"> <span class="unchecked"><span class="glyphicon glyphicon-ok"></span></span> <span>Notify me</span></label>'
			=> '<label class="check-box"><input type="checkbox" name="b" value="1"> <span class="unchecked"><span class="glyphicon glyphicon-ok"></span></span> <span>Notify me</span></label>',
		'<div class=select-option><i class="ti-angle-down"></i></div>' => '<div class=select-option><i class="ti-angle-down"></i></div>',
		// The widget's other names and the icon's other names still convert.
		'<div class="select-option pull-left"><i class="ti-angle-down pull-right"></i></div>'
			=> '<div class="select-option float-start"><i class="ti-angle-down float-end"></i></div>',
		// Only the first tag inside the widget, with nothing but white space
		// before it.
		'<div class="select-option">Size <i class="ti-angle-down"></i></div>' => '<div class="select-option">Size <i class="bi bi-chevron-down"></i></div>',
		'<span class="unchecked"></span><span class="glyphicon glyphicon-ok"></span>' => '<span class="unchecked"></span><span class="bi bi-check-lg"></span>',
		'<div class="select-option"><div><i class="ti-angle-down"></i></div></div>' => '<div class="select-option"><div><i class="bi bi-chevron-down"></i></div></div>',
		// A widget's name outside the class attribute names no widget.
		'<div title="select-option" class="x"><i class="ti-angle-down"></i></div>' => '<div title="select-option" class="x"><i class="bi bi-chevron-down"></i></div>',
		'<div class="no-select-option"><i class="ti-angle-down"></i></div>' => '<div class="no-select-option"><i class="bi bi-chevron-down"></i></div>',
		// An icon a button shows is the layout's own and gets its twin.
		'<button class="btn btn-secondary btn-thin"><span class="ti-close"></span></button>' => '<button class="btn btn-secondary btn-thin"><span class="bi bi-x-lg"></span></button>',
	);
	foreach ($cases as $in => $want) {
		$r = pg_lbs_layout_html($in);
		pg_assert_same($want, $r['html'], $in);
		pg_assert_same($r['html'], pg_lbs_layout_html($r['html'])['html'], $in . ': a second pass');
	}
	$r = pg_lbs_layout_html('<span class="unchecked"><span class="glyphicon glyphicon-ok"></span></span>');
	pg_assert_same(array('glyphicon' => 1, 'glyphicon-ok' => 1), $r['kept'], 'the icon is counted as kept');
	pg_assert_same(array(), $r['converted'], 'nothing converted');

	// In a layout file's text a PHP block between counts as white space.
	$r = pg_lbs_layout_source("<div class=\"select-option\">\n<?php if (\$a): ?>\n<i class=\"ti-angle-down\"></i><?php endif ?></div>");
	pg_assert_same(array('ti-angle-down' => 1), $r['kept'], 'a PHP block before the icon');
}

function test_lbs_layout_output_applies_on_a_bootstrap_5_design_only()
{
	pg_assert_true(pg_lbs_layout_output_applies('visual_designer', 'bootstrap5', false), 'a page of a Bootstrap 5.3.8 design');
	pg_assert_true(pg_lbs_layout_output_applies('visual_designer', 'bootstrap5'), 'drawn in a browser unless told otherwise');
	pg_assert_false(pg_lbs_layout_output_applies('visual_designer', 'bootstrap5', true), 'the same page drawn as an e-mail');
	pg_assert_false(pg_lbs_layout_output_applies('visual_designer', 'legacy', false), 'a Legacy design keeps Bootstrap 3');
	pg_assert_false(pg_lbs_layout_output_applies('visual_designer', 'custom', false), 'a design on its own framework');
	// A classic style has no framework of its own and reads as bootstrap5.
	pg_assert_false(pg_lbs_layout_output_applies('one_column_mobile', 'bootstrap5', false), 'a classic style');
	pg_assert_false(pg_lbs_layout_output_applies('', 'bootstrap5', false), 'a page without a style');
}

function test_lbs_layout_region_keeps_the_header_and_fails_open()
{
	$header = '<div class="software_system_region_header"><p class="text-right">Own text</p></div>';
	$type = '<form><div class="form-group"><input class="form-control"></div><button class="btn btn-default pull-right">Go</button></form>';
	pg_assert_same(
		$header . '<form><div class="mb-3"><input class="form-control"></div><button class="btn btn-light float-end">Go</button></form>',
		pg_lbs_layout_region($header . $type, strlen($header)),
		'the page type\'s markup gets 5.3.8 names, the header stays as written'
	);
	pg_assert_same(
		'<p class="text-end">Own text</p>',
		pg_lbs_layout_region('<p class="text-right">Own text</p>'),
		'a primary region is mapped from its start'
	);
	pg_assert_same($header, pg_lbs_layout_region($header, strlen($header)), 'a header with nothing after it');
	pg_assert_same('', pg_lbs_layout_region('', 0), 'an empty region');
	pg_assert_same($header, pg_lbs_layout_region($header, strlen($header) + 9), 'a start past the end');
	pg_assert_same('<p class="text-end">x</p>', pg_lbs_layout_region('<p class="text-right">x</p>', -4), 'a start before the beginning');

	// A tag that never ends: the region comes back as it came, never empty.
	$broken = $header . '<div class="form-group"><a class="pull-right" title="never closed';
	pg_assert_same($broken, pg_lbs_layout_region($broken, strlen($header)), 'a broken tag');

	// A second pass changes nothing.
	$once = pg_lbs_layout_region($header . $type, strlen($header));
	pg_assert_same($once, pg_lbs_layout_region($once, strlen($header)), 'a second pass');
}

function test_lbs_layout_get_page_content_runs_the_pass_once_on_the_page_type_s_markup()
{
	$src = (string)file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$at = function ($needle) use ($src) {
		pg_assert_same(1, substr_count($src, $needle), 'get_page_content.php holds once: ' . $needle);
		return strpos($src, $needle);
	};

	// The gate: a visual design's page in a browser loads the module, and
	// the framework decides.
	$load = $at("if (\$style_layout === 'visual_designer' && !\$email) {\n        require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');");
	$gate = $at("pg_lbs_layout_output_applies(\$style_layout, \$pg_design_fw['key'], \$email)");
	pg_assert_true($at("\$pg_design_fw = pg_design_framework(") < $load && $load < $gate, 'the gate reads the framework of the page\'s style');

	// Each region starts where its header ends and is mapped before its
	// footer, the edit mode frame, the canvas marks and the join.
	$reset = $at("\$system_output = '';\n        // Where the page type's own markup starts in \$system_output: after\n        // a secondary region's header.\n        \$pg_lbs_from = 0;");
	$header = $at("\$system_output .= \$output_system_region_header;\n                \$pg_lbs_from = strlen(\$system_output);");
	$extra = $at("\$system_output .= \$extra_system_content;");
	$pass = $at("if (\$pg_lbs_layout_map) {\n                \$system_output = pg_lbs_layout_region(\$system_output, \$pg_lbs_from);");
	$footer = $at("\$system_output .= \$output_system_region_footer;");
	$frame = $at("<div style=\"padding: 2em 0 0 0\">' . \$system_output . '</div>");
	$marks = $at("'<!--pg-layout-region-start-->' . \$system_output");
	$join = $at("pg_regex_replacement(\$system_output)");
	pg_assert_true($gate < $reset && $reset < $header && $header < $extra && $extra < $pass, 'the pass follows the page type\'s markup');
	pg_assert_true($pass < $footer && $footer < $frame && $frame < $marks && $marks < $join, 'the pass comes before the footer, the frame, the marks and the join');
	$code = '';
	foreach (token_get_all($src) as $t) {
		if (is_array($t) && ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) continue;
		$code .= is_array($t) ? $t[1] : $t;
	}
	pg_assert_same(2, preg_match_all('/pg_lbs_[a-z_]+\s*\(/', $code), 'get_page_content.php calls the converter in the gate and the pass only');
}

function test_lbs_layout_code_tab_says_the_names_change_on_a_bootstrap_5_design()
{
	$src = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	$text = "Pages of this design print the layout\\'s Bootstrap 3 class names as their Bootstrap 5.3.8 names; the file keeps them as written.";
	pg_assert_true(strpos($src, "note: (K.design && K.design.framework === 'bootstrap5') ? _sdT('" . $text . "') : ''") !== false, 'the layout tab carries the line on a Bootstrap 5.3.8 design only');
	pg_assert_true(strpos($src, "note.textContent = spec.note;") !== false, 'the line is written as text');
	$min = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.min.js');
	pg_assert_true(strpos($min, 'sd-code-note') !== false, 'the served bundle carries the line');
	$css = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	pg_assert_true(strpos($css, '.sd-code-pane-noted > .cm-editor') !== false, 'the editor gives the line its room');
}

// The elements of a piece of markup nest as a browser nests them: an li, dt
// or cell closes the one still open, a void element holds nothing, an end
// tag with no open element of its name is passed over.
function test_lbs_layout_elements_nest_as_a_browser_does()
{
	$els = pg_lbs_layout_elements('<ul class="a"><li>1<li><a href="#">2</a></ul><p>x<div>y</div><dl><dt>t<dd>d<dd>e</dl>');
	$tags = array_column($els, 'tag');
	pg_assert_same(array('ul', 'li', 'li', 'a', 'p', 'div', 'dl', 'dt', 'dd', 'dd'), $tags);
	pg_assert_same(array(1, 2), $els[0]['kids'], 'the second li closes the first');
	pg_assert_same(array(3), $els[2]['kids']);
	pg_assert_same(-1, $els[5]['parent'], 'a div closes the open p');
	pg_assert_same(array(7, 8, 9), $els[6]['kids'], 'dt and dd close each other');
	pg_assert_same('a', $els[0]['class']);

	$els = pg_lbs_layout_elements('<div><input type=checkbox class><img src=x></span><span>a</div><b>');
	pg_assert_same(array(1, 2, 3), $els[0]['kids'], 'void elements and a stray end tag');
	pg_assert_same(false, $els[1]['class'], 'a class attribute with no value');
	pg_assert_same('checkbox', $els[1]['type']);
	pg_assert_same(null, $els[2]['class']);
	pg_assert_same(-1, $els[4]['parent'], 'the div closes the span inside it');

	// The inside of script, textarea and pre is no markup; inside svg, "/>"
	// closes a tag.
	$els = pg_lbs_layout_elements('<div><script>var a = "<li class=x>";</script><pre><b>x</b></pre><svg><path d="M0"/><circle/></svg><i></i></div>');
	pg_assert_same(array('div', 'script', 'pre', 'svg', 'path', 'circle', 'i'), array_column($els, 'tag'));
	pg_assert_same(array(1, 2, 3, 6), $els[0]['kids']);
	pg_assert_same(array(4, 5), $els[3]['kids']);
	pg_assert_same(null, pg_lbs_layout_elements('<div class="a><p>'));
}

// A select styled as a field is 5.3.8's form-select, but in a theme's
// .select-option, whose sheet draws the arrow; the input a checkbox or radio
// block opens with is a form-check-input.
function test_lbs_layout_selects_and_the_inputs_of_choice_blocks()
{
	$cases = array(
		'<select class="form-control" name="a"></select>' => '<select class="form-select" name="a"></select>',
		'<select class="form-control input-sm"></select><input class="form-control input-lg">' => '<select class="form-select form-select-sm"></select><input class="form-control form-control-lg">',
		'<select class="form-control-lg"></select>' => '<select class="form-select-lg"></select>',
		'<div class="select-option"><i class="ti-angle-down"></i><select class="form-control"></select></div>' => '<div class="select-option"><i class="ti-angle-down"></i><select class="form-control"></select></div>',
		'<select class="form-select"></select><select></select>' => '<select class="form-select"></select><select></select>',
		// The input gets the name beside its own, a bare one a class attribute.
		'<div class="checkbox"><label><input type="checkbox" class="opt"> A</label></div>' => '<div class="form-check"><label><input type="checkbox" class="opt form-check-input"> A</label></div>',
		'<div class="checkbox"><label class="check-box"><input type="checkbox" name="r" value="1" /> <span class="unchecked"></span>A</label></div>' => '<div class="form-check"><label class="check-box"><input type="checkbox" name="r" value="1" class="form-check-input" /> <span class="unchecked"></span>A</label></div>',
		'<label class="radio-inline"><input type=radio name=a value=1>A</label>' => '<label class="form-check form-check-inline"><input type=radio name=a value=1 class="form-check-input">A</label>',
		// Not a block, not its input, or no value to add to.
		'<div class="checkbox"><label>A <input type="checkbox"></label></div>' => '<div class="checkbox"><label>A <input type="checkbox"></label></div>',
		'<label class="checkbox-inline"><input type="text"> A</label>' => '<label class="form-check form-check-inline"><input type="text"> A</label>',
		'<div class="checkbox"><input type="checkbox" class> A</div>' => '<div class="form-check"><input type="checkbox" class> A</div>',
	);
	foreach ($cases as $in => $want) {
		$r = pg_lbs_layout_html($in);
		pg_assert_same($want, $r['html'], $in);
		_test_lbs_layout_writes_5_3_8_names($in, $r['html']);
		$again = pg_lbs_layout_html($r['html']);
		pg_assert_same($r['html'], $again['html'], $in . ': a second pass');
		pg_assert_same(array(), $again['converted'], $in . ': a second pass converts nothing');
	}
	pg_assert_same(array('form-control' => 1), pg_lbs_layout_html('<select class="form-control"></select>')['converted']);
	pg_assert_same(array('input-sm' => 1), pg_lbs_layout_html('<select class="input-sm"></select>')['converted']);

	// A select a script finds by form-control keeps the name beside.
	$r = pg_lbs_layout_html('<select class="form-control"></select><script>$(".form-control").val("");</script>');
	pg_assert_same('<select class="form-select form-control"></select><script>$(".form-control").val("");</script>', $r['html']);

	// In a layout file's text, a tag PHP writes part of gets no class
	// attribute of its own, but for PHP inside a quoted value.
	$r = pg_lbs_layout_source('<div class="checkbox"><label><input type="checkbox" <?=$checked?>> A</label></div>');
	pg_assert_same('<div class="form-check"><label><input type="checkbox" <?=$checked?>> A</label></div>', $r['code']);
	$r = pg_lbs_layout_source('<div class="checkbox"><label><input type="checkbox" name="<?=$name?>"> A</label></div>');
	pg_assert_same('<div class="form-check"><label><input type="checkbox" name="<?=$name?>" class="form-check-input"> A</label></div>', $r['code']);
}

// The lists and definitions Bootstrap 3 styled through the parent: the
// items, links and terms get the names 5.3.8 needs on them.
function test_lbs_layout_lists_and_definitions_bootstrap_3_styled_through_the_parent()
{
	$cases = array(
		// dl-horizontal: a row; a second definition of one term keeps its column.
		'<dl class="dl-horizontal"><dt>Name</dt><dd>A</dd><dd>B</dd><dt class="x">Mail</dt><dd>c</dd></dl>'
			=> '<dl class="dl-horizontal row"><dt class="col-sm-3 text-sm-end">Name</dt><dd class="col-sm-9">A</dd><dd class="col-sm-9 offset-sm-3">B</dd><dt class="x col-sm-3 text-sm-end">Mail</dt><dd class="col-sm-9">c</dd></dl>',
		'<dl class="dl-horizontal"><dt class="col-sm-2">a</dt><dd class="col-sm-10">b</dd></dl>'
			=> '<dl class="dl-horizontal row"><dt class="col-sm-2">a</dt><dd class="col-sm-10">b</dd></dl>',
		// pager: the slightly customized buttons it was.
		'<ul class="pager"><li class="previous"><a href="?p=1">&laquo; Previous</a></li><li>Page 2 of 3</li><li class="next disabled"><span>Next &raquo;</span></li></ul>'
			=> '<ul class="pager list-inline text-center"><li class="previous list-inline-item float-start"><a href="?p=1" class="btn btn-outline-secondary rounded-pill">&laquo; Previous</a></li><li class="list-inline-item">Page 2 of 3</li><li class="next disabled list-inline-item float-end"><span class="btn btn-outline-secondary rounded-pill disabled">Next &raquo;</span></li></ul>',
		// nav: the item, its link, the link of the active and the disabled item.
		'<ul class="nav nav-pills nav-stacked"><li class="active"><a href="#a">A</a></li><li><a href="#b" style="padding-left: 2em">B</a></li><li class="disabled"><a>C</a></li></ul>'
			=> '<ul class="nav nav-pills flex-column"><li class="active nav-item"><a href="#a" class="nav-link active">A</a></li><li class="nav-item"><a href="#b" style="padding-left: 2em" class="nav-link">B</a></li><li class="disabled nav-item"><a class="nav-link disabled">C</a></li></ul>',
		'<ol class="breadcrumb"><li><a href="/">Home</a></li><li class="active">Cart</li></ol>'
			=> '<ol class="breadcrumb"><li class="breadcrumb-item"><a href="/">Home</a></li><li class="active breadcrumb-item">Cart</li></ol>',
		'<ul class="pagination"><li class="disabled"><span>&laquo;</span></li><li class="active"><a href="?p=1">1</a></li></ul>'
			=> '<ul class="pagination"><li class="disabled page-item"><span class="page-link">&laquo;</span></li><li class="active page-item"><a href="?p=1" class="page-link">1</a></li></ul>',
		'<ul class="list-inline"><li>a</li><li><ul><li>b</li></ul></li></ul>'
			=> '<ul class="list-inline"><li class="list-inline-item">a</li><li class="list-inline-item"><ul><li>b</li></ul></li></ul>',
	);
	foreach ($cases as $in => $want) {
		$r = pg_lbs_layout_html($in);
		pg_assert_same($want, $r['html'], $in);
		_test_lbs_layout_writes_5_3_8_names($in, $r['html']);
		$again = pg_lbs_layout_html($r['html']);
		pg_assert_same($r['html'], $again['html'], $in . ': a second pass');
		pg_assert_same(array(), $again['converted'], $in . ': a second pass converts nothing');
	}
	$r = pg_lbs_layout_html('<dl class="dl-horizontal"><dt>a</dt><dd>b</dd></dl><ul class="pager"><li><a href="#">x</a></li></ul>');
	pg_assert_same(array('dl-horizontal' => 1, 'pager' => 1), $r['converted']);
	pg_assert_same(array(), $r['by_hand']);
	// Where nothing was missing, the parent's name is neither by hand nor converted.
	$r = pg_lbs_layout_html('<dl class="dl-horizontal row"><dt class="col-sm-3">a</dt></dl>');
	pg_assert_same(array(), $r['converted']);
	pg_assert_same(array(), $r['by_hand']);
}

// The buttons of an input-group-btn are squared where they meet the field,
// and a wrapper that the group's table filled grows; a 5.3.8 group is not
// touched.
function test_lbs_layout_input_group_buttons_meet_the_field()
{
	$cases = array(
		'<div class="input-group"><input class="form-control"><span class="input-group-btn"><button class="btn btn-default">Go</button></span></div>'
			=> '<div class="input-group"><input class="form-control"><span class="input-group-btn"><button class="btn btn-light rounded-start-0">Go</button></span></div>',
		'<div class="input-group"><span class="input-group-btn" style="display:none"><a class="btn">-</a></span><input class="form-control"><span class="input-group-btn"><button class="btn">s</button> <button class="btn">x</button></span></div>'
			=> '<div class="input-group"><span class="input-group-btn" style="display:none"><a class="btn rounded-end-0">-</a></span><input class="form-control"><span class="input-group-btn"><button class="btn rounded-start-0">s</button> <button class="btn">x</button></span></div>',
		'<div class="input-group"><span class="input-group-addon">@</span><span class="input-group-btn"><button class="btn">a</button></span><input type="hidden" name="h"><input class="form-control"></div>'
			=> '<div class="input-group"><span class="input-group-text">@</span><span class="input-group-btn"><button class="btn rounded-0">a</button></span><input type="hidden" name="h"><input class="form-control"></div>',
		'<div class="form-group input-group"><div class="select-option"><i class="ti-angle-down"></i><select></select></div><span class="input-group-btn"><button class="btn btn-thin rounded">x</button></span></div>'
			=> '<div class="mb-3 input-group"><div class="select-option flex-grow-1"><i class="ti-angle-down"></i><select></select></div><span class="input-group-btn"><button class="btn btn-thin rounded">x</button></span></div>',
		// 5.3.8's own group: nothing.
		'<div class="input-group has-validation"><div class="form-floating"><input class="form-control"></div><button class="btn btn-outline-secondary">Go</button><div class="invalid-feedback">x</div></div>'
			=> '<div class="input-group has-validation"><div class="form-floating"><input class="form-control"></div><button class="btn btn-outline-secondary">Go</button><div class="invalid-feedback">x</div></div>',
		// Outside a group the span is left by hand.
		'<div><span class="input-group-btn"><button class="btn">x</button></span></div>' => '<div><span class="input-group-btn"><button class="btn">x</button></span></div>',
	);
	foreach ($cases as $in => $want) {
		$r = pg_lbs_layout_html($in);
		pg_assert_same($want, $r['html'], $in);
		_test_lbs_layout_writes_5_3_8_names($in, $r['html']);
		$again = pg_lbs_layout_html($r['html']);
		pg_assert_same($r['html'], $again['html'], $in . ': a second pass');
		pg_assert_same(array(), $again['converted'], $in . ': a second pass converts nothing');
	}
	$r = pg_lbs_layout_html('<div class="input-group"><input class="form-control"><span class="input-group-btn"><button class="btn">Go</button></span></div>');
	pg_assert_same(array('input-group-btn' => 1), $r['converted']);
	pg_assert_same(array('input-group-btn' => 1), pg_lbs_layout_html('<div><span class="input-group-btn"></span></div>')['by_hand']);
}

// Push and pull become the order of a row's columns where they reorder
// whole columns; any other move stays by hand.
function test_lbs_layout_push_and_pull_become_the_order_of_the_columns()
{
	$cols = function ($lists) {
		return array_map(function ($l) { return array_flip(explode(' ', $l)); }, $lists);
	};
	pg_assert_same(array(array('sm' => 1), array('sm' => 0)), pg_lbs_layout_column_orders($cols(array('col-sm-9 col-sm-push-3', 'col-sm-3 col-sm-pull-9'))));
	pg_assert_same(array(array('md' => 1), array('md' => 0), array('md' => 2)), pg_lbs_layout_column_orders($cols(array('col-md-4 col-md-push-4', 'col-md-4 col-md-pull-4', 'col-md-4'))));
	// A move that holds from xs, and one taken back at a wider breakpoint.
	pg_assert_same(array(array('xs' => 1, 'lg' => 0), array('xs' => 0, 'lg' => 1)), pg_lbs_layout_column_orders($cols(array('col-xs-6 col-xs-push-6 col-lg-push-0', 'col-xs-6 col-xs-pull-6 col-lg-pull-0'))));
	// No move; a lone push that leaves a gap; an overlap; an offset; a
	// wrapped row; an order of the author's.
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-6', 'col-sm-6'))));
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-6 col-sm-push-3', 'col-sm-3'))));
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-6 col-sm-push-6', 'col-sm-6'))));
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-6 col-sm-push-3', 'col-sm-3 col-sm-pull-6 col-sm-offset-3'))));
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-9 col-sm-push-3', 'col-sm-6 col-sm-pull-9'))));
	pg_assert_same(null, pg_lbs_layout_column_orders($cols(array('col-sm-9 col-sm-push-3', 'col-sm-3 col-sm-pull-9 order-2'))));

	$in = '<div class="row"><div class="col-sm-9 col-sm-push-3">Products</div><div class="col-sm-3 col-sm-pull-9">Categories</div></div>';
	$r = pg_lbs_layout_html($in);
	pg_assert_same('<div class="row"><div class="col-sm-9 order-sm-1">Products</div><div class="col-sm-3 order-sm-0">Categories</div></div>', $r['html']);
	pg_assert_same(array('col-sm-push-3' => 1, 'col-sm-pull-9' => 1), $r['converted']);
	pg_assert_same(array(), $r['by_hand']);
	pg_assert_same($r['html'], pg_lbs_layout_html($r['html'])['html'], 'a second pass');
	$r = pg_lbs_layout_html('<div class="row"><div class="col-sm-6 col-sm-push-3">a</div><div class="col-sm-3">b</div></div>');
	pg_assert_same('<div class="row"><div class="col-sm-6 col-sm-push-3">a</div><div class="col-sm-3">b</div></div>', $r['html']);
	pg_assert_same(array('col-sm-push-3' => 1), $r['by_hand']);
}
