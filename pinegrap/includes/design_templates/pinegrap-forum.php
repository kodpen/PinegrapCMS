<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Community Forum & Docs": the community site of a software
 * product. A front page with the site search, the forum's numbers, the
 * announcements, the boards, the latest activity, the topics waiting for an
 * answer, the week's most viewed topics and the featured guides; the forum
 * with its pinned topics and every topic by its newest activity; one board
 * at a time (?board=), the topics of a tag (?tag=) or of a member
 * (?member=) and the topics nobody has answered; the topic page, with its
 * replies beside a column about the topic; the form that starts a topic;
 * the docs, which members contribute and moderators review: their sections
 * (?section=) and each guide with its feedback; a form to report a post;
 * the guidelines, the questions and answers, about, contact and the legal
 * pages; the sign-in and account pages, with the member's own topics, the
 * guides they sent and the review of each, and their inbox; the
 * moderators' dashboard, every topic, the docs desk, the reported posts and
 * the staff directory; the e-mails the site sends and a site search. Built
 * on Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * A topic is a record of the Forum Topics form, owned by the Start a Topic
 * page in the "registration" folder: a member sends it, and it is public at
 * once. Moderators set its Status, pin it, make it an announcement and keep
 * a moderator note in the control panel (office-use-only fields). Every
 * list of topics a visitor or a member opens, and the topic page, carry one
 * rule: the Status is not Hidden ($topic_live). A guide is a record of the
 * Community Docs form, owned by the Contribute a Guide page: it is public
 * only while its Status is Live on the site ($doc_live); its author follows
 * it on the docs desk. A report (Forum Reports, the Report a Post page) is
 * never public: only the moderators' pages show it. Replies are comments of
 * the topic page, feedback (with a rating, held for the moderators)
 * comments of the guide page; the author and the moderators write messages
 * under a guide on the desk, and the moderators notes under a report.
 *
 * The replies and the feedback are drawn by the Comments widget, each page's
 * thread in the template's layout: replies under the post they answer, the
 * deepest quoting it, the best answer (a featured reply) first, the form,
 * the box to follow the topic and the moderators' controls. Three lists of
 * the same widget, in its recent mode, read the topic and guide pages: the
 * latest replies on the home page, the member's own in My topics and the
 * ones awaiting approval on the moderators' dashboard; a fourth reads the
 * topic page alone and keeps the replies of the board the address names
 * (its record filters), on the board page. The reply e-mails
 * draw the comment they are about with it too. The desk, the inbox and the
 * report page keep the platform's fixed comments block. Every page keeps its
 * own comment settings ('comments' below).
 *
 * The look ships in the template's User Styles: the template's own rules,
 * all named pf-. Fonts: Figtree for the text and the headings and JetBrains
 * Mono for code, from the editor's font picker.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
 *
 * One function lives outside this file: the gallery drawing
 * _pg_design_thumb_forum() (includes/fn/design_themes.php), because the
 * screens that draw it never read the template.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The control panel, and the two screens of it the moderators use most: the
// comments of every page (replies, feedback, messages and notes) and the
// records of every form (topics, guides, reports).
$panel_dir    = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
$panel_url    = $panel_dir . '/welcome.php';
$comments_url = $panel_dir . '/view_comments.php';
$forms_url    = $panel_dir . '/view_submitted_forms.php';
$shop         = defined('ECOMMERCE') && ECOMMERCE;

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. $name is the node's label in the layer
// tree ('_label'): the tag stays visible beside it.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['_label'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
};
$heading = function ($tag, $text, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'heading', 'tag' => $tag, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$para = function ($text, $class = '', $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$link = function ($text, $href, $class, $attrs = array(), $extra = array()) {
    $props = array('contentType' => 'link', 'text' => $text, 'href' => $href, 'cssClass' => $class);
    if ($attrs) $props['_attrs'] = $attrs;
    return array('type' => 'content', 'props' => array_merge($props, $extra), 'children' => array());
};
$image = function ($src, $alt, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => $alt,
        'fluid' => false, 'rounded' => false, 'cssClass' => $class), $extra), 'children' => array());
};
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A decorative icon: the words beside it say what it means.
$icon = function ($name, $class = '') use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '', $xs = '12', $class = '') {
    return array('type' => 'col', 'props' => array('xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class), 'children' => $children);
};
$root = function ($children) {
    return array('type' => 'root', 'props' => array('cssClass' => 'd-flex flex-column min-vh-100'), 'children' => $children);
};
$widget_root = function ($children) {
    return array('type' => 'root', 'props' => array(), 'children' => $children);
};
$loop = function ($children) {
    return array('type' => 'loop_area', 'props' => array(), 'children' => $children);
};
$widget = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateWidget' => $key), 'children' => array());
};
$shared = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateShared' => $key), 'children' => array());
};
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
// A date field in the format given ('relative' for "2 hours ago"), in place
// of the site's own date format. Never a month name: date() prints it in
// English on every site.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// A link that opens another page of the template with values of the record
// in its address (/board?board=<the record's board>): the list or the record
// view fills the address for each record ([[record-value-page-links]]).
// $params: address name => the record's field.
$page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};
// A part of a page the site search can point a result at (Search and SERP in
// the editor's options): its anchor, the switch, and the topics a visitor
// may search for that the part does not say itself.
$searchable = function ($id, $topics = '') {
    $props = array('id' => $id, 'searchSection' => true);
    if ($topics !== '') $props['searchKeywords'] = $topics;
    return $props;
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language. Its button carries
// the language's name, so it is filled ($outline false): an outlined grey
// button with words is too faint in the dark theme of the plain palette.
$lang_switcher = function ($class = '', $variant = 'secondary', $outline = false) {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => $outline, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// ── One vocabulary ──────────────────────────────────────────────────────
// The values the forms store, the filters compare, the menus link and the
// sample records carry, written once. Each is the lang() text of the
// language the template is opened in, so the options, the filters and the
// samples always match. They are filter words: renaming one in a form
// leaves every list that filters on it empty (the lists fail closed).

// The boards: key => array(name, one line, icon). The name is the value the
// Board field stores, the ?board= value of the board page and its title.
// It goes into an address URL-encoded (rawurlencode() here, the renderer
// for a record's own value), so "&" is safe in it.
$boards = array(
    'getting_started' => array(lang('Getting Started'),    lang('New here? Say hello and find your way around.'),              'bi-flag'),
    'help'            => array(lang('Questions & Help'),   lang('Ask how something works, or why it does not.'),               'bi-question-circle'),
    'ideas'           => array(lang('Ideas & Feedback'),   lang('Suggest a feature and tell us what could work better.'),      'bi-lightbulb'),
    'show_and_tell'   => array(lang('Show & Tell'),        lang('Share your setups, templates and the things you built.'),     'bi-easel'),
    'tips'            => array(lang('Tips & Tricks'),      lang('Shortcuts, workflows and small habits worth passing on.'),    'bi-magic'),
    'general'         => array(lang('General Discussion'), lang('Everything else about notes, tasks and getting things done.'), 'bi-chat-left-text'),
);
// The board names in menu order: the Board field's options.
$board_names = array();
foreach ($boards as $b) $board_names[] = $b[0];
// The board page for one board.
$board_url = function ($key) use ($boards) {
    return '{{page:board}}?board=' . rawurlencode($boards[$key][0]);
};

// What a topic is: the author picks one.
$kinds = array(lang('Question'), lang('Discussion'));

// A topic's Status, set by the moderators. A new topic is Open and public
// at once; Hidden takes it off the site ($topic_live below). Solved and
// Closed are only said: a moderator stops new replies with Stop new replies
// under the topic's replies.
$topic_statuses = array(lang('Open'), lang('Solved'), lang('Closed'), lang('Hidden'));
$solved_value   = $topic_statuses[1];
$hidden_value   = $topic_statuses[3];

// The answers of the office pick lists Pinned, Announcement and Featured.
// The first is the stored default: an option whose value is '' is never
// stored, and the control panel would save the first answer on the next
// change.
$no     = lang('No');
$yes    = lang('Yes');
$yes_no = array($no, $yes);

// Why a member reports a post, and where the moderators take the report.
$report_reasons  = array(lang('Spam'), lang('Harassment or abuse'), lang('Off-topic'), lang('Wrong board'), lang('Something else'));
$report_statuses = array(lang('Open'), lang('Resolved'), lang('Dismissed'));

// The sections of the docs: key => array(name, one line, icon). The name is
// the value the Section field stores and the ?section= value.
$doc_sections = array(
    'getting_started' => array(lang('Getting Started'), lang('Install the app and learn the basics in an afternoon.'),          'bi-rocket-takeoff'),
    'how_to'          => array(lang('How-to Guides'),   lang('Step-by-step guides for the things people do every day.'),        'bi-signpost-2'),
    'reference'       => array(lang('Reference'),       lang('Shortcuts, search syntax and every setting explained.'),          'bi-journal-code'),
    'troubleshooting' => array(lang('Troubleshooting'), lang('What to check when sync, reminders or the app misbehave.'),       'bi-wrench-adjustable'),
    'integrations'    => array(lang('Integrations'),    lang('Connect your calendar, your e-mail and the tools you already use.'), 'bi-plug'),
    'release_notes'   => array(lang('Release Notes'),   lang('What changed in each version, and why.'),                         'bi-megaphone'),
);
$doc_section_names = array();
foreach ($doc_sections as $s) $doc_section_names[] = $s[0];
// The docs page of one section.
$section_url = function ($key) use ($doc_sections) {
    return '{{page:docs_section}}?section=' . rawurlencode($doc_sections[$key][0]);
};

// The tags the tag page offers to begin with: words the sample topics carry,
// each as it is written there. A topic's tags are one line of words
// separated by commas, so the tag page finds the topics whose line contains
// the word. The moderators keep this short list in step with the forum.
$popular_tags = array(
    lang('sync'), lang('reminders'), lang('templates'), lang('notebooks'), lang('search'),
    lang('workspaces'), lang('keyboard shortcuts'), lang('export'), lang('feature request'), lang('getting started'),
);
$tag_url = function ($tag) {
    return '{{page:tag}}?tag=' . rawurlencode($tag);
};

// A guide's Status on the docs desk: the magazine's six desk statuses, in
// the magazine's words, and in an order of their own. Only the fourth makes
// a guide public ($doc_live below); Withdrawn takes one out of the docs.
$doc_statuses = array(lang('Awaiting review'), lang('In review'), lang('Changes requested'),
                      lang('Live on the site'), lang('Withdrawn'), lang('Not accepted'));
$published_value = $doc_statuses[3];

// ── The filters of the lists ────────────────────────────────────────────
// Operators and dynamic values are the renderer's English constants, never
// translated (pg_sw_form_list_filter_sql()).
$f = function ($field, $operator, $value, $dynamic = '', $attribute = null) {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic);
    if ($attribute !== null) $row['dynamic_value_attribute'] = $attribute;
    return $row;
};
// What keeps a topic public: every public list of topics and the topic page
// carry it. A topic is public from the moment it is sent; a moderator takes
// one off the site by setting it Hidden.
$topic_live = array(
    $f('status', 'is not equal to', $hidden_value),
);
$with_topic_live = function ($more = array()) use ($topic_live) {
    return array_merge($topic_live, $more);
};
// What makes a guide public: a moderator published it.
$doc_live = array(
    $f('status', 'is equal to', $published_value),
);
$with_doc_live = function ($more = array()) use ($doc_live) {
    return array_merge($doc_live, $more);
};
$in_board = function ($name) use ($f) {
    return $f('board', 'is equal to', $name);
};
// The board the address names (?board=); without one the filter is left out.
$board_param     = $f('board', 'is equal to', '', 'url parameter', 'board');
// The member the address names (?member=): the name a topic shows, which
// comes from the profile of the member who started it.
$member_param    = $f('submitter_name', 'is equal to', '', 'url parameter', 'member');
// A word of the topic's tags (?tag=). "contains", since the tags are one
// line: the page never prints the word or takes it as its title, and stays
// out of search engines.
$tag_param       = $f('tags', 'contains', '', 'url parameter', 'tag');
// The section of the docs the address names (?section=).
$section_param   = $f('section', 'is equal to', '', 'url parameter', 'section');
$is_pinned       = $f('pinned', 'is equal to', $yes);
// Not "is equal to No": a topic without the value (sent through the API)
// is not pinned either.
$not_pinned      = $f('pinned', 'is not equal to', $yes);
$is_announcement = $f('announcement', 'is equal to', $yes);
$is_solved       = $f('status', 'is equal to', $solved_value);
// No reply yet. "is less than 1", never "is equal to 0": a topic nobody has
// opened has no counter row, and its empty value is not the text '0'
// ([[form-list-filters-fail-closed]]).
$unanswered      = $f('number_of_comments', 'is less than', '1');
// Opened at least once: a list of the most viewed leaves out topics nobody
// has opened, which would otherwise fill it in date order.
$viewed          = $f('number_of_views', 'is greater than', '0');
$is_featured     = $f('featured', 'is equal to', $yes);

// The settings every public list of topics shares: the form, the topic page
// its rows open, the newest activity (a new topic or a reply) first. The
// activity order and the reply counts are read for the detail page.
$topic_pub = array(
    'regionType'           => 'form_list_view',
    'custom_form_page_id'  => '{{tab:new_topic}}',
    'detail_page_id'       => '{{tab:topic}}',
    'order_by_field'       => 'newest_activity_date_and_time',
    'order_by_direction'   => 'DESC',
    'order_by_2_field'     => 'submitted_date_and_time',
    'order_by_2_direction' => 'DESC',
);
// The settings every public list of guides shares: the newest first.
$doc_pub = array(
    'regionType'           => 'form_list_view',
    'custom_form_page_id'  => '{{tab:doc_new}}',
    'detail_page_id'       => '{{tab:doc}}',
    'order_by_field'       => 'submitted_date_and_time',
    'order_by_direction'   => 'DESC',
);

// ── Look: fonts and the template's own rules ───────────────────────────
// The fonts the editor's font picker lists, weights only.
$forum_fonts = array(
    array('family' => 'Figtree', 'weights' => array('400', '500', '600', '700'), 'enabled' => true),
    array('family' => 'JetBrains Mono', 'weights' => array('400', '600'), 'enabled' => true),
);

// The template's own CSS, in User Styles. Every class is named pf-; no
// block rule is used or named. Selectors use descendants and pseudo-classes
// only, so they hold on the editor's canvas too, and never :first-child or
// :last-child: the editor's edit button may bring its own style element in
// first. Colours are tokens only: a palette colour that only the colour
// palettes define (--pg-primary-fg) always carries a fallback for the plain
// Bootstrap palette, and the tint is mixed from tokens under @supports, so
// it follows any palette in both themes.
$forum_css = implode("\n", array(
    // Fonts and the accent: the palette's first colour as text, else the
    // theme's link colour.
    ':root,[data-bs-theme]{--pf-sans:"Figtree",system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;'
        . '--pf-mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;'
        . '--pg-font-heading:var(--pf-sans);--pg-font-body:var(--pf-sans);'
        . '--pg-heading-weight:700;--pg-display-weight:700;--pg-heading-tracking:-.015em;--pg-heading-line-height:1.2;'
        . '--pf-accent:var(--pg-primary-fg,var(--bs-link-color))}',
    'body{font-family:var(--pg-font-body)}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{font-family:var(--pg-font-heading)}',
    'code,kbd,pre,samp{font-family:var(--pf-mono)}',
    // Focus is an ink outline on every button, field, page link and close
    // button: Bootstrap's half-clear shadow all but vanishes on the tint
    // and in the dark theme (body outranks the theme's button rule).
    'body .btn:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    'body .page-link:focus-visible,body .btn-close:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    'body .form-control:focus,body .form-select:focus{outline:2px solid var(--bs-emphasis-color);outline-offset:1px}',
    'body .form-check-input:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px}',
    '.nav-underline .nav-link:focus-visible{box-shadow:none;outline:2px solid var(--bs-emphasis-color);outline-offset:2px}',
    // An accordion button's ring sits inside it: the next item would cover
    // an outer one.
    'body .accordion-button:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:-2px;box-shadow:none}',
    // The masthead's menu button has no colour variant and so no focus
    // ring of its own.
    '.pf-masthead .btn:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    '.pf-masthead [data-pg-theme-switcher] .bi{font-size:1rem}',
    '.pf-masthead{border-bottom:1px solid var(--bs-border-color)}',
    // The community's name beside its mark; a long name is cut, not wrapped
    // under the buttons.
    '.pf-brand{display:inline-flex;align-items:center;gap:.625rem;min-width:0;font-weight:700;font-size:1.125rem;letter-spacing:-.01em;color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-brand:hover{color:var(--bs-emphasis-color)}',
    '.pf-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.pf-logo{display:inline-flex;align-items:center;justify-content:center;flex:0 0 2.25rem;width:2.25rem;height:2.25rem;border-radius:var(--pg-radius,.5rem);font-size:1.125rem;color:var(--pg-on-primary,var(--bs-white));background-color:var(--bs-primary)}',
    // The main links: 44px targets, the current one underlined in the accent.
    '.pf-navlink{display:inline-flex;align-items:center;min-height:2.75rem;padding:0 .75rem;border-radius:var(--pg-radius,.375rem);font-weight:500;color:var(--bs-body-color);text-decoration:none}',
    '.pf-navlink:hover{color:var(--bs-emphasis-color);background-color:var(--bs-tertiary-bg)}',
    '.pf-navlink.active{color:var(--bs-emphasis-color);font-weight:600;text-decoration:underline;text-decoration-thickness:2px;text-decoration-color:var(--pf-accent);text-underline-offset:.55em}',
    // Bootstrap's nav-link focus is a faint shadow that dims the words: the
    // words keep their ink and an ink ring sits inside the link.
    '.pf-navlink:focus{color:var(--bs-emphasis-color)}',
    '.pf-navlink:focus-visible{box-shadow:none;outline:2px solid var(--bs-emphasis-color);outline-offset:-2px}',
    // The menu drawer's rows: 44px targets.
    '.pf-drawer{display:flex;align-items:center;gap:.75rem;min-height:2.75rem;border-bottom:1px solid var(--bs-border-color);font-weight:500;color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-drawer.active,.pf-drawer:hover{color:var(--bs-emphasis-color);text-decoration:underline;text-underline-offset:.2em}',
    '.pf-drawer .bi{color:var(--pf-accent)}',
    // A band's vertical rhythm (Bootstrap's largest spacer is 3rem).
    '.pf-band{padding-block:clamp(2.5rem,1.75rem + 3vw,4.5rem)}',
    // The page titles: the home page's, the inner pages' and a board's name.
    '.pf-display{font-weight:700;font-size:clamp(2rem,1.4rem + 2.4vw,3.25rem);line-height:1.1;letter-spacing:-.025em;text-wrap:balance}',
    '.pf-page-title{font-weight:700;font-size:clamp(1.75rem,1.4rem + 1.4vw,2.5rem);line-height:1.15;letter-spacing:-.02em;text-wrap:balance;overflow-wrap:anywhere}',
    '.pf-lead{max-width:44rem}',
    // The words over a title; ink on the tint, where the accent is fainter.
    '.pf-kicker{font-weight:700;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:var(--pf-accent)}',
    '.pf-tint .pf-kicker{color:var(--bs-emphasis-color)}',
    // A band's head: its title, and a link to the rest on the right.
    '.pf-sec-head{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:.5rem 1rem;margin-bottom:1.25rem}',
    '.pf-sec-title{margin:0;font-weight:700;font-size:1.25rem;letter-spacing:-.01em}',
    '.pf-more{display:inline-flex;align-items:center;gap:.25rem;font-weight:600;font-size:.875rem}',
    // A small label over a group, such as "Pinned".
    '.pf-label{display:flex;align-items:center;gap:.375rem;margin:0;font-weight:700;font-size:.8125rem;letter-spacing:.04em;text-transform:uppercase;color:var(--bs-secondary-color)}',
    // Bylines, dates and counts. Their links take the words' colour.
    '.pf-meta{font-size:.875rem;color:var(--bs-secondary-color)}',
    '.pf-meta a:not(.btn):not(.pf-badge){color:inherit}',
    // A member's name that opens their topics: raised over the row's
    // stretched title link, like the board's badge.
    '.pf-person{position:relative;z-index:2;font-weight:600}',
    // The search on the home page.
    '.pf-search .form-control,.pf-search .btn{min-height:3.25rem}',
    // A card: the boards, the guides, the forum's numbers and the boxes of
    // a side column. A card that is a link shows its border in the accent
    // under the pointer, and its title link stretches over it.
    '.pf-card{position:relative;padding:1.25rem;border:1px solid var(--bs-border-color);border-radius:var(--pg-radius-card,var(--bs-border-radius-lg));background-color:var(--bs-body-bg);color:var(--bs-body-color)}',
    // The tint of the hero, the pinned topics, the call-outs and the boxes
    // that stand out: after the card's rule, so a tinted card keeps it. The
    // plain tertiary background first: a custom value holding an
    // unsupported color-mix() would drop the background to nothing.
    '.pf-tint{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 7%,blue)){.pf-tint{background-color:color-mix(in srgb,var(--pf-accent) 7%,var(--bs-body-bg))}}',
    '.pf-card-link{transition:border-color .15s ease}',
    '.pf-card-link:hover{border-color:var(--pf-accent)}',
    '.pf-card-title{margin:0;font-weight:700;line-height:1.3;overflow-wrap:anywhere}',
    '.pf-card-title a{color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-card-link:hover .pf-card-title a,.pf-card-title a:focus-visible{text-decoration:underline;text-underline-offset:.15em}',
    // The forum's numbers.
    '.pf-stat{display:flex;flex-direction:column;gap:.125rem;padding:1rem 1.25rem}',
    '.pf-stat-num{margin:0;font-weight:700;font-size:clamp(1.5rem,1.2rem + 1vw,2rem);line-height:1.1;color:var(--bs-emphasis-color);font-variant-numeric:tabular-nums}',
    '.pf-stat-label{margin:0;font-size:.875rem;color:var(--bs-secondary-color);overflow-wrap:anywhere}',
    // A board: its icon beside its name, its line and its number of topics.
    '.pf-board{display:flex;gap:1rem;align-items:flex-start}',
    '.pf-board-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 2.75rem;width:2.75rem;height:2.75rem;border-radius:var(--pg-radius,.5rem);font-size:1.25rem;color:var(--pf-accent);background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 12%,blue)){.pf-board-icon{background-color:color-mix(in srgb,var(--pf-accent) 12%,var(--bs-body-bg))}}',
    // The boards in a side column.
    '.pf-board-nav{display:flex;align-items:center;gap:.625rem;min-height:2.75rem;padding:.25rem 0;border-bottom:1px solid var(--bs-border-color);color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-board-nav:hover{text-decoration:underline;text-underline-offset:.15em}',
    '.pf-board-nav .bi{color:var(--pf-accent)}',
    // A topic in a list: its badges, its title over the whole row, who
    // started it and when it was last active; its replies and views beside.
    '.pf-topics{border-top:1px solid var(--bs-border-color)}',
    '.pf-topic{position:relative;display:flex;gap:1rem;align-items:flex-start;padding:1rem 0;border-bottom:1px solid var(--bs-border-color)}',
    '.pf-topic-main{flex:1 1 auto;min-width:0}',
    '.pf-topic-title{margin:0 0 .25rem;font-weight:600;font-size:1.0625rem;line-height:1.35;overflow-wrap:anywhere}',
    '.pf-topic-title a{color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-topic:hover .pf-topic-title a,.pf-topic-title a:focus-visible{text-decoration:underline;text-underline-offset:.15em}',
    // The pinned topics on the tint, their rows inset from its edges.
    '.pf-pinned .pf-topic{padding-inline:1rem}',
    // The badges; the board's is a link of its own, raised over the row's
    // stretched link so it can be clicked (the rest of the row opens the
    // topic).
    '.pf-badges{display:flex;flex-wrap:wrap;align-items:center;gap:.375rem}',
    '.pf-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.125rem .5rem;border:1px solid var(--bs-border-color);border-radius:var(--pg-radius-badge,50rem);font-weight:600;font-size:.75rem;line-height:1.5;color:var(--bs-body-color);background-color:var(--bs-body-bg);text-decoration:none}',
    '.pf-badge-board{position:relative;z-index:2;color:var(--bs-primary-text-emphasis);background-color:var(--bs-primary-bg-subtle);border-color:var(--bs-primary-border-subtle)}',
    'a.pf-badge-board:hover{color:var(--bs-primary-text-emphasis);text-decoration:underline}',
    '.pf-badge-pin{color:var(--bs-emphasis-color);border-color:var(--bs-emphasis-color)}',
    // The replies and the views: the number under its label, read label
    // first by a screen reader ("Replies 3").
    '.pf-counts{display:flex;flex:0 0 auto;gap:1rem;text-align:center}',
    '.pf-count{display:flex;flex-direction:column-reverse;min-width:3.25rem;margin:0}',
    '.pf-count-num{font-weight:700;font-size:1rem;color:var(--bs-emphasis-color);font-variant-numeric:tabular-nums}',
    '.pf-count-label{font-size:.75rem;color:var(--bs-secondary-color)}',
    '@media (max-width:575.98px){.pf-counts{gap:.5rem}.pf-count{min-width:2.75rem}}',
    // A short list of topics in a side column.
    '.pf-compact{position:relative;padding:.75rem 0;border-bottom:1px solid var(--bs-border-color)}',
    '.pf-compact-title{margin:0 0 .125rem;font-weight:600;font-size:.9375rem;line-height:1.4;overflow-wrap:anywhere}',
    '.pf-compact-title a{color:var(--bs-emphasis-color);text-decoration:none}',
    '.pf-compact:hover .pf-compact-title a,.pf-compact-title a:focus-visible{text-decoration:underline;text-underline-offset:.15em}',
    // A guide in a list: its title over the whole row, its summary and its
    // details.
    '.pf-guide{position:relative;padding:1rem 0;border-bottom:1px solid var(--bs-border-color)}',
    '.pf-guide:hover .pf-topic-title a{text-decoration:underline;text-underline-offset:.15em}',
    // The tags to pick from: 32px targets that wrap.
    '.pf-tags{display:flex;flex-wrap:wrap;gap:.5rem;margin:0;padding:0;list-style:none}',
    '.pf-tag{display:inline-flex;align-items:center;min-height:2rem;padding:.25rem .75rem;border:1px solid var(--bs-border-color);border-radius:var(--pg-radius-badge,50rem);font-weight:500;font-size:.875rem;color:var(--bs-body-color);background-color:var(--bs-body-bg);text-decoration:none}',
    '.pf-tag:hover{color:var(--bs-emphasis-color);border-color:var(--pf-accent);text-decoration:underline}',
    '.pf-tag:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px}',
    // An announcement: on the primary's own subtle pair.
    '.pf-announce{position:relative;display:flex;gap:.875rem;align-items:flex-start;padding:1rem 1.25rem;border:1px solid var(--bs-primary-border-subtle);border-radius:var(--pg-radius-card,var(--bs-border-radius-lg));color:var(--bs-primary-text-emphasis);background-color:var(--bs-primary-bg-subtle)}',
    '.pf-announce a{color:inherit;text-decoration:none}',
    '.pf-announce:hover a,.pf-announce a:focus-visible{text-decoration:underline;text-underline-offset:.15em}',
    '.pf-announce .pf-meta{color:inherit}',
    // A band whose list found nothing leaves the page, its heading with it.
    // An older browser without :has() shows the list's empty sentence.
    '.pf-vanish:has(.pg-sw-empty){display:none}',
    // What a member writes: contained first, so a stretched link or a fixed
    // layer stays inside its box and a wide table scrolls; then the
    // reading measure and type, and code in the mono font.
    '.pf-post{position:relative;contain:paint;overflow-wrap:anywhere;font-size:1.0625rem;line-height:1.7}',
    '.pf-post p,.pf-post ul,.pf-post ol{margin:0 0 1.1em}',
    '.pf-post h2{margin:1.75em 0 .6em;font-weight:700;font-size:1.375rem;line-height:1.3}',
    '.pf-post h3{margin:1.5em 0 .5em;font-weight:700;font-size:1.125rem}',
    '.pf-post li{margin-bottom:.35em}',
    '.pf-post a{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.18em}',
    '.pf-post img{max-width:100%;height:auto}',
    '.pf-post blockquote{margin:1.5rem 0;padding-left:1rem;border-left:3px solid var(--bs-border-color);color:var(--bs-secondary-color)}',
    '.pf-post table{display:block;max-width:100%;overflow-x:auto}',
    '.pf-post code{padding:.1em .35em;border-radius:.25rem;font-size:.875em;color:var(--bs-emphasis-color);background-color:var(--bs-tertiary-bg)}',
    '.pf-post pre{margin:0 0 1.25em;padding:1rem;overflow-x:auto;border:1px solid var(--bs-border-color);border-radius:var(--pg-radius,.375rem);font-size:.875rem;line-height:1.6;color:var(--bs-emphasis-color);background-color:var(--bs-tertiary-bg)}',
    '.pf-post pre code{padding:0;font-size:inherit;background:none}',
    // A guide reads at a comfortable line length on a wide screen.
    '.pf-measure{max-width:42rem}',
    // The facts about a topic: a label and its value on each row.
    '.pf-fact{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.25rem 1rem;padding:.5rem 0;border-bottom:1px solid var(--bs-border-color);font-size:.875rem}',
    '.pf-fact dt{font-weight:400;color:var(--bs-secondary-color)}',
    '.pf-fact dd{margin:0;font-weight:600;color:var(--bs-emphasis-color);text-align:end;overflow-wrap:anywhere}',
    // A side column that stays in view beside a long thread from lg, on a
    // screen tall enough to show all of it: on a shorter one its lower
    // cards would stay out of view for the whole thread.
    '@media (min-width:992px) and (min-height:56rem){.pf-sticky{position:sticky;top:1.5rem}}',
    // A call-out: an icon beside its words and its button.
    '.pf-callout{display:flex;flex-direction:column;gap:1rem;padding:1.5rem;border-radius:var(--pg-radius-card,var(--bs-border-radius-lg))}',
    '@media (min-width:768px){.pf-callout{flex-direction:row;align-items:center}}',
    // A card's line, three lines at most.
    '.pf-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}',
    // A flex item that may shrink below its words (Bootstrap has no
    // min-width utility).
    '.pf-min-0{min-width:0}',
    // The platform's notices in the replies print their links without
    // .alert-link: they take the alert's link colour.
    '.pf-replies .alert a:not(.btn){color:var(--bs-alert-link-color)}',
    // A reply of a topic or a guide: a rule before a reply's body (the
    // layout shows it for replies only), its words, then its Reply, Share
    // and Edit links as 44px targets. A reply two deep moves in further by
    // the depth the server writes on each comment's own element
    // (data-pg-comment-depth); the editor's canvas shows the rule alone.
    // Share and Edit take the secondary text colour, which keeps its
    // contrast in both themes (Bootstrap's link-secondary does not in the
    // dark one); Reply keeps the ink of its link-body-emphasis class.
    '.pf-reply{display:flex;gap:1rem;padding:1.25rem 0;border-bottom:1px solid var(--bs-border-color)}',
    '.pf-reply-rule{flex:0 0 2px;margin-inline-start:.75rem;background-color:var(--bs-border-color)}',
    '.pf-reply-body{flex:1 1 auto;min-width:0}',
    '.pf-reply[data-pg-comment-depth="2"]{padding-inline-start:1.5rem}',
    '@media (min-width:768px){.pf-reply[data-pg-comment-depth="2"]{padding-inline-start:2.75rem}}',
    '.pf-reply-actions{display:flex;flex-wrap:wrap;column-gap:1.25rem}',
    '.pf-reply-actions a{display:inline-flex;align-items:center;min-height:2.75rem;font-weight:600;font-size:.875rem;color:var(--bs-secondary-color)}',
    '.pf-reply-actions a:hover{color:var(--bs-emphasis-color)}',
    // A row with the moderators' pencil in its corner (pg-edit-host, put
    // there by the list for whoever may edit the record) moves its words
    // clear of it.
    '.pf-topic.pg-edit-host,.pf-guide.pg-edit-host,.pf-compact.pg-edit-host{padding-inline-start:2.75rem}',
    // A guide card's pencil goes into the column around it, over the
    // card's corner: the card moves its words clear of it the same way.
    '.pg-edit-host .pf-card-link{padding-inline-start:2.75rem}',
    // A person's picture in the staff directory.
    '.pf-avatar{width:3.75rem;height:3.75rem;border-radius:50%;object-fit:cover}',
    '@media (prefers-reduced-motion:reduce){.pf-card-link{transition:none}}',
));

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    foreach ($more as $k => $v) $attrs[] = $attr($k, $v);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
// $cf: the field's form settings ('wysiwyg' => true makes it a rich text
// box, 'office_use_only' => true a field only the moderators fill).
$textarea = function ($id, $name, $rows, $required = true, $placeholder = '', $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    if ($placeholder !== '') $attrs[] = $attr('placeholder', $placeholder);
    foreach ($more as $k => $v) $attrs[] = $attr($k, $v);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
};
// A pick list: the first, empty option is the prompt, not an answer. Each
// answer's value is its own words, so the e-mails and the lists read as the
// member chose. $selected is the answer chosen to begin with: there is no
// prompt then, so every option is a stored answer (an office pick list
// needs that: the control panel prints only the stored options).
$select = function ($id, $name, $prompt, $options, $required = true, $selected = '', $cf = array()) use ($el, $attr) {
    $children = array();
    if ($selected === '') {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', ''))));
    }
    foreach ($options as $text) {
        $oattrs = array($attr('value', $text));
        if ($text === $selected) $oattrs[] = $attr('selected', '');
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => $oattrs));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('select', 'form-select', lang('Field'), $children, $props);
};
// A group of radio buttons under its legend: the legend names the field,
// each button's own label its answer.
$choices = function ($id, $name, $legend, $options, $required = true) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', 'radio'), $attr('name', $name), $attr('value', $o));
        if ($required) $rattrs[] = $attr('required', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o, '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};
// A hint under a field, named by the field's aria-describedby. Never under
// an office-use-only field: the hint would stay on the page for a member
// after the field itself is taken out.
$help = function ($id, $text) use ($el) {
    return $el('div', 'form-text', lang('Help Text'), array(), array('id' => $id, 'text' => $text));
};
// A field with a hint under it, which the field names as its description.
// Under a rich text box the hint stays in view: the editor hides the text
// area, not the hint.
$field_help = function ($id, $label, $control, $help_text) use ($el, $attr, $help) {
    $help_id = $id . '-help';
    $control['props']['_attrs'][] = $attr('aria-describedby', $help_id);
    return $el('div', 'mb-3', $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
        $help($help_id, $help_text),
    ));
};
// An office-use-only field: the moderators fill it in, a member never sees
// it, and a new record stores its selected option or nothing. Each sits
// alone in a box with its label: a legend or a hint around it would stay on
// a member's form (only empty wrappers are pruned).
$office = function ($control) {
    $cf = isset($control['props']['_cf']) && is_array($control['props']['_cf']) ? $control['props']['_cf'] : array();
    $control['props']['_cf'] = array_merge($cf, array('office_use_only' => true));
    return $control;
};
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the member must tick: what was agreed to is stored with the form.
$agree = function ($id, $name, $text) use ($el, $attr) {
    return $el('div', 'form-check mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    ));
};
// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*', $required = false) use ($el, $attr) {
    $attrs = array($attr('type', 'file'), $attr('name', $name), $attr('accept', $accept));
    if ($required) $attrs[] = $attr('required', '');
    return $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs,
        '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};
// A group of fields under its legend.
$fieldset = function ($legend, $children) use ($el) {
    return $el('fieldset', 'mb-4', $legend, array_merge(array(
        $el('legend', 'h5 fw-bold mb-3 pb-2 border-bottom', lang('Legend'), array(), array('text' => $legend)),
    ), $children));
};
// The name and e-mail of whoever writes to the moderators: the contact's
// own fields, so the site knows them next time.
$person_fields = function ($prefix) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '12'),
    ), '3');
};

// ── Records ─────────────────────────────────────────────────────────────

// The record's moderation button, shown to whoever may edit the submissions
// of its form; it opens the record in the control panel and comes back. A
// filled button: an outlined grey one is too faint in the dark theme of the
// plain palette.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};
// The messages and notes of the desk, the inbox and the report pages: the
// platform's fixed comments block, placed in the page's own tree. The topic
// and guide pages draw theirs with the Comments widget ($reply_thread).
$comments_block = function () {
    return array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array());
};
// The notice a record page shows above the record once its form is sent,
// and ($with_account) the account opened for a visitor who had none.
$sent_notices = function ($thanks, $with_account = false) use ($el, $icon, $span, $para, $link, $attr, $bind, $show_when) {
    $out = array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
    );
    if ($with_account) {
        $out[] = $el('div', 'alert alert-info', lang('New Account'), array(
            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
            $el('p', 'mb-1', lang('Sign-in Email'), array(
                $span(lang('Email') . ': ', '', lang('Label')),
                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
            )),
            // The single-use set password link, for the person who sent the
            // form while it is live; the sentence below covers a used or
            // expired link.
            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
        ), $show_when('has_new_account'));
    }
    return $out;
};
// A label and its value in a record's facts; $optional rows leave when the
// value is empty.
$fact = function ($label, $value_children, $optional = false) use ($el) {
    return $el('div', 'pf-fact' . ($optional ? ' pg-hide-if-empty' : ''), $label, array(
        $el('dt', '', lang('Label'), array(), array('text' => $label)),
        $el('dd', '', lang('Value'), $value_children),
    ));
};
// A record's Status as a badge.
$status_badge = function ($sample, $class = 'pf-badge pf-badge-pin') use ($span, $bind) {
    return $span($sample, $class, lang('Status'), $bind('status'));
};
// The top of a record page: back to its list, and the moderators' button.
$record_toolbar = function ($back_text, $back_href, $edit_text = '') use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 d-print-none', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-body-emphasis text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => $back_href)),
        $edit_button($edit_text),
    ));
};
// Words of the record in a box of their own, under their label; the box
// leaves when they are empty.
$record_words = function ($label, $sample, $token, $class = 'pf-card mb-4') use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $label, array(
        $para($label, 'pf-label mb-2'),
        $el('div', 'pg-field-value text-break', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// The name of whoever wrote to the moderators from the contact page.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// ── The rows of the lists ───────────────────────────────────────────────
// A topic row is an article whose title link stretches over the whole row;
// the badges above the title are raised over that link, so the board's link
// in them opens the board. No row's own element is a .row: the editor's
// edit button goes into a record's first element, and a row would pad it
// as a column. $s is the sample topic a row shows in the editor and the
// installer's preview ($topic_sample()).

// A dot between two details, silent to screen readers.
$dot = function () use ($span, $attr) {
    return $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
// The record's board, linking to the board's page with its value.
$board_badge = function ($s) use ($link, $page_link) {
    return $link($s['board'], '#', 'pf-badge pf-badge-board', array(),
        array_merge($page_link('board', array('board' => 'board')), array('_bindings' => array('text' => 'board', 'href' => '__page_link'))));
};
// The badges of a topic: pinned ($pinned: every row of a list of pinned
// topics), the board, the kind and the Status.
$topic_badges = function ($s, $pinned = false, $class = 'mb-1') use ($el, $icon, $span, $bind, $board_badge) {
    $children = array();
    if ($pinned) {
        $children[] = $el('span', 'pf-badge pf-badge-pin', lang('Pinned'), array(
            $icon('bi-pin-angle-fill'),
            $span(lang('Pinned'), '', lang('Text')),
        ));
    }
    $children[] = $board_badge($s);
    $children[] = $span($s['kind'], 'pf-badge', lang('Kind'), $bind('kind'));
    $children[] = $span($s['status'], 'pf-badge', lang('Status'), $bind('status'));
    return $el('p', trim('pf-badges ' . $class), lang('Badges'), $children);
};
// The name of the member who started the topic, linking to the topics they
// started (/member?member=<the name>). The name comes from their profile
// (submitter_name); the link is the element the empty test reads, so a
// record without a name drops the whole byline around it.
$member_link = function ($s, $class = 'pg-field-value pf-person') use ($link, $page_link) {
    return $link($s['author'], '#', $class, array(),
        array_merge($page_link('member', array('member' => 'submitter_name')), array('_bindings' => array('text' => 'submitter_name', 'href' => '__page_link'))));
};
// Who started the topic and when it was last active, and who wrote its last
// reply. A record without an author's name (a sample sent by the setup
// account) shows no "Started by" at all, and a topic without replies no
// "last reply". A list whose topics never have a reply (a sample with no
// 'replier') leaves the last reply out.
$topic_meta = function ($s) use ($el, $span, $bind, $bind_date, $dot, $member_link) {
    $children = array(
        $el('span', 'pg-hide-if-empty', lang('Author'), array(
            $span(lang('Started by'), 'me-1', lang('Label')),
            $member_link($s),
            $dot(),
        )),
        $span(lang('Last activity:'), 'me-1', lang('Label')),
        $span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')),
    );
    if ($s['replier'] !== '') {
        $children[] = $el('span', 'pg-hide-if-empty', lang('Last Reply'), array(
            $dot(),
            $span(lang('Last reply by'), 'me-1', lang('Label')),
            $span($s['replier'], 'pg-field-value', lang('Name'), $bind('newest_comment_name')),
        ));
    }
    return $el('p', 'pf-meta mb-0', lang('Byline and Activity'), $children);
};
// A topic's replies and views: each number under its label, the label read
// first by a screen reader.
$topic_counts = function ($s) use ($el, $span, $bind) {
    return $el('div', 'pf-counts', lang('Counts'), array(
        $el('p', 'pf-count', lang('Replies'), array(
            $span(lang('Replies'), 'pf-count-label', lang('Label')),
            $span($s['replies'], 'pf-count-num', lang('Number of Replies'), $bind('number_of_comments')),
        )),
        $el('p', 'pf-count', lang('Views'), array(
            $span(lang('Views'), 'pf-count-label', lang('Label')),
            $span($s['views'], 'pf-count-num', lang('Number of Views'), $bind('number_of_views')),
        )),
    ));
};
// (a) A topic of a list. $tag is the title's level under its list's heading.
$topic_row = function ($s, $pinned = false, $tag = 'h3') use ($el, $link, $topic_badges, $topic_meta, $topic_counts) {
    return $el('article', 'pf-topic', lang('Topic Row'), array(
        $el('div', 'pf-topic-main', lang('Words'), array(
            $topic_badges($s, $pinned),
            $el($tag, 'pf-topic-title', lang('Title'), array(
                $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $topic_meta($s),
        )),
        $topic_counts($s),
    ));
};
// The rows of a list of topics, in the bordered list.
$topic_rows = function ($s, $pinned = false, $tag = 'h3') use ($el, $loop, $topic_row) {
    return $el('div', 'pf-topics' . ($pinned ? ' pf-pinned pf-tint' : ''), lang('List'), array($loop(array($topic_row($s, $pinned, $tag)))));
};
// (b) A topic in a side column: its title, its board and either when it was
// sent ('sent') or how often it was opened ('views').
$compact_row = function ($s, $with) use ($el, $link, $span, $bind, $bind_date, $dot) {
    $detail = ($with === 'views')
        ? $el('span', '', lang('Views'), array(
            $span(lang('Views:'), 'me-1', lang('Label')),
            $span('48', 'fw-semibold', lang('Number of Views'), $bind('number_of_views')),
        ))
        : $span(lang('2 hours ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative'));
    return $el('li', 'pf-compact', lang('Topic Row'), array(
        $el('h3', 'pf-compact-title', lang('Title'), array(
            $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
        )),
        $el('p', 'pf-meta small mb-0', lang('Details'), array(
            $span($s['board'], '', lang('Board'), $bind('board')),
            $dot(),
            $detail,
        )),
    ));
};
// (c) An announcement: the megaphone beside its title and when it was posted.
$announce_row = function ($s) use ($el, $link, $para, $icon, $bind_date) {
    return $el('article', 'pf-announce', lang('Announcement'), array(
        $icon('bi-megaphone', 'fs-4 flex-shrink-0'),
        $el('div', 'pf-min-0', lang('Words'), array(
            $el('h3', 'h6 fw-bold mb-1', lang('Title'), array(
                $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $para(lang('2 hours ago'), 'pf-meta mb-0', $bind_date('submitted_date_and_time', 'relative')),
        )),
    ));
};
// (d) A guide card: its section over its title and its summary.
$guide_card = function ($g, $tag = 'h3') use ($el, $link, $para, $bind) {
    return $el('article', 'pf-card pf-card-link h-100', lang('Guide Card'), array(
        $para($g['section'], 'pf-kicker mb-2', $bind('section')),
        $el($tag, 'pf-card-title h5 mb-2', lang('Title'), array(
            $link($g['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
        )),
        $para($g['summary'], 'text-body-secondary small mb-0 pf-clamp-3', $bind('summary')),
    ));
};
// (e) A board's tile: its icon, its name linking to its page, its line and
// its number of live topics (a count of its own, $widget 'board_count_*').
$board_tile = function ($key, $tag = 'h3') use ($el, $link, $para, $icon, $widget, $boards, $board_url) {
    $b = $boards[$key];
    return $el('article', 'pf-card pf-card-link pf-board h-100', $b[0], array(
        $el('span', 'pf-board-icon', lang('Icon'), array($icon($b[2]))),
        $el('div', 'pf-min-0', lang('Words'), array(
            $el($tag, 'pf-card-title h6 mb-1', lang('Title'), array($link($b[0], $board_url($key), 'stretched-link'))),
            $para($b[1], 'small text-body-secondary mb-2'),
            $widget('board_count_' . $key),
        )),
    ));
};
// Every board's tile, two to a row from md, three from lg.
$board_tiles = function ($tag = 'h3') use ($el, $boards, $board_tile) {
    $cols = array();
    foreach (array_keys($boards) as $key) {
        $cols[] = $el('div', 'col-md-6 col-lg-4', lang('Column'), array($board_tile($key, $tag)));
    }
    return $el('div', 'row g-3 g-lg-4', lang('Boards'), $cols);
};
// The boards as a short list of links, for a side column.
$board_nav = function () use ($el, $heading, $link, $icon, $span, $attr, $boards, $board_url) {
    $items = array();
    foreach ($boards as $key => $b) {
        $items[] = $el('li', '', lang('List Item'), array(
            $el('a', 'pf-board-nav', lang('Link'), array(
                $icon($b[2]),
                $span($b[0], '', lang('Link Text')),
            ), array('href' => $board_url($key))),
        ));
    }
    return $el('nav', 'pf-card mb-4', lang('Boards'), array(
        $heading('h2', lang('Boards'), 'h6 fw-bold mb-2'),
        $el('ul', 'list-unstyled mb-0', lang('Links'), $items),
    ), array('_attrs' => array($attr('aria-label', lang('Boards')))));
};
// (f) A guide in a list: its title over the row, its summary, and under them
// its section ($with_section), the version it applies to and its byline.
$doc_row = function ($g, $with_section = true, $tag = 'h3') use ($el, $link, $span, $para, $bind, $dot) {
    $details = array();
    if ($with_section) {
        $details[] = $span($g['section'], '', lang('Section'), $bind('section'));
        $details[] = $dot();
    }
    $details[] = $el('span', 'pg-hide-if-empty', lang('Version'), array(
        $span(lang('Version:'), 'me-1', lang('Label')),
        $span($g['version'], 'pg-field-value', lang('Value'), $bind('version')),
        $dot(),
    ));
    $details[] = $span(lang('Written by'), 'me-1', lang('Label'));
    $details[] = $span($g['byline'], '', lang('Byline'), $bind('byline'));
    return $el('article', 'pf-guide', lang('Guide Row'), array(
        $el($tag, 'pf-topic-title', lang('Title'), array(
            $link($g['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
        )),
        $para($g['summary'], 'text-body-secondary mb-1', $bind('summary')),
        $el('p', 'pf-meta small mb-0', lang('Details'), $details),
    ));
};
// (g) A section's tile: its icon, its name linking to its page, its line and
// its number of published guides (a count of its own, 'doc_count_*').
$section_tile = function ($key, $tag = 'h3') use ($el, $link, $para, $icon, $widget, $doc_sections, $section_url) {
    $s = $doc_sections[$key];
    return $el('article', 'pf-card pf-card-link pf-board h-100', $s[0], array(
        $el('span', 'pf-board-icon', lang('Icon'), array($icon($s[2]))),
        $el('div', 'pf-min-0', lang('Words'), array(
            $el($tag, 'pf-card-title h6 mb-1', lang('Title'), array($link($s[0], $section_url($key), 'stretched-link'))),
            $para($s[1], 'small text-body-secondary mb-2'),
            $widget('doc_count_' . $key),
        )),
    ));
};
// Every section's tile, two to a row from md, three from lg.
$section_tiles = function ($tag = 'h3') use ($el, $doc_sections, $section_tile) {
    $cols = array();
    foreach (array_keys($doc_sections) as $key) {
        $cols[] = $el('div', 'col-md-6 col-lg-4', lang('Column'), array($section_tile($key, $tag)));
    }
    return $el('div', 'row g-3 g-lg-4', lang('Sections of the docs'), $cols);
};
// The sections as a short list of links, for a side column.
$doc_nav = function () use ($el, $heading, $icon, $span, $attr, $doc_sections, $section_url) {
    $items = array();
    foreach ($doc_sections as $key => $s) {
        $items[] = $el('li', '', lang('List Item'), array(
            $el('a', 'pf-board-nav', lang('Link'), array(
                $icon($s[2]),
                $span($s[0], '', lang('Link Text')),
            ), array('href' => $section_url($key))),
        ));
    }
    $items[] = $el('li', '', lang('List Item'), array(
        $el('a', 'pf-board-nav', lang('Link'), array(
            $icon('bi-journal-text'),
            $span(lang('All guides'), '', lang('Link Text')),
        ), array('href' => '{{page:docs}}')),
    ));
    return $el('nav', 'pf-card mb-4', lang('Sections of the docs'), array(
        $heading('h2', lang('Sections of the docs'), 'h6 fw-bold mb-2'),
        $el('ul', 'list-unstyled mb-0', lang('Links'), $items),
    ), array('_attrs' => array($attr('aria-label', lang('Sections of the docs')))));
};
// The tags to pick from, each opening the topics that carry it.
$tag_list = function ($class = 'pf-tags') use ($el, $link, $popular_tags, $tag_url) {
    $items = array();
    foreach ($popular_tags as $tag) {
        $items[] = $el('li', '', lang('List Item'), array($link($tag, $tag_url($tag), 'pf-tag')));
    }
    return $el('ul', $class, lang('Tags'), $items);
};

// The rows of the moderators' and the members' own lists. Each is a topic
// row's shape: its badges, its title over the whole row, a line of
// details, and its counts beside. On the moderators' pages the list puts
// its pencil (the record in the control panel) in each row's corner.

// A number under its label beside a row, read label first.
$row_count = function ($label, $sample, $name) use ($el, $span, $bind) {
    return $el('div', 'pf-counts', lang('Counts'), array(
        $el('p', 'pf-count', $label, array(
            $span($label, 'pf-count-label', lang('Label')),
            $span($sample, 'pf-count-num', $name, $bind('number_of_comments')),
        )),
    ));
};
// (h) A topic on the moderators' pages: its Status first among its badges;
// who started it, by the name on their profile and by their account's user
// name; when it was posted and last active; its replies and views. $linked
// false: a list that holds hidden topics, which only the control panel
// opens (the pencil on its row), so no title links to a page that would
// not show its topic.
$staff_topic_row = function ($s, $linked = true, $tag = 'h3') use ($el, $link, $span, $bind, $bind_date, $dot, $board_badge, $member_link, $topic_counts, $status_badge) {
    $title = $linked
        ? $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view')))
        : $span($s['title'], '', lang('Title'), $bind('title'));
    return $el('article', 'pf-topic', lang('Topic Row'), array(
        $el('div', 'pf-topic-main', lang('Words'), array(
            $el('p', 'pf-badges mb-1', lang('Badges'), array(
                $status_badge($s['status']),
                $board_badge($s),
                $span($s['kind'], 'pf-badge', lang('Kind'), $bind('kind')),
            )),
            $el($tag, 'pf-topic-title', lang('Title'), array($title)),
            $el('p', 'pf-meta mb-0', lang('Byline and Activity'), array(
                $el('span', 'pg-hide-if-empty', lang('Author'), array(
                    $span(lang('Started by'), 'me-1', lang('Label')),
                    $member_link($s),
                    $dot(),
                )),
                $el('span', 'pg-hide-if-empty', lang('Account'), array(
                    $span(lang('Account:'), 'me-1', lang('Label')),
                    $span($s['account'], 'pg-field-value', lang('User Name'), $bind('submitter')),
                    $dot(),
                )),
                $span(lang('Posted:'), 'me-1', lang('Label')),
                $span(lang('2 days ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
                $dot(),
                $span(lang('Last activity:'), 'me-1', lang('Label')),
                $span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')),
            )),
        )),
        $topic_counts($s),
    ));
};
$staff_topic_rows = function ($s, $linked = true, $tag = 'h3') use ($el, $loop, $staff_topic_row) {
    return $el('div', 'pf-topics', lang('List'), array($loop(array($staff_topic_row($s, $linked, $tag)))));
};
// (i) A guide on the docs desk: its Status and section, its title (opening
// the list's detail page: the guide's page on the desk, or the published
// guide), the name it is signed with, when it was sent and last active,
// and the messages under it ($count_label: the feedback under a published
// guide). The last activity shows which guide has gone quiet.
$desk_doc_row = function ($g, $count_label = '', $tag = 'h3') use ($el, $link, $span, $bind, $bind_date, $dot, $status_badge, $row_count) {
    return $el('article', 'pf-topic', lang('Guide Row'), array(
        $el('div', 'pf-topic-main', lang('Words'), array(
            $el('p', 'pf-badges mb-1', lang('Badges'), array(
                $status_badge($g['status']),
                $span($g['section'], 'pf-badge', lang('Section'), $bind('section')),
            )),
            $el($tag, 'pf-topic-title', lang('Title'), array(
                $link($g['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $el('p', 'pf-meta mb-0', lang('Details'), array(
                $span(lang('Written by'), 'me-1', lang('Label')),
                $span($g['byline'], 'fw-semibold', lang('Byline'), $bind('byline')),
                $dot(),
                $span(lang('Sent:'), 'me-1', lang('Label')),
                $span(lang('2 days ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
                $dot(),
                $span(lang('Last activity:'), 'me-1', lang('Label')),
                $span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')),
            )),
        )),
        $count_label === ''
            ? $row_count(lang('Messages'), '2', lang('Number of Messages'))
            : $row_count($count_label, '4', lang('Number of Comments')),
    ));
};
$desk_doc_rows = function ($g, $count_label = '') use ($el, $loop, $desk_doc_row) {
    return $el('div', 'pf-topics', lang('List'), array($loop(array($desk_doc_row($g, $count_label)))));
};
// (j) A report: its Status, its reason as the title (opening the report's
// page), the post it names, who sent it and when, and the moderators'
// notes under it. The post is what the member typed: it is printed as
// text, never as a link, so a moderator never follows an address a member
// wrote without reading it first.
$report_row = function ($r, $tag = 'h3') use ($el, $link, $span, $bind, $bind_date, $dot, $status_badge, $row_count) {
    return $el('article', 'pf-topic', lang('Report Row'), array(
        $el('div', 'pf-topic-main', lang('Words'), array(
            $el('p', 'pf-badges mb-1', lang('Badges'), array($status_badge($r['status']))),
            $el($tag, 'pf-topic-title', lang('Title'), array(
                $link($r['reason'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'reason', 'href' => 'form_item_view'))),
            )),
            $el('p', 'pf-meta text-break mb-1', lang('Post'), array(
                $span(lang('Post:'), 'me-1', lang('Label')),
                $span($r['post'], 'font-monospace', lang('Value'), $bind('post')),
            )),
            $el('p', 'pf-meta mb-0', lang('Details'), array(
                $el('span', 'pg-hide-if-empty', lang('Reported By'), array(
                    $span(lang('Reported by'), 'me-1', lang('Label')),
                    $span($r['by'], 'pg-field-value fw-semibold', lang('Name'), $bind('submitter_name')),
                    $dot(),
                )),
                $span(lang('Sent:'), 'me-1', lang('Label')),
                $span(lang('2 hours ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
            )),
        )),
        $row_count(lang('Notes'), '1', lang('Number of Notes')),
    ));
};
$report_rows = function ($r) use ($el, $loop, $report_row) {
    return $el('div', 'pf-topics', lang('List'), array($loop(array($report_row($r)))));
};
// (k) A conversation in the inbox: its subject (opening the conversation),
// who wrote it (the moderators see everybody's), when, and the messages
// under it.
$conversation_row = function () use ($el, $link, $span, $bind_date, $dot, $person_name, $row_count) {
    return $el('article', 'pf-topic', lang('Conversation'), array(
        $el('div', 'pf-topic-main', lang('Words'), array(
            $el('h2', 'pf-topic-title', lang('Subject'), array(
                $link(lang('My account'), '#', 'stretched-link', array(), array('_bindings' => array('text' => 'topic', 'href' => 'form_item_view'))),
            )),
            $el('p', 'pf-meta mb-0', lang('Details'), array(
                $el('span', 'fw-semibold', lang('Sender'), $person_name()),
                $dot(),
                $span(lang('Sent:'), 'me-1', lang('Label')),
                $span(lang('2 days ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
                $dot(),
                $span(lang('Last activity:'), 'me-1', lang('Label')),
                $span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')),
            )),
        )),
        $row_count(lang('Messages'), '2', lang('Number of Messages')),
    ));
};

// ── The parts every page repeats ────────────────────────────────────────

// The masthead: the skip link; the menu button (phone), the community's
// name, the main links from lg, then the theme, the language, the sign-in
// and Start a Topic; and the menu drawer. Not sticky, so the drawer can
// live inside it. Left out of a printed page.
$masthead = function () use ($el, $link, $icon, $span, $attr, $lang_switcher, $widget) {
    $nav_items = array();
    foreach (array(
        array(lang('Forum'), '{{page:forum}}'),
        array(lang('Docs'), '{{page:docs}}'),
        array(lang('Guidelines'), '{{page:guidelines}}'),
        array(lang('Search'), '{{page:search}}'),
    ) as $m) {
        $nav_items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($m[0], $m[1], 'nav-link pf-navlink')));
    }
    $drawer_link = function ($text, $href, $icon_name) use ($el, $icon, $span) {
        return $el('li', '', lang('Nav Item'), array(
            $el('a', 'pf-drawer', lang('Link'), array($icon($icon_name), $span($text, '', lang('Link Text'))), array('href' => $href)),
        ));
    };
    $drawer = array(
        $drawer_link(lang('Homepage'), '{{page:home}}', 'bi-house'),
        $drawer_link(lang('Forum'), '{{page:forum}}', 'bi-chat-square-text'),
        $drawer_link(lang('Boards'), '{{page:board}}', 'bi-grid'),
        $drawer_link(lang('Docs'), '{{page:docs}}', 'bi-journal-text'),
        $drawer_link(lang('Guidelines'), '{{page:guidelines}}', 'bi-heart'),
        $drawer_link(lang('Search'), '{{page:search}}', 'bi-search'),
    );
    $drawer_more = array(
        $drawer_link(lang('Start a topic'), '{{page:new_topic}}', 'bi-plus-circle'),
        $drawer_link(lang('Contribute a guide'), '{{page:doc_new}}', 'bi-pencil-square'),
        $drawer_link(lang('My Account'), '{{page:my_account}}', 'bi-person-circle'),
    );

    return $el('header', 'pf-masthead bg-body d-print-none', lang('Forum Masthead'), array(
        $link(lang('Skip to main content'), '#main', 'visually-hidden-focusable position-absolute p-2 bg-body text-body'),
        $el('div', 'container', lang('Container'), array(
            $el('div', 'd-flex align-items-center gap-2 py-2', lang('Masthead Row'), array(
                $el('button', 'btn p-2 d-lg-none', lang('Menu Button'), array($icon('bi-list', 'fs-3')), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'offcanvas'),
                    $attr('data-bs-target', '#pf-menu'),
                    $attr('aria-controls', 'pf-menu'),
                    $attr('aria-label', lang('Open the menu')),
                ))),
                $el('a', 'pf-brand me-auto me-lg-3', lang('Brand'), array(
                    $el('span', 'pf-logo', lang('Logo'), array($icon('bi-chat-square-text'))),
                    $span('{{site_name}}', 'pf-name', lang('Name')),
                ), array('href' => '{{page:home}}')),
                // The main links from lg; the current one is marked on the server.
                $el('nav', 'd-none d-lg-block me-auto', lang('Main Links'), array(
                    $el('ul', 'nav', lang('Nav Menu'), $nav_items, array('smartActive' => true)),
                ), array('_attrs' => array($attr('aria-label', lang('Main links'))))),
                $el('div', 'd-flex align-items-center gap-2', lang('Account Links'), array(
                    // The palette's Theme Switcher (Toggle): the page loads its
                    // script because of the marker.
                    $el('div', 'd-inline-block', lang('Theme Switcher'), array(
                        $el('button', 'btn btn-outline-secondary btn-sm rounded-pill', lang('Theme Toggle Button'), array(
                            $icon('bi-moon-stars-fill'),
                        ), array('_attrs' => array(
                            $attr('type', 'button'),
                            $attr('aria-label', lang('Switch the theme')),
                            $attr('aria-pressed', 'false'),
                        ))),
                    ), array('_attrs' => array($attr('data-pg-theme-switcher', 'toggle')))),
                    $lang_switcher(),
                    $widget('login_region'),
                    $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary btn-sm d-none d-md-inline-block text-nowrap'),
                )),
            )),
        )),
        // The menu drawer the phone's button opens.
        $el('div', 'offcanvas offcanvas-start', lang('Menu Drawer'), array(
            $el('div', 'offcanvas-header border-bottom', lang('Drawer Header'), array(
                // Not a heading: the drawer comes before the page's h1, and
                // the page's outline should start there. The dialog takes its
                // name from it all the same (aria-labelledby).
                $el('p', 'h5 offcanvas-title fw-bold mb-0', lang('Drawer Title'), array(), array('id' => 'pf-menu-title', 'text' => '{{site_name}}')),
                $el('button', 'btn-close', lang('Close Button'), array(), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-dismiss', 'offcanvas'),
                    $attr('aria-label', lang('Close')),
                ))),
            )),
            $el('div', 'offcanvas-body', lang('Drawer Body'), array(
                $el('form', '', lang('Search Form'), array(
                    $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the forum and the docs'), '_attrs' => array($attr('for', 'pf-q')))),
                    $el('div', 'input-group', lang('Search Box'), array(
                        $el('input', 'form-control', lang('Field'), array(), array('id' => 'pf-q', '_attrs' => array(
                            $attr('type', 'search'),
                            $attr('name', 'query'),
                            $attr('placeholder', lang('Search the forum and the docs…')),
                        ))),
                        $el('button', 'btn btn-primary', lang('Search Button'), array(), array('text' => lang('Search'), '_attrs' => array($attr('type', 'submit')))),
                    )),
                ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search')))),
                $el('ul', 'list-unstyled mt-4 mb-0', lang('Drawer Links'), $drawer, array('smartActive' => true)),
                $el('p', 'pf-label mt-4 mb-1', lang('Heading'), array(), array('text' => lang('Take part'))),
                $el('ul', 'list-unstyled mb-0', lang('Drawer Links'), $drawer_more, array('smartActive' => true)),
            )),
        ), array('id' => 'pf-menu', '_attrs' => array(
            $attr('tabindex', '-1'),
            $attr('aria-labelledby', 'pf-menu-title'),
        ))),
    ));
};

// The footer, on the tinted surface: the community in a sentence and its
// feed of topics, the forum, the docs, the community's pages and the
// member's own pages; under them the copyright and the legal pages. No
// social links: a link that looks real but goes nowhere is worse than none.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $column = function ($title, $class, $links) use ($el, $heading) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('div', $class, $title, array(
            $heading('h2', $title, 'h6 fw-bold mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Links'), $items),
        ));
    };
    return $el('footer', 'mt-auto bg-body-tertiary border-top d-print-none', lang('Forum Footer'), array(
        $el('div', 'container pf-band', lang('Footer Container'), array(
            $el('div', 'row g-4 g-lg-5', lang('Footer Columns'), array(
                $el('div', 'col-lg-4', lang('About the Community'), array(
                    $el('a', 'pf-brand mb-3', lang('Brand'), array(
                        $el('span', 'pf-logo', lang('Logo'), array($icon('bi-chat-square-text'))),
                        $span('{{site_name}}', 'pf-name', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('The place to ask questions about the app, share what you made and learn from the people who use it every day.'), 'small text-body-secondary mb-3'),
                    // Every board's live topics, the newest first: the board
                    // page's feed without a board.
                    $el('a', 'd-inline-flex gap-2 align-items-center small link-body-emphasis', lang('RSS Feed'), array(
                        $icon('bi-rss'),
                        $span(lang('Feed of new topics'), '', lang('Link Text')),
                    ), array('href' => '{{page:board}}?rss=true')),
                )),
                $column(lang('Topics'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('Every topic'), '{{page:forum}}', $plain),
                    $link(lang('Boards'), '{{page:board}}', $plain),
                    $link(lang('Unanswered topics'), '{{page:unanswered}}', $plain),
                    $link(lang('Start a topic'), '{{page:new_topic}}', $plain),
                )),
                $column(lang('Guides'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('All guides'), '{{page:docs}}', $plain),
                    $link(lang('Contribute a guide'), '{{page:doc_new}}', $plain),
                    $link(lang('Search'), '{{page:search}}', $plain),
                )),
                $column(lang('Community'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('Guidelines'), '{{page:guidelines}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('About the community'), '{{page:about}}', $plain),
                    $link(lang('Contact us'), '{{page:contact}}', $plain),
                )),
                $column(lang('Your account'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                    $link(lang('My topics'), '{{page:my_topics}}', $plain),
                    $link(lang('My guides'), '{{page:my_docs}}', $plain),
                    $link(lang('Inbox'), '{{page:inbox}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between gap-2 border-top mt-5 pt-3 pf-meta', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'mb-0'),
                $el('nav', 'd-flex flex-wrap column-gap-3 row-gap-1', lang('Legal'), array(
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    $link(lang('Terms of Use'), '{{page:terms}}', $plain),
                    $link(lang('Accessibility'), '{{page:accessibility}}', $plain),
                ), array('_attrs' => array($attr('aria-label', lang('Legal'))))),
            )),
        )),
    ));
};

// The moderators' own menu, under the masthead on the staff pages. Each
// link names a page of its own: the smart active state ignores the part
// after #, so two links to one page would both be marked current.
$staff_bar = function () use ($el, $link, $span, $attr) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small bg-body d-print-none', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center column-gap-4 row-gap-2 py-2', lang('Staff Bar Container'), array(
            $el('nav', 'nav nav-underline align-items-center column-gap-4 row-gap-1', lang('Staff Links'), array(
                $span(lang('Moderators'), 'badge text-bg-primary', lang('Staff Badge')),
                $item('staff', lang('Moderation')),
                $item('staff_topics', lang('Topics desk')),
                $item('staff_docs', lang('Docs desk')),
                $item('staff_reports', lang('Reported posts')),
                $item('inbox', lang('Inbox')),
                $item('staff_directory', lang('Staff Directory')),
            ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Moderators'))))),
        )),
    ));
};

// A page of the site: the masthead, the page's own content and the footer.
$page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('masthead'),
        $el('main', '', lang('Main Content'), $main, array('id' => 'main')),
        $shared('footer'),
    ));
};
// A page of the moderators: the staff bar under the masthead.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('masthead'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main, array('id' => 'main')),
        $shared('footer'),
    ));
};

// The trail of links above a page's title: array(text, address) each, the
// last one the page itself (address '').
$breadcrumb = function ($items) use ($el, $link, $attr) {
    $lis = array();
    foreach ($items as $it) {
        if ($it[1] === '') {
            $lis[] = $el('li', 'breadcrumb-item active', lang('Current Page'), array(), array('text' => $it[0], '_attrs' => array($attr('aria-current', 'page'))));
        } else {
            $lis[] = $el('li', 'breadcrumb-item', lang('List Item'), array($link($it[0], $it[1], 'link-body-emphasis')));
        }
    }
    return $el('nav', '', lang('Where You Are'), array(
        $el('ol', 'breadcrumb small mb-2', lang('Links'), $lis),
    ), array('_attrs' => array($attr('aria-label', lang('Where you are')))));
};

// The top of the inner pages: the trail, the title, its line and whatever
// the page adds under them.
$page_header = function ($title, $lead = '', $before = array(), $after = array()) use ($el, $heading, $para, $container) {
    $children = array_merge($before, array($heading('h1', $title, 'pf-page-title mb-2')));
    if ($lead !== '') $children[] = $para($lead, 'lead text-body-secondary pf-lead mb-0');
    return $el('header', 'pf-band pb-4', lang('Page Header'), array(
        $container(array_merge($children, $after)),
    ));
};

// The head of a band: its title, and a link to the rest on the right.
$sec_head = function ($title, $more_text = '', $more_href = '', $id = '') use ($el, $heading, $icon, $span) {
    $extra = ($id !== '') ? array('id' => $id) : array();
    $children = array($heading('h2', $title, 'pf-sec-title', $extra));
    if ($more_text !== '') {
        $children[] = $el('a', 'pf-more', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'pf-sec-head', lang('Section Header'), $children);
};

// A band of a page: a section around a container, with the band's padding.
$band = function ($name, $children, $class = 'pf-band', $extra = array()) use ($el, $container) {
    return $el('section', $class, $name, array($container($children)), $extra);
};
// A list with a check before each line.
$check_list = function ($items) use ($el, $icon, $span) {
    $children = array();
    foreach ($items as $text) {
        $children[] = $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array(
            $icon('bi-check2', 'text-primary mt-1'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Check List'), $children);
};
// A box of a side column: a title and what goes under it.
$side_card = function ($name, $title, $children, $class = 'pf-card mb-4', $extra = array()) use ($el, $heading) {
    return $el('div', $class, $name, array_merge(array($heading('h2', $title, 'h6 fw-bold mb-3')), $children), $extra);
};
$buttons = function ($links, $class = 'd-flex flex-wrap align-items-center gap-2') use ($el) {
    return $el('div', $class, lang('Buttons'), $links);
};
// A part of a static page: an anchor, a title and its paragraphs, then
// whatever else it holds.
$prose = function ($id, $name, $title, $paras, $more = array()) use ($el, $heading, $para) {
    $children = array($heading('h2', $title, 'h4 fw-bold mb-3'));
    foreach ($paras as $text) $children[] = $para($text, 'mb-3');
    return $el('section', 'pb-4 mb-4 border-bottom', $name, array_merge($children, $more), array('id' => $id));
};
// The note over the legal pages' sample text.
$sample_note = function () use ($el, $icon, $span, $attr) {
    return $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
        $icon('bi-info-circle', 'mt-1'),
        $span(lang('Sample text: adapt it to your community and to the law that applies to you before you publish.'), '', lang('Text')),
    ), array('_attrs' => array($attr('role', 'note'))));
};
// A static page: its header, then its parts in the reading column and a
// side column beside them, a landmark named $side_label.
$static_page = function ($title, $lead, $parts, $side = array(), $side_label = '') use ($page, $page_header, $band, $row, $col, $el, $attr) {
    $side_col = $side ? array($el('aside', '', lang('Side Column'), $side,
        array('_attrs' => array($attr('aria-label', $side_label !== '' ? $side_label : lang('More help')))))) : array();
    return $page(array(
        $page_header($title, $lead),
        $band(lang('Content'), array(
            $row(array(
                $col($parts, '', '8'),
                $col($side_col, '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    ));
};
// A question of the FAQ: a search section of its own, so a search that
// matches it opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answers, $topics = '') use ($el, $para, $attr, $searchable) {
    $id = 'pf-faq-' . $n;
    $body = array();
    foreach ((array)$answers as $i => $text) $body[] = $para($text, $i === count((array)$answers) - 1 ? 'mb-0' : 'mb-2');
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed fw-semibold', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body', lang('Body'), $body),
        ), array('id' => $id)),
    ), $searchable($id . '-item', $topics));
};

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// next to the Bootstrap classes; a palette colour is written twice, plain
// first, so a program that blocks the linked stylesheet still has one. No
// capitals by CSS: a Turkish i would turn into a dotless I.
$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
// The frame is light in any page theme: the dark theme would turn its
// Bootstrap backgrounds dark under the written dark text when a moderator
// opens the page in a browser (a sent e-mail has no theme).
$mail_page = function ($children) use ($el, $shared, $mail_style, $attr) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dcdfe8;border-radius:12px;overflow:hidden')),
        ), array('_attrs' => array(
            $attr('data-bs-theme', 'light'),
            $attr('style', 'background:#f3f4f8;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1d2131'),
        ))),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;line-height:1.3;margin:0 0 16px;color:#1d2131'))));
};
$mail_para = function ($text, $class = 'mb-3', $style = 'margin:0 0 16px') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', $style))));
};
// The small print at the end of an e-mail.
$mail_small = function ($text) use ($mail_para) {
    return $mail_para($text, 'small text-body-secondary mb-0', 'font-size:13px;color:#5b6070;margin:0');
};
$mail_button = function ($text, $href, $extra = array()) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:8px;font-weight:600;text-decoration:none;'
            . 'background-color:#4f46e5;background-color:var(--bs-primary,#4f46e5);color:#ffffff;color:var(--pg-on-primary,#ffffff)')), $extra),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#5b6070')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary border rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f3f4f8;border:1px solid #dcdfe8;border-radius:8px;padding:16px;margin:16px 0'));
};
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#5b6070;margin:24px 0 0'));
};
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$mail_account_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};
// A form's e-mail widget: drawn for the record just sent, which the server
// names when it sends the e-mail. Opened by its address instead (?r=), the
// page shows the record only to whoever sent it and to the moderators.
$mail_widget = function ($page_key, $form_page, $not_found, $children) use ($widget_root) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_page . '}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $not_found,
        ),
        'tree'   => $widget_root($children),
    );
};
// "Hello," and the name the record carries, in an element of its own.
$mail_greeting = function ($token, $sample) use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span($sample, '', lang('Name'), $bind($token)),
    ), $mail_style('margin:0 0 16px'));
};
// One value of the record on a line of an e-mail's box.
$mail_value = function ($label, $sample, $token) use ($mail_line, $span, $bind) {
    return $mail_line($label, array($span($sample, '', $label, $bind($token))));
};
$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// ── Comments ────────────────────────────────────────────────────────────
// The comment settings of the record pages. Each label is a singular noun:
// the platform puts it in plural and into sentences ("2 replies", "Add
// reply", "Notify me when a reply is added."). No page takes attachments,
// and nobody picks the name a comment is signed with: a member's comes from
// their profile (attachments and select_name off, the keys the editor's
// export writes).

// The replies under a topic: signed in, published at once, mailed to the
// topic's followers. No staff address: the moderators follow replies on
// their dashboard and in the control panel's Comments screen. The label is
// a key of its own, in lower case: the shared "Reply" is a verb button of
// the workspace, and its Turkish, a verb, would make the "Add reply" heading
// and button under every topic ungrammatical. The message shows over the
// reply form, the closed message in its place while a moderator stopped new
// replies.
$reply_comments = array(
    'label'          => lang('reply'),
    'allow_new'      => true,
    'auto_publish'   => true,
    'show_date'      => true,
    'login'          => true,
    'email_page'     => '{{tab:email_reply}}',
    'email_subject'  => lang('There is a new reply to a topic you follow'),
    'attachments'    => false,
    'select_name'    => false,
    'message'        => lang('Your reply is signed with the name in your profile and appears at once.'),
    'closed_message' => lang('This topic takes no new replies.'),
);
// Feedback under a guide, with a rating: held until a moderator publishes it.
$feedback_comments = array(
    'label'          => lang('Feedback'),
    'allow_new'      => true,
    'rating'         => true,
    'auto_publish'   => false,
    'show_date'      => true,
    'login'          => true,
    'email_page'     => '{{tab:email_reply}}',
    'email_subject'  => lang('There is new feedback on a guide you follow'),
    'notify_email'   => '{{site_email}}',
    'attachments'    => false,
    'select_name'    => false,
    'message'        => lang('Your feedback is signed with the name in your profile. A moderator reads it before it appears.'),
    'closed_message' => lang('This guide takes no new feedback.'),
);
// The messages between a guide's author and the moderators.
$doc_desk_comments = array(
    'label'         => lang('Message'),
    'allow_new'     => true,
    'auto_publish'  => true,
    'show_date'     => true,
    'login'         => true,
    'email_page'    => '{{tab:email_doc_message}}',
    'email_subject' => lang('New message about a guide sent to the docs'),
    'notify_email'  => '{{site_email}}',
    'attachments'   => false,
    'select_name'   => false,
);
// The answers in a conversation started from the contact page.
$conversation_comments = array(
    'label'         => lang('Message'),
    'allow_new'     => true,
    'auto_publish'  => true,
    'show_date'     => true,
    'login'         => true,
    'email_page'    => '{{tab:email_reply}}',
    'email_subject' => lang('There is a new reply to your conversation'),
    'notify_email'  => '{{site_email}}',
    'attachments'   => false,
    'select_name'   => false,
);
// The moderators' notes under a report, for the moderators only.
$staff_note_comments = array(
    'label'        => lang('Note'),
    'allow_new'    => true,
    'auto_publish' => true,
    'show_date'    => true,
    'login'        => true,
    'attachments'  => false,
    'select_name'  => false,
);

// ── The sample records ──────────────────────────────────────────────────
// The topics and the guides the forms open with, newest first. The seeder
// dates record i (4i + 1) days back, so no text names a date, a month or a
// season: they move with the install. Each is a record like any other,
// edited or deleted in the control panel. No field holds an author's name:
// a topic's author is the account that sent it (submitter_name), and the
// samples are sent by the setup account, so they show none.

// A text as the rich-text field stores it: a paragraph a <p>, a subhead
// (array('h2' => text)) an <h2>, a list (array('ul' or 'ol' => items)) its
// list, a code block (array('pre' => text)) a <pre><code>. The words are
// escaped here; the seeder keeps the markup (pg_cf_seed_rich_value()) and
// filters it.
$rich = function ($blocks) {
    $html = '';
    foreach ($blocks as $b) {
        if (!is_array($b)) {
            $html .= '<p>' . h($b) . '</p>';
        } elseif (isset($b['h2'])) {
            $html .= '<h2>' . h($b['h2']) . '</h2>';
        } elseif (isset($b['ul']) || isset($b['ol'])) {
            $tag = isset($b['ul']) ? 'ul' : 'ol';
            $html .= '<' . $tag . '>';
            foreach ($b[$tag] as $item) $html .= '<li>' . h($item) . '</li>';
            $html .= '</' . $tag . '>';
        } elseif (isset($b['pre'])) {
            $html .= '<pre><code>' . h($b['pre']) . '</code></pre>';
        }
    }
    return $html;
};

// The people the editor and the installer's preview show beside a sample
// row; the site shows the real author from their profile.
$people = array(lang('Jordan Lee'), lang('Priya Raman'), lang('Marcus Bell'), lang('Elena Ortiz'), lang('Sam Whitaker'));

// The heads of the sample topics, newest first: array(board key, kind
// index, status index, pinned, announcement, title, tags).
$topic_heads = array(
    array('getting_started', 1, 0, true,  true,  lang('Welcome! Start here'), lang('welcome, getting started, how to post')),
    array('help',            0, 1, false, false, lang('Reminders arrive an hour late after the clocks change'), lang('reminders, time zone, phone app')),
    array('ideas',           1, 0, false, false, lang('Idea: a weekly review that gathers unfinished tasks'), lang('tasks, weekly review, feature request')),
    array('tips',            1, 0, false, false, lang('Five keyboard shortcuts I use every day'), lang('keyboard shortcuts, productivity')),
    array('help',            0, 0, false, false, lang('Can I move a notebook from my personal workspace to a team workspace?'), lang('workspaces, sharing, notebooks')),
    array('show_and_tell',   1, 0, false, false, lang('My reading log template, free to copy'), lang('templates, reading, saved searches')),
    array('getting_started', 0, 1, false, false, lang('What is the difference between a notebook and a workspace?'), lang('workspaces, notebooks, getting started')),
    array('help',            0, 1, false, false, lang('Sync is stuck on Waiting on my phone'), lang('sync, phone app')),
    array('ideas',           1, 2, false, false, lang('Print notes in the light theme'), lang('printing, dark theme')),
    array('tips',            1, 0, false, false, lang('Use templates with placeholders for meeting notes'), lang('templates, meetings, tasks')),
    array('help',            0, 1, false, false, lang('Does exporting a notebook to Markdown keep the images?'), lang('export, markdown, attachments')),
    array('show_and_tell',   1, 0, false, false, lang('How our team of six plans a product launch'), lang('teams, planning, shared notebooks')),
    array('general',         1, 0, false, false, lang('What does your note-taking setup look like?'), lang('setups, organization')),
    array('getting_started', 0, 1, false, false, lang('How do I import my notes from plain text files?'), lang('import, getting started')),
    array('ideas',           1, 0, false, false, lang('Let tasks repeat on the last weekday of the month'), lang('recurring tasks, feature request')),
    array('tips',            1, 0, false, false, lang('Search operators that save me time'), lang('search, tips')),
    array('general',         1, 0, false, true,  lang('The docs are open for community guides'), lang('docs, announcements, contributing')),
    array('tips',            1, 0, true,  false, lang('How to write a question that gets a quick answer'), lang('how to post, questions')),
);
// A sample topic's values as the rows bind them, by its index above; $more
// changes some (a topic without replies: 'replier' '' and 'replies' '0').
// 'account' is the user name only the moderators' rows print.
$topic_sample = function ($i, $more = array()) use ($topic_heads, $boards, $kinds, $topic_statuses, $people) {
    $h = $topic_heads[$i];
    $author = $people[$i % count($people)];
    return array_merge(array(
        'board'   => $boards[$h[0]][0],
        'kind'    => $kinds[$h[1]],
        'status'  => $topic_statuses[$h[2]],
        'title'   => $h[5],
        'tags'    => $h[6],
        'author'  => $author,
        'account' => mb_strtolower(str_replace(' ', '.', $author), 'UTF-8'),
        'replier' => $people[($i + 2) % count($people)],
        'replies' => '3',
        'views'   => '48',
    ), $more);
};
// Sample topic $i: its head and its text.
$sample_topic = function ($i, $blocks) use ($topic_heads, $boards, $kinds, $topic_statuses, $yes, $no, $rich) {
    $h = $topic_heads[$i];
    return array(
        'board'        => $boards[$h[0]][0],
        'kind'         => $kinds[$h[1]],
        'title'        => $h[5],
        'body'         => $rich($blocks),
        'tags'         => $h[6],
        'guidelines'   => 'yes',
        'status'       => $topic_statuses[$h[2]],
        'pinned'       => $h[3] ? $yes : $no,
        'announcement' => $h[4] ? $yes : $no,
    );
};
// Eighteen topics across every board and both kinds: five solved, one
// closed, two pinned and two announcements. None has a reply yet.
$sample_topics = array(
    $sample_topic(0, array(
        lang('Welcome to the community. This is the place to ask questions about the app, share the setups and templates you built, and tell us what you would like to see next.'),
        array('h2' => lang('Where to post')),
        lang('Pick the board that fits: Questions & Help for anything that does not work the way you expect, Ideas & Feedback for suggestions, Show & Tell for things you made, and Tips & Tricks for habits worth passing on.'),
        array('h2' => lang('Before you ask')),
        lang('Search the forum and the docs first: many questions already have an answer. If yours is new, give it a title that says what happens. When a reply solves it, say so in a reply, and a moderator marks the topic as solved so the next person finds the answer too.'),
        lang('Be kind. Everyone here was new once.'),
    )),
    $sample_topic(1, array(
        lang('Since the clocks changed, every reminder I set arrives exactly one hour late on my phone. On the desktop app the same reminders are on time.'),
        lang('I tried signing out and back in, and the time zone in the app\'s settings is right. Is there anything else I should check?'),
    )),
    $sample_topic(2, array(
        lang('Every Friday I go through my notebooks looking for tasks I did not finish. A weekly review that gathers every open task from the past seven days in one list would save a lot of clicking.'),
        lang('Ideally I could move each task to next week, mark it done or drop it right from that list. Would anyone else use this?'),
    )),
    $sample_topic(3, array(
        lang('I put off learning shortcuts for a long time. These five changed how fast I take notes:'),
        array('ul' => array(
            lang('Ctrl+N starts a new note in the notebook you are in.'),
            lang('Ctrl+K opens the quick switcher: type part of a name to jump to any notebook or note.'),
            lang('Ctrl+Shift+T turns the line you are on into a task.'),
            lang('Alt+Up and Alt+Down move a line or a task up and down.'),
            lang('Ctrl+/ shows every shortcut, which is how I found the others.'),
        )),
        lang('What are yours?'),
    )),
    $sample_topic(4, array(
        lang('I started a project in my personal workspace, and now two colleagues need to work on it with me. Is there a way to move the whole notebook to our team workspace, with its tasks and attachments, without copying every note by hand?'),
    )),
    $sample_topic(5, array(
        lang('I keep a reading log in the app and finally turned it into a template. Each book gets a note with the title, the author, the day I started, a rating and a few lines on what stayed with me.'),
        lang('A saved search shows every book I finished this year, and a second one lists the books I gave up on, which turns out to be a surprisingly useful list. Copy the template and change it however you like.'),
    )),
    $sample_topic(6, array(
        lang('I am new to the app and a little confused by the words. Is a workspace just a big notebook? When should I make a new workspace instead of a new notebook?'),
    )),
    $sample_topic(7, array(
        lang('My phone app has said Waiting to sync since this morning. The desktop app syncs fine, but new notes from the desktop never appear on the phone.'),
        lang('I am on the latest version and the phone is online. What else can I try before I reinstall?'),
    )),
    $sample_topic(8, array(
        lang('When I print a note while the app is in the dark theme, the page comes out with light text that is hard to read. Could printing always use the light theme?'),
    )),
    $sample_topic(9, array(
        lang('If you take meeting notes, try a template with placeholders. Mine fills in today\'s date and the meeting\'s name, and starts with three headings: decisions, open questions and next steps.'),
        lang('Each next step is a task, so it lands in my task list with the meeting note linked. It takes ten minutes to set up and saves that much every week.'),
    )),
    $sample_topic(10, array(
        lang('I would like to keep a copy of my notes outside the app. When I export a notebook to Markdown, are the images and attachments exported too, or only the text?'),
    )),
    $sample_topic(11, array(
        lang('Our small team plans every launch in one shared notebook. The first note is a one-page brief; every other note is one part of the launch, from the website copy to the help articles.'),
        array('h2' => lang('What made it work')),
        lang('Each part has one owner and a due date, and a saved search shows everything due this week. We go through that one list in a short meeting every Monday.'),
        lang('Comments on the notes replaced most of our e-mail threads, which was the biggest surprise.'),
    )),
    $sample_topic(12, array(
        lang('I am curious how others organize things. I keep one notebook for each area of my life, tags for the status of a note and a single inbox note for anything I have not sorted yet.'),
        lang('Do you use many notebooks or a few? Tags or nested notebooks? I would love to see your setups.'),
    )),
    $sample_topic(13, array(
        lang('I have a few hundred notes as plain text files in folders on my computer. Can the app import them, and will it keep the folder names?'),
    )),
    $sample_topic(14, array(
        lang('Recurring tasks can repeat every month on a set day, but my invoices are due on the last weekday of the month. A repeat rule for that would help anyone who works with month-end deadlines.'),
    )),
    $sample_topic(15, array(
        lang('The search box understands more than words. A few operators I use all the time:'),
        array('ul' => array(
            lang('tag:urgent finds the notes with that tag.'),
            lang('is:task is:open lists open tasks only.'),
            lang('in:"Home" searches one notebook.'),
            lang('A phrase in quotes finds those words together.'),
        )),
        lang('The full list is in the Search syntax guide in the docs.'),
    )),
    $sample_topic(16, array(
        lang('You can now contribute to the docs. If you have worked out a workflow, an integration or a fix that others keep asking about, write it up as a guide.'),
        lang('A moderator reviews every guide before it is published, and you can follow the review and talk with the moderators on the guide\'s own page. Start from Contribute a guide in the docs.'),
    )),
    $sample_topic(17, array(
        lang('The questions that get answered fastest have three things in common:'),
        array('ol' => array(
            lang('A title that says what happens, such as "Reminders arrive an hour late", rather than "Help!".'),
            lang('The steps that lead to the problem, what you expected and what happened instead.'),
            lang('The version of the app and the device you use, with a screenshot if it helps.'),
        )),
        lang('Remove anything private from screenshots and logs before you attach them.'),
    )),
);

// The heads of the sample guides, newest first: array(section key,
// featured, order in its section, title, summary, version, byline, tags).
$docs_team = lang('The docs team');
$doc_heads = array(
    array('release_notes',   false, '10', lang('What changed in version 4.2'), lang('Export to PDF, attachments in Markdown exports and faster sync on phones.'), '4.2', $docs_team, lang('release notes, export, sync')),
    array('getting_started', true,  '10', lang('Install the app on your computer'), lang('Download the desktop app, sign in and see your notes in a few minutes.'), lang('4.0 and later'), $docs_team, lang('install, desktop app')),
    array('how_to',          true,  '10', lang('Share a notebook with your team'), lang('Invite people to a notebook, choose what they may change and see who changed what.'), lang('4.0 and later'), $docs_team, lang('sharing, teams, workspaces')),
    array('troubleshooting', false, '10', lang('Sync is stuck or slow'), lang('What to check when notes do not appear on your other devices.'), lang('4.0 and later'), $docs_team, lang('sync, troubleshooting')),
    array('reference',       false, '10', lang('Keyboard shortcuts'), lang('Every shortcut of the desktop and web apps, by task.'), '4.2', $docs_team, lang('keyboard shortcuts, reference')),
    array('integrations',    false, '10', lang('Subscribe to your tasks from a calendar app'), lang('Show the tasks that have a due date in any calendar that subscribes to a calendar feed.'), lang('4.1 and later'), lang('Priya Raman'), lang('calendar, integrations, tasks')),
    array('how_to',          false, '20', lang('Set up recurring tasks and reminders'), lang('Repeat a task on a schedule and get a reminder at the right time.'), lang('4.0 and later'), $docs_team, lang('tasks, reminders')),
    array('getting_started', false, '20', lang('Your first notebook in ten minutes'), lang('Create a notebook, add notes and tasks, and find them again with tags and search.'), '', $docs_team, lang('notebooks, tags, getting started')),
    array('reference',       false, '20', lang('Search syntax'), lang('Operators that narrow a search to a notebook, a tag, a time or open tasks.'), lang('4.0 and later'), lang('Marcus Bell'), lang('search, reference')),
    array('troubleshooting', false, '20', lang('Reminders arrive at the wrong time'), lang('Check the time zone of the app and of each device when reminders come early or late.'), '', lang('Elena Ortiz'), lang('reminders, time zone')),
    array('how_to',          false, '30', lang('Build a template with placeholders'), lang('Start meeting notes, journals and checklists from a template that fills in the date.'), lang('4.1 and later'), lang('Sam Whitaker'), lang('templates')),
    array('integrations',    false, '20', lang('Send notes to the app by e-mail'), lang('Forward an e-mail to your own address for notes and it becomes a note.'), '', $docs_team, lang('e-mail, integrations')),
);
// A sample guide's values as the cards bind them, by its index above.
$doc_sample = function ($i) use ($doc_heads, $doc_sections) {
    $h = $doc_heads[$i];
    return array(
        'section' => $doc_sections[$h[0]][0],
        'title'   => $h[3],
        'summary' => $h[4],
        'version' => $h[5],
        'byline'  => $h[6],
        'tags'    => $h[7],
    );
};
// Sample guide $i: its head and its text, published.
$sample_doc = function ($i, $blocks) use ($doc_heads, $doc_sections, $published_value, $yes, $no, $rich) {
    $h = $doc_heads[$i];
    return array(
        'section'   => $doc_sections[$h[0]][0],
        'title'     => $h[3],
        'summary'   => $h[4],
        'body'      => $rich($blocks),
        'version'   => $h[5],
        'tags'      => $h[7],
        'byline'    => $h[6],
        'rights'    => 'yes',
        'status'    => $published_value,
        'featured'  => $h[1] ? $yes : $no,
        'doc_order' => $h[2],
    );
};
// Twelve published guides across every section, two of them featured.
$sample_docs = array(
    $sample_doc(0, array(
        lang('Version 4.2 is rolling out to every device. Here is what is new and what we fixed.'),
        array('h2' => lang('New features')),
        array('ul' => array(
            lang('Export a note or a whole notebook to PDF, with its headings, tasks and images.'),
            lang('Markdown exports now include images and attachments, in a folder beside the notes.'),
            lang('The quick switcher also finds tags and saved searches.'),
        )),
        array('h2' => lang('Improvements')),
        array('ul' => array(
            lang('Sync on phones starts sooner after the app opens and uses less battery.'),
            lang('Printing always uses the light theme, so printed notes stay readable.'),
        )),
        array('h2' => lang('Bug fixes')),
        array('ul' => array(
            lang('Reminders set before the clocks change keep their local time.'),
            lang('A table pasted from a spreadsheet keeps its columns.'),
        )),
    )),
    $sample_doc(1, array(
        lang('The desktop app keeps your notes on your computer and syncs them with your phone and the web app. Setting it up takes a few minutes.'),
        array('h2' => lang('Installation')),
        array('ol' => array(
            lang('Open the download page and choose the installer for your computer.'),
            lang('Run the installer and follow the steps on the screen.'),
            lang('Open the app and sign in with the account you use on the web.'),
        )),
        array('h2' => lang('After you sign in')),
        lang('Your notebooks appear in the sidebar and start to sync. A large account can take a few minutes the first time; you can keep working while it does.'),
        lang('If a notebook is missing, check that you are in the right workspace with the switcher at the top of the sidebar.'),
    )),
    $sample_doc(2, array(
        lang('A notebook in a team workspace can be shared with anyone in that workspace, and with guests you invite by e-mail.'),
        array('h2' => lang('Invite people')),
        array('ol' => array(
            lang('Open the notebook and choose Share.'),
            lang('Type a name or an e-mail address.'),
            lang('Choose Can view or Can edit, then send the invitation.'),
        )),
        array('h2' => lang('See who changed what')),
        lang('Each note shows who changed it last. Open History on a note to see every change and bring back an earlier version.'),
        lang('To stop sharing, open Share again and remove the person. They lose access at once; the notes stay in the notebook.'),
    )),
    $sample_doc(3, array(
        lang('Notes sync within a few seconds while every device is online. When they do not, check these in order.'),
        array('ol' => array(
            lang('Make sure each device is signed in to the same account and the same workspace.'),
            lang('Look at the sync status at the bottom of the sidebar. Waiting means the app has not reached the server yet.'),
            lang('Close the app completely and open it again.'),
            lang('Update the app on every device: an old version can stop syncing after the service is updated.'),
        )),
        array('h2' => lang('Still stuck?')),
        lang('Sign out and back in on the device that is stuck. Notes that have not synced yet are kept and sent once you sign in again.'),
        lang('If that does not help, start a topic in Questions & Help with the version of the app and what the sync status says.'),
    )),
    $sample_doc(4, array(
        lang('These shortcuts work in the desktop and web apps. Press Ctrl+/ in the app to see them at any time.'),
        array('h2' => lang('Notes')),
        array('ul' => array(
            lang('Ctrl+N: a new note in the current notebook'),
            lang('Ctrl+K: the quick switcher, to jump to a notebook, a note or a tag'),
            lang('Ctrl+F: find in the current note'),
            lang('Ctrl+Shift+F: search everything'),
        )),
        array('h2' => lang('Tasks')),
        array('ul' => array(
            lang('Ctrl+Shift+T: turn the current line into a task'),
            lang('Ctrl+Enter: mark a task done'),
            lang('Alt+Up and Alt+Down: move a line or a task'),
        )),
        array('h2' => lang('Text')),
        array('ul' => array(
            lang('Ctrl+B, Ctrl+I and Ctrl+U: bold, italic and underline'),
            lang('Ctrl+Shift+7: a numbered list'),
            lang('Ctrl+Shift+8: a bulleted list'),
        )),
    )),
    $sample_doc(5, array(
        lang('Every account has a private calendar feed of its tasks that have a due date. Any calendar app that can subscribe to a calendar by its address can show them.'),
        array('ol' => array(
            lang('In the app, open Settings and choose Calendar feed.'),
            lang('Copy the feed address.'),
            lang('In your calendar app, add a calendar by its address and paste the feed address.'),
        )),
        lang('The calendar app checks the feed on its own schedule, often every few hours, so a new task can take a while to appear.'),
        lang('The feed address works like a password: anyone who has it can see your tasks. If you shared it by mistake, choose Reset address in the same settings.'),
    )),
    $sample_doc(6, array(
        lang('A task can repeat on a schedule and remind you before it is due.'),
        array('h2' => lang('Make a task repeat')),
        array('ol' => array(
            lang('Open the task and choose Repeat.'),
            lang('Pick daily, weekly, monthly or yearly, and the day it repeats on.'),
            lang('Save. When you mark the task done, the next one is created with the next due date.'),
        )),
        array('h2' => lang('Add a reminder')),
        lang('Choose Remind me on the task and pick a time, or a number of minutes before the task is due. Reminders follow the time zone set in Settings, so set it to where you are.'),
    )),
    $sample_doc(7, array(
        lang('A notebook holds the notes on one subject: a project, a class, a trip. Here is how to set up your first one.'),
        array('ol' => array(
            lang('Choose New notebook in the sidebar and give it a name.'),
            lang('Add a note with Ctrl+N and write a title and a few lines.'),
            lang('Turn a line into a task with Ctrl+Shift+T and give it a due date.'),
            lang('Add a tag or two, such as idea or urgent, to find the note later.'),
        )),
        lang('Use the search box at the top to find anything by a word, a tag or a task. When you are ready to work with others, read Share a notebook with your team.'),
    )),
    $sample_doc(8, array(
        lang('Type words to find the notes that contain all of them. Add operators to narrow the search; you can combine as many as you like.'),
        array('ul' => array(
            lang('tag:urgent finds the notes with the tag urgent.'),
            lang('in:"Home renovation" searches one notebook.'),
            lang('is:task is:open lists open tasks only.'),
            lang('due:week finds the tasks due this week.'),
            lang('updated:7d finds the notes changed in the last seven days.'),
            lang('A phrase in quotes finds those words in that order.'),
            lang('A minus sign leaves a word out: -draft skips the notes that contain draft.'),
        )),
        lang('For example, this search lists the open tasks of one notebook that are due this week:'),
        array('pre' => lang('is:task is:open due:week in:"Product launch"')),
        lang('To keep a search, choose Save search: it appears in the sidebar.'),
    )),
    $sample_doc(9, array(
        lang('A reminder is set for a time in the time zone of the app. When reminders come early or late by whole hours, the time zone is usually the cause.'),
        array('ol' => array(
            lang('Open Settings, then Date and time, and check the time zone.'),
            lang('On your phone, check that the device sets its time zone automatically.'),
            lang('Update the app: since version 4.2, reminders keep their local time when the clocks change.'),
        )),
        lang('A reminder that comes a few minutes late on a phone is often held back by the phone\'s battery saver. Let the app run in the background to get it on time.'),
    )),
    $sample_doc(10, array(
        lang('A template is a note you start other notes from. The placeholders in it are filled in when you use it.'),
        array('h2' => lang('Make a template')),
        array('ol' => array(
            lang('Write the note the way every copy should start.'),
            lang('Put placeholders where the details go, such as {date} or {title}.'),
            lang('Choose Save as template from the note\'s menu.'),
        )),
        array('h2' => lang('Placeholders you can use')),
        array('ul' => array(
            lang('{date}: today\'s date'),
            lang('{time}: the time now'),
            lang('{title}: the title you give the new note'),
            lang('{notebook}: the notebook the note goes into'),
        )),
        lang('To use a template, choose New from template and pick it from the list.'),
    )),
    $sample_doc(11, array(
        lang('Every account has a private e-mail address for notes. Anything you send or forward to it becomes a note in your Inbox notebook.'),
        array('ol' => array(
            lang('Open Settings and choose E-mail to notes to see your address.'),
            lang('Add the address to your contacts, so it is easy to find.'),
            lang('Forward an e-mail to it: the subject becomes the title and the message the note.'),
        )),
        lang('Add #tag to the subject to tag the note, and @notebook to put it in another notebook.'),
        lang('Attachments up to 25 MB come along with the note.'),
    )),
);

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// The front page: the search and the forum's numbers, the announcements,
// the boards, the latest activity beside the topics waiting for an answer
// and the week's most viewed, the featured guides and the guidelines. Its
// RSS feed is its first list, the count of every live topic: every live
// topic, the newest first.
// Three numbers in a row from sm; on a phone two in a row and the third
// across, so a count of thousands keeps inside its card.
$stat = function ($widget_key, $class = 'col-6 col-sm-4') use ($el, $widget) {
    return $el('div', $class, lang('Column'), array($widget($widget_key)));
};
$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Community forum and docs'),
    'meta_description' => lang('Ask questions, share what you made and read the guides, with the people who use the app every day.'),
    'tree'             => $page(array(
        // The search first: most visitors come with a question.
        $el('section', 'pf-band pf-tint border-bottom', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para('{{site_name}}', 'pf-kicker mb-2'),
                        $heading('h1', lang('How can we help?'), 'pf-display mb-3', array('id' => 'pf-welcome-title')),
                        $para(lang('Search the forum and the docs, or start a topic and the community will help.'), 'lead text-body-secondary mb-4'),
                        $el('form', 'pf-search mb-3', lang('Search Form'), array(
                            $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the forum and the docs'), '_attrs' => array($attr('for', 'pf-hero-q')))),
                            $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                                $el('input', 'form-control', lang('Field'), array(), array('id' => 'pf-hero-q', '_attrs' => array(
                                    $attr('type', 'search'),
                                    $attr('name', 'query'),
                                    $attr('placeholder', lang('Search the forum and the docs…')),
                                ))),
                                $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array('_attrs' => array(
                                    $attr('type', 'submit'),
                                    $attr('aria-label', lang('Search')),
                                ))),
                            )),
                        ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search')))),
                        $buttons(array(
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary'),
                            $link(lang('Browse the docs'), '{{page:docs}}', 'btn btn-secondary'),
                        )),
                    ), '', '8', '', '12', 'col-xl-7'),
                )),
                // The forum's numbers: three counts of their own.
                $el('div', 'row g-2 g-md-3 mt-4', lang('Numbers'), array(
                    $stat('home_count_topics'),
                    $stat('home_count_solved'),
                    $stat('home_count_guides', 'col-12 col-sm-4'),
                ), array('_attrs' => array($attr('aria-label', lang('The forum in numbers')), $attr('role', 'group')))),
            )),
        ), array('_attrs' => array($attr('aria-labelledby', 'pf-welcome-title')))),
        // The moderators' announcements; the band leaves the page while
        // there is none.
        $band(lang('Announcements'), array(
            $sec_head(lang('Announcements')),
            $widget('home_announcements'),
        ), 'pf-band pb-0 pf-vanish'),
        $band(lang('Boards'), array(
            $sec_head(lang('Browse the boards'), lang('Every topic'), '{{page:forum}}'),
            $board_tiles(),
        ), 'pf-band', array('id' => 'boards')),
        $band(lang('Latest Activity'), array(
            $row(array(
                $col(array(
                    $sec_head(lang('Latest activity'), lang('Every topic'), '{{page:forum}}'),
                    $widget('home_latest'),
                ), '', '8'),
                $col(array(
                    $el('section', 'pf-card mb-4', lang('Unanswered'), array(
                        $sec_head(lang('Waiting for an answer'), lang('See all'), '{{page:unanswered}}'),
                        $widget('home_unanswered'),
                    )),
                    $el('section', 'pf-card mb-4', lang('Popular this week'), array(
                        $sec_head(lang('Popular this week')),
                        $widget('home_popular'),
                    )),
                    // The newest published replies and feedback of every
                    // topic and guide, each opening at its post.
                    $el('section', 'pf-card', lang('Latest replies'), array(
                        $sec_head(lang('Latest replies')),
                        $widget('home_latest_replies'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
        // The featured guides; the band leaves the page while there is none.
        $band(lang('Featured guides'), array(
            $sec_head(lang('Featured guides'), lang('Browse the docs'), '{{page:docs}}'),
            $widget('home_guides'),
        ), 'pf-band pt-0 pf-vanish'),
        $band(lang('Guidelines Call-out'), array(
            $el('div', 'pf-callout pf-tint', lang('Call-out'), array(
                $el('span', 'pf-board-icon', lang('Icon'), array($icon('bi-heart'))),
                $el('div', 'flex-grow-1', lang('Words'), array(
                    $heading('h2', lang('Be kind, be clear, be helpful'), 'h5 fw-bold mb-1'),
                    $para(lang('Read the community guidelines before you post: they keep the forum friendly and easy to search.'), 'mb-0'),
                )),
                $link(lang('Read the guidelines'), '{{page:guidelines}}', 'btn btn-primary flex-shrink-0'),
            )),
        ), 'pf-band pt-0'),
    )),
);

// The forum: every live topic. Its first list is the count of every live
// topic, so its RSS feed is every live topic, the newest first; then the
// pinned topics, then every other topic by its newest activity, with a
// search (the page's one list with page links). The boards beside them.
$pages[] = array(
    'key'              => 'forum',
    'name'             => lang('forum'),
    'folder'           => 'public',
    'title'            => lang('Forum'),
    'meta_description' => lang('Every topic of the community, the most recently active first.'),
    'tree'             => $page(array(
        $page_header(lang('Forum'), lang('Every topic of the community, the most recently active first.'), array(), array(
            $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-3', lang('Forum Numbers'), array(
                $widget('forum_count'),
                $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary'),
            )),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $widget('forum_pinned'),
                    $el('section', '', lang('All topics'), array(
                        $sec_head(lang('All topics'), '', '', 'pf-all-topics'),
                        $widget('forum_topics'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-all-topics')))),
                ), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $board_nav(),
                        $side_card(lang('Popular Tags'), lang('Popular tags'), array(
                            $tag_list('pf-tags mb-3'),
                            $link(lang('Browse topics by tag'), '{{page:tag}}', 'small fw-semibold'),
                        )),
                        $side_card(lang('Waiting for an answer'), lang('Can you help?'), array(
                            $para(lang('These topics have no reply yet. A short answer is better than none.'), 'small text-body-secondary mb-3'),
                            $link(lang('Unanswered topics'), '{{page:unanswered}}', 'btn btn-sm btn-secondary'),
                        )),
                        $side_card(lang('Guidelines'), lang('Before you post'), array(
                            $check_list(array(
                                lang('Search first: your question may have an answer.'),
                                lang('Pick the board that fits.'),
                                lang('One question or idea for each topic.'),
                            )),
                            $link(lang('Read the guidelines'), '{{page:guidelines}}', 'd-inline-block small fw-semibold mt-3'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('Boards and help'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// A board (?board=<name>): the count of its live topics with its name, its
// pinned topics, then its other topics by their newest activity (the page's
// one list with page links). The name comes from the address; only a name a
// live topic carries prints, and an unknown one keeps the page out of the
// index. Every board below. The count comes first, so the page's feed is
// the board's topics (/board?board=<name>&rss=true), and every board's
// without a name.
$pages[] = array(
    'key'              => 'board',
    'name'             => lang('board'),
    'folder'           => 'public',
    'title'            => lang('Boards'),
    'meta_description' => lang('The boards of the forum and the topics in each.'),
    'tree'             => $page(array(
        $page_header(lang('Boards'), '', array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Boards'), ''))),
        )),
        $band(lang('Board'), array(
            $widget('board_head'),
            $row(array(
                $col(array(
                    $widget('board_pinned'),
                    $widget('board_topics'),
                    // The newest replies of the board's topics, each opening
                    // at its post; every board's on the bare page.
                    $el('section', 'pf-card mt-4', lang('Latest replies'), array(
                        $sec_head(lang('Latest replies')),
                        $widget('board_latest_replies'),
                    )),
                ), '', '', '', '12', 'col-xl-10'),
            )),
        ), 'pf-band pt-0'),
        $band(lang('All boards'), array(
            $sec_head(lang('All boards'), lang('Every topic'), '{{page:forum}}'),
            $board_tiles(),
        ), 'pf-band pt-0', array('id' => 'all-boards')),
    )),
);

// The topic (?r=): the topic and its replies (signed in, published at once)
// in the main column, and beside them what is known about it, a way to
// report a post and the guidelines. Two record views of the same topic: the
// replies, the topic's own Comments widget, sit in the page's own tree
// between them. The bare page shows "not found", so it stays out of the
// sitemap; its topics get there through the lists.
$pages[] = array(
    'key'              => 'topic',
    'name'             => lang('topic'),
    'folder'           => 'public',
    'title'            => lang('Topic'),
    'meta_description' => lang('A topic of the community forum.'),
    'sitemap'          => false,
    'comments'         => $reply_comments,
    'tree'             => $page(array(
        $el('div', 'container pf-band pt-4', lang('Topic'), array(
            $row(array(
                $col(array(
                    $widget('topic_view'),
                    $el('div', 'pf-replies d-print-none', lang('Replies'), array($widget('topic_replies'))),
                ), '', '8'),
                $col(array($widget('topic_aside')), '', '4', '', '12', 'd-print-none'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The topics that carry a tag (?tag=<word>): the tags to pick from, then the
// live topics whose tags contain the word, the most recently active first
// (the page's one list with page links). A topic's tags are one line of
// words, so the word is looked for in it: the page cannot say which word it
// found and never takes it as its title. Without a word the list holds
// every live topic. Every address of the page lists topics the forum and
// the boards already list, so search engines and the site search leave it
// out.
$pages[] = array(
    'key'              => 'tag',
    'name'             => lang('tag'),
    'folder'           => 'public',
    'title'            => lang('Topics by tag'),
    'meta_description' => lang('The topics of the forum that carry a tag.'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $page(array(
        $page_header(lang('Topics by tag'), lang('Pick a tag to see the topics that carry it, the most recently active first.'), array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Topics by tag'), ''))),
        ), array(
            $el('nav', 'mt-4', lang('Tags'), array($tag_list()), array('_attrs' => array($attr('aria-label', lang('Tags'))))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('tag_topics')), '', '', '', '12', 'col-xl-10'),
            )),
        ), 'pf-band pt-0'),
    )),
);

// The topics a member started (?member=<their name>): the name a topic
// shows, which comes from the profile of the member who started it. Every
// byline of the forum links here. While a live topic carries the name, the
// page names the member and lists their live topics, the most recently
// active first (the page's one list with page links), under the title
// "Topics started by <name>"; a name no live topic carries says so and keeps
// the page out of search engines. Without a name the page says how to get
// to one.
$pages[] = array(
    'key'              => 'member',
    'name'             => lang('member'),
    'folder'           => 'public',
    'title'            => lang('Topics by member'),
    'meta_description' => lang('The topics a member of the community started.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $page_header(lang('Topics by member'), lang('Every topic a member started, the most recently active first.'), array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Topics by member'), ''))),
        ), array(
            $el('p', 'pf-meta mt-3 mb-0', lang('Names Note'), array(
                $span(lang('The name on a topic comes from the profile of the member who started it.'), 'me-1', lang('Text')),
                $link(lang('Edit my profile'), '{{page:profile}}', ''),
            )),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('member_topics')), '', '', '', '12', 'col-xl-10'),
            )),
        ), 'pf-band pt-0'),
    )),
);

// The topics nobody has answered yet, the oldest first, so the longest
// wait comes first (the page's one list with page links); how to answer well
// and the boards beside them.
$pages[] = array(
    'key'              => 'unanswered',
    'name'             => lang('unanswered'),
    'folder'           => 'public',
    'title'            => lang('Unanswered topics'),
    'meta_description' => lang('Topics that are still waiting for a first reply, the oldest first.'),
    'tree'             => $page(array(
        $page_header(lang('Unanswered topics'), lang('Topics that are still waiting for a first reply, the oldest first. A short answer is better than none.'), array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Unanswered topics'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('unanswered_topics')), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $side_card(lang('How to Answer'), lang('How to answer well'), array(
                            $check_list(array(
                                lang('Answer the question that was asked, in a few clear steps.'),
                                lang('Say which version of the app you tried it on.'),
                                lang('Link to a guide in the docs when one covers it.'),
                            )),
                        ), 'pf-card pf-tint mb-4'),
                        $board_nav(),
                    ), array('_attrs' => array($attr('aria-label', lang('Help and boards'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// The docs: the number of published guides first (the page's first list,
// so its feed is every published guide), then the sections, the featured
// guides, the newest guides and every guide from A to Z with a search (the
// page's one list with page links).
$pages[] = array(
    'key'              => 'docs',
    'name'             => lang('docs'),
    'folder'           => 'public',
    'title'            => lang('Docs'),
    'meta_description' => lang('Guides to the app, written by the community and checked by the moderators.'),
    'tree'             => $page(array(
        $page_header(lang('Docs'), lang('Guides to the app, written by the community and checked by the moderators.'), array(), array(
            $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-3', lang('Docs Numbers'), array(
                $widget('docs_count'),
                $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-primary'),
            )),
        )),
        $band(lang('Sections of the docs'), array(
            $sec_head(lang('Browse by section')),
            $section_tiles(),
        ), 'pf-band pt-0', array('id' => 'doc-sections')),
        // The featured guides; the band leaves the page while there is none.
        $band(lang('Featured guides'), array(
            $sec_head(lang('Featured guides')),
            $widget('docs_featured'),
        ), 'pf-band pt-0 pf-vanish'),
        $band(lang('Latest guides'), array(
            $sec_head(lang('Latest guides')),
            $widget('docs_latest'),
        ), 'pf-band pt-0'),
        $band(lang('Every guide'), array(
            $row(array(
                $col(array(
                    $el('section', '', lang('Every guide'), array(
                        $sec_head(lang('Every guide, A to Z'), '', '', 'pf-every-guide'),
                        $widget('docs_all'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-every-guide')))),
                ), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $side_card(lang('Contribute'), lang('Know something others should?'), array(
                            $para(lang('Write it down as a guide. A moderator reviews it before it is published, and the next person finds the answer here.'), 'small mb-3'),
                            $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-sm btn-primary'),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('Ask the Community'), lang('No guide for it yet?'), array(
                            $para(lang('Ask in the forum: someone may have solved it already.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-secondary'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('Contribute and ask'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0', array('id' => 'every-guide')),
    )),
);

// A section of the docs (?section=<name>): its name and number of published
// guides, then its guides in reading order: by their order in the section,
// then by title (the page's one list with page links). The name comes from
// the address; only a name a published guide carries prints, and an unknown
// one keeps the page out of the index. Every section below. The list comes
// first, so the page's feed is the section's guides.
$pages[] = array(
    'key'              => 'docs_section',
    'name'             => lang('docs-section'),
    'folder'           => 'public',
    'title'            => lang('Sections of the docs'),
    'meta_description' => lang('The guides of one section of the docs, in reading order.'),
    'tree'             => $page(array(
        $page_header(lang('Sections of the docs'), '', array(
            $breadcrumb(array(array(lang('Docs'), '{{page:docs}}'), array(lang('Sections of the docs'), ''))),
        )),
        $band(lang('Section'), array(
            $row(array(
                $col(array($widget('section_guides')), '', '', '', '12', 'col-xl-10'),
            )),
        ), 'pf-band pt-0'),
        $band(lang('All sections'), array(
            $sec_head(lang('All sections'), lang('All guides'), '{{page:docs}}'),
            $section_tiles(),
        ), 'pf-band pt-0', array('id' => 'all-sections')),
    )),
);

// A guide (?r=): shown while it is published, as long-form text, with the
// feedback under it (signed in, with a rating, held until a moderator
// publishes it) and the sections of the docs beside it. The bare page shows
// "not found", so it stays out of the sitemap; its guides get there through
// the lists. A printed guide leaves out the feedback and the side column.
$pages[] = array(
    'key'              => 'doc',
    'name'             => lang('guide'),
    'folder'           => 'public',
    'title'            => lang('Guide'),
    'meta_description' => lang('A guide from the community docs.'),
    'sitemap'          => false,
    'comments'         => $feedback_comments,
    'tree'             => $page(array(
        $el('div', 'container pf-band pt-4', lang('Guide'), array(
            $row(array(
                $col(array(
                    $widget('doc_view'),
                    $el('div', 'pf-replies d-print-none', lang('Feedback'), array($widget('doc_feedback'))),
                ), '', '8'),
                $col(array(
                    $el('aside', 'pf-sticky', lang('Side Column'), array(
                        $doc_nav(),
                        $side_card(lang('Contribute'), lang('Know something others should?'), array(
                            $para(lang('Write it down as a guide. A moderator reviews it before it is published, and the next person finds the answer here.'), 'small mb-3'),
                            $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-sm btn-primary'),
                        ), 'pf-card pf-tint'),
                    ), array('_attrs' => array($attr('aria-label', lang('More from the docs'))))),
                ), '', '4', '', '12', 'd-print-none'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// Start a Topic: the Forum Topics form, in the registration folder (members
// only). Its page owns the form. A topic is public as soon as it is sent:
// its Status starts as Open. The moderators get an e-mail; the author is
// taken to the topic. Addresses are ?r=<reference code> (no pretty URLs):
// the code never changes, while a pretty address would follow every title
// edit.
$pages[] = array(
    'key'     => 'new_topic',
    'name'    => lang('start-a-topic'),
    'folder'  => 'registration',
    'title'   => lang('Start a topic'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Forum Topics'),
        'confirmation_message' => lang('Thank you. Your topic is live.'),
        'confirmation_page_id' => '{{tab:topic}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New topic in the forum: ^^title^^'),
        'notify_page_id'       => '{{tab:email_topic_new}}',
        'pretty_urls'          => 0,
        'sample_records'       => $sample_topics,
    ),
    'tree'    => $page(array(
        $page_header(lang('Start a topic'), lang('Ask a question or start a discussion. Your topic is public as soon as you post it.'), array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Start a topic'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('topic_form'))),
                ), '', '8'),
                $col(array(
                    $side_card(lang('Before you post'), lang('Before you post'), array(
                        $check_list(array(
                            lang('Search the forum and the docs: your question may already have an answer.'),
                            lang('Pick the board that fits, and keep to one question or idea for each topic.'),
                            lang('Give it a title that says what happens.'),
                            lang('Say what you tried, what you expected and what happened instead.'),
                        )),
                    ), 'pf-card pf-tint mb-4'),
                    $side_card(lang('After you post'), lang('After you post'), array(
                        $para(lang('Your topic is public at once. Replies come by e-mail while you follow the topic.'), 'small'),
                        $para(lang('When a reply solves your question, say so in a reply: a moderator marks the topic as solved, so the next person finds the answer.'), 'small'),
                        $para(lang('Your name comes from your profile. To change it, edit your profile.'), 'small mb-3'),
                        $link(lang('Read the guidelines'), '{{page:guidelines}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// What each Status of a guide means to its author, in the words of the
// Status field.
$doc_status_notes = array(
    lang('It reached the moderators; no one has opened it yet.'),
    lang('A moderator is reading and checking it.'),
    lang('The moderators wrote under your guide what to change.'),
    lang('Readers see it in the docs.'),
    lang('It was taken out of the docs, for example because it no longer applies.'),
    lang('The moderators decided not to publish it.'),
);
$doc_status_list = array();
foreach ($doc_statuses as $i => $status) {
    $doc_status_list[] = $el('dt', 'fw-semibold', lang('Status'), array(), array('text' => $status));
    $doc_status_list[] = $el('dd', 'mb-2', lang('Text'), array(), array('text' => $doc_status_notes[$i]));
}

// Contribute a Guide: the Community Docs form, in the registration folder.
// Its page owns the form; a guide is sent as Awaiting review and only a
// moderator publishes it. The author follows it on its own page.
$pages[] = array(
    'key'     => 'doc_new',
    'name'    => lang('contribute-a-guide'),
    'folder'  => 'registration',
    'title'   => lang('Contribute a guide'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Community Docs'),
        'confirmation_message' => lang('Thank you. Your guide has reached the moderators; they will write to you on its page.'),
        'confirmation_page_id' => '{{tab:doc_submission}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New guide to review: ^^title^^'),
        'notify_page_id'       => '{{tab:email_doc_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Your guide reached the moderators: ^^title^^'),
        'confirm_page_id'      => '{{tab:email_doc_received}}',
        'pretty_urls'          => 0,
        'sample_records'       => $sample_docs,
    ),
    'tree'    => $page(array(
        $page_header(lang('Contribute a guide'), lang('Write down what you worked out, so the next person does not have to. A moderator reviews every guide before it is published.'), array(
            $breadcrumb(array(array(lang('Docs'), '{{page:docs}}'), array(lang('Contribute a guide'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('doc_form'))),
                ), '', '8'),
                $col(array(
                    $side_card(lang('What makes a good guide'), lang('What makes a good guide'), array(
                        $check_list(array(
                            lang('One task or one question, from start to finish'),
                            lang('Steps in the order you take them, as a numbered list'),
                            lang('The version of the app it applies to'),
                            lang('Screenshots and files you may share, with nothing private in them'),
                        )),
                    ), 'pf-card pf-tint mb-4'),
                    $side_card(lang('How review works'), lang('How review works'), array(
                        $el('dl', 'small mb-3', lang('Statuses'), $doc_status_list),
                        $para(lang('Nothing you send is shown to readers until a moderator publishes it.'), 'small fw-semibold'),
                        $para(lang('Follow your guide under My guides. The moderators write to you under the guide, and each message also comes by e-mail. To change your guide, write to them there.'), 'small mb-3'),
                        $link(lang('My guides'), '{{page:my_docs}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// Report a Post: the Forum Reports form, in the registration folder. A
// report is never public: the moderators read it on their desk, and the
// member gets a copy by e-mail.
$pages[] = array(
    'key'     => 'report',
    'name'    => lang('report-a-post'),
    'folder'  => 'registration',
    'title'   => lang('Report a post'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Forum Reports'),
        'confirmation_message' => lang('Thank you. A moderator will look at the post and write to you if they need more.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New report in the forum: ^^reason^^'),
        'notify_page_id'       => '{{tab:email_report_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your report'),
        'confirm_page_id'      => '{{tab:email_report_received}}',
        'pretty_urls'          => 0,
    ),
    'tree'    => $page(array(
        $page_header(lang('Report a post'), lang('Tell the moderators about a topic or a reply that breaks the guidelines. Only the moderators see your report.'), array(
            $breadcrumb(array(array(lang('Forum'), '{{page:forum}}'), array(lang('Report a post'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('report_form'))),
                ), '', '8'),
                $col(array(
                    $side_card(lang('What happens next'), lang('What happens next'), array(
                        $check_list(array(
                            lang('A moderator reads every report.'),
                            lang('A post that breaks the guidelines is edited or hidden.'),
                            lang('The member who wrote it is not told who reported it.'),
                        )),
                    ), 'pf-card pf-tint mb-4'),
                    $side_card(lang('In a hurry?'), lang('Something urgent?'), array(
                        $para(lang('If someone is in danger, contact the local emergency services first.'), 'small mb-0'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// Search: the whole site for ?query= (the home page's and the menu
// drawer's search send it here), with the boards and the docs beside it.
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('search'),
    'folder'           => 'public',
    'title'            => lang('Search'),
    'meta_description' => lang('Search every topic, every guide and every page of the community.'),
    'tree'             => $page(array(
        $page_header(lang('Search'), lang('Search every topic, every guide and every page of the community.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('site_search')), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $board_nav(),
                        $side_card(lang('Not found?'), lang('No answer yet?'), array(
                            $para(lang('Ask the community: start a topic in Questions & Help.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-primary'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('Boards and help'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// The community guidelines: what the forum expects of everyone, how to
// write a topic that gets an answer, what to keep private and how the
// moderators work. Each part is a search section.
$pages[] = array(
    'key'              => 'guidelines',
    'name'             => lang('guidelines'),
    'folder'           => 'public',
    'title'            => lang('Community guidelines'),
    'meta_description' => lang('How we keep the forum friendly, useful and easy to search.'),
    'tree'             => $static_page(lang('Community guidelines'), lang('How we keep the forum friendly, useful and easy to search.'), array(
        $prose('be-kind', lang('Be Kind'), lang('Be kind'), array(
            lang('Everyone here was new once. Assume good intent, thank the people who help you, and disagree with ideas, not with people.'),
            lang('No insults, threats, harassment or discrimination of any kind. The moderators remove such posts, and repeated breaches close an account.'),
        )),
        $prose('search-first', lang('Search First'), lang('Search first'), array(
            lang('Many questions already have an answer in the forum or in the docs. Search before you post: you get your answer sooner, and the forum stays easy to search for the next person.'),
            lang('If you find a topic about your problem, reply there instead of starting a new one, and say what is different for you.'),
        )),
        $prose('good-topic', lang('Write a Good Topic'), lang('Write a topic people can answer'), array(
            lang('Pick the board that fits and keep to one question or idea for each topic.'),
            lang('Give it a title that says what happens, such as "Reminders arrive an hour late", rather than "Help!". Then say what you tried, what you expected and what happened instead, and which version of the app and which device you use.'),
            lang('When a reply solves your question, say so in a reply. A moderator then marks the topic as solved, so the next person finds the answer.'),
        )),
        $prose('stay-on-topic', lang('Stay on Topic'), lang('Stay on topic'), array(
            lang('Keep replies to the topic they are under. A new question deserves a topic of its own.'),
            lang('No advertising, no links that only promote something, and no posts written to gather clicks or votes.'),
        )),
        $prose('keep-it-private', lang('Keep It Private'), lang('Keep private things private'), array(
            lang('Never post passwords, keys, invoices or anyone\'s personal details. Check screenshots and log files before you attach them.'),
            lang('Everything you post is public, and search engines may show it. Share only files you have the right to share.'),
        )),
        $prose('moderation', lang('How Moderation Works'), lang('How moderation works'), array(
            lang('The moderators keep the forum tidy: they mark topics as solved, pin the topics everyone should read, move a topic to the right board and hide posts that break these guidelines.'),
            lang('If you see a post that breaks them, report it. Only the moderators see your report, and the member who wrote the post is not told who reported it.'),
        ), array($buttons(array(
            $link(lang('Report a post'), '{{page:report}}', 'btn btn-secondary'),
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-secondary'),
        )))),
    ), array(
        $side_card(lang('In Short'), lang('In short'), array(
            $check_list(array(
                lang('Be kind and assume good intent.'),
                lang('Search before you ask.'),
                lang('One question or idea for each topic.'),
                lang('Keep private things out of your posts.'),
                lang('Report what breaks the guidelines.'),
            )),
        ), 'pf-card pf-tint mb-4'),
        $side_card(lang('Ready to Post'), lang('Ready to post?'), array(
            $buttons(array(
                $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-primary'),
                $link(lang('Search'), '{{page:search}}', 'btn btn-sm btn-secondary'),
            )),
        ), 'pf-card'),
    ), lang('In short')),
);

// Frequently asked questions, in three groups. Each question is a search
// section: a search that matches it opens the page at the question.
$pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently asked questions'),
    'meta_description' => lang('Answers about posting, replies, your account and the docs.'),
    'tree'             => $static_page(lang('Frequently asked questions'), lang('Answers about posting, replies, your account and the docs.'), array(
        $el('section', 'mb-5', lang('Posting and Replying'), array(
            $heading('h2', lang('Posting and replying'), 'h4 fw-bold mb-3'),
            $el('div', 'accordion', lang('FAQ'), array(
                $faq(1, lang('Do I need an account to post?'), array(
                    lang('You can read every topic and guide without one. To start a topic, reply or contribute a guide, sign up: it takes a minute.'),
                ), lang('sign up, register, account, join')),
                $faq(2, lang('How do I start a topic?'), array(
                    lang('Choose Start a topic at the top of any page, pick the board that fits and give the topic a title that says what happens. Your topic is public as soon as you post it.'),
                ), lang('new topic, ask a question, post')),
                $faq(3, lang('Will I know when someone replies?'), array(
                    lang('Yes: every reply to a topic you started comes to you by e-mail. To follow another topic, choose Follow this topic under its replies, or tick the box under the reply form when you reply: its new replies then come to you too.'),
                ), lang('notifications, e-mail, follow, watch, replies')),
                $faq(4, lang('Can I change or delete my topic?'), array(
                    lang('Once a topic is posted, only the moderators can change it. Add details or corrections in a reply to your own topic. To have a topic changed or removed, write to the moderators through the contact page.'),
                ), lang('edit, delete, remove, change a post')),
                $faq(5, lang('What does Solved mean?'), array(
                    lang('The question of the topic has an answer. When a reply solves your question, say so in a reply: a moderator marks the topic as solved and shows that reply first, as the best answer.'),
                ), lang('solved, answered, accepted answer')),
                $faq(6, lang('Why can I no longer find my topic?'), array(
                    lang('A moderator hides a topic that breaks the community guidelines or repeats another topic. If you think that was a mistake, write to the moderators through the contact page.'),
                ), lang('hidden, removed, missing topic, moderation')),
                // Numbered after the others, so their anchors stay as they
                // were.
                $faq(13, lang('How do I answer one reply, or point someone to it?'), array(
                    lang('Choose Reply under it: your answer goes right under it, and deep in a thread it quotes it. Share copies a link that opens the topic at that reply: paste it in a reply, or in a report to the moderators.'),
                ), lang('reply to a reply, quote, share, link to a reply')),
            )),
        )),
        $el('section', 'mb-5', lang('Account and E-mails'), array(
            $heading('h2', lang('Account and e-mails'), 'h4 fw-bold mb-3'),
            $el('div', 'accordion', lang('FAQ'), array(
                $faq(7, lang('How do I change the name others see on my topics?'), array(
                    lang('The name on your topics and replies comes from your profile. Open My Account, choose My Profile and change your first and last name: every topic you started shows the new name, and your next replies are signed with it.'),
                ), lang('display name, profile, rename, username')),
                $faq(8, lang('I forgot my password. What now?'), array(
                    lang('Choose Forgot Password on the sign-in page and enter your e-mail address. We send you a link to set a new password.'),
                ), lang('password, reset, sign in, log in')),
                $faq(9, lang('How do I close my account?'), array(
                    lang('Write to the moderators through the contact page from the e-mail address of your account. Your topics and replies stay in the forum unless you ask us to remove them too.'),
                ), lang('delete account, close account, privacy')),
            )),
        )),
        $el('section', 'mb-5', lang('The Docs'), array(
            $heading('h2', lang('The docs'), 'h4 fw-bold mb-3'),
            $el('div', 'accordion', lang('FAQ'), array(
                $faq(10, lang('Who writes the docs?'), array(
                    lang('Members of the community and the team that makes the app. Anyone with an account can contribute a guide, and a moderator reviews every guide before it is published.'),
                ), lang('docs, guides, contribute, write')),
                $faq(11, lang('How do I follow the review of my guide?'), array(
                    lang('Open My guides from My Account. Each guide you sent has its own page, where the moderators write to you and you can answer them; each message also comes by e-mail.'),
                ), lang('review, my guides, status, moderators')),
                $faq(12, lang('Why does my feedback on a guide not show yet?'), array(
                    lang('Feedback on guides appears once a moderator has read it. Until then you find it under Your replies in My topics, marked Awaiting approval. Questions get the quickest answer as a topic in the forum.'),
                ), lang('feedback, rating, comment, held')),
            )),
        )),
    ), array(
        $side_card(lang('Still Have a Question'), lang('Still have a question?'), array(
            $para(lang('Ask the community, or write to the moderators about your account.'), 'small text-body-secondary mb-3'),
            $buttons(array(
                $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-primary'),
                $link(lang('Contact us'), '{{page:contact}}', 'btn btn-sm btn-secondary'),
            )),
        ), 'pf-card pf-tint'),
    ), lang('More help')),
);

// About the community: who it is for, who runs it, the docs and how to
// reach the moderators.
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the community'),
    'meta_description' => lang('A place to ask questions about the app, share what you made and learn from each other.'),
    'tree'             => $static_page(lang('About the community'), lang('A place to ask questions about the app, share what you made and learn from each other.'), array(
        $prose('who-we-are', lang('Who We Are'), lang('Who we are'), array(
            lang('This community is for everyone who uses the app to take notes, plan tasks and get things done: people who started last week and people who have used it for years.'),
            lang('Most answers here come from other members. The team that makes the app reads along, answers where it can and takes the ideas you post to its planning.'),
        )),
        $prose('the-moderators', lang('The Moderators'), lang('The moderators'), array(
            lang('A few volunteers from the community and members of the team look after the forum. They keep the boards tidy, mark solved topics, review the guides for the docs and act on reports.'),
        ), array($link(lang('Read the guidelines'), '{{page:guidelines}}', 'fw-semibold'))),
        $prose('the-docs', lang('The Docs'), lang('The docs'), array(
            lang('The docs are guides written by members and by the team, from the first steps to the settings few people know about. A moderator reviews every guide before it is published, and readers can rate each one.'),
        ), array($buttons(array(
            $link(lang('Browse the docs'), '{{page:docs}}', 'btn btn-primary'),
            $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-secondary'),
        )))),
        $prose('get-in-touch', lang('Get in Touch'), lang('Get in touch'), array(
            lang('Questions about the app get the quickest answer as a topic in the forum. For anything about your account or the community itself, write to the moderators.'),
        ), array($buttons(array(
            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary'),
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-secondary'),
        )))),
    ), array(
        $side_card(lang('Where to Start'), lang('Where to start'), array(
            $check_list(array(
                lang('Say hello in Getting Started.'),
                lang('Ask in Questions & Help.'),
                lang('Share your setup in Show & Tell.'),
            )),
            $link(lang('Browse the boards'), '{{page:board}}', 'btn btn-sm btn-primary mt-3'),
        ), 'pf-card pf-tint'),
    ), lang('Where to start')),
);

// Contact: a message to the moderators. What is sent opens the conversation
// page, and the answers follow there and in the inbox; a visitor without an
// account gets one.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('Questions about your account or the community: the moderators read every message.'),
    'form'             => array(
        'form_name'            => lang('Write to the Moderators'),
        'confirmation_message' => lang('Thank you, your message has reached the moderators. Their answer comes to your inbox.'),
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message to the moderators: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('Questions about your account or the community: the moderators read every message.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('contact_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('Help With the App'), lang('A question about the app?'), array(
                        $para(lang('Ask it in the forum: other members know the app well, and the answer helps the next person too.'), 'small mb-3'),
                        $buttons(array(
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-primary'),
                            $link(lang('Search'), '{{page:search}}', 'btn btn-sm btn-secondary'),
                        )),
                    ), 'pf-card pf-tint mb-4'),
                    $side_card(lang('Contact Details'), lang('Other ways to reach us'), array(
                        $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Email'), array(
                            $icon('bi-envelope'),
                            $link('community@example.com', 'mailto:community@example.com', 'link-body-emphasis'),
                        )),
                        $el('p', 'd-flex align-items-start gap-2 small text-body-secondary mb-0', lang('Hours'), array(
                            $icon('bi-clock', 'mt-1'),
                            $span(lang('We answer on weekdays, usually within two working days.'), '', lang('Text')),
                        )),
                    ), 'pf-card mb-4'),
                    $el('div', 'pf-card', lang('Inbox Note'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                            $icon('bi-inbox'),
                            $span(lang('We answer in your account\'s inbox.'), '', lang('Text')),
                        )),
                        $para(lang('Your message opens a conversation: the page that opens after you send it, and your inbox, show the moderators\' answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                        $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// The legal pages: sample text, each with a note to adapt it.
$pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the community keeps about you, why, and what you can ask of us.'),
    'tree'             => $static_page(lang('Privacy Policy'), lang('What the community keeps about you, why, and what you can ask of us.'), array(
        $sample_note(),
        $prose('your-data', lang('Your Data'), lang('Your data'), array(
            lang('When you open an account, we keep your name, your e-mail address and the settings you choose. What you post, the topics, replies, guides and feedback, is public and kept with your account; reports and messages to the moderators are seen only by the moderators.'),
            lang('We use your e-mail address to sign you in, to answer you and to tell you about replies to the topics you follow. We never sell it or give it to advertisers.'),
            lang('The site uses the cookies it needs to keep you signed in and to remember your choices. You can see and change your account data from My Account, and ask us to delete it through the contact page.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'terms',
    'name'             => lang('terms-of-use'),
    'folder'           => 'public',
    'title'            => lang('Terms of Use'),
    'meta_description' => lang('The rules for posting in the forum and contributing to the docs.'),
    'tree'             => $static_page(lang('Terms of Use'), lang('The rules for posting in the forum and contributing to the docs.'), array(
        $sample_note(),
        $prose('using-the-site', lang('Using the Site'), lang('Using the site'), array(
            lang('Anyone may read the forum and the docs. To post you need an account, and you are responsible for what you post with it.'),
            lang('Posts must follow the community guidelines. The moderators may edit, hide or remove a post that breaks them, and close an account that keeps breaking them.'),
            lang('What you post stays your own work. By posting it, you allow the community to show it on the site; by sending a guide, you also allow the moderators to edit it before they publish it.'),
            lang('Answers in the forum come from other members and are given in good faith, without a guarantee. Keep a copy of anything important before you follow a step you are unsure of.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'accessibility',
    'name'             => lang('accessibility'),
    'folder'           => 'public',
    'title'            => lang('Accessibility'),
    'meta_description' => lang('How the site works with a keyboard, a screen reader, the dark theme and reduced motion.'),
    'tree'             => $static_page(lang('Accessibility'), lang('We want everyone to be able to take part in the community.'), array(
        $sample_note(),
        $prose('accessibility-help', lang('How the Site Helps'), lang('How the site helps'), array(
            lang('Every page works with a keyboard alone: the first press of Tab shows a link that skips to the main content, and every link, button and field shows a clear outline when it has the focus.'),
            lang('Pages have landmarks and headings in order, so a screen reader can jump to the topics, the replies or the side column.'),
            lang('Words and buttons keep a readable contrast in the light and the dark theme; switch the theme with the button at the top of the page.'),
            lang('Nothing on the site moves by itself, and the little motion there is stops when your device asks for less motion.'),
            lang('A printed topic or guide leaves out the menus, the replies, the feedback and the side column, so the text alone is printed.'),
            lang('If something on the site does not work for you, tell us and we will fix it.'),
        ), array(
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-primary'),
        )),
    )),
);

// Signing in and out. The forms are the palette's own layouts, each a
// narrow card centred in its own container, set in a band so the card
// keeps the other pages' distance from the masthead; the sign-in pages end
// with a tinted band that leads to the forum.
$sign_panel = function () use ($el, $para, $link, $container) {
    return $el('div', 'pf-band pf-tint text-center d-print-none', lang('Tinted Panel'), array(
        $container(array(
            $para(lang('Ask, share and learn with the people who use the app every day.'), 'mb-2'),
            $el('p', 'mb-0', lang('Link'), array(
                $link(lang('Read the forum without signing in'), '{{page:forum}}', 'fw-semibold'),
            )),
        )),
    ));
};
$sign_page = function ($key, $name, $title, $widget_key, $with_panel = false) use ($el, $page, $widget, $sign_panel) {
    $main = array($el('div', 'pf-band pb-4', lang('Content'), array($widget($widget_key))));
    if ($with_panel) $main[] = $sign_panel();
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'public',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page($main),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login', true);
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register', true);
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password', true);
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the member's own pages, behind the registration folder.
$pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security'))),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The inbox: the member's conversations with the moderators (for the
// moderators, everybody's), and the conversation page, where both sides
// answer as messages under the first one.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('Your conversations with the moderators: the messages you sent from the contact page, and their answers.'), array(
            $breadcrumb(array(array(lang('My Account'), '{{page:my_account}}'), array(lang('Inbox'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('inbox_list')), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $side_card(lang('New Message'), lang('Something for the moderators?'), array(
                            $para(lang('Write to them from the contact page: your message and their answers stay here, in one conversation.'), 'small mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('Help With the App'), lang('A question about the app?'), array(
                            $para(lang('Ask it in the forum: other members know the app well, and the answer helps the next person too.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-sm btn-secondary'),
                        ), 'pf-card mb-4'),
                        $side_card(lang('Guide Messages'), lang('Sent a guide?'), array(
                            $para(lang('Messages about a guide you sent are written under the guide itself, in My guides.'), 'small text-body-secondary mb-3'),
                            $link(lang('My guides'), '{{page:my_docs}}', 'btn btn-sm btn-secondary'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('More ways to get help'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);
// One conversation: the first message, then the answers as messages. The
// contact form's confirmation page: whoever wrote lands here (?r=<code>).
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $conversation_comments,
    'tree'     => $page(array(
        $el('div', 'container pf-band pt-4', lang('Conversation'), array(
            $row(array(
                $col(array(
                    $widget('conversation'),
                    $el('div', 'pf-replies d-print-none', lang('Replies'), array($comments_block())),
                ), '', '8'),
            ), '', 'center'),
        )),
    )),
);

// The member's own topics: the ones they started, the most recently active
// first (the page's one list with page links), and the ones they follow.
// Both hold live topics only: a topic the moderators hid opens nowhere, so
// it leaves this page too. Under them, the member's own replies and
// feedback, the newest first, the ones awaiting approval marked so.
$pages[] = array(
    'key'     => 'my_topics',
    'name'    => lang('my-topics'),
    'folder'  => 'registration',
    'title'   => lang('My topics'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array(
        $page_header(lang('My topics'), lang('The topics you started, the topics you follow and your latest replies.'), array(
            $breadcrumb(array(array(lang('My Account'), '{{page:my_account}}'), array(lang('My topics'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('section', 'mb-5', lang('Started'), array(
                        $sec_head(lang('Topics you started'), lang('Start a topic'), '{{page:new_topic}}', 'pf-started'),
                        $widget('my_topics_started'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-started')))),
                    $el('section', 'mb-5', lang('Followed'), array(
                        $sec_head(lang('Topics you follow'), '', '', 'pf-followed'),
                        $widget('my_topics_followed'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-followed')))),
                    $el('section', '', lang('Your replies'), array(
                        $sec_head(lang('Your replies'), '', '', 'pf-my-replies'),
                        $widget('my_replies'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-my-replies')))),
                ), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $side_card(lang('Following'), lang('How following works'), array(
                            $para(lang('Every reply to a topic you started comes to you by e-mail.'), 'small'),
                            $para(lang('To follow someone else\'s topic, choose Follow this topic under its replies, or tick the box under the reply form when you reply: the topic then appears here, and its new replies come to you by e-mail.'), 'small mb-0'),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('Missing Topic'), lang('A topic is missing?'), array(
                            $para(lang('A topic the moderators hid no longer shows anywhere, this page included. If you think that was a mistake, write to them.'), 'small text-body-secondary mb-3'),
                            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-sm btn-secondary'),
                        ), 'pf-card mb-4'),
                        $side_card(lang('Your Name'), lang('The name on your topics'), array(
                            $para(lang('Your topics show the first and last name in your profile.'), 'small text-body-secondary mb-3'),
                            $link(lang('Edit my profile'), '{{page:profile}}', 'btn btn-sm btn-secondary'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('About your topics'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);

// The member's guides on the docs desk: every guide they sent, in any
// status, the newest first, each opening its page on the desk (the page's
// one list with page links); beside them, the ones readers see now.
$pages[] = array(
    'key'     => 'my_docs',
    'name'    => lang('my-guides'),
    'folder'  => 'registration',
    'title'   => lang('My guides'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array(
        $page_header(lang('My guides'), lang('The guides you sent to the docs, their status and the moderators\' messages, the newest first.'), array(
            $breadcrumb(array(array(lang('My Account'), '{{page:my_account}}'), array(lang('My guides'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('section', '', lang('Sent Guides'), array(
                        $sec_head(lang('Guides you sent'), lang('Contribute a guide'), '{{page:doc_new}}', 'pf-sent-guides'),
                        $widget('my_docs_list'),
                    ), array('_attrs' => array($attr('aria-labelledby', 'pf-sent-guides')))),
                ), '', '8'),
                $col(array(
                    $el('aside', '', lang('Side Column'), array(
                        $side_card(lang('In the Docs'), lang('In the docs now'), array(
                            $widget('my_docs_live'),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('How review works'), lang('What each status means'), array(
                            $el('dl', 'small mb-0', lang('Statuses'), $doc_status_list),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('Your published guides and what each status means'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pf-band pt-0'),
    )),
);
// One guide on the docs desk: what its author sent, its Status and the
// messages between the author and the moderators, for them alone. The
// Community Docs form's confirmation page: an author lands here
// (?r=<code>) right after sending.
$pages[] = array(
    'key'      => 'doc_submission',
    'name'     => lang('guide-on-the-desk'),
    'folder'   => 'registration',
    'title'    => lang('Guide on the Desk'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'comments' => $doc_desk_comments,
    'tree'     => $page(array(
        $el('div', 'container pf-band pt-4', lang('Guide on the Desk'), array(
            $row(array(
                $col(array(
                    $widget('doc_submission_view'),
                    $el('div', 'pf-replies d-print-none', lang('Messages'), array($comments_block())),
                ), '', '8'),
                $col(array(
                    $el('aside', 'pf-sticky', lang('Side Column'), array(
                        $side_card(lang('How review works'), lang('What each status means'), array(
                            $el('dl', 'small mb-3', lang('Statuses'), $doc_status_list),
                            $para(lang('Nothing you send is shown to readers until a moderator publishes it.'), 'small fw-semibold mb-0'),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('Changes'), lang('Want to change something?'), array(
                            $para(lang('Write to the moderators under your guide: a moderator makes the change, and each answer also comes by e-mail.'), 'small text-body-secondary mb-0'),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('How review works'))))),
                ), '', '4', '', '12', 'd-print-none'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// ── The moderators' pages ───────────────────────────────────────────────
// Behind the private folder, under the staff bar: the dashboard, every
// topic, the docs desk, the reported posts and one report, and the staff
// directory with its form.

// A list of the dashboard or a desk under its heading: the anchor the jump
// list points at, a line on what it holds and, under it, the way to the
// rest. $lg '' gives it the whole width.
$desk_section = function ($id, $title, $text, $widget_key, $more_text = '', $more_href = '', $lg = '6') use ($el, $heading, $para, $widget, $icon, $span, $attr) {
    $head_id = 'pf-' . $id . '-title';
    $children = array(
        $el('div', 'mb-3', lang('Section Header'), array(
            $heading('h2', $title, 'pf-sec-title mb-1', array('id' => $head_id)),
            $para($text, 'small text-body-secondary mb-0'),
        )),
        $widget($widget_key),
    );
    if ($more_text !== '') {
        $children[] = $el('a', 'pf-more mt-3', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('section', 'col-12' . ($lg !== '' ? ' col-lg-' . $lg : ''), $title, $children,
        array('id' => $id, '_attrs' => array($attr('aria-labelledby', $head_id))));
};
// The jump list over a desk: plain anchors, outside any smart active menu
// (it would mark every one of them as the current page).
$jump_list = function ($items) use ($el, $link, $attr) {
    $lis = array();
    foreach ($items as $it) {
        $lis[] = $el('li', 'nav-item', lang('Menu Item'), array($link($it[0], '#' . $it[1], 'nav-link')));
    }
    return $el('nav', 'border-bottom d-print-none', lang('On this page'), array(
        $el('div', 'container', lang('Container'), array(
            $el('ul', 'nav nav-underline column-gap-4 small', lang('Links'), $lis),
        )),
    ), array('_attrs' => array($attr('aria-label', lang('On this page')))));
};
// A shortcut of the dashboard: an icon, its name linking to the page and a
// line on what it is for.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $link, $para, $icon) {
    return $el('div', 'col-sm-6 col-xl-3', lang('Column'), array(
        $el('article', 'pf-card pf-card-link pf-board h-100', $title, array(
            $el('span', 'pf-board-icon', lang('Icon'), array($icon($icon_name))),
            $el('div', 'pf-min-0', lang('Words'), array(
                $el('h3', 'pf-card-title h6 mb-1', lang('Title'), array($link($title, $href, 'stretched-link'))),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ));
};
// Numbered steps, one sentence each.
$steps = function ($items, $class = 'small ps-3 mb-0') use ($el) {
    $lis = array();
    foreach ($items as $text) $lis[] = $el('li', 'mb-2', lang('Step'), array(), array('text' => $text));
    return $el('ol', $class, lang('Steps'), $lis);
};

// How moderation works: who gets access, how a topic, a guide and a report
// are handled, and what must not change. One sentence or two each, with
// the control panel's own words.
$staff_notes = array(
    lang('Moderators: open the account under Users and, under Content and Forms, tick the private, registration and public folders and switch on Reach the data submitted through forms. Administrators, designers and managers need none of this.'),
    lang('The private folder opens these pages. The registration folder holds the Forum Topics, Community Docs and Forum Reports forms: it lets a moderator open any guide on the docs desk and any report, and change them in the control panel. The public folder holds the topic and guide pages and the contact form: it lets a moderator publish, edit or delete replies and feedback, stop new replies under a topic and answer contact messages in the inbox. A ticked folder also lets the moderator edit its pages.'),
    lang('To take a topic off the site, open it with the pencil on its row or Moderate on its page, set its Status to Hidden and save: it leaves every list and its page at once, and Open brings it back. A hidden topic opens only in the control panel.'),
    lang('Mark a topic Solved, pin it or make it an announcement the same way, in its Status, Pinned and Announcement fields. Closed is only a word on the topic: to stop new replies, use Stop new replies under its replies.'),
    lang('Replies are published at once; feedback on a guide waits until a moderator publishes it, and is listed under Replies awaiting approval on this page. Edit, publish or delete them under Comments in the control panel, or with Edit on each one on its page.'),
    lang('To show the reply that solved a topic first, open it with Edit, tick Featured and save: it moves to the top of the topic as the best answer.'),
    lang('A topic shows the first and last name in its author\'s profile, and a member\'s reply or message is signed with that name, or with Member when the profile has none. Your replies, messages and notes are signed with the name in the form\'s name box, filled in from your own profile: fill in your first and last name under My Profile.'),
    lang('A guide arrives as Awaiting review. Open it on the docs desk, write to its author under it and set its Status: In review while you read it, Changes requested once you wrote what to change, Live on the site to put it in the docs, Withdrawn to take it out, Not accepted to decline it. A Status alone sends no e-mail: say what you did in a message under the guide.'),
    lang('Before you publish a guide, check its links and remove pictures that load from other sites: both are printed as written, for every reader. Readers can open its attachment once it is published.'),
    lang('Give each guide an order of two digits in its section, such as 10, 20 and 30: the order is compared as text, so 100 would come before 20, and a guide without one comes first. Featured shows a published guide on the home page and in the docs.'),
    lang('A report arrives as Open. Its post is what the member typed: copy it into your browser, it is never a link. Act on the post, write a note under the report for the other moderators, then set the report to Resolved or Dismissed. The member who wrote the post is never told who reported it.'),
    lang('Never rename or remove the fields named status, board, kind, pinned, announcement, section, featured or doc_order, nor their options: the lists filter on these words, and a list whose filter names a missing field or word shows nothing. Keep Hide from the visitor (staff only) on for the office fields, or members could set them. A board, a section or a status is renamed by an administrator or designer in the visual editor, together with the board and section links of the pages; then publish, and set the new word on the records that had the old one.'),
    lang('Site search changes when its index is rebuilt, once a day: after you hide a topic, an administrator, designer or manager can run Update Search Index in the control panel to take it out of search at once. The popular tags of the forum and tag pages are written in the design: keep them in step with the tags members use.'),
    lang('New topics, guides, reports and contact messages are e-mailed to the site\'s address; replies are not: follow them in Latest replies on the home page and under Comments.'),
);
// The notes in two columns from lg: the first half, then the rest.
$staff_note_cols = array();
foreach (array_chunk($staff_notes, (int)ceil(count($staff_notes) / 2)) as $half) {
    $items = array();
    foreach ($half as $text) $items[] = $el('li', 'mb-2', lang('Note'), array(), array('text' => $text));
    $staff_note_cols[] = $col(array($el('ul', 'small mb-0 ps-3', lang('Notes'), $items)), '', '6');
}
$staff_stat = function ($widget_key) use ($el, $widget) {
    return $el('div', 'col-6 col-md-4 col-xl-2', lang('Column'), array($widget($widget_key)));
};

// The dashboard: what is waiting for the moderators in numbers, then the
// lists (each capped, none pages through), the shortcuts and how
// moderation works.
$pages[] = array(
    'key'     => 'staff',
    'name'    => lang('moderation'),
    'folder'  => 'private',
    'title'   => lang('Moderation'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'pf-band pb-4 pf-tint border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $para(lang('Moderators'), 'pf-kicker mb-2'),
                $heading('h1', lang('Moderation'), 'pf-page-title mb-2', array('id' => 'pf-staff-title')),
                $para(lang('What is waiting for the moderators: reports, guides to review, replies awaiting approval, new and unanswered topics, and the topics taken off the site.'), 'lead text-body-secondary pf-lead mb-4'),
                $buttons(array(
                    $link(lang('Moderate replies and feedback'), $comments_url, 'btn btn-primary'),
                    $link(lang('Open the control panel'), $panel_url, 'btn btn-secondary'),
                )),
                // What is waiting, in numbers: six counts of their own.
                $el('div', 'row g-2 g-md-3 mt-4', lang('Numbers'), array(
                    $staff_stat('staff_count_open'),
                    $staff_stat('staff_count_unanswered'),
                    $staff_stat('staff_count_solved'),
                    $staff_stat('staff_count_hidden'),
                    $staff_stat('staff_count_reports'),
                    $staff_stat('staff_count_guides'),
                ), array('_attrs' => array($attr('aria-label', lang('Moderation in numbers')), $attr('role', 'group')))),
            )),
        ), array('_attrs' => array($attr('aria-labelledby', 'pf-staff-title')))),
        $jump_list(array(
            array(lang('Open reports'), 'open-reports'),
            array(lang('Guides awaiting review'), 'guides-to-review'),
            array(lang('Replies awaiting approval'), 'replies-waiting'),
            array(lang('New topics this week'), 'new-topics'),
            array(lang('Unanswered topics'), 'unanswered-topics'),
            array(lang('Hidden topics'), 'hidden-topics'),
            array(lang('How moderation works'), 'how-moderation-works'),
        )),
        $band(lang('Desk'), array(
            $row(array(
                $desk_section('open-reports', lang('Open reports'), lang('Reported posts nobody has dealt with yet, the oldest first.'), 'staff_reports_waiting', lang('All reports'), '{{page:staff_reports}}'),
                $desk_section('guides-to-review', lang('Guides awaiting review'), lang('Guides sent to the docs that no moderator has opened yet, the oldest first.'), 'staff_guides_waiting', lang('Docs desk'), '{{page:staff_docs}}'),
                $desk_section('replies-waiting', lang('Replies awaiting approval'), lang('Feedback on guides and replies held for a moderator, the oldest first. Publish, edit or delete each one.'), 'staff_replies_waiting', lang('Moderate replies and feedback'), $comments_url),
                $desk_section('new-topics', lang('New topics this week'), lang('Every live topic posted in the last seven days, the newest first. A topic taken off the site is under Hidden topics.'), 'staff_new_topics', lang('Topics desk'), '{{page:staff_topics}}'),
                $desk_section('unanswered-topics', lang('Unanswered topics'), lang('Live topics still waiting for a first reply, the oldest first.'), 'staff_unanswered', lang('See all'), '{{page:unanswered}}'),
                $desk_section('most-viewed', lang('Most viewed this week'), lang('The live topics viewed most in the last seven days.'), 'staff_popular'),
                $desk_section('recently-solved', lang('Recently solved'), lang('Solved topics, the most recently active first.'), 'staff_solved'),
                $desk_section('hidden-topics', lang('Hidden topics'), lang('Topics taken off the site, the most recently active first. Only the control panel opens them: use the pencil on a row.'), 'staff_hidden',
                    lang('Every hidden topic'), '{{page:staff_topics}}?status=' . rawurlencode($hidden_value)),
            ), '4', '', 'g-lg-5'),
        )),
        $band(lang('Shortcuts'), array(
            $sec_head(lang('Shortcuts')),
            $row(array(
                $staff_shortcut('bi-chat-square-quote', lang('Comments'), lang('Every reply, feedback, message and note: publish feedback, edit or delete a reply.'), $comments_url),
                $staff_shortcut('bi-ui-checks', lang('Submitted Forms'), lang('Every topic, guide and report as a record: set a status, pin a topic, feature a guide.'), $forms_url),
                $staff_shortcut('bi-list-ul', lang('Topics desk'), lang('Every topic in any status, with a search.'), '{{page:staff_topics}}'),
                $staff_shortcut('bi-journal-check', lang('Docs desk'), lang('The guides sent to the docs, by status.'), '{{page:staff_docs}}'),
                $staff_shortcut('bi-flag', lang('Reported posts'), lang('Open, resolved and dismissed reports.'), '{{page:staff_reports}}'),
                $staff_shortcut('bi-inbox', lang('Inbox'), lang('Every message from the contact page. Answer under each one.'), '{{page:inbox}}'),
                $staff_shortcut('bi-person-lines-fill', lang('Staff Directory'), lang('The moderators and the team, and how to reach them.'), '{{page:staff_directory}}'),
                $staff_shortcut('bi-speedometer2', lang('Control panel'), lang('Users, folders, pages and the site\'s settings.'), $panel_url),
            ), '3'),
        ), 'pf-band pt-0'),
        // How moderation works: who gets access, how a topic, a guide and a
        // report are handled, and what must not change. Under the lists, so
        // they come first; the jump list leads here.
        $band(lang('Moderation Note'), array(
            $sec_head(lang('How moderation works')),
            $row($staff_note_cols, '4', '', 'g-lg-5'),
        ), 'pf-band pt-0', array('id' => 'how-moderation-works')),
    )),
);

// The topics desk: every topic in any status, the most recently active
// first, with a search (the page's one list with page links); the links
// above it narrow the list to one Status (?status=). Its titles are no
// links: a hidden topic's page would not show it, and a list cannot link
// some rows and not others. The pencil on a row opens the record.
$status_links = array($el('li', 'nav-item', lang('Menu Item'), array($link(lang('Every status'), '{{page:staff_topics}}', 'nav-link'))));
foreach ($topic_statuses as $status) {
    $status_links[] = $el('li', 'nav-item', lang('Menu Item'), array($link($status, '{{page:staff_topics}}?status=' . rawurlencode($status), 'nav-link')));
}
$pages[] = array(
    'key'     => 'staff_topics',
    'name'    => lang('topics-desk'),
    'folder'  => 'private',
    'title'   => lang('Topics desk'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Topics desk'), lang('Every topic of the forum in any status, the most recently active first. The pencil on a row opens the topic in the control panel: set its status, pin it or write a note there.'), array(
            $breadcrumb(array(array(lang('Moderation'), '{{page:staff}}'), array(lang('Topics desk'), ''))),
        ), array(
            $el('nav', 'mt-4', lang('Statuses'), array(
                $el('ul', 'nav nav-underline column-gap-4', lang('Links'), $status_links),
            ), array('_attrs' => array($attr('aria-label', lang('Topics by status'))))),
        )),
        $band(lang('Content'), array($widget('staff_topics_list')), 'pf-band pt-0'),
    )),
);

// The docs desk: the guides sent to the docs by Status, each opening its
// page on the desk; the published ones open as readers see them. Only the
// last list, every guide, pages through.
$pages[] = array(
    'key'     => 'staff_docs',
    'name'    => lang('docs-desk'),
    'folder'  => 'private',
    'title'   => lang('Docs desk'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Docs desk'), lang('The guides members sent to the docs, by status. Open one to read it and write to its author; set its Status with Change Status.'), array(
            $breadcrumb(array(array(lang('Moderation'), '{{page:staff}}'), array(lang('Docs desk'), ''))),
        )),
        $jump_list(array(
            array(lang('Awaiting review'), 'awaiting-review'),
            array(lang('In review'), 'in-review'),
            array(lang('With the author'), 'with-the-author'),
            array(lang('Live on the site'), 'published'),
            array(lang('Every guide'), 'every-guide'),
        )),
        $band(lang('Desk'), array(
            $row(array(
                $desk_section('awaiting-review', lang('Awaiting review'), lang('New guides, the oldest first.'), 'staff_docs_waiting'),
                $desk_section('in-review', lang('In review'), lang('Guides a moderator is reading now.'), 'staff_docs_in_review'),
                $desk_section('with-the-author', lang('With the author'), lang('Guides sent back for changes. Look at the last activity: a quiet one may need a word.'), 'staff_docs_changes'),
                $desk_section('published', lang('Live on the site'), lang('What readers see in the docs, the newest first. A row opens the guide as readers see it.'), 'staff_docs_published'),
                $desk_section('every-guide', lang('Every guide'), lang('Every guide sent to the docs, in any status, with a search.'), 'staff_docs_all', '', '', ''),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The reported posts: the open ones, the oldest first, then the resolved
// and the dismissed ones, and every report with a search (the page's one
// list with page links). Each opens the report's page.
$pages[] = array(
    'key'     => 'staff_reports',
    'name'    => lang('reported-posts'),
    'folder'  => 'private',
    'title'   => lang('Reported posts'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Reported posts'), lang('What members reported to the moderators. Open a report, act on the post, write a note, then resolve or dismiss it.'), array(
            $breadcrumb(array(array(lang('Moderation'), '{{page:staff}}'), array(lang('Reported posts'), ''))),
        )),
        $jump_list(array(
            array(lang('Open'), 'open'),
            array(lang('Resolved'), 'resolved'),
            array(lang('Dismissed'), 'dismissed'),
            array(lang('Every report'), 'every-report'),
        )),
        $band(lang('Desk'), array(
            $row(array(
                $desk_section('open', lang('Open'), lang('Reports nobody has dealt with yet, the oldest first.'), 'staff_reports_open', '', '', ''),
                $desk_section('resolved', lang('Resolved'), lang('The ten most recently active: the post was dealt with.'), 'staff_reports_resolved'),
                $desk_section('dismissed', lang('Dismissed'), lang('The ten most recently active: the post broke no guideline.'), 'staff_reports_dismissed'),
                $desk_section('every-report', lang('Every report'), lang('Every report in any status, the newest first, with a search.'), 'staff_reports_all', '', '', ''),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// One report, for the moderators: what the member reported and why, and
// the moderators' notes under it (published at once, never e-mailed).
$pages[] = array(
    'key'      => 'staff_report',
    'name'     => lang('report'),
    'folder'   => 'private',
    'title'    => lang('Reported post'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'comments' => $staff_note_comments,
    'tree'     => $staff_page(array(
        $el('div', 'container pf-band pt-4', lang('Content'), array(
            $row(array(
                $col(array(
                    $widget('staff_report_view'),
                    $el('div', 'pf-replies d-print-none', lang('Notes'), array($comments_block())),
                ), '', '8'),
                $col(array(
                    $el('aside', 'pf-sticky', lang('Side Column'), array(
                        $side_card(lang('Handling a Report'), lang('Handling a report'), array(
                            $steps(array(
                                lang('Copy the post\'s address into your browser and read the post.'),
                                lang('If it breaks the guidelines, hide the topic, or edit or delete the reply under Comments.'),
                                lang('Write a note below: what you found and what you did.'),
                                lang('Set the report to Resolved or Dismissed with Change Status.'),
                            )),
                        ), 'pf-card pf-tint mb-4'),
                        $side_card(lang('Where to Act'), lang('Where to act'), array(
                            $buttons(array(
                                $link(lang('Comments'), $comments_url, 'btn btn-sm btn-secondary'),
                                $link(lang('Submitted Forms'), $forms_url, 'btn btn-sm btn-secondary'),
                            )),
                        ), 'pf-card'),
                    ), array('_attrs' => array($attr('aria-label', lang('Handling a report'))))),
                ), '', '4', '', '12', 'd-print-none'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The community's directory, for the moderators only (it lists e-mails and
// phones), and the form that adds a person to it.
$pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'private',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Staff Directory'), lang('The moderators and the team behind the community, and how to reach them. Only the moderators see this page.'), array(), array(
            $buttons(array($link(lang('Add to the Staff Directory'), '{{page:staff_entry}}', 'btn btn-primary')), 'd-flex flex-wrap align-items-center gap-2 mt-3'),
        )),
        $band(lang('Directory'), array($widget('staff_directory')), 'pf-band pt-0'),
    )),
);
$pages[] = array(
    'key'     => 'staff_entry',
    'name'    => lang('add-to-staff-directory'),
    'folder'  => 'private',
    'title'   => lang('Add to the Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Community Team Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('Add to the Staff Directory'), lang('Add a moderator or a member of the team to the directory. Only the moderators see it.'), array(
            $breadcrumb(array(array(lang('Staff Directory'), '{{page:staff_directory}}'), array(lang('Add to the Staff Directory'), ''))),
        )),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('staff_form'))),
                ), '', '8'),
            ), '', 'center'),
        ), 'pf-band pt-0'),
    )),
);

// The page a missing address shows.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page'))),
);

// The forms' e-mails: each form's notification to the moderators and its
// copy to whoever sent it. Their widgets show the record just sent.
$mail_pages = array(
    // Forum Topics: to the moderators (a topic is live at once).
    array('email_topic_new', lang('email-new-topic'), lang('New Topic E-mail'), 'email_topic_staff'),
    // Forum Reports: to the moderators, and to the member who reported.
    array('email_report_new', lang('email-new-report'), lang('New Report E-mail'), 'email_report_staff'),
    array('email_report_received', lang('email-report-received'), lang('Report Received E-mail'), 'email_report_member'),
    // Community Docs: to the moderators, and to the guide's author.
    array('email_doc_new', lang('email-new-guide'), lang('New Guide E-mail'), 'email_doc_staff'),
    array('email_doc_received', lang('email-guide-received'), lang('Guide Received E-mail'), 'email_doc_author'),
    // The contact form: to the moderators, and to whoever wrote.
    array('email_new_message', lang('email-new-message'), lang('New Message E-mail'), 'email_message_admin'),
    array('email_message_received', lang('email-message-received'), lang('Message Received E-mail'), 'email_message_sender'),
);
foreach ($mail_pages as $m) {
    $pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// The comment e-mails. Their Comments widget draws the comment the e-mail
// is about in the template's layout, with its "View or Reply" button. The
// System region after it keeps the fixed comment and its own View or Reply
// button for an e-mail the widget does not draw: a comment of a product or
// an event, or a card edited so it lost its link to the comment.
$mail_system = array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array());
// Something new on a page the member follows: a reply under a topic they
// started or follow, feedback a moderator published under a guide they
// wrote or follow, or an answer in their conversation with the moderators.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is something new for you')),
        $mail_para(lang('Someone wrote on a page you follow: a reply under a topic, feedback under a guide, or an answer from the moderators in your conversation. Read it below and answer on the page.')),
        $widget('email_comment'),
        $mail_system,
    )),
));
// A message under a guide sent to the docs: to its author, to whoever
// follows it and to the moderators, in the same words for every status.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_doc_message',
    'name'  => lang('email-guide-message'),
    'title' => lang('Guide Message E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('New message about a guide sent to the docs')),
        $mail_para(lang('There is a new message under a guide sent to the docs. Read it below and answer on the guide\'s page.')),
        $widget('email_comment'),
        $mail_system,
    )),
));

// The account e-mails: the welcome after a sign-up and the password reset
// link. Their Account E-mail widgets fill in the member's name and links.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_account_welcome',
    'name'  => lang('email-account-welcome'),
    'title' => lang('Account Welcome E-mail'),
    'tree'  => $mail_page(array($widget('account_email_welcome'))),
));
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_password_reset',
    'name'  => lang('email-password-reset'),
    'title' => lang('Password Reset E-mail'),
    'tree'  => $mail_page(array($widget('account_email_reset'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// A link of the account menu; $token binds its address (a page the widget
// knows), $flag shows the link only while that widget flag is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};
// The member's account menu. The staff directory is not in it: it lists
// the moderators' e-mails and phones and is for the moderators only.
$account_links = array(
    $account_link('bi-chat-square-text', lang('My topics'), '{{page:my_topics}}'),
    $account_link('bi-journal-text', lang('My guides'), '{{page:my_docs}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-check', lang('Email Preferences'), '#', '__email_preferences_url');

// A card of the account page: an icon, a title, a line and its buttons.
$account_card = function ($icon_name, $title, $text, $buttons, $extra = array()) use ($el, $heading, $para, $icon) {
    return $el('div', 'pf-card mb-4', $title, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $el('span', 'pf-board-icon', lang('Icon'), array($icon($icon_name))),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h5 fw-bold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        )),
    ), $extra);
};

$widgets = array(
    // The sign-in in the masthead: two links for a visitor, the account
    // menu for a member. "Moderation" is drawn for whoever the staff page's
    // private folder lets in.
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
            'staff_page_id'      => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-secondary text-nowrap d-none d-sm-inline-block', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 px-0 py-1 border-0 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/4f46e5/ffffff?text=JC', '', 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url'))),
                    // The member's first name names the button.
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My topics'), '{{page:my_topics}}'),
                    $login_page_item(lang('My guides'), '{{page:my_docs}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Moderation'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // The account page: who is signed in, the account menu, the member's
    // topics and guides and, for whoever the moderation page's private
    // folder lets in, the way to the moderation dashboard.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'pf-card pf-tint mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-bold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card('bi-shield-check', lang('Moderation'), lang('The topics, guides and reports waiting for the moderators.'), array(
                            $link(lang('Open the dashboard'), '#', 'btn btn-primary', array(), array('_bindings' => array('href' => '__staff_url'))),
                        ), $show_when('is_staff')),
                        $account_card('bi-chat-square-text', lang('Your topics'), lang('Ask a question or start a discussion, and follow the topics you take part in.'), array(
                            $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary'),
                            $link(lang('My topics'), '{{page:my_topics}}', 'btn btn-secondary'),
                        )),
                        $account_card('bi-journal-text', lang('Your guides'), lang('Write a guide for the docs and follow its review.'), array(
                            $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-primary'),
                            $link(lang('My guides'), '{{page:my_docs}}', 'btn btn-secondary'),
                        )),
                        $account_card('bi-inbox', lang('Messages'), lang('Your conversations with the moderators, and the e-mails you get from the community.'), array(
                            $link(lang('Inbox'), '{{page:inbox}}', 'btn btn-primary'),
                            $link(lang('Email Preferences'), '#', 'btn btn-secondary', array(), array('_bindings' => array('href' => '__email_preferences_url'))),
                        )),
                        $heading('h2', lang('My Orders'), 'h5 fw-bold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),
    'account_invoices' => array(
        'page'   => 'my_account',
        'slug'   => lang('invoices'),
        'config' => array('regionType' => 'account_invoices', 'max_results' => 50),
        'tree'   => 'starter',
    ),
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'            => 'registration',
            'show_remember_me'      => true,
            'login_page_id'         => '{{tab:login}}',
            'redirect_page_id'      => '{{tab:my_account}}',
            'welcome_email_page_id' => '{{tab:email_account_welcome}}',
            'welcome_email_subject' => lang('Welcome! Your account is ready'),
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array(
            'regionType'          => 'forgot_password',
            'login_page_id'       => '{{tab:login}}',
            'reset_email_page_id' => '{{tab:email_password_reset}}',
            'reset_email_subject' => lang('Reset your password'),
        ),
        'tree'   => 'starter',
    ),
    // The account e-mails' widgets, drawn for the member the e-mail is
    // sent to. The reset e-mail's button carries the set password link: a
    // page without it is not sent (the built-in e-mail goes instead).
    'account_email_welcome' => array(
        'page'   => 'email_account_welcome',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome!')),
            $mail_account_greeting(),
            $mail_para(lang('Your community account is ready. Ask questions, reply to topics, contribute guides to the docs and follow the topics you care about.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_small(lang('Glad to have you with us.')),
        )),
    ),
    'account_email_reset' => array(
        'page'   => 'email_password_reset',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Reset your password')),
            $mail_account_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_small(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.')),
        )),
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),
);

// ── The Comments widgets ────────────────────────────────────────────────
// The replies of a topic, the feedback of a guide, the lists of recent
// replies and the comment e-mails are Comments widgets. Their layouts ship
// with the template, in its own look: a widget left to the editor's starter
// would keep its page out of the installer's preview. Every control is a
// binding the renderer wires to its handler: the form posts the comment, the
// watch form follows the thread, the moderators' form opens or closes it,
// Reply and Share answer or point at one post. A flag shows a part only
// while it holds for the visitor.

// A Messages node: the notices and errors of the form it sits in.
$messages = function ($class = '') {
    return array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => $class), 'children' => array());
};

// A thread: the posts in the layout's order, each a .pf-reply card with a
// rule before a reply, its byline (the best answer marked, the account only
// for the moderators), the post it answers quoted where the thread stops
// going deeper, its words and its Reply, Share and Edit links; the page
// links; the form (its notices first, the post being answered, the stars of
// a guide, the words, the name for whoever types one, the publish options of
// the moderators, the box to follow the thread, the CAPTCHA); the box to
// follow or stop following; the moderators' Stop and Allow buttons.
// $w: the words of the thread (empty, heading, label, sign_in, submit,
// watch_box, follow, unfollow, close, reopen, featured) and rating, whether
// the form asks for stars.
$reply_thread = function ($w) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $loop, $widget_root, $messages, $people) {
    $body = array();
    if ($w['rating']) {
        $body[] = $el('div', 'text-warning small mb-1', lang('Stars'), array(), array('_bindings' => array('text' => '__comment_stars_html', 'eo_visible_if' => 'comment_has_rating')));
    }
    $body[] = $el('p', 'pf-meta d-flex flex-wrap align-items-center gap-2 mb-2', lang('Name Line'), array(
        $span($people[1], 'fw-semibold text-body-emphasis', lang('Name'), $bind('__comment_name')),
        $el('span', 'pf-badge pf-badge-pin', lang('Featured'), array(
            $icon('bi-patch-check-fill'),
            $span($w['featured'], '', lang('Text')),
        ), $show_when('comment_is_featured')),
        $span(lang('Moderator'), 'pf-badge', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
        $span(lang('2 hours ago'), 'pg-hide-if-empty', lang('Time'), $bind('__comment_date')),
        // The account behind the post, for the moderators only.
        $el('span', 'pg-hide-if-empty', lang('Account'), array(
            $span(lang('Account:'), 'me-1', lang('Label')),
            $span('priya.raman', 'pg-field-value', lang('User Name'), $bind('__comment_staff_name')),
        ), $show_when('is_staff')),
    ));
    $body[] = $el('p', 'small fw-semibold text-danger-emphasis mb-2 pg-hide-if-empty', lang('Comment Status'), array(),
        array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending')));
    $body[] = $el('div', 'pf-post', lang('Message'), array(
        $el('blockquote', '', lang('Quote'), array(
            $el('p', 'small mb-1', lang('In reply to'), array(
                $span(lang('In reply to'), 'me-1', lang('Label')),
                $link($people[0], '#', 'fw-semibold link-body-emphasis', array(), array('_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
            )),
            $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
        ), $show_when('comment_has_quote')),
        $el('div', '', lang('Comment text'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
    ));
    // The file a post carries, while the page takes attachments.
    $body[] = $el('p', 'small mb-2', lang('Attachment'), array(
        $icon('bi-paperclip', 'me-1'),
        $link('screenshot.png', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
        $span('84 KB', 'pf-meta ms-1', lang('Value'), $bind('__comment_file_size')),
    ), $show_when('comment_has_file'));
    $body[] = $el('div', 'pf-reply-actions', lang('Comment Actions'), array(
        $link(lang('Reply'), '#', 'link-body-emphasis', array(), array('_bindings' => array('action' => 'comment_reply'))),
        $link(lang('Share'), '#', '', array(), array('_bindings' => array('action' => 'comment_share'))),
        // Bound to the post's edit screen, so the list adds no pencil of
        // its own.
        $link(lang('Edit'), '#', '', array(), array('_bindings' => array('href' => '__comment_edit_url', 'eo_visible_if' => 'comment_can_edit'))),
    ));

    $label = function ($for, $text, $class = 'form-label') use ($el, $attr) {
        return $el('label', $class, lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $for))));
    };
    $control = function ($tag, $class, $id, $value, $attrs = array()) use ($el) {
        return $el($tag, $class, lang('Field'), array(), array('id' => $id, '_attrs' => $attrs, '_bindings' => array('value' => $value)));
    };
    $fields = array(
        $messages(),
        // The post the reply answers, while it answers one. The page keeps
        // the box with the hidden attribute for Reply to show it, so it
        // takes no display utility: d-flex, !important and later in
        // Bootstrap than its [hidden] rule, would keep it on screen, empty.
        $el('div', 'alert alert-secondary py-2 small', lang('Replying To'), array(
            $span(lang('Replying to'), 'me-1', lang('Text')),
            $el('strong', '', lang('Name'), array(), array('text' => $people[1], '_bindings' => array('text' => '__reply_to_name'))),
            $link(lang('Cancel'), '#', 'ms-3 fw-semibold', array(), array('_bindings' => array('action' => 'comment_reply_cancel'))),
        ), $show_when('is_replying')),
    );
    if ($w['rating']) {
        $fields[] = $el('div', 'mb-3', lang('Rating'), array(
            $el('div', 'form-label', lang('Label'), array(), array('text' => lang('Rating'))),
            $el('div', '', lang('Stars'), array(), array('_bindings' => array('section' => 'comment_rating_input'))),
        ), $show_when('rating_on'));
    }
    $fields[] = $el('div', 'mb-3', lang('Message'), array(
        $label('comment_message', $w['label']),
        $control('textarea', 'form-control', 'comment_message', 'comment_message', array($attr('rows', '6'))),
    ));
    // The name: typed by whoever may type one (the moderators, filled in
    // from their profile), else the profile's, as the page shows it.
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name', lang('Your Name')),
        $control('input', 'form-control', 'comment_name', 'comment_name', array($attr('type', 'text'))),
    ), $show_when('name_is_typed'));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name_type', lang('Your Name')),
        $control('select', 'form-select', 'comment_name_type', 'comment_name_type'),
    ), $show_when('name_is_chosen'));
    $fields[] = $el('div', 'mb-3 small', lang('Posting as'), array(
        $span(lang('Posting as'), 'me-1', lang('Label')),
        $el('strong', '', lang('Name'), array(), array('text' => $people[0], '_bindings' => array('text' => '__comment_author_name'))),
    ), $show_when('name_is_fixed'));
    $fields[] = $el('div', 'mb-3', lang('Attach a File'), array(
        $label('comment_file', lang('Attach a File')),
        $control('input', 'form-control', 'comment_file', 'comment_file', array($attr('type', 'file'))),
    ), $show_when('files_on'));
    $fields[] = $el('div', 'mb-3 small', lang('Publish'), array(), array('_bindings' => array('section' => 'comment_publish')));
    $fields[] = $el('div', 'form-check mb-3', lang('Watch'), array(
        $control('input', 'form-check-input', 'comment_watcher', 'comment_watcher', array($attr('type', 'checkbox'))),
        $label('comment_watcher', $w['watch_box'], 'form-check-label'),
    ), $show_when('watching_on'));
    $fields[] = $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'comment_captcha')));
    $fields[] = $el('div', '', lang('Send Button'), array(
        $el('button', 'btn btn-primary px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-secondary', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
    };

    return $widget_root(array(
        $el('div', 'pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('article', 'pf-reply', lang('Comment'), array(
                $el('div', 'pf-reply-rule', lang('Reply Indent'), array(), $show_when('comment_is_reply')),
                $el('div', 'pf-reply-body', lang('Comment Card'), $body),
            )))),
            $el('div', 'mt-3', lang('Pagination'), array(), array('_bindings' => array('section' => 'comment_pagination'))),
        )),
        $para($w['empty'], 'pf-meta mb-0', $show_when('no_comments')),
        $el('div', 'mt-4', lang('Add a comment'), array(
            $heading('h3', $w['heading'], 'h5 fw-bold mb-2', $show_when('comments_open')),
            $el('p', 'pf-meta mb-3 pg-hide-if-empty', lang('Text'), array(), array('_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
            $el('div', 'alert alert-secondary', lang('Closed'), array(), array('_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2', lang('Sign in'), array(
                $span($w['sign_in'], '', lang('Text')),
                $link(lang('Sign in'), '#', 'fw-semibold', array(), array('rel' => 'nofollow', '_bindings' => array('href' => '__login_url'))),
            ), $show_when('must_sign_in')),
            $el('form', 'pf-card', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
        )),
        // Following the thread: how many follow it, and the member's own
        // button; the moderators' list of who follows.
        $el('div', 'pf-card pf-tint mt-4 pg-hide-if-empty', lang('Watch'), array(
            $el('p', 'pf-meta mb-2 pg-hide-if-empty', lang('Text'), array(), $bind('__watcher_count_text')),
            $el('form', 'd-flex flex-wrap align-items-center gap-2', lang('Watch Form'), array(
                $messages('w-100'),
                $small_button($w['follow'], 'comment_watch'),
                $small_button($w['unfollow'], 'comment_unwatch'),
            ), array('_bindings' => array('action' => 'comment_watch_form'))),
            $el('div', '', lang('Watchers'), array(), array('_bindings' => array('section' => 'comment_watchers'))),
        ), $show_when('watching_on')),
        $el('div', 'd-flex justify-content-end mt-3', lang('Staff Controls'), array(
            $el('form', '', lang('Open or Close'), array(
                $small_button($w['close'], 'comment_close'),
                $small_button($w['reopen'], 'comment_reopen'),
            ), array('_bindings' => array('action' => 'comment_allow_new_form'))),
        ), $show_when('is_staff')),
    ));
};
// The words of a topic's replies and of a guide's feedback.
$topic_words = array(
    'empty'     => lang('No replies yet. Be the first to answer.'),
    'heading'   => lang('Write a reply'),
    'label'     => lang('Your reply'),
    'sign_in'   => lang('Sign in to reply.'),
    'submit'    => lang('Post reply'),
    'watch_box' => lang('Follow this topic: e-mail me its new replies.'),
    'follow'    => lang('Follow this topic'),
    'unfollow'  => lang('Stop following this topic'),
    'close'     => lang('Stop new replies'),
    'reopen'    => lang('Allow new replies'),
    'featured'  => lang('Best answer'),
    'rating'    => false,
);
$doc_words = array(
    'empty'     => lang('No feedback yet. Tell the next reader whether this guide helped you.'),
    'heading'   => lang('Rate this guide'),
    'label'     => lang('Your feedback'),
    'sign_in'   => lang('Sign in to rate this guide.'),
    'submit'    => lang('Send feedback'),
    'watch_box' => lang('Follow this guide: e-mail me its new feedback.'),
    'follow'    => lang('Follow this guide'),
    'unfollow'  => lang('Stop following this guide'),
    'close'     => lang('Stop new feedback'),
    'reopen'    => lang('Allow new feedback'),
    'featured'  => lang('Featured'),
    'rating'    => true,
);

// A list of recent replies, in a side card's short rows: whether a reply
// awaits approval ($w status), the topic or guide it was written on (its
// link opens the page at the reply; over the whole row unless the row has
// its own Publish or edit link, $w edit), who wrote it and when, its stars
// and the start of its words. A list draws no form, no quote and nothing
// else of a thread. $w: empty, the sentence while there is none, and title,
// the sample the editor shows.
$recent_list = function ($w) use ($el, $para, $link, $span, $bind, $dot, $show_when, $loop, $widget_root, $people) {
    $row = array();
    if ($w['status']) {
        $row[] = $el('p', 'small fw-semibold text-danger-emphasis mb-1 pg-hide-if-empty', lang('Comment Status'), array(),
            array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending')));
    }
    $row[] = $el('h3', 'pf-compact-title', lang('Thread Title'), array(
        $link($w['title'], '#', $w['edit'] ? '' : 'stretched-link', array(), array('_bindings' => array('href' => '__comment_url', 'text' => '__comment_thread_title'))),
    ));
    $row[] = $el('p', 'pf-meta small mb-1', lang('Name Line'), array(
        $span($people[2], 'fw-semibold', lang('Name'), $bind('__comment_name')),
        $el('span', 'pg-hide-if-empty', lang('Time'), array(
            $dot(),
            $span(lang('2 hours ago'), 'pg-field-value', lang('Value'), $bind('__comment_date')),
        )),
    ));
    $row[] = $el('div', 'text-warning small mb-1', lang('Stars'), array(), array('_bindings' => array('text' => '__comment_stars_html', 'eo_visible_if' => 'comment_has_rating')));
    $row[] = $el('p', 'small text-body-secondary mb-0 pf-clamp-3', lang('Excerpt'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_excerpt')));
    if ($w['edit']) {
        $row[] = $el('p', 'small mt-2 mb-0', lang('Link'), array(
            $link(lang('Publish or edit'), '#', 'fw-semibold', array(), array('_bindings' => array('href' => '__comment_edit_url'))),
        ), $show_when('comment_can_edit'));
    }
    return $widget_root(array(
        $el('ul', 'list-unstyled mb-0 pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('li', 'pf-compact', lang('Comment'), $row))),
        )),
        $para($w['empty'], 'pf-meta mb-0', $show_when('no_comments')),
    ));
};
// The pages the lists read: the topics' replies and the guides' feedback.
$reply_sources = array('{{tab:topic}}', '{{tab:doc}}');

// The comment of a reply e-mail (a reply, feedback or a message under a
// guide), drawn for the comment the e-mail is about: who wrote it and when,
// its stars, the post it answers, its words and its file in the e-mail's
// box, then its View or Reply button. The button is bound to the comment's
// own address, with no flag over it: an e-mail whose card has no such link
// keeps the fixed comment the System region carries. No form, no control,
// no capitals by CSS; every colour written for mail programs that drop the
// stylesheet, with its fallback inside var().
$mail_comment_tree = $widget_root(array(
    $el('div', '', lang('Comment List'), array(
        $loop(array(
            $mail_box(array(
                $el('p', 'mb-1', lang('Name Line'), array(
                    $span($people[1], 'fw-semibold', lang('Name'), array_merge($bind('__comment_name'), $mail_style('font-weight:600;color:#1d2131'))),
                    $span('·', '', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true'), $attr('style', 'color:#5b6070;margin:0 6px')))),
                    $span(lang('Date'), 'text-body-secondary', lang('Date'), array_merge($bind('__comment_date'), $mail_style('color:#5b6070'))),
                ), $mail_style('margin:0 0 8px')),
                $el('div', '', lang('Stars'), array(), array_merge(
                    array('_bindings' => array('text' => '__comment_stars_html', 'eo_visible_if' => 'comment_has_rating')),
                    $mail_style('color:#b45309;margin:0 0 8px'))),
                $el('blockquote', '', lang('Quote'), array(
                    $el('p', 'small mb-1', lang('In reply to'), array(
                        $span(lang('In reply to'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
                        $span($people[0], 'fw-semibold', lang('Name'), $bind('__comment_parent_name')),
                    ), $mail_style('font-size:13px;margin:0 0 4px')),
                    $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array_merge(
                        array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote')), $mail_style('margin:0'))),
                ), array_merge(array('_bindings' => array('eo_visible_if' => 'comment_has_quote')),
                    $mail_style('margin:0 0 12px;padding:0 0 0 12px;border-left:3px solid #dcdfe8;color:#5b6070'))),
                $el('div', '', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
                $el('p', 'small mt-2 mb-0', lang('Attachment'), array(
                    $link('screenshot.png', '#', '', array($attr('style', 'color:#4f46e5;color:var(--bs-link-color,#4f46e5)')),
                        array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                ), array_merge(array('_bindings' => array('eo_visible_if' => 'comment_has_file')), $mail_style('font-size:13px;margin:8px 0 0'))),
            ), lang('Comment')),
            $mail_button(lang('View or Reply'), '#', array('_bindings' => array('href' => '__comment_url'))),
        )),
    )),
));

// ── The lists ───────────────────────────────────────────────────────────
// Every public list carries its form's live rule before its own filters.
// Lists on one page share the page_number and query parameters, so only
// one list of a page pages through its records (max_results 0); every
// other list shows a fixed number. A list's page links come after its rows,
// inside the rows' parent.
$topic_list = function ($page_key, $ipp, $max, $more, $empty, $tree, $extra = array()) use ($topic_pub, $with_topic_live, $widget_root) {
    $config = array_merge($topic_pub, array('items_per_page' => $ipp, 'max_results' => $max, 'filters' => $with_topic_live($more)), $extra);
    if ($empty !== '') $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};
$doc_list = function ($page_key, $ipp, $max, $more, $empty, $tree, $extra = array()) use ($doc_pub, $with_doc_live, $widget_root) {
    $config = array_merge($doc_pub, array('items_per_page' => $ipp, 'max_results' => $max, 'filters' => $with_doc_live($more)), $extra);
    if ($empty !== '') $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};
// A count of the records a list finds and nothing else: its loop area is
// empty, so it prints its surround with ^^__result_count^^ and reads no
// row ([[form-list-result-count]]). The loop area sits in a box shown only
// while the list found records, so a count of 0 prints no empty sentence.
$count_list = function ($page_key, $base, $filters, $surround, $extra = array()) use ($el, $loop, $show_when, $widget_root) {
    $config = array_merge($base, array('items_per_page' => 10, 'max_results' => 0, 'filters' => $filters), $extra);
    $tree = array_merge($surround, array($el('div', '', lang('Rows'), array($loop(array())), $show_when('has_records'))));
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};
// A number of the forum's numbers strip, and "Topics: N" in a line.
$stat_surround = function ($sample, $label) use ($el, $para, $bind) {
    return array($el('div', 'pf-card pf-stat h-100', lang('Number'), array(
        $para($sample, 'pf-stat-num', $bind('__result_count')),
        $para($label, 'pf-stat-label'),
    )));
};
$count_line = function ($sample, $class = 'pf-meta mb-0', $label = '') use ($el, $span, $bind) {
    return array($el('p', $class, lang('Count'), array(
        $span($label !== '' ? $label : lang('Topics:'), 'me-1', lang('Label')),
        $span($sample, 'fw-semibold', lang('Number of Results'), $bind('__result_count')),
    )));
};

// The home page's numbers: every live topic, the solved ones and the
// published guides. The first is the page's first list: its feed.
$widgets['home_count_topics'] = $count_list('home', $topic_pub, $topic_live, $stat_surround('1,284', lang('Topics')));
$widgets['home_count_solved'] = $count_list('home', $topic_pub, $with_topic_live(array($is_solved)), $stat_surround('612', lang('Solved')));
$widgets['home_count_guides'] = $count_list('home', $doc_pub, $doc_live, $stat_surround('48', lang('Guides')));
// Each board's number of live topics, on its tile (home, the board page).
$board_samples = array('getting_started' => '86', 'help' => '512', 'ideas' => '204', 'show_and_tell' => '97', 'tips' => '143', 'general' => '242');
foreach ($boards as $key => $b) {
    $widgets['board_count_' . $key] = $count_list('', $topic_pub, $with_topic_live(array($in_board($b[0]))),
        $count_line($board_samples[$key], 'pf-meta small mb-0'));
}

// The announcements: the three newest live topics marked as one, the
// newest first, one under the other.
$widgets['home_announcements'] = $topic_list('home', 3, 3, array($is_announcement),
    lang('Topics the moderators mark as an announcement appear here.'),
    array($el('div', 'd-grid gap-3', lang('Announcements'), array($loop(array($announce_row($topic_sample(0))))))),
    array('order_by_field' => 'submitted_date_and_time'));
// The latest activity: the ten live topics with the newest topic or reply.
$widgets['home_latest'] = $topic_list('home', 10, 10, array(),
    lang('No topics yet. Start the first one.'),
    array($topic_rows($topic_sample(1))));
// Waiting for an answer: the five newest live topics without a reply.
$widgets['home_unanswered'] = $topic_list('home', 5, 5, array($unanswered),
    lang('Every topic has a reply. Thank you, everyone.'),
    array($el('ul', 'list-unstyled mb-0', lang('List'), array($loop(array($compact_row($topic_sample(4), 'sent')))))),
    array('order_by_field' => 'submitted_date_and_time'));
// Popular this week: the five live topics opened most in the last seven
// days (the views the topic page counts); topics nobody opened are left
// out.
$widgets['home_popular'] = $topic_list('home', 5, 5, array($viewed),
    lang('The topics viewed most this week appear here.'),
    array($el('ul', 'list-unstyled mb-0', lang('List'), array($loop(array($compact_row($topic_sample(3), 'views')))))),
    array(
        'order_by_field'       => 'recent_views',
        'order_by_direction'   => 'DESC',
        'order_recent_days'    => 7,
        'order_by_2_field'     => 'newest_activity_date_and_time',
        'order_by_2_direction' => 'DESC',
    ));
// The featured guides: the three newest published guides marked featured.
$widgets['home_guides'] = $doc_list('home', 3, 3, array($is_featured),
    lang('Guides the moderators feature appear here.'),
    array($el('div', 'row row-cols-1 row-cols-md-3 g-3 g-lg-4', lang('Featured guides'), array(
        $loop(array($el('div', 'col', lang('Column'), array($guide_card($doc_sample(1)))))),
    ))));
// The latest replies: the five newest published replies and feedback of
// every topic and guide a visitor may open (the topic and guide pages' own
// rules keep a hidden topic's and an unpublished guide's out), each opening
// its page at the post.
$widgets['home_latest_replies'] = array(
    'page'   => 'home',
    'slug'   => lang('recent-comments'),
    'config' => array(
        'regionType'      => 'comments',
        'mode'            => 'recent',
        'recent_show'     => 'published',
        'recent_page_ids' => $reply_sources,
        'recent_limit'    => '5',
    ),
    'tree'   => $recent_list(array(
        'empty'  => lang('No replies yet.'),
        'title'  => $topic_heads[1][5],
        'status' => false,
        'edit'   => false,
    )),
);

// The latest replies of a board: the five newest published replies of the
// topics whose board the address names (?board=, the list's record filter,
// after the topic page's own rules), each opening the topic at the reply.
// Without a name the filter is left out: every board's.
$widgets['board_latest_replies'] = array(
    'page'   => 'board',
    'slug'   => lang('recent-comments'),
    'config' => array(
        'regionType'      => 'comments',
        'mode'            => 'recent',
        'recent_show'     => 'published',
        'recent_page_ids' => array('{{tab:topic}}'),
        'recent_filters'  => array($board_param),
        'recent_limit'    => '5',
    ),
    'tree'   => $recent_list(array(
        'empty'  => lang('No replies yet.'),
        'title'  => $topic_heads[1][5],
        'status' => false,
        'edit'   => false,
    )),
);

// The forum: the count of every live topic (the page's feed), the pinned
// topics, then every other live topic with a search over the title, the
// text, the tags and the board, twenty to a page.
$widgets['forum_count'] = $count_list('forum', $topic_pub, $topic_live, $count_line('1,284'));
$widgets['forum_pinned'] = $topic_list('forum', 10, 10, array($is_pinned), '',
    array($el('section', 'mb-5', lang('Pinned'), array(
        $el('h2', 'pf-sec-title d-flex align-items-center gap-2 mb-3', lang('Title'), array(
            $icon('bi-pin-angle-fill', 'text-primary'),
            $span(lang('Pinned'), '', lang('Text')),
        ), array('id' => 'pf-pinned-title')),
        $topic_rows($topic_sample(0), true),
    ), array_merge(array('_attrs' => array($attr('aria-labelledby', 'pf-pinned-title'))), $show_when('has_records')))));
$widgets['forum_topics'] = $topic_list('forum', 20, 0, array($not_pinned),
    lang('No topics found. Try other words, or start a topic.'),
    array($topic_rows($topic_sample(1))),
    array(
        'search_enabled' => true,
        'search_fields'  => array('title', 'body', 'tags', 'board'),
        'search_width'   => 'full',
        'search_label'   => lang('Search the topics…'),
    ));

// A board: its name and number of live topics, from the address. The name
// prints only while a live topic carries it exactly; a board without
// topics, or a name no board has, says so instead. Without a name the
// block stays off the page, which keeps its title and every board.
$widgets['board_head'] = $count_list('board', $topic_pub, $with_topic_live(array($board_param)), array(
    $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4 pb-4 border-bottom', lang('Board Header'), array(
        $el('div', 'pf-min-0', lang('Words'), array(
            $para(lang('Board'), 'pf-kicker mb-1'),
            $heading('h2', $boards['help'][0], 'pf-display mb-2', $bind('__filter_value')),
            $el('p', 'pf-meta mb-0', lang('Topic Count'), array(
                $span(lang('Topics:'), 'me-1', lang('Label')),
                $span('512', 'fw-semibold', lang('Number of Results'), $bind('__result_count')),
            )),
        )),
        $link(lang('Start a topic'), '{{page:new_topic}}', 'btn btn-primary'),
    ), $show_when('has_filter_value')),
    $el('div', 'alert alert-secondary d-flex gap-2 align-items-start', lang('Empty Board'), array(
        $icon('bi-info-circle', 'mt-1'),
        $el('p', 'mb-0', lang('Text'), array(
            $span(lang('There are no topics in this board yet.'), 'me-1', lang('Text')),
            $link(lang('Start the first one'), '{{page:new_topic}}', 'alert-link'),
        )),
    ), $show_when('no_records')),
), array('filter_title' => lang('Board: {value}')));
// The board's pinned topics, under its name.
$widgets['board_pinned'] = $topic_list('board', 10, 10, array($board_param, $is_pinned), '',
    array($el('div', 'mb-4', lang('Pinned'), array(
        $el('p', 'pf-label mb-2', lang('Title'), array(
            $icon('bi-pin-angle-fill'),
            $span(lang('Pinned'), '', lang('Text')),
        )),
        $topic_rows($topic_sample(17), true),
    ), $show_when('has_filter_value'))));
// The board's other topics, twenty to a page.
$widgets['board_topics'] = $topic_list('board', 20, 0, array($board_param, $not_pinned), '',
    array($el('div', '', lang('Topics'), array(
        $topic_rows($topic_sample(1)),
    ), $show_when('has_filter_value'))));

// The topics whose tags contain ?tag=, twenty to a page. "contains" never
// prints the word, so the heading says what the list is, and a line says
// so while no word is given and the list holds every topic.
$widgets['tag_topics'] = $topic_list('tag', 20, 0, array($tag_param),
    lang('No topic carries this tag yet. Pick another tag, or search the forum.'),
    array($el('section', '', lang('Topics'), array(
        $sec_head(lang('Matching topics'), '', '', 'pf-tag-topics'),
        $para(lang('No tag is chosen, so every topic is listed. Pick a tag above to narrow the list.'), 'pf-meta mb-3', $show_when('no_filter_value')),
        $topic_rows($topic_sample(15)),
    ), array('_attrs' => array($attr('aria-labelledby', 'pf-tag-topics'))))));

// A member's live topics: the name a topic shows must equal ?member=
// exactly. While it does, the member's name, the number of their topics and
// the topics, twenty to a page, under the title "Topics started by <name>".
// A name no live topic carries says so; without a name, how to find one.
$widgets['member_topics'] = $topic_list('member', 20, 0, array($member_param), '',
    array(
        $el('div', '', lang('Member\'s Topics'), array(
            $el('div', 'mb-4 pb-4 border-bottom', lang('Member Header'), array(
                $para(lang('Member'), 'pf-kicker mb-1'),
                $heading('h2', $people[1], 'pf-display mb-2', $bind('__filter_value')),
                $count_line('12', 'pf-meta mb-0', lang('Topics started:'))[0],
            )),
            $topic_rows($topic_sample(5)),
        ), $show_when('has_filter_value')),
        $el('div', 'alert alert-secondary d-flex gap-2 align-items-start', lang('No Topics Found'), array(
            $icon('bi-info-circle', 'mt-1'),
            $el('p', 'mb-0', lang('Text'), array(
                $span(lang('No topics were found for this name.'), 'me-1', lang('Text')),
                $link(lang('Search the forum'), '{{page:search}}', 'alert-link'),
            )),
        ), $show_when('no_records')),
        $el('div', 'alert alert-secondary d-flex gap-2 align-items-start', lang('No Member Chosen'), array(
            $icon('bi-person', 'mt-1'),
            $para(lang('Open a member\'s name on any topic to see every topic they started.'), 'mb-0'),
        ), $show_when('no_filter_value')),
    ),
    array('filter_title' => lang('Topics started by {value}')));

// The live topics without a reply, the oldest first, twenty to a page, under
// their number.
$widgets['unanswered_topics'] = $topic_list('unanswered', 20, 0, array($unanswered),
    lang('Every topic has a reply. Thank you, everyone.'),
    array(
        $el('p', 'pf-meta mb-3', lang('Count'), array(
            $span(lang('Waiting for a reply:'), 'me-1', lang('Label')),
            $span('12', 'fw-semibold', lang('Number of Results'), $bind('__result_count')),
        ), $show_when('has_records')),
        $topic_rows($topic_sample(4, array('replier' => '', 'replies' => '0', 'views' => '6')), false, 'h2'),
    ),
    array(
        'order_by_field'     => 'submitted_date_and_time',
        'order_by_direction' => 'ASC',
        'order_by_2_field'   => '',
    ));

// The docs page's number of published guides: its first list, so its feed.
$widgets['docs_count'] = $count_list('docs', $doc_pub, $doc_live, $count_line('48', 'pf-meta mb-0', lang('Guides:')));
// Each section's number of published guides, on its tile (docs, a section).
$section_samples = array('getting_started' => '6', 'how_to' => '14', 'reference' => '9', 'troubleshooting' => '8', 'integrations' => '5', 'release_notes' => '6');
foreach ($doc_sections as $key => $s) {
    $widgets['doc_count_' . $key] = $count_list('', $doc_pub, $with_doc_live(array($f('section', 'is equal to', $s[0]))),
        $count_line($section_samples[$key], 'pf-meta small mb-0', lang('Guides:')));
}
// The featured guides of the docs page: the three newest.
$widgets['docs_featured'] = $doc_list('docs', 3, 3, array($is_featured),
    lang('Guides the moderators feature appear here.'),
    array($el('div', 'row row-cols-1 row-cols-md-3 g-3 g-lg-4', lang('Featured guides'), array(
        $loop(array($el('div', 'col', lang('Column'), array($guide_card($doc_sample(2)))))),
    ))));
// The six newest published guides.
$widgets['docs_latest'] = $doc_list('docs', 6, 6, array(),
    lang('No guides yet. Contribute the first one.'),
    array($el('div', 'row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 g-lg-4', lang('Latest guides'), array(
        $loop(array($el('div', 'col', lang('Column'), array($guide_card($doc_sample(0)))))),
    ))));
// Every published guide from A to Z, twelve to a page, with a search over
// the title, the summary, the text, the tags and the section.
$widgets['docs_all'] = $doc_list('docs', 12, 0, array(),
    lang('No guides found. Try other words, or browse the sections.'),
    array($el('div', 'pf-topics', lang('List'), array($loop(array($doc_row($doc_sample(8))))))),
    array(
        'order_by_field'     => 'title',
        'order_by_direction' => 'ASC',
        'search_enabled'     => true,
        'search_fields'      => array('title', 'summary', 'body', 'tags', 'section'),
        'search_width'       => 'full',
        'search_label'       => lang('Search the guides…'),
    ));

// A section's published guides in reading order: their order in the
// section, then their title (a guide without an order comes first),
// twenty to a page. While a published guide carries ?section= exactly, the
// section's name and number of guides head the list, under the title
// "Docs: <name>". A name no published guide carries says so.
$widgets['section_guides'] = $doc_list('docs_section', 20, 0, array($section_param), '',
    array(
        $el('div', '', lang('Section\'s Guides'), array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4 pb-4 border-bottom', lang('Section Header'), array(
                $el('div', 'pf-min-0', lang('Words'), array(
                    $para(lang('Section'), 'pf-kicker mb-1'),
                    $heading('h2', $doc_sections['how_to'][0], 'pf-display mb-2', $bind('__filter_value')),
                    $count_line('14', 'pf-meta mb-0', lang('Guides:'))[0],
                )),
                $link(lang('Contribute a guide'), '{{page:doc_new}}', 'btn btn-primary'),
            )),
            $el('div', 'pf-topics', lang('List'), array($loop(array($doc_row($doc_sample(2), false))))),
        ), $show_when('has_filter_value')),
        $el('div', 'alert alert-secondary d-flex gap-2 align-items-start', lang('Empty Section'), array(
            $icon('bi-info-circle', 'mt-1'),
            $el('p', 'mb-0', lang('Text'), array(
                $span(lang('There are no guides in this section yet.'), 'me-1', lang('Text')),
                $link(lang('Contribute the first one'), '{{page:doc_new}}', 'alert-link'),
            )),
        ), $show_when('no_records')),
    ),
    array(
        'filter_title'         => lang('Docs: {value}'),
        'order_by_field'       => 'doc_order',
        'order_by_direction'   => 'ASC',
        'order_by_2_field'     => 'title',
        'order_by_2_direction' => 'ASC',
    ));

// The topic: shown while it is not Hidden (anything else is "not found" and
// not indexed). Its title is the page's title; its text gives the
// description of the head and the feed. Above the replies, which the page
// puts right under it, their heading. The heading leaves with a missing
// topic.
$topic = $topic_sample(1);
$topic_page_link = function ($text, $class) use ($link, $page_link) {
    return $link($text, '#', $class, array(), $page_link('board', array('board' => 'board')));
};
$widgets['topic_view'] = array(
    'page'   => 'topic',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:new_topic}}',
        'access_control'      => 'public',
        'filters'             => $topic_live,
        'title_field'         => 'title',
        'not_found_message'   => lang('This topic is not available. It may have been removed or hidden by the moderators.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-chat-square-dots', 'display-5 text-body-secondary'),
            $heading('h1', lang('Topic not available'), 'h3 fw-bold mt-3'),
            $para(lang('This topic is not available. It may have been removed or hidden by the moderators.'), 'text-body-secondary mb-4', $bind('not_found')),
            $buttons(array(
                $link(lang('Back to the forum'), '{{page:forum}}', 'btn btn-primary'),
                $link(lang('Search'), '{{page:search}}', 'btn btn-secondary'),
            ), 'd-flex flex-wrap justify-content-center gap-2'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Topic'), array_merge(
            $sent_notices(lang('Thank you. Your topic is live.')),
            array(
                $el('nav', '', lang('Where You Are'), array(
                    $el('ol', 'breadcrumb small mb-3', lang('Links'), array(
                        $el('li', 'breadcrumb-item', lang('List Item'), array($link(lang('Forum'), '{{page:forum}}', 'link-body-emphasis'))),
                        $el('li', 'breadcrumb-item', lang('List Item'), array(
                            $link($topic['board'], '#', 'link-body-emphasis', array(),
                                array_merge($page_link('board', array('board' => 'board')), array('_bindings' => array('text' => 'board', 'href' => '__page_link')))),
                        )),
                    )),
                ), array('_attrs' => array($attr('aria-label', lang('Where you are'))))),
                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-2', lang('Badges and Moderation'), array(
                    $topic_badges($topic, false, 'mb-0'),
                    $edit_button(lang('Moderate')),
                )),
                $heading('h1', $topic['title'], 'pf-page-title mb-2', $bind('title')),
                $el('p', 'pf-meta mb-4', lang('Byline and Time'), array(
                    $el('span', 'pg-hide-if-empty', lang('Author'), array(
                        $span(lang('Started by'), 'me-1', lang('Label')),
                        $member_link($topic, 'pg-field-value pf-person link-body-emphasis'),
                        $dot(),
                    )),
                    $span(lang('Posted:'), 'me-1', lang('Label')),
                    $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                )),
                // The topic's text is rich text: a div holds what the author
                // wrote, contained by the template's rules.
                $el('div', 'pf-post mb-4', lang('Topic Text'), array(), array_merge(
                    array('text' => lang('The text of the topic appears here.')), $bind('body'))),
                // The attachment leaves with an empty field: its address,
                // never shown, is the value the row is judged by.
                $el('p', 'pg-hide-if-empty mb-3', lang('Attachment'), array(
                    $span('/attachment.png', 'pg-field-value d-none', lang('Value'), $bind('attachment')),
                    $el('a', 'btn btn-sm btn-secondary d-inline-flex align-items-center gap-2', lang('Link'), array(
                        $icon('bi-paperclip'),
                        $span(lang('Open the attachment'), '', lang('Link Text')),
                    ), array('href' => '#', '_bindings' => array('href' => 'attachment'))),
                )),
                // The tags, and the page that finds the topics sharing one.
                $el('p', 'pf-meta pg-hide-if-empty mb-4', lang('Tags'), array(
                    $span(lang('Tags:'), 'fw-semibold me-1', lang('Label')),
                    $span($topic['tags'], 'pg-field-value', lang('Value'), $bind('tags')),
                    $dot(),
                    $link(lang('Browse topics by tag'), '{{page:tag}}', 'd-print-none'),
                )),
                $el('div', 'border-top pt-4 d-print-none', lang('Replies'), array(
                    $heading('h2', lang('Replies'), 'pf-sec-title mb-1'),
                    $para(lang('Be kind, stay on topic, and say so when a reply solves your question.'), 'pf-meta mb-3'),
                )),
            )
        ), $show_when('record_found')),
    )),
);
// Beside the topic and its replies: what is known about it, the way to its
// board, a way to report a post and the guidelines. A second record view
// of the same topic, under the same rule; with no topic, ways to find one.
$widgets['topic_aside'] = array(
    'page'   => 'topic',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:new_topic}}',
        'access_control'      => 'public',
        'filters'             => $topic_live,
        'not_found_message'   => lang('This topic is not available. It may have been removed or hidden by the moderators.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'pf-card', lang('Find Your Way'), array(
            $heading('h2', lang('Looking for something else?'), 'h6 fw-bold mb-3'),
            $buttons(array(
                $link(lang('Browse the boards'), '{{page:board}}', 'btn btn-sm btn-primary'),
                $link(lang('Search'), '{{page:search}}', 'btn btn-sm btn-secondary'),
            )),
        ), $show_when('record_not_found')),
        $el('aside', 'pf-sticky', lang('About This Topic'), array(
            $el('div', 'pf-card mb-4', lang('About This Topic'), array(
                $heading('h2', lang('About this topic'), 'h6 fw-bold mb-2'),
                $el('dl', 'mb-3', lang('Facts'), array(
                    $fact(lang('Board'), array($link($topic['board'], '#', 'link-body-emphasis', array(),
                        array_merge($page_link('board', array('board' => 'board')), array('_bindings' => array('text' => 'board', 'href' => '__page_link')))))),
                    $fact(lang('Status'), array($span($topic['status'], '', lang('Status'), $bind('status')))),
                    $fact(lang('Posted'), array($span(lang('2 days ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')))),
                    $fact(lang('Last activity'), array($span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')))),
                    $fact(lang('Last reply by'), array($span($topic['replier'], 'pg-field-value', lang('Name'), $bind('newest_comment_name'))), true),
                    $fact(lang('Replies'), array($span('3', '', lang('Number of Replies'), $bind('number_of_comments')))),
                    $fact(lang('Views'), array($span('48', '', lang('Number of Views'), $bind('number_of_views')))),
                )),
                $topic_page_link(lang('More topics in this board'), 'btn btn-sm btn-secondary'),
            )),
            // Report a Post opens with this topic's address in its Post
            // field (/report?post=<the topic's address>; the field takes
            // ?post= as its starting value).
            $el('div', 'pf-card mb-4', lang('Report Card'), array(
                $heading('h2', lang('Something wrong?'), 'h6 fw-bold mb-2'),
                $para(lang('If this topic or a reply breaks the guidelines, tell the moderators. The report starts with the topic\'s address; for a reply, paste the link its Share button copies instead.'), 'small text-body-secondary mb-3'),
                $link(lang('Report a post'), '#', 'btn btn-sm btn-secondary', array(), $page_link('report', array('post' => 'form_item_view'))),
            )),
            $el('div', 'pf-card pf-tint', lang('Guidelines'), array(
                $heading('h2', lang('Before you reply'), 'h6 fw-bold mb-2'),
                $para(lang('Answer the question that was asked, be kind, and keep each reply to one point. A moderator marks a topic as solved when its author says a reply solved it.'), 'small mb-3'),
                $link(lang('Read the guidelines'), '{{page:guidelines}}', 'small fw-semibold'),
            )),
        ), array_merge(array('_attrs' => array($attr('aria-label', lang('About this topic')))), $show_when('record_found'))),
    )),
);

// The guide: shown while it is published (anything else is "not found" and
// not indexed). Its title is the page's title; its summary gives the
// description of the head and the feed. The text keeps a reading measure.
// Above the feedback, which the page puts right under it, its heading. The
// heading leaves with a missing guide.
$guide = $doc_sample(2);
$widgets['doc_view'] = array(
    'page'   => 'doc',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:doc_new}}',
        'access_control'      => 'public',
        'filters'             => $doc_live,
        'title_field'         => 'title',
        'not_found_message'   => lang('This guide is not available. It may not be published yet, or it was taken out of the docs.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-journal-x', 'display-5 text-body-secondary'),
            $heading('h1', lang('Guide not available'), 'h3 fw-bold mt-3'),
            $para(lang('This guide is not available. It may not be published yet, or it was taken out of the docs.'), 'text-body-secondary mb-4', $bind('not_found')),
            $buttons(array(
                $link(lang('Back to the docs'), '{{page:docs}}', 'btn btn-primary'),
                $link(lang('Search'), '{{page:search}}', 'btn btn-secondary'),
            ), 'd-flex flex-wrap justify-content-center gap-2'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Guide'), array(
            $el('nav', '', lang('Where You Are'), array(
                $el('ol', 'breadcrumb small mb-3', lang('Links'), array(
                    $el('li', 'breadcrumb-item', lang('List Item'), array($link(lang('Docs'), '{{page:docs}}', 'link-body-emphasis'))),
                    $el('li', 'breadcrumb-item', lang('List Item'), array(
                        $link($guide['section'], '#', 'link-body-emphasis', array(),
                            array_merge($page_link('docs_section', array('section' => 'section')), array('_bindings' => array('text' => 'section', 'href' => '__page_link')))),
                    )),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Where you are'))))),
            $el('header', 'mb-4', lang('Guide Header'), array(
                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-2', lang('Kicker and Moderation'), array(
                    $para($guide['section'], 'pf-kicker mb-0', $bind('section')),
                    $edit_button(lang('Moderate')),
                )),
                $heading('h1', $guide['title'], 'pf-page-title mb-3', $bind('title')),
                $para($guide['summary'], 'lead text-body-secondary pf-measure mb-3', $bind('summary')),
                $el('p', 'pf-meta mb-0', lang('Byline and Time'), array(
                    $span(lang('Written by'), 'me-1', lang('Label')),
                    $span($guide['byline'], 'fw-semibold text-body', lang('Byline'), $bind('byline')),
                    $dot(),
                    $span(lang('Last updated:'), 'me-1', lang('Label')),
                    $span(lang('Date'), '', lang('Date'), $bind('last_modified_date_and_time')),
                )),
            )),
            // Which version of the app the guide is for; it leaves when the
            // guide applies to every version.
            $el('p', 'alert alert-secondary d-flex align-items-center gap-2 py-2 small pf-measure pg-hide-if-empty mb-4', lang('Version Note'), array(
                $icon('bi-info-circle'),
                $span(lang('Applies to version:'), '', lang('Label')),
                $span($guide['version'], 'pg-field-value fw-semibold', lang('Value'), $bind('version')),
            )),
            // The guide's text is rich text: a div holds what the author
            // wrote, contained by the template's rules, at a reading measure.
            $el('div', 'pf-post pf-measure mb-4', lang('Guide Text'), array(), array_merge(
                array('text' => lang('The text of the guide appears here.')), $bind('body'))),
            $el('p', 'pg-hide-if-empty mb-3', lang('Attachment'), array(
                $span('/attachment.png', 'pg-field-value d-none', lang('Value'), $bind('attachment')),
                $el('a', 'btn btn-sm btn-secondary d-inline-flex align-items-center gap-2', lang('Link'), array(
                    $icon('bi-paperclip'),
                    $span(lang('Open the attachment'), '', lang('Link Text')),
                ), array('href' => '#', '_bindings' => array('href' => 'attachment'))),
            )),
            $el('p', 'pf-meta pg-hide-if-empty mb-4', lang('Tags'), array(
                $span(lang('Tags:'), 'fw-semibold me-1', lang('Label')),
                $span($guide['tags'], 'pg-field-value', lang('Value'), $bind('tags')),
            )),
            $el('div', 'border-top pt-4 d-print-none', lang('Feedback'), array(
                $heading('h2', lang('Feedback'), 'pf-sec-title mb-1'),
                $para(lang('Was this guide helpful? Rate it and say what could be clearer. A moderator reads all feedback before it appears.'), 'pf-meta mb-3'),
            )),
        ), $show_when('record_found')),
    )),
);

// The replies under a topic: the topic page's own Comments widget, under
// the topic view (whose record the replies belong to). Oldest first, a
// reply under the post it answers and one more level under that; a reply
// to a reply that deep stays at that level and quotes the post it answers.
// A featured reply, the best answer, comes first. Twenty posts with their
// replies to a page, each post a permalink.
$widgets['topic_replies'] = array(
    'page'   => 'topic',
    'slug'   => lang('comments'),
    'config' => array(
        'regionType' => 'comments',
        'order'      => 'oldest',
        'featured'   => 'first',
        'layout'     => 'threaded',
        'max_depth'  => 2,
        'per_page'   => 20,
    ),
    'tree'   => $reply_thread($topic_words),
);
// The feedback under a guide, with its stars: the guide page's own Comments
// widget. The newest first, an answer to a feedback right under it (an
// answer to that answer quotes it), a featured one first, ten to a page.
$widgets['doc_feedback'] = array(
    'page'   => 'doc',
    'slug'   => lang('comments'),
    'config' => array(
        'regionType' => 'comments',
        'order'      => 'newest',
        'featured'   => 'first',
        'layout'     => 'threaded',
        'max_depth'  => 1,
        'per_page'   => 10,
    ),
    'tree'   => $reply_thread($doc_words),
);

// ── The forms ───────────────────────────────────────────────────────────

// The Forum Topics form, on Start a Topic. What a member fills in sits in
// one group; the fields after it are for the moderators only (office use):
// each in a box of its own with its label and no hint, so a member's form
// keeps nothing of them. A new topic stores their selected option: Open,
// not pinned, no announcement. Every option of an office pick list is a
// stored answer (no empty prompt): the control panel prints only stored
// options, so an empty one would show the first answer and save it on the
// next change.
$widgets['topic_form'] = array(
    'page'   => 'new_topic',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $fieldset(lang('Your topic'), array(
            $field('pf-topic-board', lang('Board'), pg_cf_with_role($select('pf-topic-board', 'board', lang('Choose a board…'), $board_names), 'category')),
            $choices('pf-topic-kind', 'kind', lang('What kind of topic is it?'), $kinds),
            $field_help('pf-topic-title', lang('Title'), pg_cf_with_role($input('pf-topic-title', 'text', 'title', 'off', true, array(), array('maxlength' => '120')), 'title'),
                lang('One clear sentence, at most 120 characters, that says what happens.')),
            $field_help('pf-topic-body', lang('Your post'), pg_cf_with_role($textarea('pf-topic-body', 'body', 12, true, '', array('wysiwyg' => true)), 'description'),
                lang('Say what you tried, what you expected and what happened instead. Links, lists and code blocks are kept.')),
            $field_help('pf-topic-tags', lang('Tags (optional)'), $input('pf-topic-tags', 'text', 'tags', 'off', false, array(), array('maxlength' => '100')),
                lang('A few words, separated by commas, such as sync, reminders.')),
            $field_help('pf-topic-attachment', lang('Attachment (optional)'), $file('pf-topic-attachment', 'attachment', 'public', 'image/*,.txt,.log,.pdf'),
                lang('A screenshot or a log file. Everyone who can read the topic can open it, so remove anything private first.')),
        )),
        $agree('pf-topic-guidelines', 'guidelines', lang('I have read the community guidelines and searched for an existing topic first.')),
        // For the moderators only.
        $field('pf-topic-status', lang('Status'), $office($select('pf-topic-status', 'status', '', $topic_statuses, false, $topic_statuses[0]))),
        $field('pf-topic-pinned', lang('Pinned at the top of the forum and its board'), $office($select('pf-topic-pinned', 'pinned', '', $yes_no, false, $no))),
        $field('pf-topic-announcement', lang('Announcement on the home page'), $office($select('pf-topic-announcement', 'announcement', '', $yes_no, false, $no))),
        $field('pf-topic-staff-note', lang('Moderator note (never shown to members)'), $office($textarea('pf-topic-staff-note', 'staff_note', 3, false))),
        $captcha(),
        $submit(lang('Post the topic'), 'btn btn-primary btn-lg px-4'),
        $loop(array()),
    )),
);

// The Community Docs form, on Contribute a Guide: the guide in one group,
// the author's name in another; then the moderators' fields. A new guide
// stores Awaiting review, not featured and no order.
$widgets['doc_form'] = array(
    'page'   => 'doc_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $fieldset(lang('Your guide'), array(
            $field('pf-doc-section', lang('Section'), pg_cf_with_role($select('pf-doc-section', 'section', lang('Choose a section…'), $doc_section_names), 'category')),
            $field_help('pf-doc-title', lang('Title'), pg_cf_with_role($input('pf-doc-title', 'text', 'title', 'off', true, array(), array('maxlength' => '120')), 'title'),
                lang('Say what the guide helps with, such as "Share a notebook with your team".')),
            $field_help('pf-doc-summary', lang('Summary'), pg_cf_with_role($textarea('pf-doc-summary', 'summary', 3, true, '', array(), array('maxlength' => '300')), 'description'),
                lang('One or two sentences readers see in the lists and in the search results.')),
            $field_help('pf-doc-body', lang('The guide'), $textarea('pf-doc-body', 'body', 20, true, '', array('wysiwyg' => true)),
                lang('Start with what the guide helps with. Use subheads for the parts and numbered lists for the steps; code blocks and links are kept.')),
            $field_help('pf-doc-version', lang('Applies to version (optional)'), $input('pf-doc-version', 'text', 'version', 'off', false, array(), array('maxlength' => '40')),
                lang('Such as 4.2 or 4.0 and later. Leave it empty if the guide applies to every version.')),
            $field_help('pf-doc-tags', lang('Tags (optional)'), $input('pf-doc-tags', 'text', 'tags', 'off', false, array(), array('maxlength' => '100')),
                lang('A few words, separated by commas.')),
            $field_help('pf-doc-attachment', lang('Attachment (optional)'), $file('pf-doc-attachment', 'attachment', 'public', 'image/*,.txt,.pdf,.zip'),
                lang('A screenshot, a sample file or a template. Readers can open it once the guide is published.')),
        )),
        $fieldset(lang('About you'), array(
            $field_help('pf-doc-byline', lang('Your name as readers will see it'), $input('pf-doc-byline', 'text', 'byline', 'name', true, array(), array('maxlength' => '80')),
                lang('The guide is signed with this name.')),
        )),
        $agree('pf-doc-rights', 'rights', lang('I wrote this guide myself, I may share its files, and the moderators may edit it.')),
        // For the moderators only.
        $field('pf-doc-status', lang('Status'), $office($select('pf-doc-status', 'status', '', $doc_statuses, false, $doc_statuses[0]))),
        $field('pf-doc-featured', lang('Featured on the home page and in the docs'), $office($select('pf-doc-featured', 'featured', '', $yes_no, false, $no))),
        $field('pf-doc-order', lang('Order in its section (two digits, such as 10, 20 or 30)'), $office($input('pf-doc-order', 'text', 'doc_order', 'off', false, array(), array('inputmode' => 'numeric', 'maxlength' => '2')))),
        $captcha(),
        $submit(lang('Send for review'), 'btn btn-primary btn-lg px-4'),
        $loop(array()),
    )),
);

// The Forum Reports form, on Report a Post: the post's link, the reason and
// the details; then the moderators' fields. A new report is Open. The topic
// page's "Report a post" opens the form with the topic's address in the
// link field (?post=, the field's starting value): the visitor can change
// it, so it is only what they reported, never trusted as more.
$widgets['report_form'] = array(
    'page'   => 'report',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $field_help('pf-report-post', lang('Link to the post'), $input('pf-report-post', 'text', 'post', 'off', true, array('url_param' => 'post'), array('maxlength' => '300', 'inputmode' => 'url')),
            lang('Opened from a topic, this holds the topic\'s address. To report a reply, paste the link its Share button copies instead.')),
        $field('pf-report-reason', lang('Reason'), $select('pf-report-reason', 'reason', lang('Choose a reason…'), $report_reasons)),
        $field_help('pf-report-details', lang('Details (optional)'), $textarea('pf-report-details', 'details', 5, false, '', array(), array('maxlength' => '2000')),
            lang('What should the moderators look at? Only the moderators read this.')),
        // For the moderators only.
        $field('pf-report-status', lang('Status'), $office($select('pf-report-status', 'status', '', $report_statuses, false, $report_statuses[0]))),
        $field('pf-report-staff-note', lang('Moderator note'), $office($textarea('pf-report-staff-note', 'staff_note', 3, false))),
        $captcha(),
        $submit(lang('Send the report')),
        $loop(array()),
    )),
);

// The contact form: who is writing, what about and the message. What is
// sent opens a conversation with the moderators.
$widgets['contact_form'] = array(
    'page'   => 'contact',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $person_fields('pf-contact'),
        $field('pf-contact-topic', lang('What is your message about?'), $select('pf-contact-topic', 'topic', lang('Choose one…'), array(
            lang('My account'),
            lang('A topic or a reply'),
            lang('A guide in the docs'),
            lang('A problem with the site'),
            lang('Something else'),
        ))),
        $field('pf-contact-message', lang('Your message'), $textarea('pf-contact-message', 'message', 6)),
        $captcha(),
        $submit(lang('Send the message')),
        $loop(array()),
    )),
);

// ── The members' own lists ──────────────────────────────────────────────
// A member sees their own records: the viewer filter on the submitter,
// never every record for whoever may edit the form (editor off), so a
// moderator's own pages show their own too.
$own = function ($submitter, $watcher) {
    return array(
        'viewer_filter'           => true,
        'viewer_filter_submitter' => $submitter,
        'viewer_filter_watcher'   => $watcher,
        'viewer_filter_editor'    => false,
    );
};
// The topics the member started, live ones only (a hidden topic opens
// nowhere), the most recently active first, twenty to a page.
$widgets['my_topics_started'] = $topic_list('my_topics', 20, 0, array(),
    lang('You have not started a topic yet. Ask a question or start a discussion: the community will help.'),
    array($topic_rows($topic_sample(2))), $own(true, false));
// The live topics the member follows: the ones under whose reply form they
// ticked the box, the twenty most recently active. A follower is kept per
// topic page, the list's detail page, whose record view shows the topics'
// form (pg_sw_list_watcher_page_id()).
$widgets['my_topics_followed'] = $topic_list('my_topics', 20, 20, array(),
    lang('You follow no topic yet. Tick the box under the reply form when you reply, and the topic appears here.'),
    array($topic_rows($topic_sample(7))), $own(false, true));
// The member's own replies and feedback, the newest first, published or
// waiting for a moderator (marked so). Only a signed-in member reaches the
// page, and the list shows nobody else's.
$widgets['my_replies'] = array(
    'page'   => 'my_topics',
    'slug'   => lang('recent-comments'),
    'config' => array(
        'regionType'      => 'comments',
        'mode'            => 'recent',
        'recent_show'     => 'mine',
        'recent_page_ids' => $reply_sources,
        'recent_limit'    => '10',
        'recent_order'    => 'newest',
    ),
    'tree'   => $recent_list(array(
        'empty'  => lang('You have not replied yet. Your replies and your feedback on guides appear here, the newest first.'),
        'title'  => $topic_heads[4][5],
        'status' => true,
        'edit'   => false,
    )),
);

// A list of the docs desk or the moderators' desks: the records in their
// state on the desk. No live rule: these lists sit on the member's own page
// or the moderators' and show every status.
$desk_list = function ($page_key, $base, $ipp, $max, $filters, $empty, $tree, $extra = array()) use ($widget_root) {
    $config = array_merge($base, array('items_per_page' => $ipp, 'max_results' => $max), $extra);
    if ($filters) $config['filters'] = $filters;
    $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};
// The guides on the desk, each opening its page there, the oldest first.
$desk_doc_base = array(
    'regionType'          => 'form_list_view',
    'custom_form_page_id' => '{{tab:doc_new}}',
    'detail_page_id'      => '{{tab:doc_submission}}',
    'order_by_field'      => 'submitted_date_and_time',
    'order_by_direction'  => 'ASC',
);
// A sample guide with the Status the desk shows it in.
$desk_guide = function ($i, $status) use ($doc_sample) {
    return array_merge($doc_sample($i), array('status' => $status));
};
// The member's guides in every status, the newest first, twenty to a page.
$widgets['my_docs_list'] = $desk_list('my_docs', $desk_doc_base, 20, 0, array(),
    lang('Nothing sent yet. Your first guide is one form away.'),
    array($desk_doc_rows($desk_guide(5, $doc_statuses[2]))),
    array_merge($own(true, false), array('order_by_direction' => 'DESC')));
// Beside them: the member's guides readers see now, each opening the
// published guide.
$mine = $doc_sample(5);
$widgets['my_docs_live'] = array(
    'page'   => 'my_docs',
    'slug'   => lang('form-list'),
    'config' => array_merge($doc_pub, $own(true, false), array(
        'items_per_page' => 5,
        'max_results'    => 5,
        'filters'        => $doc_live,
        'empty_message'  => lang('None of your guides is published yet.'),
    )),
    'tree'   => $widget_root(array(
        $el('ul', 'list-unstyled mb-0', lang('List'), array($loop(array(
            $el('li', 'pf-compact', lang('Guide Row'), array(
                $el('h3', 'pf-compact-title', lang('Title'), array(
                    $link($mine['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'pf-meta small mb-0', lang('Details'), array(
                    $span($mine['section'], '', lang('Section'), $bind('section')),
                    $dot(),
                    $span(lang('Feedback:'), 'me-1', lang('Label')),
                    $span('4', 'fw-semibold', lang('Number of Comments'), $bind('number_of_comments')),
                )),
            )),
        )))),
    )),
);

// One guide on the desk, for its author (the browser that sent it, or the
// account) and the moderators: everything the author sent, its Status, and
// the rich text in a box of its own (what an author writes cannot reach
// outside it). Above the messages, which the page puts right under it,
// their heading; it leaves with a missing guide.
$sent_guide = $desk_guide(5, $doc_statuses[2]);
$widgets['doc_submission_view'] = array(
    'page'   => 'doc_submission',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:doc_new}}',
        'access_control'      => 'submitter_only',
        'title_field'         => 'title',
        'title_suffix'        => lang('| Docs desk'),
        'not_found_message'   => lang('This guide could not be found, or it is not yours to see.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-journal-x', 'display-5 text-body-secondary'),
            $heading('h1', lang('Guide not found'), 'h3 fw-bold mt-3'),
            $para(lang('This guide could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
            $link(lang('My guides'), '{{page:my_docs}}', 'btn btn-primary'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Guide on the Desk'), array_merge(
            $sent_notices(lang('Thank you. Your guide reached the moderators.')),
            array(
                $record_toolbar(lang('My guides'), '{{page:my_docs}}', lang('Change Status')),
                $el('header', 'mb-4', lang('Guide Header'), array(
                    $para($sent_guide['section'], 'pf-kicker mb-1', $bind('section')),
                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title and Status'), array(
                        $heading('h1', $sent_guide['title'], 'pf-page-title mb-0', $bind('title')),
                        $status_badge($sent_guide['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6'),
                    )),
                    $para($sent_guide['summary'], 'lead text-body-secondary mb-0', $bind('summary')),
                )),
                $el('dl', 'pf-card mb-4', lang('Facts'), array(
                    $fact(lang('Name on the guide'), array($span($sent_guide['byline'], '', lang('Byline'), $bind('byline')))),
                    $fact(lang('Applies to version'), array($span($sent_guide['version'], 'pg-field-value', lang('Value'), $bind('version'))), true),
                    $fact(lang('Tags'), array($span($sent_guide['tags'], 'pg-field-value', lang('Value'), $bind('tags'))), true),
                    $fact(lang('Sent'), array($span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
                    $fact(lang('Last activity'), array($span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')))),
                    $fact(lang('Reference Code'), array($span('ABC123', 'font-monospace', lang('Reference Code'), $bind('reference_code')))),
                )),
                $para(lang('Text of the guide'), 'pf-label mb-2'),
                $el('div', 'pf-post pf-card mb-4', lang('Guide Text'), array(), array_merge(
                    array('text' => lang('The text of the guide appears here.')), $bind('body'))),
                // The attachment leaves with an empty field. Until the guide
                // is published only the moderators can open it.
                $el('div', 'pg-hide-if-empty pf-card mb-4', lang('Attachment'), array(
                    $span('/attachment.png', 'pg-field-value d-none', lang('Value'), $bind('attachment')),
                    $el('p', 'mb-2', lang('Link'), array(
                        $el('a', 'btn btn-sm btn-secondary d-inline-flex align-items-center gap-2', lang('Link'), array(
                            $icon('bi-paperclip'),
                            $span(lang('Open the attachment'), '', lang('Link Text')),
                        ), array('href' => '#', '_bindings' => array('href' => 'attachment'))),
                    )),
                    $para(lang('Until the guide is published, only the moderators can open its attachment.'), 'small text-body-secondary mb-0'),
                )),
                $el('div', 'border-top pt-4 d-print-none', lang('Messages'), array(
                    $heading('h2', lang('Messages with the moderators'), 'pf-sec-title mb-1'),
                    $para(lang('Write to the moderators below. They answer here, and each message also comes by e-mail.'), 'pf-meta mb-3'),
                )),
            )
        ), $show_when('record_found')),
    )),
);

// ── The moderators' lists ───────────────────────────────────────────────
// They sit on private pages, so they show every status unless they say
// otherwise; the moderators' lists of live topics carry the live rule all
// the same. Each row gets the list's pencil for whoever may edit the form's
// records.

// The reports, each opening its page for the moderators, the oldest first.
$report_base = array(
    'regionType'          => 'form_list_view',
    'custom_form_page_id' => '{{tab:report}}',
    'detail_page_id'      => '{{tab:staff_report}}',
    'order_by_field'      => 'submitted_date_and_time',
    'order_by_direction'  => 'ASC',
);
$is_status = function ($value) use ($f) {
    return $f('status', 'is equal to', $value);
};
// The sample report the rows, the report's page and its e-mails show in
// the editor and the installer's preview: a topic's address under the
// topic page's own name.
$report = array(
    'status'  => $report_statuses[0],
    'reason'  => $report_reasons[0],
    'post'    => '/' . lang('topic') . '?r=AbC123xYz9',
    'by'      => $people[3],
    'account' => mb_strtolower(str_replace(' ', '.', $people[3]), 'UTF-8'),
);

// What is waiting, in numbers: the open topics, the live topics without a
// reply, the solved and the hidden ones, the open reports and the guides
// awaiting review.
$widgets['staff_count_open']       = $count_list('staff', $topic_pub, array($is_status($topic_statuses[0])), $stat_surround('214', lang('Open topics')));
$widgets['staff_count_unanswered'] = $count_list('staff', $topic_pub, $with_topic_live(array($unanswered)), $stat_surround('12', lang('Unanswered')));
$widgets['staff_count_solved']     = $count_list('staff', $topic_pub, array($is_solved), $stat_surround('612', lang('Solved')));
$widgets['staff_count_hidden']     = $count_list('staff', $topic_pub, array($is_status($hidden_value)), $stat_surround('3', lang('Hidden')));
$widgets['staff_count_reports']    = $count_list('staff', $report_base, array($is_status($report_statuses[0])), $stat_surround('2', lang('Open reports')));
$widgets['staff_count_guides']     = $count_list('staff', $desk_doc_base, array($is_status($doc_statuses[0])), $stat_surround('4', lang('Guides to review')));

// The dashboard's lists, ten each.
$widgets['staff_reports_waiting'] = $desk_list('staff', $report_base, 10, 10, array($is_status($report_statuses[0])),
    lang('No report is waiting.'), array($report_rows($report)));
$widgets['staff_guides_waiting'] = $desk_list('staff', $desk_doc_base, 10, 10, array($is_status($doc_statuses[0])),
    lang('No guide is waiting for review.'), array($desk_doc_rows($desk_guide(8, $doc_statuses[0]))));
// The replies and the feedback held for a moderator, the oldest first, on
// the topic and guide pages the moderator may edit; each with its way to
// the comment's edit screen, where it is published.
$widgets['staff_replies_waiting'] = array(
    'page'   => 'staff',
    'slug'   => lang('recent-comments'),
    'config' => array(
        'regionType'      => 'comments',
        'mode'            => 'recent',
        'recent_show'     => 'pending',
        'recent_page_ids' => $reply_sources,
        'recent_limit'    => '10',
        'recent_order'    => 'oldest',
    ),
    'tree'   => $recent_list(array(
        'empty'  => lang('Nothing is waiting for approval.'),
        'title'  => $doc_heads[3][3],
        'status' => true,
        'edit'   => true,
    )),
);
// Every live topic of the last seven days, the newest first: its title
// opens its page. A hidden one is in the hidden topics' list.
$widgets['staff_new_topics'] = $desk_list('staff', $topic_pub, 10, 10,
    $with_topic_live(array($f('submitted_date_and_time', 'is greater than or equal to', '', 'days ago', 7))),
    lang('No topic was posted in the last seven days.'), array($staff_topic_rows($topic_sample(2))),
    array('order_by_field' => 'submitted_date_and_time', 'order_by_direction' => 'DESC', 'order_by_2_field' => ''));
$widgets['staff_unanswered'] = $desk_list('staff', $topic_pub, 10, 10, $with_topic_live(array($unanswered)),
    lang('Every live topic has a reply.'), array($staff_topic_rows($topic_sample(4, array('replier' => '', 'replies' => '0', 'views' => '6')))),
    array('order_by_field' => 'submitted_date_and_time', 'order_by_direction' => 'ASC', 'order_by_2_field' => ''));
$widgets['staff_popular'] = $desk_list('staff', $topic_pub, 10, 10, $with_topic_live(array($viewed)),
    lang('No topic was viewed this week.'), array($staff_topic_rows($topic_sample(3))),
    array(
        'order_by_field'       => 'recent_views',
        'order_by_direction'   => 'DESC',
        'order_recent_days'    => 7,
        'order_by_2_field'     => 'newest_activity_date_and_time',
        'order_by_2_direction' => 'DESC',
    ));
$widgets['staff_solved'] = $desk_list('staff', $topic_pub, 10, 10, array($is_solved),
    lang('No topic is solved yet.'), array($staff_topic_rows($topic_sample(1))));
// A hidden topic's title is no link: its page would not show it.
$widgets['staff_hidden'] = $desk_list('staff', $topic_pub, 10, 10, array($is_status($hidden_value)),
    lang('No topic is hidden.'), array($staff_topic_rows($topic_sample(9, array('status' => $hidden_value)), false)));

// Every topic in any status, or in the Status the address names (?status=),
// the most recently active first, twenty to a page, with a search over the
// title, the text, the tags, the board, the Status and the moderator note.
// No title links: the list holds hidden topics too.
$widgets['staff_topics_list'] = $desk_list('staff_topics', $topic_pub, 20, 0,
    array($f('status', 'is equal to', '', 'url parameter', 'status')),
    lang('No topic found. Try other words, or another status.'),
    array(
        $el('p', 'pf-meta mb-3', lang('Count'), array(
            $el('span', '', lang('Status'), array(
                $span(lang('Status:'), 'me-1', lang('Label')),
                $span($hidden_value, 'fw-semibold', lang('Value'), $bind('__filter_value')),
                $dot(),
            ), $show_when('has_filter_value')),
            $span(lang('Topics:'), 'me-1', lang('Label')),
            $span('1,284', 'fw-semibold', lang('Number of Results'), $bind('__result_count')),
        ), $show_when('has_records')),
        $staff_topic_rows($topic_sample(8), false, 'h2'),
    ),
    array(
        'filter_title'   => lang('Topics: {value}'),
        'search_enabled' => true,
        'search_fields'  => array('title', 'body', 'tags', 'board', 'status', 'staff_note'),
        'search_width'   => 'full',
        'search_label'   => lang('Search by title, text, tag, board, status or moderator note…'),
    ));

// The docs desk. Only the last list, every guide, pages through.
$widgets['staff_docs_waiting'] = $desk_list('staff_docs', $desk_doc_base, 20, 20, array($is_status($doc_statuses[0])),
    lang('No guide is waiting for review.'), array($desk_doc_rows($desk_guide(8, $doc_statuses[0]))));
$widgets['staff_docs_in_review'] = $desk_list('staff_docs', $desk_doc_base, 20, 20, array($is_status($doc_statuses[1])),
    lang('No guide is in review.'), array($desk_doc_rows($desk_guide(10, $doc_statuses[1]))));
// Back with the author: a guide can wait weeks there, so the rows' last
// activity tells which one has gone quiet.
$widgets['staff_docs_changes'] = $desk_list('staff_docs', $desk_doc_base, 20, 20, array($is_status($doc_statuses[2])),
    lang('No guide is back with its author.'), array($desk_doc_rows($desk_guide(5, $doc_statuses[2]))));
// What readers see now, the newest first; a row opens the published guide
// and counts its feedback.
$widgets['staff_docs_published'] = $desk_list('staff_docs', $doc_pub, 10, 10, $doc_live,
    lang('No guide is published yet.'), array($desk_doc_rows($desk_guide(2, $published_value), lang('Feedback'))));
$widgets['staff_docs_all'] = $desk_list('staff_docs', $desk_doc_base, 20, 0, array(),
    lang('No guide was sent yet.'), array($desk_doc_rows($desk_guide(7, $published_value))),
    array(
        'order_by_direction' => 'DESC',
        'search_enabled'     => true,
        'search_fields'      => array('title', 'summary', 'body', 'byline', 'section', 'status', 'tags'),
        'search_width'       => 'full',
        'search_label'       => lang('Search by title, text, writer, section, status or tag…'),
    ));

// The reported posts. Only the last list, every report, pages through.
$widgets['staff_reports_open'] = $desk_list('staff_reports', $report_base, 50, 50, array($is_status($report_statuses[0])),
    lang('No report is waiting.'), array($report_rows($report)));
$widgets['staff_reports_resolved'] = $desk_list('staff_reports', $report_base, 10, 10, array($is_status($report_statuses[1])),
    lang('No report is resolved yet.'), array($report_rows(array_merge($report, array('status' => $report_statuses[1])))),
    array('order_by_field' => 'newest_activity_date_and_time', 'order_by_direction' => 'DESC'));
$widgets['staff_reports_dismissed'] = $desk_list('staff_reports', $report_base, 10, 10, array($is_status($report_statuses[2])),
    lang('No report is dismissed yet.'), array($report_rows(array_merge($report, array('status' => $report_statuses[2], 'reason' => $report_reasons[2])))),
    array('order_by_field' => 'newest_activity_date_and_time', 'order_by_direction' => 'DESC'));
$widgets['staff_reports_all'] = $desk_list('staff_reports', $report_base, 20, 0, array(),
    lang('No report yet.'), array($report_rows($report)),
    array(
        'order_by_direction' => 'DESC',
        'search_enabled'     => true,
        'search_fields'      => array('post', 'reason', 'details', 'status', 'staff_note'),
        'search_width'       => 'full',
        'search_label'       => lang('Search by post, reason, details, status or note…'),
    ));

// One report, for the moderators alone: the private page lets them in, and
// the record view shows it only to whoever may edit the Forum Reports form
// (and to the member who sent it, whom the private folder keeps out). The
// post is printed as text: it is what the member typed.
$widgets['staff_report_view'] = array(
    'page'   => 'staff_report',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:report}}',
        'access_control'      => 'submitter_only',
        'title_field'         => 'reason',
        'title_suffix'        => lang('| Reported posts'),
        'not_found_message'   => lang('This report could not be found.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-flag', 'display-5 text-body-secondary'),
            $heading('h1', lang('Report not found'), 'h3 fw-bold mt-3'),
            $para(lang('This report could not be found.'), 'text-body-secondary mb-4', $bind('not_found')),
            $link(lang('Reported posts'), '{{page:staff_reports}}', 'btn btn-primary'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Reported Post'), array(
            $record_toolbar(lang('Reported posts'), '{{page:staff_reports}}', lang('Change Status')),
            $el('header', 'mb-4', lang('Report Header'), array(
                $para(lang('Reported post'), 'pf-kicker mb-1'),
                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2', lang('Title and Status'), array(
                    $heading('h1', $report['reason'], 'pf-page-title mb-0', $bind('reason')),
                    $status_badge($report['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6'),
                )),
            )),
            $el('dl', 'pf-card mb-3', lang('Facts'), array(
                $fact(lang('Address of the post'), array($span($report['post'], 'font-monospace', lang('Value'), $bind('post')))),
                $fact(lang('Reported by'), array($span($report['by'], 'pg-field-value', lang('Name'), $bind('submitter_name'))), true),
                $fact(lang('Account'), array($span($report['account'], 'pg-field-value', lang('User Name'), $bind('submitter'))), true),
                $fact(lang('Sent'), array($span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
                $fact(lang('Last activity'), array($span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')))),
                $fact(lang('Reference Code'), array($span('ABC123', 'font-monospace', lang('Reference Code'), $bind('reference_code')))),
            )),
            $para(lang('To read the post, copy its address into your browser: it is what the member typed, so it is never a link.'), 'pf-meta mb-4'),
            $record_words(lang('Details of the report'), lang('This reply only links to a shop and has nothing to do with the topic.'), 'details'),
            $record_words(lang('Moderator note'), lang('Hid the reply and wrote to the member.'), 'staff_note'),
            $el('div', 'border-top pt-4 d-print-none', lang('Notes'), array(
                $heading('h2', lang('Notes'), 'pf-sec-title mb-1'),
                $para(lang('Notes are for the moderators only: say what you found and what you did.'), 'pf-meta mb-3'),
            )),
        ), $show_when('record_found')),
    )),
);

// The staff directory's form: one person.
$widgets['staff_form'] = array(
    'page'   => 'staff_entry',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $row(array(
            $col(array($field('pf-staff-name', lang('Full Name'), $input('pf-staff-name', 'text', 'full_name', 'off'))), '6'),
            $col(array($field('pf-staff-title', lang('Job Title'), $input('pf-staff-title', 'text', 'job_title', 'off'))), '6'),
            $col(array($field('pf-staff-department', lang('Department'), $input('pf-staff-department', 'text', 'department', 'off', false))), '6'),
            $col(array($field('pf-staff-photo', lang('Photo'), $file('pf-staff-photo', 'photo', 'private'))), '6'),
            $col(array($field('pf-staff-email', lang('Email'), $input('pf-staff-email', 'email', 'email', 'off', false))), '6'),
            $col(array($field('pf-staff-phone', lang('Phone'), $input('pf-staff-phone', 'tel', 'phone', 'off', false))), '6'),
        ), '3'),
        $field('pf-staff-bio', lang('A few words'), $textarea('pf-staff-bio', 'bio', 3, false)),
        $submit(lang('Add')),
        $loop(array()),
    )),
);
// The directory, for the moderators only: a card for each person, by name.
// A card's picture has an empty alt: the name beside it says who it is.
$widgets['staff_directory'] = array(
    'page'   => 'staff_directory',
    'slug'   => lang('form-list'),
    'config' => array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:staff_entry}}',
        'items_per_page'      => 50,
        'max_results'         => 0,
        'order_by_field'      => 'full_name',
        'order_by_direction'  => 'ASC',
        'search_enabled'      => true,
        'search_fields'       => array('full_name', 'job_title', 'department'),
        'search_width'        => 'half',
        'search_label'        => lang('Search by name or department...'),
        'empty_message'       => lang('The directory is empty for now.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 g-lg-4', lang('Cards'), array($loop(array(
            $el('div', 'col', lang('Column'), array(
                $el('article', 'pf-card h-100', lang('Person Card'), array(
                    $el('div', 'd-flex align-items-start gap-3', lang('Person'), array(
                        $el('div', 'pg-hide-if-empty flex-shrink-0', lang('Picture'), array(
                            $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                                $image('https://placehold.co/128x128/4f46e5/ffffff?text=PR', '', 'pf-avatar', array(
                                    'width'     => '60',
                                    'height'    => '60',
                                    '_bindings' => array('src' => 'photo'),
                                    '_attrs'    => array($attr('loading', 'lazy')),
                                )),
                            )),
                        )),
                        $el('div', 'pf-min-0', lang('Details'), array(
                            $heading('h2', $people[1], 'h6 fw-bold mb-0', $bind('full_name')),
                            $para(lang('Community moderator'), 'small fw-semibold text-body-secondary mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Community team'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span('priya.raman@example.com', 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('(518) 555-0142', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Looks after Questions & Help and reviews the guides sent to the docs.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                )),
            )),
        )))),
    )),
);

// The inbox: the conversations the member started (a signed-in message
// from the contact page) or follows; the moderators see them all. A
// conversation's subject is its topic.
$widgets['inbox_list'] = array(
    'page'   => 'inbox',
    'slug'   => lang('form-list'),
    'config' => array(
        'regionType'              => 'form_list_view',
        'custom_form_page_id'     => '{{tab:contact}}',
        'detail_page_id'          => '{{tab:conversation}}',
        'items_per_page'          => 20,
        'max_results'             => 0,
        'order_by_field'          => 'newest_activity_date_and_time',
        'order_by_direction'      => 'DESC',
        'order_by_2_field'        => 'submitted_date_and_time',
        'order_by_2_direction'    => 'DESC',
        'viewer_filter'           => true,
        'viewer_filter_submitter' => true,
        'viewer_filter_watcher'   => true,
        'viewer_filter_editor'    => true,
        'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'pf-topics', lang('Conversation List'), array($loop(array($conversation_row())))),
    )),
);

// One conversation: the first message; the answers follow as messages.
// Above them, which the page puts right under it, their heading; it leaves
// with a missing conversation.
$widgets['conversation'] = array(
    'page'   => 'conversation',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:contact}}',
        'access_control'      => 'submitter_only',
        'title_field'         => 'topic',
        'title_suffix'        => lang('| Inbox'),
        'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'text-center py-5', lang('Not Found'), array(
            $icon('bi-chat-left-dots', 'display-5 text-body-secondary'),
            $heading('h1', lang('Conversation not found'), 'h3 fw-bold mt-3'),
            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Conversation'), array_merge(
            $sent_notices(lang('Thank you, your message has reached the moderators. Their answers appear on this page and in your inbox, and you can write back below.'), true),
            array(
                $record_toolbar(lang('Inbox'), '{{page:inbox}}'),
                $para(lang('Conversation'), 'pf-kicker mb-1'),
                $heading('h1', lang('My account'), 'pf-page-title mb-2', $bind('topic')),
                $el('p', 'pf-meta mb-4', lang('Conversation Details'), array(
                    $span(lang('Started:'), 'me-1', lang('Label')),
                    $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                    $dot(),
                    $span(lang('Reference Code') . ':', 'me-1', lang('Label')),
                    $span('ABC123', 'font-monospace', lang('Reference Code'), $bind('reference_code')),
                )),
                $el('div', 'pf-card pf-tint mb-4', lang('First Message'), array(
                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array_merge(
                        array($icon('bi-person-circle', 'fs-5 text-primary')), $person_name())),
                    $el('div', 'text-break', lang('Message'), array(), array_merge(
                        array('text' => lang('Hello, I would like to change the e-mail address of my account. Can you help?')), $bind('message'))),
                )),
                $el('div', 'border-top pt-4 d-print-none', lang('Replies'), array(
                    $heading('h2', lang('Replies'), 'pf-sec-title mb-1'),
                    $para(lang('The moderators answer below, and each answer also comes by e-mail.'), 'pf-meta mb-3'),
                )),
            )
        ), $show_when('record_found')),
    )),
);

// ── The e-mails' widgets ────────────────────────────────────────────────
// Each is drawn for the record just sent ($mail_widget). Whoever wrote to
// the moderators from the contact page.
$mail_person = function ($label) use ($mail_box, $mail_line, $person_name, $mail_value) {
    return $mail_box(array(
        $mail_line($label, $person_name()),
        $mail_value(lang('Email'), lang('jane.cooper@example.com'), 'email'),
    ), $label);
};
// The account the contact form opened for a visitor who had none: the
// address it signs in with, and how to choose a password (the set password
// link is shown on the conversation's page, not mailed).
$mail_new_account = function () use ($el, $span, $link, $attr, $bind, $mail_style, $show_when) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', '', array($attr('style', 'color:#4f46e5;color:var(--bs-link-color,#4f46e5)'))),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #dcdfe8;padding-top:16px'), $show_when('has_new_account')));
};
// A topic's lines for the moderators.
$mail_topic = $topic_sample(1);
// A guide's lines: all of them for the moderators, the title and the
// section for its author.
$mail_doc = function ($full) use ($mail_box, $mail_value, $sent_guide) {
    $lines = array(
        $mail_value(lang('Title'), $sent_guide['title'], 'title'),
        $mail_value(lang('Section'), $sent_guide['section'], 'section'),
    );
    if ($full) {
        $lines[] = $mail_value(lang('Name on the guide'), $sent_guide['byline'], 'byline');
        $lines[] = $mail_value(lang('Summary'), $sent_guide['summary'], 'summary');
        $lines[] = $mail_value(lang('Applies to version'), $sent_guide['version'], 'version');
        $lines[] = $mail_value(lang('Account'), 'priya.raman', 'submitter');
    }
    return $mail_box($lines, lang('Guide'));
};
$widgets['email_topic_staff'] = $mail_widget('email_topic_new', 'new_topic', lang('The topic could not be found.'), array(
    $mail_heading(lang('A new topic was posted')),
    $mail_para(lang('It is live in the forum now. Read it; if it breaks the guidelines, set its Status to Hidden in the control panel.')),
    $mail_box(array(
        $mail_value(lang('Title'), $mail_topic['title'], 'title'),
        $mail_value(lang('Board'), $mail_topic['board'], 'board'),
        $mail_value(lang('Kind'), $mail_topic['kind'], 'kind'),
        $mail_value(lang('Started by'), $mail_topic['author'], 'submitter_name'),
        $mail_value(lang('Account'), $mail_topic['account'], 'submitter'),
        $mail_value(lang('Tags'), $mail_topic['tags'], 'tags'),
    ), lang('Topic')),
    $mail_button(lang('Open the topic'), '{{page:topic}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_report_staff'] = $mail_widget('email_report_new', 'report', lang('The report could not be found.'), array(
    $mail_heading(lang('A post was reported')),
    $mail_para(lang('A member reported a post in the forum. Read the post, act on it, then write a note under the report and set its Status.')),
    $mail_box(array(
        $mail_value(lang('Reason'), $report['reason'], 'reason'),
        $mail_value(lang('Address of the post'), $report['post'], 'post'),
        $mail_value(lang('Details of the report'), lang('This reply only links to a shop and has nothing to do with the topic.'), 'details'),
        $mail_value(lang('Reported by'), $report['by'], 'submitter_name'),
        $mail_value(lang('Account'), $report['account'], 'submitter'),
    ), lang('Report Details')),
    $mail_small(lang('The post is what the member typed: copy it into your browser rather than following it.')),
    $mail_button(lang('Open the report'), '{{page:staff_report}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_report_member'] = $mail_widget('email_report_received', 'report', lang('The report could not be found.'), array(
    $mail_heading(lang('Thank you for your report')),
    $mail_para(lang('It reached the moderators. A moderator reads every report and acts on the posts that break the community guidelines.')),
    $mail_box(array(
        $mail_value(lang('Address of the post'), $report['post'], 'post'),
        $mail_value(lang('Reason'), $report['reason'], 'reason'),
    ), lang('Your Report')),
    $mail_para(lang('The member who wrote the post is not told who reported it.')),
    $mail_button(lang('Read the guidelines'), '{{page:guidelines}}'),
    $mail_reference(),
));
$widgets['email_doc_staff'] = $mail_widget('email_doc_new', 'doc_new', lang('The guide could not be found.'), array(
    $mail_heading(lang('A new guide is waiting for review')),
    $mail_para(lang('It came from the Contribute a Guide page. Read it on the docs desk, write to its author under it and set its Status in the control panel.')),
    $mail_doc(true),
    $mail_button(lang('Open it on the docs desk'), '{{page:doc_submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_doc_author'] = $mail_widget('email_doc_received', 'doc_new', lang('The guide could not be found.'), array(
    $mail_heading(lang('Your guide reached the moderators')),
    $mail_greeting('byline', $sent_guide['byline']),
    $mail_para(lang('Thank you for writing it. A moderator reviews every guide: follow its status under My guides, where the moderators also write to you.')),
    $mail_doc(false),
    $mail_para(lang('Nothing you send is shown to readers until a moderator publishes it.')),
    $mail_button(lang('Follow my guide'), '{{page:doc_submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_admin'] = $mail_widget('email_new_message', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('A new message has arrived')),
    $mail_para(lang('It came from the contact page. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
    $mail_person(lang('Sender')),
    $mail_box(array(
        $mail_value(lang('Subject'), lang('My account'), 'topic'),
    ), lang('Subject')),
    $el('div', 'mb-3', lang('Message'), array(), array_merge(
        array('text' => lang('Hello, I would like to change the e-mail address of my account. Can you help?')),
        $bind('message'), $mail_style('margin:0 0 16px'))),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_sender'] = $mail_widget('email_message_received', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('Your message has reached us')),
    $mail_greeting('first_name', lang('Jane')),
    $mail_para(lang('Thank you for writing to the moderators. Their answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
    $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
            array('text' => lang('My account'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', '', lang('Message'), array(), array_merge(
            array('text' => lang('Hello, I would like to change the e-mail address of my account. Can you help?')), $bind('message'))),
    ), lang('Your Message')),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_new_account(),
));
// The comment of the reply e-mails: placed on the new reply e-mail and the
// guide message e-mail, drawn for the comment the e-mail is about, flat and
// alone. The pages that name these e-mails keep their comment settings.
$widgets['email_comment'] = array(
    'page'   => 'email_reply',
    'slug'   => lang('comments'),
    'config' => array(
        'regionType' => 'comments',
        'order'      => 'oldest',
        'featured'   => 'none',
        'layout'     => 'flat',
        'max_depth'  => 1,
        'per_page'   => 0,
    ),
    'tree'   => $mail_comment_tree,
);

// The site search: the whole site for ?query=, ten results a page. A
// result that matches a part of a page links to that part.
$widgets['site_search'] = array(
    'page'   => 'search',
    'slug'   => lang('search'),
    'config' => array(
        'regionType'       => 'search_results',
        'search_scope'     => 'all',
        'results_per_page' => 10,
        'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
    ),
    'tree'   => $widget_root(array(
        $el('form', 'mb-4', lang('Search Form'), array(
            $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                $el('input', 'form-control', lang('Field'), array(), array(
                    '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the forum and the docs…')), $attr('aria-label', lang('Search the forum and the docs'))),
                    '_bindings' => array('value' => 'search_query'),
                )),
                $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                    '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                    '_bindings' => array('action' => 'submit_search'),
                )),
            )),
        ), array('_bindings' => array('action' => 'search_form'))),
        $para(lang('Results heading'), 'pf-meta fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
        $el('div', '', lang('Results'), array(
            $loop(array(
                $el('article', 'pf-compact py-3', lang('Result Row'), array(
                    $span(lang('Page'), 'pf-badge mb-2', lang('Result Type'), $bind('__result_type_label')),
                    $el('h2', 'h5 fw-bold mb-1', lang('Result Title'), array(
                        $link(lang('Title'), '#', 'stretched-link link-body-emphasis', array(),
                            array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                    )),
                    $para(lang('Before you ask'), 'pf-kicker mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                    $para(lang('Excerpt'), 'text-body-secondary mb-0', $bind('__result_excerpt')),
                )),
            )),
        )),
        $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
    )),
);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// Parts inside a shared component or a widget belong to that, not to the
// page, so only the page's own parts are listed here.
$search_sections = array(
    'home' => array(
        '#boards' => array('boards', lang('boards, categories, where to post, getting started')),
    ),
    'board' => array(
        '#all-boards' => array('all-boards', lang('boards, categories, where to post')),
    ),
    'docs' => array(
        '#doc-sections' => array('doc-sections', lang('docs, documentation, guides, manual, help articles')),
        '#every-guide'  => array('every-guide', lang('all guides, index, a to z, documentation')),
    ),
    'docs_section' => array(
        '#all-sections' => array('all-sections', lang('docs sections, documentation, guides')),
    ),
    'guidelines' => array(
        '#be-kind'          => array('be-kind', lang('code of conduct, rules, respect, harassment')),
        '#search-first'     => array('search-first', lang('duplicate topics, search before posting')),
        '#good-topic'       => array('good-topic', lang('how to ask, good question, title, details')),
        '#stay-on-topic'    => array('stay-on-topic', lang('off-topic, advertising, spam, self-promotion')),
        '#keep-it-private'  => array('keep-it-private', lang('privacy, passwords, personal data, screenshots')),
        '#moderation'       => array('moderation', lang('moderators, hidden posts, reports, solved')),
    ),
    'about' => array(
        '#who-we-are'     => array('who-we-are', lang('about us, community, who we are')),
        '#the-moderators' => array('the-moderators', lang('moderators, volunteers, team')),
        '#the-docs'       => array('the-docs', lang('docs, guides, documentation, contribute')),
        '#get-in-touch'   => array('get-in-touch', lang('contact, help, support, questions')),
    ),
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('e-mail, support, contact the moderators')),
    ),
    'privacy' => array(
        '#your-data' => array('your-data', lang('privacy, personal data, cookies')),
    ),
    'terms' => array(
        '#using-the-site' => array('using-the-site', lang('terms, rules, posting policy')),
    ),
    'accessibility' => array(
        '#accessibility-help' => array('accessibility-help', lang('accessibility, screen reader, keyboard, print')),
    ),
);
$searchable_tree = function ($node, $marks, &$used) use (&$searchable_tree) {
    if (!is_array($node)) return $node;
    if (isset($node['props']) && is_array($node['props'])) {
        $p = $node['props'];
        $name = isset($p['customName']) ? (string)$p['customName'] : (isset($p['_label']) ? (string)$p['_label'] : '');
        $key = null;
        if (!empty($p['id']) && isset($marks['#' . $p['id']]) && !isset($used['#' . $p['id']])) {
            $key = '#' . $p['id'];
        } elseif ($name !== '' && isset($marks[$name]) && !isset($used[$name])) {
            $key = $name;
        }
        if ($key !== null) {
            $used[$key] = true;
            if (empty($p['id'])) $node['props']['id'] = $marks[$key][0];
            $node['props']['searchSection'] = true;
            if ($marks[$key][1] !== '') $node['props']['searchKeywords'] = $marks[$key][1];
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) $node['children'][$i] = $searchable_tree($child, $marks, $used);
    }
    return $node;
};
foreach ($pages as $i => $p) {
    if (!isset($search_sections[$p['key']])) continue;
    $used = array();
    $pages[$i]['tree'] = $searchable_tree($p['tree'], $search_sections[$p['key']], $used);
}

return array(
    'name'        => lang('Community Forum & Docs'),
    'version'     => '1.2.0',
    'framework'   => 'bootstrap5',
    'order'       => 90,
    'icon'        => 'bi-chat-square-text',
    // The picture on the template's card and beside the designs made from
    // it: a forum's front page (pg_design_thumb_svg()).
    'thumb'       => 'forum',
    'description' => lang('A community forum with docs for a software product: boards, topics with replies, member-written guides that moderators review, reports and a moderation dashboard.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'minimal',
    'palette'     => 'aurora',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Boards, pinned topics and announcements, with the latest activity, the latest replies, unanswered topics and the week\'s most viewed on the home page.'),
        lang('Topic pages with threaded replies that quote each other and a best answer shown first, the topic\'s facts beside them and a way to report a post.'),
        lang('Docs that members write and moderators review, publish and feature.'),
        lang('A moderation dashboard with the replies awaiting approval, member accounts with their own replies and an inbox, and e-mails for new topics, replies, guides and reports.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_forum', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'masthead'     => array('name' => lang('Forum Masthead'), 'tree' => $masthead()),
        'footer'       => array('name' => lang('Forum Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Moderators\' Bar'), 'tree' => $staff_bar()),
        'email_header' => array('name' => lang('Forum E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold link-body-emphasis text-decoration-none',
                    array($attr('style', 'font-size:20px;font-weight:700;text-decoration:none;color:#1d2131;color:var(--bs-emphasis-color,#1d2131)'))),
            ), $mail_style('padding:16px 24px;border-bottom:3px solid #4f46e5'))),
        'email_footer' => array('name' => lang('Forum E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $para(lang('You get this e-mail because of your account in the community.'), 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit the community'), '{{page:home}}', '',
                    array($attr('style', 'color:#4f46e5;color:var(--bs-link-color,#4f46e5)'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #dcdfe8;font-size:13px;color:#5b6070'))),
    ),

    // The template's own look: its pf- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $forum_css),
        'fonts' => json_encode($forum_fonts),
    ),
);
