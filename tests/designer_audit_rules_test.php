<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for five rules of the visual designer's audit (assets/js/
 * style_designer.js): an empty link or button (empty_btn), an image without
 * an alt decision (img_alt), a form without a submit control
 * (form_no_submit) and an empty heading (heading_empty) from
 * _validationRules, and a form sent nowhere (form_no_action) from the
 * page-level pass. The designer's own validateTree(), its rules and their
 * helpers are cut out of the file and run under Node on node trees, with
 * the canvas pass stubbed and the page-level pass stubbed unless a case
 * asks for it; on a machine without Node the run is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function declaration of the designer, braces matched
// (the braces in these functions' strings come in pairs); '' when missing.
function designer_audit_rules_test_function($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

// The rule list: from its declaration to the first line that closes it.
function designer_audit_rules_test_rules($source)
{
	$from = strpos($source, 'var _validationRules = [');
	$to = ($from !== false) ? strpos($source, "\n    ];\n", $from) : false;

	return ($to !== false) ? substr($source, $from, $to - $from + 7) : '';
}

// Runs $script after the audit's code and returns the JSON it prints; null
// when there is no Node. With $page_level the editor's page-level pass
// (_pageLevelIssues()) runs too, over the shared components a case puts in
// _sharedCache; _swIsType() knows the kinds in swTypes.
function designer_audit_rules_test_run($script, $page_level = false)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$code_js = '';

	$names = array('_sdAttr', '_sdAuditHasText', '_sdAuditAttr', '_sdAuditHasName', '_sdAuditAltDecided', '_sdAuditTypeSubmits', '_sdAuditSubmits', '_sdAuditFormHasSubmit', '_sdAuditFormGoesNowhere', 'validateTree');
	if ($page_level) {
		$names[] = '_pageLevelIssues';
		$names[] = '_cfWidgetOwnsPageForm';
	}

	foreach ($names as $name) {
		$found = designer_audit_rules_test_function($source, $name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$code_js .= $found . "\n";
	}

	$rules = designer_audit_rules_test_rules($source);
	pg_assert_true($rules !== '', 'the rule list is in the designer');

	// _sdT() answers from sdTMap, so a case can set the editor language.
	$stubs = 'var sdTMap = {}; function _sdT(s) { return sdTMap.hasOwnProperty(s) ? sdTMap[s] : s; }'
		. 'function getNodeLabel(n) { return (n && n.props && n.props.customName) || ""; }'
		. ($page_level
			? 'var _sharedCache = {}, _sdSharedOpen = {}, swTypes = ["custom_form", "login_form", "catalog_listing"];'
				. 'function _swIsType(t) { return swTypes.indexOf(t) !== -1; }'
				. 'function _cfIsFormControl() { return false; }'
				. 'function _cfControlName() { return ""; }'
			: 'function _pageLevelIssues() { return []; }')
		. 'function _sdCanvasAuditIssues() { return []; }'
		// Every case is one tree under a root; the result is the names of
		// the nodes the given rule flags.
		. 'function flagged(rule, kids) {'
		. '  return validateTree({ type: "root", props: {}, children: kids })'
		. '    .filter(function (i) { return i.rule === rule; })'
		. '    .map(function (i) { return i.nodeLabel; });'
		. '}'
		. 'function N(type, props, kids) { return { type: type, props: props, children: kids || [] }; }'
		. 'function icon() { return N("content", { contentType: "icon", iconName: "bi-x" }); }'
		. 'var hidden = [{ name: "aria-hidden", value: "true" }];';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdaudit');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stubs . "\n" . $code_js . "\n" . $rules . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A link or button is empty only when nothing gives it a name: its text
// prop (the model the ready-made blocks use), a bound text, a child that is
// read (an icon beside a visually hidden label), an image's alt, or an
// aria-label / aria-labelledby / title. Hidden ones are not looked at. A
// text node drawn with the button or a tag is one too, and a child whose
// markup the server writes (custom PHP, messages, applied offers,
// breadcrumb, variant choices, the product gallery) is taken as named.
function test_designer_audit_rules_empty_button_or_link()
{
	$result = designer_audit_rules_test_run(
		'console.log(JSON.stringify(flagged("empty_btn", ['
		// Still flagged.
		. 'N("semantic", { tag: "a", href: "#", customName: "bare link" }),'
		. 'N("semantic", { tag: "a", href: "#", text: " ", customName: "blank text" }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon only" }, [icon()]),'
		. 'N("semantic", { tag: "button", text: "", customName: "empty span" }, [N("semantic", { tag: "span", text: "" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "hidden label" }, [icon(), N("content", { contentType: "span", text: "Share", _attrs: hidden })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "markup only" }, [N("content", { contentType: "span", text: "<i class=\"bi bi-x\"></i>&nbsp;" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "blank label", _attrs: [{ name: "aria-label", value: " " }] }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "decorative picture" }, [N("content", { contentType: "image", src: "/a.jpg", alt: "" })]),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content link" }),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content icon link" }, [icon()]),'
		. 'N("content", { contentType: "heading", tag: "button", text: "", customName: "text drawn as empty button" }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "around an svg" }, [N("content", { contentType: "svg", html: "<svg></svg>" })]),'
		// Not flagged.
		. 'N("semantic", { tag: "a", href: "#", text: "Headline", customName: "text prop" }),'
		. 'N("semantic", { tag: "button", text: "Notify me", customName: "button text" }),'
		. 'N("semantic", { tag: "a", href: "#", _bindings: { text: "__title" }, customName: "bound" }),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon and hidden span" }, [icon(), N("content", { contentType: "heading", tag: "span", text: "Pause", cssClass: "visually-hidden" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "nested text" }, [N("semantic", { tag: "span", cssClass: "d-flex" }, [N("content", { contentType: "paragraph", text: "Weather" })])]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "picture" }, [N("content", { contentType: "image", src: "/a.jpg", alt: "Logo" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "icon label", _attrs: [{ name: "aria-label", value: "Share" }] }, [icon()]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "titled icon" }, [N("content", { contentType: "icon", iconName: "bi-x", _attrs: [{ name: "title", value: "Close" }] })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "tooltip", tooltipEnabled: true, tooltipTitle: "Help" }),'
		. 'N("semantic", { tag: "button", cssClass: "btn-close", customName: "dismiss" }),'
		. 'N("semantic", { tag: "div", _attrs: hidden }, [N("semantic", { tag: "a", href: "#", customName: "under aria-hidden" })]),'
		. 'N("semantic", { tag: "a", href: "#", customName: "shared" }, [N("shared_ref", { sharedId: 4 })]),'
		. 'N("content", { contentType: "link", text: "", href: "#", customName: "content link child" }, [icon(), N("content", { contentType: "span", text: "Next", cssClass: "visually-hidden" })]),'
		. 'N("content", { contentType: "link", text: "Contact", href: "#", customName: "content link text" }),'
		. 'N("content", { contentType: "heading", tag: "button", text: "", customName: "indicator", _attrs: [{ name: "aria-label", value: "Slide 1" }] }),'
		. 'N("content", { contentType: "heading", tag: "BUTTON", text: "Tab", customName: "text drawn as button" }),'
		. 'N("content", { contentType: "heading", tag: "button", text: "", cssClass: "btn-close", customName: "text drawn as dismiss" }),'
		. 'N("content", { contentType: "heading", tag: "span", text: "", customName: "empty span text" }),'
		. '["custom_php", "messages", "applied_offers", "breadcrumb", "variant_attr", "carousel"].map(function (t) {'
		. '  return N("semantic", { tag: "a", href: "#", customName: "around " + t }, [N("content", { contentType: t })]);'
		. '})'
		. '].reduce(function (a, b) { return a.concat(b); }, []))));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'bare link', 'blank text', 'icon only', 'empty span', 'hidden label', 'markup only', 'blank label',
		'decorative picture', 'content link', 'content icon link', 'text drawn as empty button', 'around an svg',
	), $result, 'only the links and buttons with no name');
}

// An image is flagged only when it carries no alt decision: alt="" is the
// decorative one, and role="presentation" / "none", aria-hidden on it or an
// ancestor and a bound alt are decisions too. No alt prop at all is none,
// and neither is the palette image's placeholder alt, in English or in the
// editor's language.
function test_designer_audit_rules_image_alt_decision()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(
		(bool) preg_match("/\n        image: \\{\n(?:.*\n){0,12}?\\s*defaultProps: \\{ src: '', alt: 'Image',/", $source),
		'the palette image starts with the alt the rule reads as a placeholder'
	);

	$result = designer_audit_rules_test_run(
		'sdTMap.Image = "Görsel";'
		. 'function img(name, extra) { var p = { contentType: "image", src: "/a.jpg", customName: name }; for (var k in extra) p[k] = extra[k]; return N("content", p); }'
		. 'console.log(JSON.stringify(flagged("img_alt", ['
		. 'img("no alt", {}),'
		. 'img("null alt", { alt: null }),'
		. 'img("other role", { _attrs: [{ name: "role", value: "img" }] }),'
		. 'img("aria-hidden false", { _attrs: [{ name: "aria-hidden", value: "false" }] }),'
		. 'img("described", { alt: "Team photo" }),'
		. 'img("decorative", { alt: "" }),'
		. 'img("bound", { _bindings: { alt: "__title" } }),'
		. 'img("presentation", { _attrs: [{ name: "role", value: "presentation" }] }),'
		. 'img("none", { _attrs: [{ name: "role", value: "none" }] }),'
		. 'img("hidden", { _attrs: hidden }),'
		. 'N("semantic", { tag: "div", _attrs: hidden }, [N("semantic", { tag: "div" }, [img("under hidden copy", {})])]),'
		. 'img("after the hidden copy", {}),'
		. 'img("palette default", { alt: "Image" }),'
		. 'img("default with spaces", { alt: " image " }),'
		. 'img("translated default", { alt: "Görsel" }),'
		. 'img("words around it", { alt: "Image of the team" }),'
		. 'img("bound over the default", { alt: "Image", _bindings: { alt: "__title" } }),'
		. 'img("default but presentation", { alt: "Image", _attrs: [{ name: "role", value: "presentation" }] }),'
		. 'N("semantic", { tag: "div", _attrs: hidden }, [img("default under hidden copy", { alt: "Image" })])'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'no alt', 'null alt', 'other role', 'aria-hidden false', 'after the hidden copy',
		'palette default', 'default with spaces', 'translated default',
	), $result, 'only the images with no decision');
}

// A form is flagged when nothing in it submits it, read as the browser
// reads the printed type: a <button> (a semantic node, or a text node drawn
// with the button tag) submits unless its type is "button" or "reset" in
// any case, so a missing, blank or invalid type submits; an <input> submits
// only with type "submit" or "image", and the value is not trimmed. The old
// content button, which no renderer draws, does not count, and neither does
// an inputType prop, which no renderer prints. A Button component counts
// when it is drawn as a <button> or <input> that submits by its btnType
// (missing, it prints "button"); drawn as a link it does not.
function test_designer_audit_rules_form_submit_control()
{
	$result = designer_audit_rules_test_run(
		'function form(name, kids) { return N("semantic", { tag: "form", customName: name }, [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "email" }] })].concat(kids)); }'
		. 'function btn(extra) { var p = { componentType: "btn", text: "Send" }; for (var k in extra) p[k] = extra[k]; return N("component", p); }'
		. 'console.log(JSON.stringify(flagged("form_no_submit", ['
		. 'form("fields only", []),'
		. 'form("link button", [btn({})]),'
		. 'form("plain button component", [btn({ btnElement: "button", btnType: "button" })]),'
		. 'form("reset input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "reset" }] })]),'
		. 'form("semantic button", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: "submit" }] })]),'
		. 'form("button component", [btn({ btnElement: "button", btnType: "submit" })]),'
		. 'form("input component", [btn({ btnElement: "input", btnType: "submit" })]),'
		. 'form("submit input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "submit" }] })]),'
		. 'form("image input", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "IMAGE" }] })]),'
		. 'form("nested", [N("semantic", { tag: "div", cssClass: "input-group" }, [btn({ btnElement: "button", btnType: "submit" })])]),'
		. 'form("type button", [N("semantic", { tag: "button", text: "Show", _attrs: [{ name: "type", value: "button" }] })]),'
		. 'form("reset button", [N("semantic", { tag: "button", text: "Clear", _attrs: [{ name: "type", value: "reset" }] })]),'
		. 'form("text drawn as type button", [N("content", { contentType: "heading", tag: "button", text: "Tab", _attrs: [{ name: "type", value: "button" }] })]),'
		. 'form("old content button", [N("content", { contentType: "button", text: "Send" })]),'
		. 'form("button without type", [N("semantic", { tag: "button", text: "Send" })]),'
		. 'form("submit written loosely", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: " Submit " }] })]),'
		. 'form("text drawn as button", [N("content", { contentType: "heading", tag: "button", text: "Send" })]),'
		. 'form("show and send", [N("semantic", { tag: "button", text: "Show", _attrs: [{ name: "type", value: "button" }] }), btn({ btnElement: "button", btnType: "submit" })]),'
		. 'form("input type prop only", [N("semantic", { tag: "input", inputType: "submit" })]),'
		. 'form("input type with spaces", [N("semantic", { tag: "input", _attrs: [{ name: "type", value: " submit " }] })]),'
		. 'form("type button in capitals", [N("semantic", { tag: "button", text: "Show", _attrs: [{ name: "type", value: "BUTTON" }] })]),'
		. 'form("blank button type", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: "" }] })]),'
		. 'form("invalid button type", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: "bogus" }] })]),'
		. 'form("type button with spaces", [N("semantic", { tag: "button", text: "Send", _attrs: [{ name: "type", value: " button " }] })]),'
		. 'form("text drawn with blank type", [N("content", { contentType: "heading", tag: "button", text: "Send", _attrs: [{ name: "type", value: "" }] })]),'
		. 'form("button component without type", [btn({ btnElement: "button" })]),'
		. 'form("button component blank type", [btn({ btnElement: "button", btnType: "" })]),'
		. 'form("reset button component", [btn({ btnElement: "button", btnType: "Reset" })]),'
		. 'form("input component without type", [btn({ btnElement: "input" })])'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'fields only', 'link button', 'plain button component', 'reset input',
		'type button', 'reset button', 'text drawn as type button', 'old content button',
		'input type prop only', 'input type with spaces', 'type button in capitals',
		'button component without type', 'reset button component', 'input component without type',
	), $result, 'only the forms nothing submits');
}

// A text node is an empty heading only when it prints as a heading: the tag
// it is drawn with is h1-h6, in any case, or missing or malformed, which
// the renderer prints as h2. Drawn as a button, span, div or paragraph (the
// carousel's indicators and arrows, the toast's dismiss button, badges and
// labels) it is not a heading.
function test_designer_audit_rules_empty_heading()
{
	$result = designer_audit_rules_test_run(
		'function h(name, extra) { var p = { contentType: "heading", text: "", customName: name }; for (var k in extra) p[k] = extra[k]; return N("content", p); }'
		. 'console.log(JSON.stringify(flagged("heading_empty", ['
		// Flagged.
		. 'h("empty h2", { tag: "h2" }),'
		. 'h("capital tag", { tag: "H3", text: "&nbsp;" }),'
		. 'h("markup only", { tag: "h4", text: "<b></b>" }),'
		. 'h("no tag", {}),'
		. 'h("malformed tag", { tag: "h2 class=x" }),'
		. 'h("blank tag", { tag: "" }),'
		. 'N("semantic", { tag: "h3", customName: "semantic heading" }),'
		// Not flagged.
		. 'h("written", { tag: "h2", text: "Our services" }),'
		. 'h("bound", { tag: "h1", _bindings: { text: "__title" } }),'
		. 'h("indicator", { tag: "button", _attrs: [{ name: "aria-label", value: "Slide 1" }] }),'
		. 'h("arrow", { tag: "span", _attrs: hidden }),'
		. 'h("dismiss", { tag: "button", cssClass: "btn-close" }),'
		. 'h("division", { tag: "div" }),'
		. 'h("paragraph", { tag: "p" }),'
		. 'h("past h6", { tag: "h7" }),'
		. 'N("semantic", { tag: "h3", customName: "semantic heading with a child" }, [N("content", { contentType: "span", text: "Hi" })])'
		. '])));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'empty h2', 'capital tag', 'markup only', 'no tag', 'malformed tag', 'blank tag', 'semantic heading',
	), $result, 'only the text nodes printed as an empty h1-h6');
}

// A form outside every system widget that has no action is sent nowhere:
// submitting it reloads the page and nothing keeps what was typed (the
// Components tab's Form item dropped on a page, a block's sample form). The
// page-level pass names it on the form, in the page and in a plain shared
// component; a written action, a bound action, or a system widget around it
// (custom_form wraps it in its own <form>) takes the hint away.
function test_designer_audit_rules_form_without_action()
{
	$result = designer_audit_rules_test_run(
		'function form(name, attrs, b) { var p = { tag: "form", customName: name, _attrs: attrs || [] }; if (b) p._bindings = b; return N("semantic", p, [N("semantic", { tag: "input", _attrs: [{ name: "type", value: "email" }] })]); }'
		. 'function shared(sid, type, kids) { _sharedCache[sid] = { tree: N("root", {}, kids), system_region_config: type === null ? "" : JSON.stringify({ regionType: type }) }; return N("shared_ref", { sharedId: sid }); }'
		. 'var post = [{ name: "method", value: "post" }];'
		. 'console.log(JSON.stringify(flagged("form_no_action", ['
		// Flagged.
		. 'form("form item", [{ name: "method", value: "post" }, { name: "novalidate", value: "" }]),'
		. 'form("no attributes"),'
		. 'form("blank action", [{ name: "action", value: "  " }].concat(post)),'
		. 'N("semantic", { tag: "section" }, [N("semantic", { tag: "div" }, [form("block form", post)])]),'
		. 'N("semantic", { tag: "FORM", customName: "capital tag", _attrs: post }),'
		. 'shared(3, null, [form("in a shared header", [{ name: "role", value: "search" }])]),'
		. 'shared(5, "", [form("in an empty widget", post)]),'
		// Not flagged.
		. 'form("with action", [{ name: "action", value: "/thanks" }].concat(post)),'
		. 'form("bound action", [], { action: "search_form" }),'
		. 'shared(1, "custom_form", [form("in a custom form widget", post)]),'
		. 'shared(2, "login_form", [form("in a login widget")]),'
		. 'shared(4, "custom_form", [shared(6, null, [form("in a component inside a widget", post)])]),'
		. 'N("semantic", { tag: "div", customName: "not a form" })'
		. '])));',
		true
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		'form item', 'no attributes', 'blank action', 'block form', 'capital tag', 'in a shared header', 'in an empty widget',
	), $result, 'only the forms outside a system widget that go nowhere');
}
