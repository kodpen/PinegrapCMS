<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 3 to 5.3.8 converter on a page type's markup
 * (includes/fn/legacy_bootstrap.php): the rules of a class list where no
 * Legacy theme styles the markup, the pass over rendered markup and over a
 * layout file's text, the starting sites' layouts and templates, and the
 * names the site's own scripts and sheets use.
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
		// a label that opens with one.
		'<div class="checkbox"><label><input type="checkbox" name="a"> A</label></div>' => '<div class="form-check"><label><input type="checkbox" name="a"> A</label></div>',
		"<div class=\"radio mb24\">\n\t<label class=\"x\">\n\t\t<input\n\t\t\tname=\"a\"\n\t\t\ttype=\"radio\"> A</label></div>" => "<div class=\"form-check mb24\">\n\t<label class=\"x\">\n\t\t<input\n\t\t\tname=\"a\"\n\t\t\ttype=\"radio\"> A</label></div>",
		'<div class="radio"><input type=radio id=a><label for=a>A</label></div>' => '<div class="form-check"><input type=radio id=a><label for=a>A</label></div>',
		// An input's own class, a wrapper around other markup, the other
		// type's input.
		'<input type="checkbox" class="checkbox check">' => '<input type="checkbox" class="checkbox check">',
		'<div class="radio"><div class="radio-option"><div class="inner"></div><input type="radio" name="a"></div></div>' => '<div class="radio"><div class="radio-option"><div class="inner"></div><input type="radio" name="a"></div></div>',
		'<div class="checkbox"><label>A <input type="checkbox"></label></div>' => '<div class="checkbox"><label>A <input type="checkbox"></label></div>',
		'<div class="checkbox"><label><input type="radio"> A</label></div>' => '<div class="checkbox"><label><input type="radio"> A</label></div>',
		// The inline ones are nobody else's name.
		'<label class="checkbox-inline"><input type="checkbox"> A</label>' => '<label class="form-check form-check-inline"><input type="checkbox"> A</label>',
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
		. '<div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></form>';
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
	// is the theme's own .radio around its payment options and one input's
	// own class.
	ksort($left);
	pg_assert_same(array('checkbox', 'radio'), array_keys($left));
	$kept = array_keys($report['kept']);
	sort($kept);
	pg_assert_same(array('checkbox', 'radio'), $kept);
	pg_assert_true($report['kept']['radio'] < $report['converted']['radio'] && $report['kept']['checkbox'] < 5, 'the blocks are converted');
	// What a designer finishes by hand until the names with no twin are done.
	foreach (array('input-group-btn', 'dl-horizontal', 'pager', 'form-inline') as $name) {
		pg_assert_true(isset($report['by_hand'][$name]), $name . ' is left by hand');
	}
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
