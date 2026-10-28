<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Long-Read Magazine": a calm magazine of long reads. A
 * front page with the cover story, the latest stories, a line from the
 * cover, the editor's letter, the editors' picks and the newest issues; one
 * page for each section (Culture, Ideas, Travel, Food, Design & Home,
 * Wellbeing); every story with a search; the issues, each with its numbered
 * contents (?issue=); the contributors, each with their own stories
 * (?author=); the story page set for reading, with its letters; an author
 * desk where authors send stories the editors review, schedule and set
 * live; the editors' desk; the sign-in and account pages, the e-mails the
 * site sends and a site search. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * A story is a record of the Magazine Stories form, owned by the Submit a
 * Story page in the private "contributors" folder: an author with view
 * access to that folder sends it, and an editor sets its Status, its
 * publication time, its place on the home page, its issue, its reading time
 * and a correction in the control panel (office-use-only fields). A record
 * is public only while its Status is "Live on the site" and its publication
 * time is set and has come: every public list and the story page carry
 * those three filters ($live).
 *
 * The look ships in the template's User Styles: a few of the ready-made
 * blocks' rules (pg_design_template_user_styles()) and the template's own
 * rules, all named pm-. Fonts: Fraunces for headings and DM Sans for labels
 * and controls, from the editor's font picker; the reading text is the
 * system serif (Iowan Old Style, Palatino, Georgia), which has true
 * italics.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
 *
 * One function lives outside this file: the gallery drawing
 * _pg_design_thumb_magazine() (includes/fn/design_themes.php), because the
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

$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$shop      = defined('ECOMMERCE') && ECOMMERCE;

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
// Words only a screen reader hears, next to an icon that says them.
$sr_only = function ($text) use ($span) {
    return $span($text, 'visually-hidden', lang('Text'));
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

// A sample portrait: the contributor's initials in sage ink on a sage tint,
// until the magazine puts a photo in its place. The initials follow the
// name in the site's language.
$portrait = function ($name, $size = 192) {
    $words = preg_split('/\s+/u', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . $size . 'x' . $size . '/e7ece8/4a6b53?text=' . rawurlencode($initials !== '' ? $initials : 'M');
};
// A sample photo, the same for a seed on every site.
$photo = function ($seed, $w, $h) {
    return 'https://picsum.photos/seed/pinegrap-magazine-' . $seed . '/' . $w . '/' . $h;
};

// ── One vocabulary ──────────────────────────────────────────────────────
// The values the form stores, the filters compare, the menus link and the
// sample stories carry, written once. Each is the lang() text of the
// language the template is opened in, so the options, the filters and the
// samples always match.

// The sections: key => array(title, one line, page slug). The key is the
// section page's key (never translated); the title is the value the Section
// field stores, the filter value and the label everywhere.
$sections = array(
    'culture'     => array(lang('Culture'),       lang('Books, music, film and the people who keep them going.'),       lang('culture')),
    'ideas'       => array(lang('Ideas'),         lang('Essays on attention, work, time and the way we live now.'),     lang('ideas')),
    'travel'      => array(lang('Travel'),        lang('Slow journeys by boat, train and on foot.'),                   lang('travel')),
    'food'        => array(lang('Food'),          lang('Kitchens, bakers and the meals that take their time.'),         lang('food')),
    'design_home' => array(lang('Design & Home'), lang('Small rooms, good chairs and the things made to last.'),        lang('design-and-home')),
    'wellbeing'   => array(lang('Wellbeing'),     lang('Walking, resting and the habits that make a day feel longer.'), lang('wellbeing')),
);
// The section titles in menu order: the Section field's options.
$section_titles = array();
foreach ($sections as $s) $section_titles[] = $s[0];

// A story's Status, set by the editors: the News Portal template's six, in
// the same order. Only the fourth makes a story public (with a publication
// time that has come): $live below.
$statuses   = array(lang('Awaiting review'), lang('In review'), lang('Changes requested'),
                    lang('Live on the site'), lang('Not accepted'), lang('Withdrawn'));
$live_value = $statuses[3];
// Where a story stands on the home page. The newest live Cover story fills
// the cover, the newest Editor's letter the letter band; older ones keep
// their placement as their issue's cover and letter.
$placements = array(lang('Standard'), lang('Cover story'), lang('Editor\'s letter'), lang('Editors\' pick'));

// The issues, newest first. A name is the stored value of the office field
// 'issue', the ?issue= value and the archive's title, and it is printed into
// an href unencoded: plain names only, no & # ? + % = " ' < >.
// The field's first option, and its default, is a real value: an option
// whose value is '' is never stored, and the control panel would then save
// the first issue on the next status change.
$online_only = lang('Online only');
$issues = array(
    'small-places' => lang('The Small Places Issue'),
    'water'        => lang('The Water Issue'),
    'slow'         => lang('The Slow Issue'),
);
$issue_options = array_merge(array($online_only), array_values($issues));

// The contributors: name => array(role, one line). The one line carries no
// name: the name prints right above it, and a name never sits inside a
// longer text to translate.
$contributors = array(
    'Emily Turner'   => array(lang('Editor in chief'),           lang('Edits the magazine and writes its letters and its essays on ideas.')),
    'Jane Cooper'    => array(lang('Culture editor'),            lang('Writes about books, music and the small places that keep them.')),
    'Michael Brooks' => array(lang('Travel writer'),             lang('Travels slowly, by mail boat and sleeper car, and writes it down.')),
    'Daniel Ross'    => array(lang('Design and home writer'),    lang('Visits small rooms and the people who build furniture by hand.')),
    'Nora Whitfield' => array(lang('Food and wellbeing editor'), lang('Cooks, walks and writes about the meals and habits that take time.')),
);
// A contributor's name as the byline stores it, and the address of the
// contributor's own stories: the contributors page reads ?author= and lists
// the live stories whose byline is exactly that name.
$contributor_name = function ($key) {
    return lang($key);
};
$contributor_url = function ($key) {
    return '{{page:author}}?author=' . rawurlencode(lang($key));
};
// The archive of an issue, for a link printed by a live record: the token is
// replaced by the record's own value (pg_sw_apply_tokens()), HTML-escaped,
// which is why issue names stay plain.
$issue_href = '{{page:issues}}?issue=^^issue^^';

// The heads of the sample stories, newest first: array(section key,
// placement index, issue key ('' = Online only), contributor, title,
// standfirst, picture seed). The cards show them in the editor and in the
// installer's preview, and the sample records are built from them.
$story_heads = array(
    array('ideas', 2, 'small-places', 'Emily Turner', lang('Small Places, Long Afternoons'),
        lang('Why this issue goes looking for the corners most maps leave out.'), 'letter-small-places'),
    array('food', 1, 'small-places', 'Nora Whitfield', lang('Twelve Stools and a Griddle'),
        lang('A diner at the edge of a river town opens before dawn and knows every order by heart. We sat at the counter until closing.'), 'diner'),
    array('design_home', 3, 'small-places', 'Daniel Ross', lang('Five Rooms Under 400 Square Feet'),
        lang('Small apartments in Chicago, Portland and Brooklyn where every inch earns its place.'), 'small-rooms'),
    array('culture', 0, 'small-places', 'Jane Cooper', lang('The Smallest Library in the County'),
        lang('A converted rail car holds two thousand books, one librarian and a waiting list for the reading chair.'), 'rail-library'),
    array('travel', 0, 'small-places', 'Michael Brooks', lang('Three Blocks of Main Street'),
        lang('A slow walk down one short street in a high desert town, from the hardware store to the last streetlight.'), 'main-street'),
    array('wellbeing', 0, '', 'Nora Whitfield', lang('The Walk Before Work'),
        lang('Twenty minutes outside before the inbox opens, tried for a month.'), 'morning-walk'),
    array('ideas', 2, 'water', 'Emily Turner', lang('Notes From the Water Issue'),
        lang('On rivers, harbors and the patience of anything that has to wait for the tide.'), 'letter-water'),
    array('travel', 1, 'water', 'Michael Brooks', lang('Down the River on a Mail Boat'),
        lang('For a century a small boat has carried letters to the cabins along one northern river. We rode along for the whole route.'), 'mail-boat'),
    array('food', 3, 'water', 'Nora Whitfield', lang('What a Harbor Town Eats When the Visitors Leave'),
        lang('Smoked fish, slow stews and the kitchens that stay open for the people who stay.'), 'harbor-kitchen'),
    array('culture', 0, 'water', 'Jane Cooper', lang('The Lighthouse Keepers\' Logbooks'),
        lang('A museum volunteer is reading a hundred years of tides, storms and small talk, one page at a time.'), 'lighthouse'),
    array('wellbeing', 0, 'water', 'Nora Whitfield', lang('Learning to Swim at Forty'),
        lang('A lake, a patient coach and the slow work of trusting the water.'), 'lake-swim'),
    array('ideas', 2, 'slow', 'Emily Turner', lang('Welcome to the Slow Issue'),
        lang('Why our first issue is about the things that refuse to hurry.'), 'letter-slow'),
    array('food', 1, 'slow', 'Nora Whitfield', lang('The Bread That Takes Three Days'),
        lang('A neighborhood baker on patience, flour and the four o\'clock alarm.'), 'three-day-bread'),
    array('design_home', 3, 'slow', 'Daniel Ross', lang('A Table Built to Last a Century'),
        lang('Inside a woodshop where the joinery takes years to learn and the glue is almost an afterthought.'), 'woodshop'),
    array('ideas', 3, 'slow', 'Emily Turner', lang('The Case for Doing One Thing at a Time'),
        lang('Attention is not a muscle you build with more tabs.'), 'one-thing'),
    array('culture', 3, 'slow', 'Jane Cooper', lang('A Year of Listening to Whole Albums'),
        lang('No shuffle, no skipping: every record played from the first track to the last.'), 'whole-albums'),
    array('travel', 0, 'slow', 'Michael Brooks', lang('The Slow Train Across the Plains'),
        lang('Two days, one sleeper car and a view that changes only when you stop waiting for it to.'), 'sleeper-train'),
);
// A sample story's values as the cards bind them, by its index above.
$head = function ($i) use ($story_heads, $sections, $issues, $online_only, $contributors, $contributor_name, $photo) {
    $h = $story_heads[$i];
    return array(
        'section' => $sections[$h[0]][0],
        'issue'   => $h[2] === '' ? $online_only : $issues[$h[2]],
        'byline'  => $contributor_name($h[3]),
        'bio'     => $contributors[$h[3]][1],
        'title'   => $h[4],
        'summary' => $h[5],
        'cover'   => $photo($h[6], 1600, 1200),
    );
};
// The newest sample story of a section: what its page shows in the editor.
$head_of_section = function ($key) use ($story_heads, $head) {
    foreach ($story_heads as $i => $h) {
        if ($h[0] === $key) return $head($i);
    }
    return $head(0);
};

// ── The filters of the story lists ──────────────────────────────────────
// Operators and dynamic values are the renderer's English constants, never
// translated (pg_sw_form_list_filter_sql()).
$f = function ($field, $operator, $value, $dynamic = '', $attribute = null) {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic);
    if ($attribute !== null) $row['dynamic_value_attribute'] = $attribute;
    return $row;
};
// What makes a story public; every row must match. The second row keeps a
// story with no publication time off the site: an empty value is not
// greater than or equal to a date, while it would pass "less than or equal
// to now". The third row holds a future time back until it comes.
$live = array(
    $f('status', 'is equal to', $live_value),
    $f('publish_date', 'is greater than or equal to', '2000-01-01 00:00:00'),
    $f('publish_date', 'is less than or equal to', '', 'current date and time'),
);
$with_live = function ($more = array()) use ($live) {
    return array_merge($live, $more);
};
$in_section = function ($title) use ($f) {
    return $f('section', 'is equal to', $title);
};
$recent = function ($days) use ($f) {
    return $f('publish_date', 'is greater than or equal to', '', 'days ago', $days);
};
$is_placement = function ($value) use ($f) {
    return $f('placement', 'is equal to', $value);
};
$not_placement = function ($value) use ($f) {
    return $f('placement', 'is not equal to', $value);
};
// A story with a line to set apart (an empty value is compared as '').
$has_quote = $f('pull_quote', 'is not equal to', '');
// The stories of a real issue: the Editor's letters of the issues.
$in_an_issue = $f('issue', 'is not equal to', $online_only);
// The settings every public list of stories shares: the form, the story
// page its rows open, newest publication first.
$pub = array(
    'regionType'           => 'form_list_view',
    'custom_form_page_id'  => '{{tab:blog_new}}',
    'detail_page_id'       => '{{tab:blog_post}}',
    'order_by_field'       => 'publish_date',
    'order_by_direction'   => 'DESC',
    'order_by_2_field'     => 'submitted_date_and_time',
    'order_by_2_direction' => 'DESC',
);

// ── Look: fonts and the template's own rules ───────────────────────────
// The fonts the editor's font picker lists, weights only: the font loader
// requests upright styles, so the reading text, which needs true italics,
// is the system serif and not a web font.
$magazine_fonts = array(
    array('family' => 'Fraunces', 'weights' => array('400', '600'), 'enabled' => true),
    array('family' => 'DM Sans', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS, beside the blocks' rules in User Styles. Every
// class is named pm-; no block rule's class is named here (the editor's
// palette would then take its rule as present). Selectors use descendants
// and pseudo-classes only, so they hold on the editor's canvas too, and
// never :first-child or :last-child: the editor's edit button may bring its
// own style element in first. Colours are tokens only: a palette colour
// that only the colour palettes define (--pg-secondary-fg) always carries a
// fallback for the plain Bootstrap palette, and the paper tone is mixed
// from tokens, so it follows any palette in both themes.
$magazine_css = implode("\n", array(
    // Fonts, weights and the accent: the palette's second colour, else the
    // theme's own.
    ':root,[data-bs-theme]{--pm-text:"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua",Georgia,serif;'
        . '--pm-sans:"DM Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-heading:"Fraunces",Georgia,"Times New Roman",serif;--pg-font-body:var(--pm-text);'
        . '--pg-heading-weight:600;--pg-display-weight:400;--pg-heading-tracking:-.005em;--pg-heading-line-height:1.18;'
        . '--pm-accent:var(--pg-secondary-fg,var(--bs-secondary-text-emphasis))}',
    // Paper: a 6% tint of the accent on the page colour. The plain tertiary
    // background first: a custom value holding an unsupported color-mix()
    // would drop the background to transparent.
    '.pm-paper{background-color:var(--bs-tertiary-bg);color:var(--bs-body-color)}',
    '@supports (background-color:color-mix(in srgb,red 6%,blue)){.pm-paper{background-color:color-mix(in srgb,var(--pm-accent) 6%,var(--bs-body-bg))}}',
    // Headings and the body under the plain look too, and DM Sans for every
    // control and label, so the account, form and desk pages read as tools.
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    '.btn,.badge,.nav-link,.dropdown-menu,.form-label,.form-control,.form-select,.form-text,.form-check-label,.input-group-text,.breadcrumb,.pagination,.list-group-item,.alert,.offcanvas-title,.software_captcha_label,.software_captcha_question,.pm-sans{font-family:var(--pm-sans)}',
    // The masthead: a double rule under it, a hairline over the sections.
    '.pm-masthead{border-bottom:3px double var(--bs-emphasis-color)}',
    '.pm-nav{border-top:1px solid var(--bs-border-color)}',
    // The menu and search buttons have no colour variant and so no focus
    // ring of their own; the theme button's icon at the size of the words.
    '.pm-masthead .btn:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    // Every other button and field: Bootstrap's focus is a half-clear
    // shadow that all but vanishes on paper and in the dark theme, so focus
    // is an ink outline everywhere (body outranks the theme's button rule).
    // Page links and the drawer's close button are not .btn, and Bootstrap's
    // own :focus rules for them set no outline.
    'body .btn:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    'body .page-link:focus-visible,body .btn-close:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    'body .form-control:focus,body .form-select:focus{outline:2px solid var(--bs-emphasis-color);outline-offset:1px}',
    'body .form-check-input:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px}',
    '.pm-masthead [data-pg-theme-switcher] .bi{font-size:1rem}',
    // The name and its line, centred between two equal side slots; a long
    // name wraps instead of running under the buttons.
    '.pm-brand{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;align-items:center}',
    '.pm-wordmark{font-family:var(--pg-font-heading);font-weight:400;font-size:clamp(2.25rem,1.4rem + 3.6vw,4.5rem);line-height:1;letter-spacing:-.02em;overflow-wrap:anywhere}',
    '.pm-wordmark-sm{font-size:2rem}',
    '.pm-tagline{margin-top:.5rem;font-family:var(--pm-text);font-style:italic;font-size:1rem;color:var(--bs-secondary-color)}',
    '.pm-slot{flex:0 0 2.75rem;display:flex;justify-content:center}',
    // The sections: quiet capitals, the current one underlined.
    '.pm-navlink{display:inline-flex;align-items:center;min-height:2.75rem;padding:0 .875rem;font-weight:500;font-size:.8125rem;letter-spacing:.12em;text-transform:uppercase;color:var(--bs-emphasis-color);text-decoration:none}',
    '.pm-navlink:hover,.pm-navlink.active{color:var(--bs-emphasis-color);text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.6em}',
    // Bootstrap's nav-link focus is a faint blue shadow and dims the words:
    // the words keep their ink and an ink ring sits inside the link.
    '.pm-navlink:focus{color:var(--bs-emphasis-color)}',
    '.pm-navlink:focus-visible{box-shadow:none;outline:2px solid currentColor;outline-offset:-4px}',
    // The staff bar and the desk's jump list are underlined navs.
    '.nav-underline .nav-link:focus-visible{box-shadow:none;outline:2px solid var(--bs-emphasis-color);outline-offset:2px}',
    // The menu drawer's rows: large serif words, 44px targets.
    '.pm-drawer{display:flex;align-items:center;min-height:44px;border-bottom:1px solid var(--bs-border-color);font-family:var(--pg-font-heading);font-size:1.375rem;color:var(--bs-emphasis-color);text-decoration:none}',
    '.pm-drawer.active,.pm-drawer:hover{text-decoration:underline;text-underline-offset:.2em}',
    // The words over a title: raised over the card's stretched link; in ink
    // on paper and on the tinted surface, where the accent is too faint.
    '.pm-kicker{position:relative;z-index:2;font-family:var(--pm-sans);font-weight:700;font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;color:var(--pm-accent)}',
    '.pm-paper .pm-kicker,.bg-body-tertiary .pm-kicker{color:var(--bs-emphasis-color)}',
    // A band's head: a label under an ink hairline.
    '.pm-label{margin:0;font-family:var(--pm-sans);font-weight:700;font-size:.8125rem;letter-spacing:.16em;text-transform:uppercase;color:var(--bs-emphasis-color)}',
    '.pm-sec-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:.5rem 1rem;justify-content:space-between;margin-bottom:2rem;padding-top:.75rem;border-top:1px solid var(--bs-emphasis-color)}',
    // Bylines, dates, reading times and captions. Their links take the
    // words' colour; a button keeps its own (the masthead's top row is set
    // in these small letters too).
    '.pm-meta{font-family:var(--pm-sans);font-size:.8125rem;letter-spacing:.02em;color:var(--bs-secondary-color)}',
    '.pm-meta a:not(.btn){color:inherit}',
    // The large titles: the cover, the story and the archives.
    '.pm-display{font-family:var(--pg-font-heading);font-weight:400;font-size:clamp(2.25rem,1.3rem + 3.4vw,4.25rem);line-height:1.04;letter-spacing:-.02em;text-wrap:balance}',
    // Card titles: underlined on hover and on focus, as the whole card is
    // the link.
    '.pm-title{font-family:var(--pg-font-heading);font-weight:600;line-height:1.18;letter-spacing:-.005em;text-wrap:balance}',
    '.pm-title a,.pm-display a{color:inherit;text-decoration:none}',
    '.pm-card:hover .pm-title a,.pm-card:hover .pm-display a,.pm-title a:focus-visible,.pm-display a:focus-visible{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.15em}',
    // The standfirst under a title, in true italics.
    '.pm-dek{font-family:var(--pm-text);font-style:italic;font-size:1.125rem;line-height:1.5;color:var(--bs-secondary-color)}',
    '.pm-dek.lead{font-size:clamp(1.1875rem,1.05rem + .6vw,1.4375rem)}',
    '.pm-standfirst{max-width:40rem;margin-inline:auto}',
    // Picture frames, which grow a little under the pointer, and the two
    // ratios Bootstrap has no class for.
    '.pm-media{overflow:hidden;background-color:var(--bs-secondary-bg)}',
    '.pm-media img{transition:transform .8s ease}',
    '.pm-card:hover .pm-media img{transform:scale(1.02)}',
    '.pm-ratio-4x5{--bs-aspect-ratio:125%}',
    '.pm-ratio-3x2{--bs-aspect-ratio:66.6667%}',
    // The story's photograph across the page: 4:3 on a phone, 16:9 on a
    // tablet, 21:9 on a wide screen, never taller than the window.
    '.pm-hero-img{display:block;width:100%;height:auto;aspect-ratio:4 / 3;object-fit:cover;max-height:85vh}',
    '@media (min-width:768px){.pm-hero-img{aspect-ratio:16 / 9}}',
    '@media (min-width:1200px){.pm-hero-img{aspect-ratio:21 / 9}}',
    // The story text. Contained first: what an author writes stays inside
    // its box (no link or layer laid over the page), and a wide table
    // scrolls. Then the reading measure and type.
    '.pm-body{position:relative;contain:paint;overflow-wrap:anywhere;max-width:38rem;margin-inline:auto;font-family:var(--pm-text);font-size:clamp(1.0625rem,1rem + .35vw,1.1875rem);line-height:1.75;hyphens:auto}',
    '.pm-body p,.pm-body ul,.pm-body ol{margin:0 0 1.35em}',
    '.pm-body h2{margin:2.25em 0 .75em;font-family:var(--pg-font-heading);font-weight:600;font-size:1.5rem;line-height:1.25}',
    '.pm-body h3{margin:2em 0 .5em;font-family:var(--pg-font-heading);font-weight:600;font-size:1.25rem}',
    '.pm-body a{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.18em}',
    '.pm-body blockquote{margin:2rem 0;padding-left:1.25rem;border-left:2px solid var(--pm-accent);font-style:italic}',
    '.pm-body img{max-width:100%;height:auto}',
    '.pm-body figure{margin:2rem 0}',
    '.pm-body figcaption{font-family:var(--pm-sans);font-size:.8125rem;color:var(--bs-secondary-color)}',
    '.pm-body table{display:block;max-width:100%;overflow-x:auto}',
    '.pm-body hr{width:4rem;margin:2.5rem auto}',
    // The drop cap on the story's first paragraph, even when it starts with
    // a subhead or a figure, and a small square after its last paragraph,
    // even when a list or a quotation follows. Neither inside a quotation,
    // a list item, a table cell, a definition or a div: each reset repeats
    // the pseudo-class, so it outranks the main rule in any order. The
    // square has empty alternative text; a browser without the "/" syntax
    // keeps the first declaration.
    '.pm-dropcap p:first-of-type::first-letter{float:left;margin:.07em .1em 0 0;font-family:var(--pg-font-heading);font-weight:400;font-size:4.4em;line-height:.82;color:var(--pm-accent)}',
    '.pm-dropcap blockquote p:first-of-type::first-letter,.pm-dropcap li p:first-of-type::first-letter,.pm-dropcap td p:first-of-type::first-letter,.pm-dropcap th p:first-of-type::first-letter,.pm-dropcap dd p:first-of-type::first-letter,.pm-dropcap div p:first-of-type::first-letter{float:none;margin:0;font:inherit;line-height:inherit;color:inherit}',
    '.pm-endmark p:last-of-type::after{content:"\25A0";content:"\25A0" / "";display:inline-block;margin-inline-start:.45em;font-size:.65em;vertical-align:.12em;color:var(--pm-accent)}',
    '.pm-endmark blockquote p:last-of-type::after,.pm-endmark li p:last-of-type::after,.pm-endmark td p:last-of-type::after,.pm-endmark th p:last-of-type::after,.pm-endmark dd p:last-of-type::after,.pm-endmark div p:last-of-type::after{content:none}',
    // The pull quote: a column beside the text from xl, a line above it
    // below xl.
    '.pm-quote{margin:0;padding:1.25rem 0;border-top:2px solid var(--pm-accent);border-bottom:1px solid var(--bs-border-color);font-family:var(--pm-text);font-style:italic;font-size:clamp(1.375rem,1.1rem + 1vw,1.75rem);line-height:1.35;color:var(--bs-emphasis-color);text-wrap:balance}',
    // The home page's quote, and the single-story bands that leave the page
    // while their list is empty.
    '.pm-bigquote{font-family:var(--pm-text);font-style:italic;font-size:clamp(1.625rem,1.2rem + 1.8vw,2.5rem);line-height:1.3;color:var(--bs-emphasis-color);text-wrap:balance}',
    '.pm-quote-band:has(.pg-sw-empty),.pm-letter-band:has(.pg-sw-empty){display:none}',
    // Links on paper: ink and underlined, which reads on any tint; card
    // titles keep their own rule.
    '.pm-paper a:not(.btn):not(.stretched-link){color:var(--bs-emphasis-color);text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.18em}',
    // A band's calm vertical rhythm (Bootstrap's largest spacer is 3rem).
    '.pm-band{padding-block:clamp(3rem,2rem + 4vw,6rem)}',
    // A section's river: the newest story as a wide feature, then two
    // columns. The first article, not the first child: an editor's edit
    // button may bring its own style element in first.
    '.pm-river{display:grid;gap:3rem 2rem}',
    '@media (min-width:768px){.pm-river{grid-template-columns:1fr 1fr}.pm-river article:first-of-type{grid-column:1 / -1;display:grid;grid-template-columns:7fr 5fr;gap:2rem;align-items:center}.pm-river article:first-of-type .pm-title{font-weight:400;font-size:clamp(1.75rem,1.2rem + 1.4vw,2.5rem)}}',
    // Numbers counted by the list (every row of a list is drawn alike):
    // Most read and an issue's contents.
    '.pm-rank{counter-reset:pm-rank}',
    '.pm-rank-item{counter-increment:pm-rank}',
    '.pm-rank-num::before{content:counter(pm-rank)}',
    '.pm-rank-num{flex:0 0 2.5rem;font-family:var(--pg-font-heading);font-weight:400;font-size:2rem;line-height:1;color:var(--pm-accent)}',
    // A rule between the rows, none above the first: the list's heading
    // sits right over it.
    '.pm-rank-item{border-top:var(--bs-border-width) solid var(--bs-border-color)}',
    '.pm-rank-item:first-of-type{border-top:0}',
    // A card's standfirst, three lines at most.
    '.pm-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}',
    // The editors' picks shelf scrolls with the keyboard: its focus shows,
    // inside the box, since the row's negative margins reach the screen's
    // edges on a phone; the card gutter keeps it off the pictures.
    '.pm-shelf:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:-2px}',
    // A browser scrolls a focused link into the shelf only when it is out
    // of sight, so a title half in view would keep half its focus outside.
    // While a link has keyboard focus the shelf's edges count as out of
    // sight; at rest the cards snap to the edge as before.
    '.pm-shelf:has(a:focus-visible){scroll-padding-inline:25%}',
    // The correction under a story.
    '.pm-correction{padding-left:1rem;border-left:3px solid var(--bs-warning)}',
    // A flex or grid item that may shrink below its words (Bootstrap has no
    // min-width utility).
    '.pm-min-0{min-width:0}',
    '@media (prefers-reduced-motion:reduce){.pm-media img{transition:none}.pm-card:hover .pm-media img{transform:none}}',
    // The reading measure for what follows the story text, so the edges
    // line up with the text where its column is wider than the measure.
    '.pm-measure{max-width:38rem;margin-inline:auto}',
    // The platform's notices there (the letters' sign-in line) print their
    // links without .alert-link, so they would keep the page's link colour
    // on the alert's tint.
    '.pm-measure .alert a:not(.btn){color:var(--bs-alert-link-color)}',
    // A single-story list shows one row in the installer's preview, which
    // repeats every list three times; a live list has one row anyway.
    '.pm-one article:not(:first-of-type){display:none}',
    // An issue's name as a link inside a card whose title is a stretched
    // link: raised over it, like the kicker.
    '.pm-issue-link{position:relative;z-index:2;color:inherit;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:.2em}',
    // A list's page links come after its rows inside the rows' parent: they
    // span the river and the issues' row.
    '.pm-river .pg-sw-pagination{grid-column:1 / -1}',
    '.pm-issue-grid .pg-sw-pagination{flex:0 0 100%;width:100%;max-width:100%;margin-top:2rem}',
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
// box, 'office_use_only' => true a field only the editors fill).
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
// visitor chose. $selected is the answer chosen to begin with: there is no
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
// A hint under a field, named by the field's aria-describedby. Never under
// an office-use-only field: the hint would stay on the page for an author
// after the field itself is taken out.
$help = function ($id, $text) use ($el) {
    return $el('div', 'form-text', lang('Help Text'), array(), array('id' => $id, 'text' => $text));
};
// An office-use-only field: the editors fill it in, an author never sees
// it, and a new record stores its selected option or nothing.
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
// A box the visitor must tick: what was agreed to is stored with the form.
$agree = function ($id, $name, $text) use ($el, $attr) {
    return $el('div', 'form-check small mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    ));
};
// The newsletter's fields in the order they are filled in: the e-mail
// address, the consent, the CAPTCHA, then the button, so Subscribe is the
// last step. The address is the contact's, so the sign-up becomes a contact
// of the newsletter's group.
$newsletter_fields = function ($id, $button_class = 'btn btn-primary px-4') use ($el, $attr, $submit, $agree, $captcha) {
    return array(
        $el('div', 'mb-3', lang('Email'), array(
            $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id . '-email')))),
            $el('input', 'form-control', lang('Field'), array(), array('id' => $id . '-email', '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
                $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
                $attr('placeholder', lang('Your e-mail address')),
            ))),
        )),
        $agree($id . '-consent', 'consent', lang('I would like to receive The Weekend Read by e-mail. I can unsubscribe at any time.')),
        $captcha(),
        $submit(lang('Subscribe'), $button_class),
    );
};
// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*', $required = false) use ($el, $attr) {
    $attrs = array($attr('type', 'file'), $attr('name', $name), $attr('accept', $accept));
    if ($required) $attrs[] = $attr('required', '');
    return $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs,
        '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};
// The name, e-mail and phone of whoever sends a form; the contact's own
// fields, so the site knows them next time.
$person_fields = function ($prefix, $last_required = true, $phone_required = true) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', $last_required, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', $phone_required, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
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
// A group of fields under its legend.
$fieldset = function ($legend, $children) use ($el) {
    return $el('fieldset', 'mb-4', $legend, array_merge(array(
        $el('legend', 'h5 pm-title mb-3 pb-2 border-bottom', lang('Legend'), array(), array('text' => $legend)),
    ), $children));
};

// ── Records ─────────────────────────────────────────────────────────────

// Every list picture loads lazily; the cover's picture and the story's
// photograph, each its page's largest element, do not.
$lazy = array('_attrs' => array($attr('loading', 'lazy')));
// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt, $extra = array()) use ($el, $image) {
    $bindings = array('src' => $token);
    if ($alt_token !== '') $bindings['alt'] = $alt_token;
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', trim('pg-field-value d-block ' . $frame), lang('Picture Frame'), array(
            $image($sample, $alt, $class, array_merge(array('_bindings' => $bindings), $extra)),
        )),
    ));
};
// The record's Change Status button, shown to whoever may edit the
// submissions of its form; it opens the record in the control panel and
// comes back. A filled button: an outlined grey one is too faint in the
// dark theme of the plain palette.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};
// Summarize the story with AI: with the visitor's own assistant (each link
// opens it with a question about the story's address) or with the
// browser's built-in model, whose button the browser shows only where it
// can summarize and whose points come into the box below. Shown for a story
// anybody may open; left out of a printed page.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-secondary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    // An aside, so its own words stay out of the summary; named for the
    // landmark list.
    return $el('aside', 'p-3 bg-body-tertiary my-5 d-print-none', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars'),
            $span(lang('Summarize this story with AI'), 'small fw-semibold me-1 pm-sans', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the story, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), array_merge(array('_attrs' => array($attr('aria-label', lang('Summarize this story with AI')))), $show_when('has_ai_links')));
};
// The comments of a record page, on their own: the region alone, for a
// column the page lays out itself.
$comments_block = function () {
    return array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array());
};
// The comments of a record page in their own band, in the reading column;
// left out of a printed page.
$comments_section = function ($name) use ($el, $container, $row, $col, $comments_block) {
    return $el('section', 'pm-band pt-0 d-print-none', $name, array(
        $container(array(
            $row(array(
                $col(array($comments_block()), '', '8'),
            ), '', 'center'),
        )),
    ));
};
// The notice a record page shows above the record once its form is sent,
// and the account opened for a visitor who had none.
$sent_notices = function ($thanks) use ($el, $icon, $span, $para, $link, $attr, $bind, $show_when) {
    return array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
        $el('div', 'alert alert-info', lang('New Account'), array(
            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
            $el('p', 'mb-1', lang('Sign-in Email'), array(
                $span(lang('Email') . ': ', '', lang('Label')),
                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
            )),
            // The single-use set password link, for the person who sent the form
            // while it is live; the sentence below covers a used or expired link.
            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
        ), $show_when('has_new_account')),
    );
};
// A story's Status as a badge.
$status_badge = function ($sample, $class = 'badge rounded-pill bg-primary-subtle text-primary-emphasis') use ($span, $bind) {
    return $span($sample, $class, lang('Status'), $bind('status'));
};
// A record page's toolbar: back to its list, and the editors' button.
$record_toolbar = function ($back_text, $back_page, $edit_text = '') use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-body-emphasis text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_page . '}}')),
        $edit_button($edit_text),
    ));
};
// A label and its value in a record's details; $optional rows leave when
// the value is empty.
$record_detail = function ($label, $sample, $token, $optional = false, $value_class = '') use ($el, $bind) {
    return $el('div', 'row mb-1' . ($optional ? ' pg-hide-if-empty' : ''), $label, array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 mb-0 ' . ($optional ? 'pg-field-value ' : '') . $value_class), lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A box of words from the record, which leaves when they are empty.
$record_words = function ($label, $sample, $token) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary border mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2 pm-sans'),
        $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// The name of whoever sent the contact form.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// ── The cards of the story lists ────────────────────────────────────────
// Each story card is an article whose title link stretches over the whole
// card; the kicker and the issue link above the title are raised over that
// link. Nothing is written on a picture. A list picture has an empty alt
// (the title beside it names the story) and loads lazily; the cover's does
// not. No card's own element is a .row: the editor's edit button goes into
// a record's first element, and a row would pad it as a column. $s is the
// sample story a card shows in the editor and the preview ($head()).

// A dot between two details, silent to screen readers.
$dot = function () use ($span, $attr) {
    return $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
// The story's section over its title.
$card_kicker = function ($s, $class = 'mb-2') use ($para, $bind) {
    return $para($s['section'], trim('pm-kicker ' . $class), $bind('section'));
};
// The title, linking to the story over the whole card.
$card_title = function ($tag, $class, $s) use ($el, $link) {
    return $el($tag, trim('pm-title ' . $class), lang('Title'), array(
        $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
    ));
};
// "· 12 min read", gone while the editors left the reading time empty: the
// words around the value do not keep it on the page.
$reading_time = function () use ($el, $span, $bind, $dot) {
    return $el('span', 'pg-hide-if-empty text-nowrap', lang('Reading Time'), array(
        $dot(),
        $span('12', 'pg-field-value', lang('Value'), $bind('reading_time')),
        $span(lang('min read'), '', lang('Text')),
    ));
};
// The byline, how long ago the story went live ($with_time) and its
// reading time. Lists print relative times; the story page the site's date.
$card_meta = function ($s, $with_time = false, $class = 'mb-0') use ($el, $span, $bind, $bind_date, $dot, $reading_time) {
    $children = array(
        $span(lang('Written by'), 'visually-hidden', lang('Label')),
        $span($s['byline'], '', lang('Byline'), $bind('byline')),
    );
    if ($with_time) {
        $children[] = $dot();
        $children[] = $span(lang('2 hours ago'), '', lang('Time'), $bind_date('publish_date', 'relative'));
    }
    $children[] = $reading_time();
    return $el('p', trim('pm-meta ' . $class), lang('Byline and Time'), $children);
};
// The story's issue, linking to the issue's contents: a static address
// with the record's own value in it (the list fills ^^issue^^ in).
$issue_link = function ($s) use ($el, $span, $bind, $dot, $issue_href) {
    return $el('span', 'pg-hide-if-empty', lang('Issue Link'), array(
        $dot(),
        $el('a', 'pm-issue-link', lang('Link'), array(
            $span($s['issue'], 'pg-field-value', lang('Issue Name'), $bind('issue')),
        ), array('href' => $issue_href)),
    ));
};
// A picture of the story in a frame that leaves with it.
$card_picture = function ($s, $frame, $lazy_load = true) use ($optional_image, $lazy) {
    return $optional_image('cover_image', '', 'object-fit-cover', $frame, $s['cover'], '', $lazy_load ? $lazy : array());
};

// (a) The cover: the picture beside the words, never under them. The title
// is set as large as a page title, under the band's hidden heading.
$cover_card = function ($s) use ($el, $link, $span, $para, $bind, $placements, $card_picture, $issue_link, $card_meta) {
    return $el('article', 'pm-card position-relative', $placements[1], array(
        $el('div', 'row g-4 g-lg-5 align-items-center', lang('Row'), array(
            $el('div', 'col-lg-7', lang('Picture'), array(
                $card_picture($s, 'ratio ratio-4x3 pm-media', false),
            )),
            $el('div', 'col-lg-5', lang('Words'), array(
                $el('p', 'pm-meta mb-3', lang('Kicker'), array(
                    $span($placements[1], 'pm-kicker', lang('Kicker')),
                    $issue_link($s),
                )),
                $el('h3', 'pm-display mb-3', lang('Title'), array(
                    $link($s['title'], '#', 'stretched-link', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($s['summary'], 'pm-dek lead mb-4', $bind('summary')),
                $card_meta($s),
            )),
        )),
    ));
};
// (b) A portrait card of The latest.
$portrait_card = function ($s) use ($el, $para, $bind, $card_picture, $card_kicker, $card_title, $card_meta) {
    return $el('article', 'pm-card position-relative', lang('Card'), array(
        $card_picture($s, 'ratio pm-ratio-4x5 pm-media mb-3'),
        $card_kicker($s),
        $card_title('h3', 'h4 mb-2', $s),
        $para($s['summary'], 'pm-dek pm-clamp-3 mb-2', $bind('summary')),
        $card_meta($s),
    ));
};
// (c) The cover story's line, set large. The caption names the story, not
// a person: the line is the story's, and a name beside it would put the
// words in the writer's mouth.
$quote_card = function ($s, $quote) use ($el, $link, $span, $para, $bind, $icon, $dot) {
    return $el('article', 'mx-auto col-lg-9', lang('Quote'), array(
        $el('figure', 'text-center mb-0', lang('Quote'), array(
            $icon('bi-quote', 'display-4 text-body-secondary'),
            $el('blockquote', 'pm-bigquote mb-4', lang('Quote Text'), array(
                $para($quote, 'mb-0', $bind('pull_quote')),
            )),
            $el('figcaption', 'pm-meta', lang('Caption'), array(
                $span(lang('From the cover story'), 'pm-kicker', lang('Kicker')),
                $dot(),
                $link($s['title'], '#', 'link-body-emphasis', array(), array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
        )),
    ));
};
// (d) The editor's letter: the editor's portrait beside the words.
$letter_card = function ($s) use ($el, $para, $bind, $optional_image, $portrait, $contributor_name, $lazy, $placements, $card_title, $card_meta) {
    return $el('article', 'pm-card position-relative', $placements[2], array(
        $el('div', 'row align-items-center g-4 g-lg-5', lang('Row'), array(
            $el('div', 'col-lg-3 text-lg-center', lang('Portrait'), array(
                $optional_image('byline_photo', '', 'rounded-circle object-fit-cover pg-circle-160', '', $portrait($contributor_name('Emily Turner'), 320), '', $lazy),
            )),
            $el('div', 'col-lg-9', lang('Words'), array(
                $para(lang('From the editor'), 'pm-kicker mb-2'),
                $card_title('h3', 'h2 mb-3', $s),
                $para($s['summary'], 'pm-dek lead mb-3', $bind('summary')),
                $card_meta($s),
            )),
        )),
    ));
};
// (e) A landscape card: the editors' picks and More from the magazine.
$landscape_card = function ($s) use ($el, $card_picture, $card_kicker, $card_title, $card_meta) {
    return $el('article', 'pm-card position-relative', lang('Card'), array(
        $card_picture($s, 'ratio pm-ratio-3x2 pm-media mb-3'),
        $card_kicker($s),
        $card_title('h3', 'h5 mb-2', $s),
        $card_meta($s),
    ));
};
// (f) A story of a river (a section's page, a contributor's stories): the
// template's rules draw the first one wide. $tag is the title's level (h2
// under a page's h1, h3 under an archive's h2); $kicker the field over it
// (the issue on a section's page, where every story is of that section;
// the section elsewhere). The kicker is plain text: the whole card is the
// story's link.
$river_card = function ($tag, $kicker, $s) use ($el, $span, $para, $bind, $card_picture, $card_title, $card_meta) {
    return $el('article', 'pm-card position-relative', lang('Card'), array(
        $el('div', 'pm-min-0', lang('Picture'), array(
            $card_picture($s, 'ratio pm-ratio-3x2 pm-media'),
        )),
        $el('div', 'pm-min-0 pt-3', lang('Words'), array(
            $el('p', 'pg-hide-if-empty mb-2', lang('Kicker'), array(
                $span($kicker === 'issue' ? $s['issue'] : $s['section'], 'pm-kicker pg-field-value', lang('Kicker'), $bind($kicker)),
            )),
            $card_title($tag, 'h3 mb-2', $s),
            $para($s['summary'], 'pm-dek mb-2', $bind('summary')),
            $card_meta($s, true),
        )),
    ));
};
// (g) A row of Every story: a small portrait picture beside the words; the
// rule under each row.
$index_row = function ($s) use ($el, $para, $bind, $card_picture, $card_kicker, $card_title, $card_meta) {
    return $el('article', 'pm-card position-relative py-4 border-bottom', lang('Card'), array(
        $el('div', 'row g-3 g-md-4', lang('Row'), array(
            $el('div', 'col-4 col-md-3', lang('Picture'), array(
                $card_picture($s, 'ratio pm-ratio-4x5 pm-media'),
            )),
            $el('div', 'col-8 col-md-9 pm-min-0', lang('Words'), array(
                $card_kicker($s, 'mb-1'),
                $card_title('h2', 'h4 mb-2', $s),
                $para($s['summary'], 'pm-dek d-none d-md-block mb-2', $bind('summary')),
                $card_meta($s, true),
            )),
        )),
    ));
};
// (h) An issue: a live Editor's letter of an issue, drawn as the issue. Its
// picture stands for the issue, its title link opens the issue's contents
// (the static address with the record's issue in it), and on Every issue
// its standfirst reads as the issue's line.
$issue_card = function ($with_dek) use ($el, $link, $para, $bind, $bind_date, $head, $photo, $card_picture, $issue_href) {
    $s = $head(0);
    $s['cover'] = $photo('letter-small-places', 800, 1000);
    $children = array(
        $card_picture($s, 'ratio pm-ratio-4x5 pm-media mb-3'),
        $el('h3', 'pm-title h4 mb-1', lang('Title'), array(
            $link($s['issue'], $issue_href, 'stretched-link', array(), $bind('issue')),
        )),
        $para(lang('Date'), 'pm-meta mb-2', $bind_date('publish_date', 'relative')),
    );
    if ($with_dek) $children[] = $para($s['summary'], 'pm-dek pm-clamp-3 mb-0', $bind('summary'));
    return $el('article', 'pm-card position-relative', lang('Issue Card'), $children);
};
// (i) A story of an issue's contents, numbered by the list.
$contents_row = function ($s) use ($el, $para, $bind, $attr, $card_kicker, $card_title, $card_meta) {
    return $el('li', 'pm-rank-item pm-card d-flex gap-3 py-4 position-relative', lang('Card'), array(
        $el('span', 'pm-rank-num', lang('Rank Number'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
        $el('div', 'pm-min-0', lang('Words'), array(
            $card_kicker($s, 'mb-1'),
            $card_title('h3', 'h4 mb-1', $s),
            $para($s['summary'], 'pm-dek mb-2', $bind('summary')),
            $card_meta($s),
        )),
    ));
};

// ── The desk's rows ─────────────────────────────────────────────────────

// A story in a desk list: its title and Status on top, then its section,
// the byline ($with_writer: the editors' lists), its issue, when it was
// sent, its publication time once an editor set one, when anything last
// happened to it (a message, a change) and the messages under it. Each row
// shows the last activity, so a story that has gone quiet stands out. The
// row opens the list's detail page: the submission page on the desks, the
// public story in "Live now".
$desk_row = function ($with_writer) use ($el, $span, $icon, $bind, $bind_date, $status_badge, $head, $statuses) {
    $s = $head(3);
    $detail = function ($icon_name, $name, $children, $class = 'd-inline-flex align-items-center gap-1') use ($el, $icon) {
        return $el('span', $class, $name, array_merge(array($icon($icon_name)), $children));
    };
    $details = array($detail('bi-bookmark', lang('Section'), array($span($s['section'], '', lang('Section'), $bind('section')))));
    if ($with_writer) {
        $details[] = $detail('bi-person', lang('Byline'), array($span($s['byline'], '', lang('Byline'), $bind('byline'))));
    }
    $details[] = $detail('bi-journal-text', lang('Part of the issue'), array(
        $span($s['issue'], 'pg-field-value', lang('Value'), $bind('issue')),
    ), 'pg-hide-if-empty d-inline-flex align-items-center gap-1');
    $details[] = $detail('bi-send', lang('Sent'), array(
        $span(lang('Sent:'), '', lang('Label')),
        $span(lang('2 hours ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
    ));
    $details[] = $detail('bi-calendar-check', lang('Goes live:'), array(
        $span(lang('Goes live:'), '', lang('Label')),
        $span(lang('Date'), 'pg-field-value', lang('Value'), $bind('publish_date')),
    ), 'pg-hide-if-empty d-inline-flex align-items-center gap-1');
    $details[] = $detail('bi-clock-history', lang('Last activity'), array(
        $span(lang('Last activity:'), '', lang('Label')),
        $span(lang('2 hours ago'), '', lang('Time'), $bind_date('newest_activity_date_and_time', 'relative')),
    ));
    $details[] = $detail('bi-chat-left-text', lang('Messages'), array(
        $span('2', 'fw-semibold', lang('Number of Comments'), $bind('number_of_comments')),
        $span(lang('messages'), '', lang('Label')),
    ));
    return $el('a', 'list-group-item list-group-item-action d-flex flex-column gap-1 py-3', lang('Story Row'), array(
        $el('div', 'd-flex align-items-start gap-2', lang('Top Line'), array(
            $span($s['title'], 'fw-semibold', lang('Title'), $bind('title')),
            $el('span', 'ms-auto flex-shrink-0', lang('Status'), array($status_badge($statuses[0]))),
        )),
        $el('div', 'small text-body-secondary d-flex flex-wrap column-gap-3 row-gap-1', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// ── The parts every page repeats ────────────────────────────────────────

// The masthead: the skip link; a quiet row of quick links, the theme, the
// language, the sign-in and Subscribe; the site's name centred between the
// menu button (phone) and the search; the sections from lg; and the menu
// drawer. Not sticky, so the drawer can live inside it. Left out of a
// printed page.
$masthead = function () use ($el, $link, $icon, $span, $attr, $lang_switcher, $widget, $sections) {
    $quick = function ($text, $href) use ($link) {
        return $link($text, $href, 'd-inline-flex align-items-center py-1 link-body-emphasis text-decoration-none');
    };
    $nav_items = array();
    foreach ($sections as $key => $s) {
        $nav_items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($s[0], '{{page:' . $key . '}}', 'nav-link pm-navlink')));
    }
    $nav_items[] = $el('li', 'nav-item', lang('Nav Item'), array($link(lang('Every story'), '{{page:blog}}', 'nav-link pm-navlink')));

    $drawer = array($el('li', '', lang('Nav Item'), array($link(lang('Homepage'), '{{page:home}}', 'pm-drawer'))));
    foreach ($sections as $key => $s) {
        $drawer[] = $el('li', '', lang('Nav Item'), array($link($s[0], '{{page:' . $key . '}}', 'pm-drawer')));
    }
    foreach (array(
        array(lang('Every story'), '{{page:blog}}'),
        array(lang('Every issue'), '{{page:issues}}'),
        array(lang('Contributors'), '{{page:author}}'),
    ) as $m) {
        $drawer[] = $el('li', '', lang('Nav Item'), array($link($m[0], $m[1], 'pm-drawer')));
    }
    $drawer_more = array();
    foreach (array(
        array(lang('About the magazine'), '{{page:about}}'),
        array(lang('Write for us'), '{{page:write_for_us}}'),
        array(lang('Newsletter'), '{{page:newsletter}}'),
        array(lang('Contact us'), '{{page:contact}}'),
        array(lang('My Account'), '{{page:my_account}}'),
    ) as $m) {
        $drawer_more[] = $el('li', '', lang('Nav Item'), array($link($m[0], $m[1], 'pm-drawer')));
    }

    return $el('header', 'pm-masthead bg-body d-print-none', lang('Magazine Masthead'), array(
        $link(lang('Skip to main content'), '#main', 'visually-hidden-focusable position-absolute p-2 bg-body text-body'),
        // The quiet row on top.
        $el('div', 'border-bottom', lang('Top Bar'), array(
            $el('div', 'container d-flex align-items-center gap-3 py-2 pm-meta', lang('Top Bar Container'), array(
                $el('nav', 'd-none d-md-flex gap-3', lang('Quick Links'), array(
                    $quick(lang('Every issue'), '{{page:issues}}'),
                    $quick(lang('Contributors'), '{{page:author}}'),
                    $quick(lang('Newsletter'), '{{page:newsletter}}'),
                ), array('_attrs' => array($attr('aria-label', lang('Quick Links'))))),
                $el('div', 'ms-auto d-flex align-items-center gap-2', lang('Account Links'), array(
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
                    $link(lang('Subscribe'), '{{page:newsletter}}', 'btn btn-primary btn-sm d-none d-sm-inline-block'),
                )),
            )),
        )),
        // The name, centred: the side slots are equally wide, so the name
        // stays in the middle at every width and a long one wraps.
        $el('div', 'container', lang('Brand Row'), array(
            $el('div', 'd-flex align-items-center py-3 py-lg-4', lang('Brand Row Container'), array(
                $el('div', 'pm-slot', lang('Menu Slot'), array(
                    $el('button', 'btn p-2 d-lg-none', lang('Menu Button'), array($icon('bi-list', 'fs-3')), array('_attrs' => array(
                        $attr('type', 'button'),
                        $attr('data-bs-toggle', 'offcanvas'),
                        $attr('data-bs-target', '#pm-menu'),
                        $attr('aria-controls', 'pm-menu'),
                        $attr('aria-label', lang('Open the menu')),
                    ))),
                )),
                $el('a', 'pm-brand text-center text-decoration-none link-body-emphasis', lang('Brand'), array(
                    $span('{{site_name}}', 'pm-wordmark', lang('Name')),
                    $span(lang('Stories that take their time.'), 'pm-tagline d-none d-sm-block', lang('Tagline')),
                ), array('href' => '{{page:home}}')),
                $el('div', 'pm-slot', lang('Search Slot'), array(
                    $el('a', 'btn p-2', lang('Search Link'), array($icon('bi-search', 'fs-4')), array(
                        'href' => '{{page:search}}', '_attrs' => array($attr('aria-label', lang('Search'))))),
                )),
            )),
        )),
        // The sections from lg; the current one is marked on the server.
        $el('nav', 'pm-nav d-none d-lg-block', lang('Sections Menu'), array(
            $el('div', 'container', lang('Container'), array(
                $el('ul', 'nav justify-content-center', lang('Nav Menu'), $nav_items, array('smartActive' => true)),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Sections'))))),
        // The menu drawer the phone's button opens.
        $el('div', 'offcanvas offcanvas-start', lang('Menu Drawer'), array(
            $el('div', 'offcanvas-header', lang('Drawer Header'), array(
                // Not a heading: the drawer comes before the page's h1, and
                // the page's outline should start there. The dialog takes its
                // name from it all the same (aria-labelledby).
                $el('p', 'h5 offcanvas-title pm-title mb-0', lang('Drawer Title'), array(), array('id' => 'pm-menu-title', 'text' => '{{site_name}}')),
                $el('button', 'btn-close', lang('Close Button'), array(), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-dismiss', 'offcanvas'),
                    $attr('aria-label', lang('Close')),
                ))),
            )),
            $el('div', 'offcanvas-body', lang('Drawer Body'), array(
                $el('form', '', lang('Search Form'), array(
                    $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the magazine'), '_attrs' => array($attr('for', 'pm-q')))),
                    $el('div', 'input-group', lang('Search Box'), array(
                        $el('input', 'form-control', lang('Field'), array(), array('id' => 'pm-q', '_attrs' => array(
                            $attr('type', 'search'),
                            $attr('name', 'query'),
                            $attr('placeholder', lang('Search the magazine…')),
                        ))),
                        // A filled button in the second colour: its words read
                        // in both themes of every palette.
                        $el('button', 'btn btn-secondary', lang('Search Button'), array(), array('text' => lang('Search'), '_attrs' => array($attr('type', 'submit')))),
                    )),
                ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search')))),
                $el('ul', 'list-unstyled mt-4 mb-0', lang('Drawer Sections'), $drawer, array('smartActive' => true)),
                $el('p', 'pm-label mt-4 mb-1', lang('Heading'), array(), array('text' => lang('The magazine'))),
                $el('ul', 'list-unstyled mb-0', lang('Drawer Links'), $drawer_more, array('smartActive' => true)),
            )),
        ), array('id' => 'pm-menu', '_attrs' => array(
            $attr('tabindex', '-1'),
            $attr('aria-labelledby', 'pm-menu-title'),
        ))),
    ));
};

// The footer, on the tinted surface: the magazine in a sentence and its
// feed, the sections, the magazine's pages, the reader's own pages and the
// legal pages; under them the copyright. No social links: a link that looks
// real but goes nowhere is worse than none.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr, $sections) {
    $plain = 'link-body-emphasis text-decoration-none';
    $column = function ($title, $class, $links) use ($el, $heading) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('div', $class, $title, array(
            $heading('h2', $title, 'pm-label mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Links'), $items),
        ));
    };
    $section_links = array();
    foreach ($sections as $key => $s) $section_links[] = $link($s[0], '{{page:' . $key . '}}', $plain);

    return $el('footer', 'mt-auto bg-body-tertiary border-top d-print-none', lang('Magazine Footer'), array(
        $el('div', 'container pm-band', lang('Footer Container'), array(
            $el('div', 'row g-4 g-lg-5', lang('Footer Columns'), array(
                $el('div', 'col-lg-4', lang('About the Magazine'), array(
                    $link('{{site_name}}', '{{page:home}}', 'pm-wordmark pm-wordmark-sm link-body-emphasis text-decoration-none'),
                    $para(lang('Stories that take their time.'), 'pm-tagline mb-3'),
                    $para(lang('An independent magazine of long reads, essays and photography.'), 'small mb-3'),
                    $el('a', 'd-inline-flex gap-2 align-items-center small link-body-emphasis', lang('RSS Feed'), array(
                        $icon('bi-rss'),
                        $span(lang('RSS Feed'), '', lang('Link Text')),
                    ), array('href' => '{{page:blog}}?rss=true')),
                )),
                $column(lang('Sections'), 'col-6 col-md-3 col-lg-2', $section_links),
                $column(lang('The magazine'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('About the magazine'), '{{page:about}}', $plain),
                    $link(lang('Contributors'), '{{page:author}}', $plain),
                    $link(lang('Every issue'), '{{page:issues}}', $plain),
                    $link(lang('Write for us'), '{{page:write_for_us}}', $plain),
                    $link(lang('Contact us'), '{{page:contact}}', $plain),
                )),
                $column(lang('Your account'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                    $link(lang('My Stories'), '{{page:my_articles}}', $plain),
                    $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    $link(lang('Search'), '{{page:search}}', $plain),
                )),
                $column(lang('Legal'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    $link(lang('Terms of Use'), '{{page:terms}}', $plain),
                    $link(lang('Accessibility'), '{{page:accessibility}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between gap-2 border-top mt-5 pt-3 pm-meta', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'mb-0'),
                $para(lang('All rights reserved.'), 'mb-0'),
            )),
        )),
    ));
};

// The editors' own menu, under the masthead on the staff pages. Each link
// names a page of its own: the smart active state ignores the part after #,
// so two links to one page would both be marked current.
$staff_bar = function () use ($el, $link, $span, $attr) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small bg-body d-print-none', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center column-gap-4 row-gap-2 py-2', lang('Staff Bar Container'), array(
            $el('nav', 'nav nav-underline align-items-center column-gap-4 row-gap-1', lang('Staff Links'), array(
                $span(lang('Editors'), 'badge text-bg-primary', lang('Staff Badge')),
                $item('staff', lang('Editors\' desk')),
                $item('blog_new', lang('Write a story')),
                $item('inbox', lang('Inbox')),
                $item('staff_directory', lang('Staff Directory')),
                $item('staff_entry', lang('Add to the Staff Directory')),
            ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Editors'))))),
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
// A page of the editors: the staff bar under the masthead.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('masthead'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main, array('id' => 'main')),
        $shared('footer'),
    ));
};

// The top of the inner pages: the title, centred, its standfirst and
// whatever the page adds under them.
$page_header = function ($title, $lead = '', $more = array()) use ($el, $heading, $para, $container, $row, $col) {
    $children = array($heading('h1', $title, 'pm-display mb-3'));
    if ($lead !== '') $children[] = $para($lead, 'pm-dek lead pm-standfirst mb-0');
    return $el('header', 'pm-band pb-4', lang('Page Header'), array(
        $container(array(
            $row(array(
                $col(array_merge($children, $more), '', '9', '', '12', 'text-center'),
            ), '', 'center'),
        )),
    ));
};

// The head of a band: its label under an ink hairline, and a link to the
// rest on the right.
$sec_head = function ($title, $more_text = '', $more_href = '') use ($el, $heading, $icon, $span) {
    $children = array($heading('h2', $title, 'pm-label'));
    if ($more_text !== '') {
        $children[] = $el('a', 'pm-meta link-body-emphasis d-inline-flex align-items-center gap-1', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'pm-sec-head', lang('Section Header'), $children);
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
// Bootstrap backgrounds dark under the written dark text when an editor
// opens the page in a browser (a sent e-mail has no theme).
$mail_page = function ($children) use ($el, $shared, $mail_style, $attr) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dcd6c8;overflow:hidden')),
        ), array('_attrs' => array(
            $attr('data-bs-theme', 'light'),
            $attr('style', 'background:#f5f1e8;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2421'),
        ))),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 mb-3', array('_attrs' => array($attr('style', "font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:400;line-height:1.25;margin:0 0 16px;color:#1f2421"))));
};
$mail_para = function ($text, $class = 'mb-3', $style = 'margin:0 0 16px') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', $style))));
};
// The small print at the end of an e-mail.
$mail_small = function ($text) use ($mail_para) {
    return $mail_para($text, 'small text-body-secondary mb-0', 'font-size:13px;color:#5c5a55;margin:0');
};
$mail_button = function ($text, $href, $extra = array()) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;font-weight:600;text-decoration:none;text-transform:none;'
            . 'background-color:#4a6b53;background-color:var(--bs-primary,#4a6b53);color:#ffffff;color:var(--pg-on-primary,#ffffff)')), $extra),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#5c5a55')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary border p-3 my-3', $name, $children,
        $mail_style('background:#f5f1e8;border:1px solid #dcd6c8;padding:16px;margin:16px 0'));
};
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#5c5a55;margin:24px 0 0'));
};
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$mail_account_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};
$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// A band of a page: a section around a container, with the calm padding.
$band = function ($name, $children, $class = 'pm-band', $extra = array()) use ($el, $container) {
    return $el('section', $class, $name, array($container($children)), $extra);
};
// A list with a check before each line.
$check_list = function ($items) use ($el, $icon, $span) {
    $children = array();
    foreach ($items as $text) {
        $children[] = $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array(
            $icon('bi-check2', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Check List'), $children);
};
// A box of the side column: a title and what goes under it.
$side_card = function ($name, $title, $children, $class = 'p-4 border bg-body mb-4', $extra = array()) use ($el, $heading) {
    return $el('div', $class, $name, array_merge(array($heading('h2', $title, 'h5 pm-title mb-3')), $children), $extra);
};
// A part of a static page: an anchor, a title and its paragraphs, then
// whatever else it holds.
$prose = function ($id, $name, $title, $paras, $more = array()) use ($el, $heading, $para) {
    $children = array($heading('h2', $title, 'h3 pm-title mb-3'));
    foreach ($paras as $text) $children[] = $para($text, 'mb-3');
    return $el('section', 'pb-4 mb-4 border-bottom', $name, array_merge($children, $more), array('id' => $id));
};
// A sample photo at the top of a static page's side column, in the card
// frame. It is a random picture, so it is decorative (no alt) until the
// magazine puts its own in.
$side_photo = function ($seed) use ($el, $image, $photo, $lazy) {
    return $el('figure', 'mb-4', lang('Picture'), array(
        $el('div', 'ratio ratio-16x9 pm-media', lang('Picture Frame'), array(
            $image($photo($seed, 1200, 900), '', 'object-fit-cover', $lazy),
        )),
    ));
};
// The note under the legal pages' sample text.
$sample_note = function () use ($el, $icon, $span, $attr) {
    return $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
        $icon('bi-info-circle'),
        $span(lang('Sample text: adapt it to your magazine and to the law that applies to you before you publish.'), '', lang('Text')),
    ), array('_attrs' => array($attr('role', 'note'))));
};
// A static page: its header, then its parts in the reading column and a
// side column beside them.
$static_page = function ($title, $lead, $parts, $side = array()) use ($page, $page_header, $band, $row, $col) {
    return $page(array(
        $page_header($title, $lead),
        $band(lang('Content'), array(
            $row(array(
                $col($parts, '', '8'),
                $col($side, '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    ));
};

// The letters under a story: signed in, held until an editor publishes
// them. "Letter" is a singular noun: the platform puts it in plural and
// into sentences ("2 Letters", "Notify me when a Letter is added.").
$letter_comments = array(
    'label'         => lang('Letter'),
    'allow_new'     => true,
    'auto_publish'  => false,
    'show_date'     => true,
    'login'         => true,
    'email_page'    => '{{tab:email_reply}}',
    'email_subject' => lang('There is a new letter on a story you follow'),
    'notify_email'  => '{{site_email}}',
);
// The messages between an author and the editors under a sent story, and
// the answers in a conversation: published at once, each one e-mailed.
$desk_comments = array(
    'label'         => lang('Message'),
    'allow_new'     => true,
    'auto_publish'  => true,
    'show_date'     => true,
    'login'         => true,
    'email_page'    => '{{tab:email_desk_message}}',
    'email_subject' => lang('New message about a story sent to the magazine'),
    'notify_email'  => '{{site_email}}',
);
$conversation_comments = array(
    'label'         => lang('Message'),
    'allow_new'     => true,
    'auto_publish'  => true,
    'show_date'     => true,
    'login'         => true,
    'email_page'    => '{{tab:email_reply}}',
    'email_subject' => lang('There is a new reply to your conversation'),
    'notify_email'  => '{{site_email}}',
);

// A contributor's card: the portrait, the name linking to the
// contributor's own stories, the role and ($with_line) the one line. The
// about page draws the small portrait and no line.
$contributor_card = function ($key, $with_line = true) use ($el, $image, $link, $para, $attr, $portrait, $contributor_name, $contributor_url, $contributors) {
    $children = array(
        $image($portrait($contributor_name($key), $with_line ? 320 : 192), '',
            'rounded-circle object-fit-cover mb-3 ' . ($with_line ? 'pg-circle-160' : 'pg-circle-60'), array('_attrs' => array($attr('loading', 'lazy')))),
        $el('h3', 'pm-title h5 mb-1', lang('Name'), array($link($contributor_name($key), $contributor_url($key), 'link-body-emphasis'))),
        $para($contributors[$key][0], 'pm-kicker ' . ($with_line ? 'mb-2' : 'mb-0')),
    );
    if ($with_line) $children[] = $para($contributors[$key][1], 'pm-dek small mb-0');
    return $el('article', 'text-center', lang('Contributor Card'), $children);
};
$contributor_cards = function ($with_line = true) use ($el, $contributors, $contributor_card) {
    $cols = array();
    foreach (array_keys($contributors) as $key) {
        $cols[] = $el('div', $with_line ? 'col-sm-6 col-lg-4' : 'col-6 col-md-4', lang('Column'), array($contributor_card($key, $with_line)));
    }
    return $el('div', 'row g-4 g-lg-5', lang('Contributors'), $cols);
};

// The front page: the cover story, the latest, a line from the cover, the
// editor's letter, the editors' picks, the sections, the newest issues and
// the newsletter. Its RSS feed is the cover's list (the first list on the
// page): every live Cover story. The footer links the full feed.
$section_tiles = array();
foreach ($sections as $key => $s) {
    $section_tiles[] = $el('div', 'col-6 col-lg-4', $s[0], array(
        $el('article', 'pm-card position-relative', lang('Card'), array(
            $el('div', 'ratio ratio-16x9 pm-media mb-3', lang('Picture Frame'), array(
                $image($photo('section-' . str_replace('_', '-', $key), 1200, 675), '', 'object-fit-cover', $lazy),
            )),
            $el('h3', 'pm-title h5 mb-1', lang('Title'), array($link($s[0], '{{page:' . $key . '}}', 'stretched-link'))),
            $para($s[1], 'pm-meta mb-0 d-none d-sm-block'),
        )),
    ));
}
$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Long reads, essays and photography'),
    'meta_description' => lang('A magazine of long reads about culture, ideas, travel, food, homes and wellbeing, published a few stories at a time.'),
    // Neighbouring bands on the same surface share one gap: the lower one
    // has no top padding. The quote band and the letter band leave the page
    // while their list is empty, with their headings (the template's rules).
    'tree'             => $page(array(
        $heading('h1', '{{site_name}}', 'visually-hidden'),
        $band($placements[1], array(
            $heading('h2', $placements[1], 'visually-hidden'),
            $widget('home_cover'),
        )),
        $band(lang('The latest'), array(
            $sec_head(lang('The latest'), lang('Every story'), '{{page:blog}}'),
            $widget('home_posts'),
        ), 'pm-band pt-0'),
        $band(lang('Quote'), array(
            $widget('home_quote'),
        ), 'pm-band pm-quote-band border-top border-bottom', array('_attrs' => array($attr('aria-label', lang('A line from the cover story'))))),
        // The letter's h3 sits under its own (hidden) h2, not under The
        // latest.
        $band(lang('From the editor'), array(
            $el('h2', 'visually-hidden', lang('Title'), array(), array('id' => 'pm-letter-title', 'text' => lang('From the editor'))),
            $widget('home_letter'),
        ), 'pm-band pm-paper pm-letter-band', array('_attrs' => array($attr('aria-labelledby', 'pm-letter-title')))),
        $band(lang('Editors\' picks'), array(
            $el('div', 'pm-sec-head', lang('Section Header'), array(
                $heading('h2', lang('Editors\' picks'), 'pm-label'),
                $el('span', 'pm-meta d-inline-flex align-items-center gap-2', lang('Help Text'), array(
                    $span(lang('Scroll sideways'), '', lang('Text')),
                    $icon('bi-arrow-left-right'),
                )),
            )),
            $widget('home_picks'),
        )),
        $band(lang('Sections'), array(
            $sec_head(lang('Browse the sections')),
            $el('div', 'row g-4 g-lg-5', lang('Section Tiles'), $section_tiles),
        ), 'pm-band pt-0'),
        $band(lang('Recent issues'), array(
            $sec_head(lang('Recent issues'), lang('All the issues'), '{{page:issues}}'),
            $widget('home_issues'),
        ), 'pm-band pt-0'),
        $widget('newsletter_band'),
    )),
);

// One page for each section: its name, its line, its live stories (its RSS
// feed), the newest drawn wide, and the other sections under them.
$section_nav = array();
foreach ($sections as $key => $s) {
    $section_nav[] = $el('li', 'nav-item', lang('Nav Item'), array($link($s[0], '{{page:' . $key . '}}', 'nav-link pm-navlink')));
}
foreach ($sections as $key => $s) {
    $pages[] = array(
        'key'              => $key,
        'name'             => $s[2],
        'folder'           => 'public',
        'title'            => $s[0],
        'meta_description' => $s[1],
        'tree'             => $page(array(
            $el('header', 'pm-band pb-4 text-center', lang('Page Header'), array(
                $container(array(
                    $para(lang('Section'), 'pm-kicker mb-3'),
                    $heading('h1', $s[0], 'pm-display mb-3'),
                    $para($s[1], 'pm-dek lead pm-standfirst mb-0'),
                )),
            )),
            $el('section', 'pb-5', lang('List'), array(
                $container(array($widget('section_' . $key))),
            )),
            $el('nav', 'border-top py-4 d-print-none', lang('Other sections'), array(
                $container(array(
                    $el('ul', 'nav justify-content-center', lang('Nav Menu'), $section_nav, array('smartActive' => true)),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Other sections'))))),
        )),
    );
}

// Every story: every live story, newest first, with a search; the site's
// full RSS feed. The most read beside it.
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('stories'),
    'folder'           => 'public',
    'title'            => lang('Every story'),
    'meta_description' => lang('Every story we have published, the newest first.'),
    'tree'             => $page(array(
        $page_header(lang('Every story'), lang('Every story we have published, the newest first.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('blog_list')), '', '8'),
                $col(array($widget('most_read')), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);

// The story (?r=): the story set for reading, then its letters (signed in,
// held until an editor publishes them) in the reading column, more stories
// with the most read beside them, and the newsletter. The bare page shows
// "not found", so it stays out of the sitemap; its stories get there
// through the lists.
$pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('story'),
    'folder'           => 'public',
    'title'            => lang('Magazine story'),
    'meta_description' => lang('A story from the magazine.'),
    'sitemap'          => false,
    'comments'         => $letter_comments,
    'tree'             => $page(array(
        $widget('blog_post'),
        // The letters' heading closes the story widget, so it leaves with a
        // missing story; the letters follow in the same column, in line with
        // the story text (from xl the text column starts one column in).
        $el('section', 'pm-band pt-0 d-print-none', lang('Letters'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'pm-measure', lang('Reading Measure'), array($comments_block())),
                    ), '', '8', '', '12', 'col-xl-7 offset-xl-1'),
                ), '4', 'center', 'g-xl-5 justify-content-xl-start'),
            )),
        )),
        $el('section', 'pm-band pt-0 d-print-none', lang('Keep reading'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $sec_head(lang('More from the magazine')),
                        $widget('article_more'),
                    ), '', '8'),
                    $col(array($widget('most_read')), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $widget('newsletter_band'),
    )),
);

// The issues: with ?issue=<name> the issue's numbered contents (the list
// takes the name from the address, and its feed is the issue's); always the
// page's heading and every published issue, drawn from the issues' live
// Editor's letters.
$pages[] = array(
    'key'              => 'issues',
    'name'             => lang('issues'),
    'folder'           => 'public',
    'title'            => lang('Every issue'),
    'meta_description' => lang('Each issue gathers a few stories around one idea.'),
    'tree'             => $page(array(
        $page_header(lang('Every issue'), lang('Each issue gathers a few stories around one idea.')),
        $el('section', 'pb-5', lang('Contents'), array(
            $container(array(
                $row(array(
                    $col(array($widget('issue_list')), '', '8'),
                ), '', 'center'),
            )),
        )),
        $el('section', 'pm-band pt-0', lang('The issues so far'), array(
            $container(array(
                $sec_head(lang('The issues so far')),
                $widget('issue_index'),
            )),
        ), array('id' => 'every-issue')),
    )),
);

// The contributors: with ?author=<name> the contributor's own live stories
// (the list takes the name from the address); always the page's heading and
// the directory, each name linking to that address.
$pages[] = array(
    'key'              => 'author',
    'name'             => lang('contributors'),
    'folder'           => 'public',
    'title'            => lang('Contributors'),
    'meta_description' => lang('The writers and editors who make the magazine.'),
    'tree'             => $page(array(
        $page_header(lang('Contributors'), lang('The writers and editors who make the magazine.')),
        $el('section', 'pb-5', lang('List'), array(
            $container(array($widget('author_list'))),
        )),
        $el('section', 'pm-band pt-0', lang('Meet our contributors'), array(
            $container(array(
                $sec_head(lang('Meet our contributors')),
                $contributor_cards(),
            )),
        ), array('id' => 'contributors')),
    )),
);

// Search: the whole site for ?query= (the menu drawer's search sends it
// here), with the sections beside it.
$browse = array();
foreach ($sections as $key => $s) {
    $browse[] = $el('li', 'mb-2', lang('List Item'), array($link($s[0], '{{page:' . $key . '}}', 'link-body-emphasis')));
}
$browse[] = $el('li', 'mb-2', lang('List Item'), array($link(lang('Every story'), '{{page:blog}}', 'link-body-emphasis')));
$browse[] = $el('li', '', lang('List Item'), array($link(lang('Every issue'), '{{page:issues}}', 'link-body-emphasis')));
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('search'),
    'folder'           => 'public',
    'title'            => lang('Search'),
    'meta_description' => lang('Search every story and every page.'),
    'tree'             => $page(array(
        $page_header(lang('Search'), lang('Search every story and every page.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('site_search')), '', '8'),
                $col(array(
                    $el('aside', 'p-4 bg-body-tertiary', lang('Sections'), array(
                        $heading('h2', lang('Browse the sections'), 'pm-label mb-3'),
                        $el('ul', 'list-unstyled mb-0', lang('Links'), $browse),
                    ), array('_attrs' => array($attr('aria-label', lang('Browse the sections'))))),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);

// About the magazine: who we are, the people, how we work and how to reach
// us. A letter is only what readers write under a story; a word to the
// editors goes through the contact page.
$buttons = function ($links) use ($el) {
    return $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Buttons'), $links);
};
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the magazine'),
    'meta_description' => lang('An independent magazine of long reads, essays and photography.'),
    'tree'             => $static_page(lang('About the magazine'), lang('An independent magazine of long reads, essays and photography.'), array(
        $prose('who-we-are', lang('Who We Are'), lang('Who we are'), array(
            lang('We are a small, independent magazine of long reads. We publish a few stories at a time and give each one the room it needs: the reporting, the photographs and a page set for reading.'),
            lang('Each issue gathers a few stories around one idea, a place, a habit or a way of living. Stories that belong to no issue appear online between them.'),
        )),
        $prose('the-people', lang('Who makes the magazine'), lang('Who makes the magazine'), array(
            lang('A small group of editors and writers makes every issue. Each name opens that person\'s stories.'),
        ), array($contributor_cards(false))),
        $prose('how-we-work', lang('How We Work'), lang('How we work'), array(
            lang('Our writers report their stories themselves, and an editor checks every fact before a story goes live.'),
            lang('No one pays to appear in the magazine: there are no paid stories and no reviews of products.'),
            lang('When we get something wrong, we correct it and say so under the story itself.'),
        ), array($sample_note())),
        $prose('get-in-touch', lang('Get in Touch'), lang('Get in touch'), array(
            lang('Write a letter under any story; send story ideas and questions through the contact page.'),
        ), array($buttons(array(
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-primary'),
            $link(lang('Write for us'), '{{page:write_for_us}}', 'btn btn-secondary'),
        )))),
    ), array(
        $side_photo('about-studio'),
    )),
);

// Write for us: what the magazine publishes, how a writer gets the author
// desk, and what to send.
$pages[] = array(
    'key'              => 'write_for_us',
    'name'             => lang('write-for-us'),
    'folder'           => 'public',
    'title'            => lang('Write for us'),
    'meta_description' => lang('We publish long reads, essays and photo stories by writers from outside the magazine too.'),
    'tree'             => $static_page(lang('Write for us'), lang('We publish long reads, essays and photo stories by writers from outside the magazine too.'), array(
        $prose('what-we-publish', lang('What We Publish'), lang('What we publish'), array(
            lang('We publish long reads, essays and photo stories in our six sections, from culture and ideas to food, homes and wellbeing.'),
            lang('We do not publish reviews of products or pieces someone paid for.'),
        )),
        $prose('how-it-works', lang('How It Works'), lang('How it works'), array(), array(
            $el('ol', 'mb-0 ps-3', lang('Steps'), array(
                $el('li', 'mb-2', lang('List Item'), array(), array('text' => lang('Ask for an author account through the contact page.'))),
                $el('li', 'mb-2', lang('List Item'), array(), array('text' => lang('Write your story on the Submit a Story page.'))),
                $el('li', '', lang('List Item'), array(), array('text' => lang('Follow it under My Stories; the editors write to you under the story.'))),
            )),
        )),
        $prose('guidelines', lang('Guidelines'), lang('Guidelines'), array(
            lang('Most of our stories run between one and three thousand words. Send a title, a summary of one or two sentences and the name readers should see.'),
            lang('Send only photos you took yourself or may publish, and say who took them.'),
            lang('Search engines are also given the name on your account, not only the name you give on your story.'),
            lang('Nothing you send is shown to readers until an editor sets it live.'),
        ), array($buttons(array(
            $link(lang('Ask for an author account'), '{{page:contact}}', 'btn btn-primary'),
            $link(lang('Submit a Story'), '{{page:blog_new}}', 'btn btn-secondary'),
        )))),
    ), array(
        $side_photo('writing-desk'),
    )),
);

// Contact: a message to the editors. What is sent opens the conversation
// page, and the answers follow there and in the inbox; a visitor without an
// account gets one.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('Story ideas, corrections and questions: the editors read every message.'),
    'form'             => array(
        'form_name'            => lang('Write to the Magazine'),
        'confirmation_message' => lang('Thank you, your message has reached the editors. Their answer comes to your inbox.'),
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message to the magazine: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('Story ideas, corrections and questions: the editors read every message.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('contact_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('Contact Details'), lang('Contact Details'), array(
                        $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                            $icon('bi-geo-alt', 'mt-1'),
                            $span(lang('75 Example Lane, Hudson, NY 12534'), '', lang('Text')),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                            $icon('bi-telephone'),
                            $link('(518) 555-0137', 'tel:+15185550137', 'link-body-emphasis'),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Email'), array(
                            $icon('bi-envelope'),
                            $link('editors@example.com', 'mailto:editors@example.com', 'link-body-emphasis'),
                        )),
                    ), 'p-4 bg-body-tertiary border mb-4'),
                    $el('div', 'p-4 border', lang('Inbox Note'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2 pm-sans', lang('Title'), array(
                            $icon('bi-inbox'),
                            $span(lang('We answer in your account\'s inbox.'), '', lang('Text')),
                        )),
                        $para(lang('Your message opens a conversation: the page that opens after you send it, and your inbox, show the editors\' answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                        $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);

// The newsletter: its page owns the form. A sign-up joins The Weekend
// Read's contact group and gets the welcome e-mail. The band on the home
// page and under every story sends the same form.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('One long read, one essay and a letter from the editor, by e-mail.'),
    'form'             => array(
        'form_name'            => lang('The Weekend Read'),
        'confirmation_message' => lang('Thank you! The Weekend Read will reach your inbox.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to The Weekend Read'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('The Weekend Read'), lang('One long read, one essay and a letter from the editor, by e-mail.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4 p-lg-5', lang('Form Card'), array($widget('newsletter_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('What You Get'), lang('What you get'), array(
                        $check_list(array(
                            lang('One long read and one essay, chosen by the editors'),
                            lang('A letter from the editor about the stories of the week'),
                            lang('One e-mail a week, and never your address to anyone else'),
                        )),
                    ), 'p-4 bg-body-tertiary border', array('id' => 'the-weekend-read')),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);

// The legal pages: sample text, each with a note to adapt it.
$pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What we keep about you, why, and what you can ask of us.'),
    'tree'             => $static_page(lang('Privacy Policy'), lang('What we keep about you, why, and what you can ask of us.'), array(
        $sample_note(),
        $prose('your-data', lang('Your Data'), lang('Your data'), array(
            lang('When you open an account, we keep your name, your e-mail address and the settings you choose. When you write a letter under a story or a message to the editors, we keep what you sent with your account.'),
            lang('We use your e-mail address to answer you, to send The Weekend Read if you asked for it and to tell you about replies you follow. We never sell it or give it to advertisers.'),
            lang('The site uses the cookies it needs to keep you signed in and to remember your choices. You can see and change your account data from My Account, and ask us to delete it.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'terms',
    'name'             => lang('terms-of-use'),
    'folder'           => 'public',
    'title'            => lang('Terms of Use'),
    'meta_description' => lang('The rules for reading, writing letters and writing for the magazine.'),
    'tree'             => $static_page(lang('Terms of Use'), lang('The rules for reading, writing letters and writing for the magazine.'), array(
        $sample_note(),
        $prose('using-the-site', lang('Using the Site'), lang('Using the site'), array(
            lang('You may read, share and link to our stories. Copying a whole story or its photos to another site needs our written permission.'),
            lang('Letters are read by an editor before they appear. We do not publish insults, threats, advertising or anything unlawful, and we may remove a letter or close an account that breaks these rules.'),
            lang('A story sent from the Submit a Story page stays its writer\'s own work; by sending it, the writer allows us to edit and publish it.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'accessibility',
    'name'             => lang('accessibility'),
    'folder'           => 'public',
    'title'            => lang('Accessibility'),
    'meta_description' => lang('How the site works with a keyboard, a screen reader, the dark theme and reduced motion.'),
    'tree'             => $static_page(lang('Accessibility'), lang('We want everyone to be able to read the magazine.'), array(
        $sample_note(),
        $prose('accessibility-help', lang('How the Site Helps'), lang('How the site helps'), array(
            lang('Every page works with a keyboard alone: the first press of Tab shows a link that skips to the main content.'),
            lang('Words and buttons keep a readable contrast in the light and the dark theme; switch the theme with the button at the top of the page (a moon in the light theme, a sun in the dark one).'),
            lang('Nothing on the site moves by itself. Pictures grow very slightly under the pointer, and not at all when your device asks for less motion.'),
            lang('The editors\' picks scroll sideways: move into the row with Tab and use the arrow keys, or swipe.'),
            lang('A printed story leaves out the menus, the letters and the newsletter, so the story alone is printed.'),
            lang('If something on the site does not work for you, tell us and we will fix it.'),
        ), array(
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-primary'),
        )),
    )),
);

// Signing in and out. The forms are the palette's own layouts, each a
// narrow card centred in its own container, set in a band so the card
// keeps the other pages' distance from the masthead. The paper panel of
// the sign-in pages is a band of its own under the card, not a column
// beside it: the card would be too narrow in a column. The panel is
// decorative: the tagline and a link that says where it goes.
$sign_panel = function () use ($el, $para, $link, $container) {
    return $el('div', 'pm-band pm-paper text-center d-print-none', lang('Paper Panel'), array(
        $container(array(
            $para(lang('Stories that take their time.'), 'pm-tagline mt-0 mb-2'),
            $el('p', 'mb-0', lang('Link'), array(
                $link(lang('Get The Weekend Read by e-mail'), '{{page:newsletter}}', ''),
            )),
        )),
    ));
};
$sign_page = function ($key, $name, $title, $widget_key, $with_panel = false) use ($el, $page, $widget, $sign_panel) {
    $main = array($el('div', 'pm-band pb-4', lang('Content'), array($widget($widget_key))));
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

// My account: the reader's own pages, behind the registration folder.
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

// The inbox: the member's conversations with the editors (for the editors,
// everybody's), and the conversation page, where both sides answer as
// messages under the first one.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('The messages you sent the editors from the contact page, and their answers.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('inbox_list')), '', '8'),
                $col(array(
                    $el('div', 'p-4 bg-body-tertiary border mb-4', lang('Inbox Note'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2 pm-sans', lang('Title'), array(
                            $icon('bi-chat-left-text', 'text-primary'),
                            $span(lang('A question for the editors?'), '', lang('Text')),
                        )),
                        $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                        $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                    )),
                    $el('div', 'p-4 border', lang('Author Note'), array(
                        $heading('h2', lang('Writing for us?'), 'h6 pm-title mb-2'),
                        $para(lang('Messages about a story you sent are written under the story itself, in My Stories.'), 'small text-body-secondary mb-3'),
                        $link(lang('My Stories'), '{{page:my_articles}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $conversation_comments,
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_section(lang('Replies')),
    )),
);

// What each Status means to the author, in the words of the Status field.
$status_notes = array(
    lang('It reached the editors; no one has opened it yet.'),
    lang('An editor is reading and editing it.'),
    lang('The editors wrote under your story what to change.'),
    lang('Readers see it from its publication time.'),
    lang('The editors decided not to publish it.'),
    lang('It was taken back or taken off the site, for example at your request.'),
);
$status_list = array();
foreach ($statuses as $i => $status) {
    $status_list[] = $el('dt', 'fw-semibold', lang('Status'), array(), array('text' => $status));
    $status_list[] = $el('dd', 'mb-2', lang('Text'), array(), array('text' => $status_notes[$i]));
}

// ── The sample stories ──────────────────────────────────────────────────
// Seventeen live stories the Magazine Stories form opens with, newest
// first: three issues of five or six stories, each with one Cover story and
// one Editor's letter, plus one story that is Online only, so the home page,
// every section, issue and contributor page are filled on the first day.
// Each is a record like any other, edited or deleted in the control panel.
// The seeder dates record i (4i + 1) days back; the publication time follows
// it by two hours. The texts name no month, season or weekday: the dates
// move with the install. The pictures are random sample photos, so every
// credit says so and only one caption shows, asking for a real one. No
// sample carries a reading time: these are short pieces, and a long-read
// card should not claim a length the text does not have.

// A story's text as the rich-text field stores it: each paragraph a <p>, a
// subhead (array('h2' => text)) an <h2>. The words are escaped here; the
// seeder keeps the markup (pg_cf_seed_rich_value()) and filters it.
$story_body = function ($blocks) {
    $html = '';
    foreach ($blocks as $b) {
        $html .= is_array($b) ? '<h2>' . h($b['h2']) . '</h2>' : '<p>' . h($b) . '</p>';
    }
    return $html;
};
// Sample record $i: the head of $story_heads[$i], its text, its pull quote
// (a sentence of the text, word for word) and its topics.
$sample_story = function ($i, $blocks, $pull, $tags, $more = array())
    use ($story_heads, $sections, $placements, $issues, $online_only, $live_value, $contributors, $contributor_name, $portrait, $photo, $story_body) {
    $h = $story_heads[$i];
    return array_merge(array(
        'section'       => $sections[$h[0]][0],
        'title'         => $h[4],
        'summary'       => $h[5],
        'body'          => $story_body($blocks),
        'pull_quote'    => $pull,
        'cover_image'   => $photo($h[6], 1600, 1200),
        'image_caption' => '',
        'image_credit'  => lang('Sample photo'),
        'byline'        => $contributor_name($h[3]),
        'byline_bio'    => $contributors[$h[3]][1],
        'byline_photo'  => $portrait($contributor_name($h[3]), 192),
        'tags'          => $tags,
        'rights'        => 'yes',
        'status'        => $live_value,
        'publish_date'  => date('Y-m-d H:i', time() - (4 * $i + 1) * 86400 + 2 * 3600),
        'placement'     => $placements[$h[1]],
        // A stored option, never '': Online only is the field's first option.
        'issue'         => $h[2] === '' ? $online_only : $issues[$h[2]],
        'reading_time'  => '',
    ), $more);
};
$sample_stories = array(
    $sample_story(0, array(
        lang('Every issue of this magazine starts with a list on the wall of our office. This time the list was short and oddly specific: a counter with twelve stools, a library in a rail car, a street that ends at the last lamp post.'),
        lang('We kept noticing that the places we loved most were the smallest ones. They ask for nothing, they stay open, and they reward anyone who sits still long enough to learn their habits.'),
        lang('So this is the Small Places Issue. Our food editor spent long days at a diner counter, our culture editor borrowed a library card in a town of nine hundred people, and our design writer measured five homes that would fit inside an ordinary garage.'),
        lang('Read them slowly. None of these places is going anywhere, and neither, we hope, are you.'),
    ), lang('They ask for nothing, they stay open, and they reward anyone who sits still long enough to learn their habits.'),
        lang('small places, editor\'s letter')),
    $sample_story(1, array(
        lang('The first customer arrives at a quarter to five, before the sign is switched on, and takes the stool nearest the coffee. Nobody asks what he wants. The cook has already cracked two eggs onto the griddle.'),
        lang('The diner has twelve stools, four booths and one window that looks out on the bridge. It has changed owners twice in sixty years, and each time the new owner kept the menu exactly as it was.'),
        lang('"People think a diner sells breakfast," says the cook, who has worked the griddle for nineteen years. "It sells the same breakfast. That is the whole idea."'),
        array('h2' => lang('A menu that never changes')),
        lang('The menu fits on one laminated card: eggs any way, hash browns, a short stack, a grilled cheese, the soup of the day. The only thing that moves is the pie, which depends on what the orchard down the road has too much of.'),
        lang('By mid-morning the regulars have rotated through: the road crew, two retired teachers who split a single order of toast, a nurse coming off a night shift who eats pancakes and reads the paper upside down to the person beside her.'),
        lang('At two in the afternoon the cook scrapes the griddle clean, the owner counts the register, and the last stool turns empty. Tomorrow the first customer will arrive at a quarter to five, and his eggs will already be cooking.'),
    ), lang('The only thing that moves is the pie, which depends on what the orchard down the road has too much of.'),
        lang('diners, breakfast, small towns'), array('image_caption' => lang('Replace this picture with your own photo and caption.'))),
    $sample_story(2, array(
        lang('The smallest home in this story measures 290 square feet, and its owner keeps a piano in it. The trick, she says, is to decide what a room is for before you decide what goes in it.'),
        lang('We visited five apartments, each under 400 square feet, and asked their owners the same question: what did you give up, and what did you get back?'),
        lang('A teacher in Chicago gave up a dining table and got back a window seat that doubles as a bed for guests. A couple in Portland gave up a sofa and built a deep bench that runs the length of one wall.'),
        array('h2' => lang('Furniture that does two jobs')),
        lang('Almost every home had a piece that did two jobs: a stair that is also a bookcase, a kitchen counter that folds down from the wall, a wardrobe with a desk hidden behind one door.'),
        lang('None of the owners described their homes as a sacrifice. They talked instead about how little time they spend looking for things, and how quickly a small room can be made tidy and calm.'),
        lang('"A small home edits your life for you," says the owner of the piano. "Everything that stays has to earn its place, and everything that earns its place gets used."'),
    ), lang('Everything that stays has to earn its place, and everything that earns its place gets used.'),
        lang('small homes, furniture')),
    $sample_story(3, array(
        lang('The library sits on a short length of track behind the post office, in a passenger car that last carried travelers before most of its readers were born. Inside, the seats are gone and the walls are shelves.'),
        lang('There are about two thousand books, one librarian, a card catalog in wooden drawers, and a single armchair by the window that the town\'s children treat as the best seat in the county.'),
        lang('The librarian keeps a waiting list for the chair. It is written on an index card taped to the door, and nobody has ever argued with it.'),
        array('h2' => lang('Every book chosen by hand')),
        lang('With room for so few books, every one is chosen by hand. Readers leave requests in a tin box, and the librarian drives to the regional library every few weeks to trade what the town has finished for what it wants next.'),
        lang('When the school bus stops at the crossing, the car fills up. Homework spreads across shelves that fold down from the walls, and the sound of pages turning is louder than the trains on the main line.'),
        lang('"People expect a small library to feel like a compromise," the librarian says. "It feels more like a living room that happens to lend books."'),
    ), lang('It feels more like a living room that happens to lend books.'),
        lang('libraries, reading, small towns'), array('correction' => lang('An earlier version of this story gave the wrong number of books in the collection.'))),
    $sample_story(4, array(
        lang('Main Street is three blocks long. It starts at a hardware store with a bench out front and ends at a streetlight beyond which the road turns to gravel and the desert begins.'),
        lang('I walked it the way the town walks it: slowly, stopping at every door. It took most of a day, and I was told more than once that I had rushed.'),
        lang('The hardware store sells nails by the pound and lends ladders for free. The café next door has no sign, only a chalkboard with the word "Open" written on it in careful capitals.'),
        array('h2' => lang('The middle block')),
        lang('The middle block holds a barber, a post office, a bookstore with a cat, and a dance hall that opens twice a year. Its owner sweeps the floor every morning anyway, in case someone wants to practice.'),
        lang('Past the dance hall the buildings thin out. A garden grows tomatoes in old tires, and an artist paints signs for the businesses that do not have one yet.'),
        lang('At the last streetlight there is a bench facing the desert. Nobody could tell me who put it there, but by sunset every seat on it was taken.'),
    ), lang('At the last streetlight there is a bench facing the desert.'),
        lang('walking, main streets, desert towns')),
    $sample_story(5, array(
        lang('The experiment was simple. For a month, before opening a laptop or reading a single message, I would walk outside for twenty minutes, without headphones and without a destination.'),
        lang('The first week was mostly impatience. I checked the time at every corner and walked faster than I needed to, as if the walk were another task to finish.'),
        lang('By the second week the route had settled into a loop past the park, the bakery and the river path. I started to notice which neighbors were up early, and which dogs knew me.'),
        array('h2' => lang('What changed, and what did not')),
        lang('I did not become a different person, and my inbox was exactly as full when I returned. But I sat down to it having already done one thing that morning purely for its own sake.'),
        lang('A few habits arrived on their own. I left my phone at home more often. I learned the name of the man who opens the bakery, and he learned that I take my bread unsliced.'),
        lang('The month is over, and I still walk most mornings. The day seems to begin a little more gently when it begins outside.'),
    ), lang('The day seems to begin a little more gently when it begins outside.'),
        lang('walking, habits')),
    $sample_story(6, array(
        lang('Water sets its own schedule. A mail boat leaves when the river allows it, a harbor kitchen cooks what the boats bring in, and a swimmer learns to float only after she stops fighting the lake.'),
        lang('That patience is what drew us to this issue. Every story in it is about people who have learned to work with something larger than themselves, and to wait for it without complaint.'),
        lang('Our travel writer rode a mail boat for the length of its route. Our food editor ate her way through a harbor town after the visitors had gone home. Our culture editor sat with the volunteer who is reading a century of lighthouse logbooks, page by careful page.'),
        lang('We hope you read them the way water moves: without hurry, and all the way to the end.'),
    ), lang('Water sets its own schedule.'),
        lang('water, editor\'s letter')),
    $sample_story(7, array(
        lang('The mail boat leaves the landing at seven, loaded with letters, groceries, a crate of chickens and one passenger who has paid a small fare to see the river the slow way.'),
        lang('There is no road to most of the cabins along this stretch of water. For a hundred years the boat has been how letters and parcels arrived, and how the people who live here stay connected to everyone else.'),
        lang('The captain knows every dock by the color of its flag. A red flag means there is mail going out. A white one means someone wants a word, and the boat always stops for a word.'),
        array('h2' => lang('Stops along the way')),
        lang('At one dock a woman hands up a pie in exchange for her parcels. At another, a boy waits with a letter he has written to his grandmother, and the captain stamps it herself.'),
        lang('By midday the river widens and the cabins grow farther apart. The engine slows, the deckhand pours coffee, and for a long while the only sound is water against the hull.'),
        lang('We return to the landing a little before dark. The captain ties up, counts the empty mailbags and says the route was quiet. Tomorrow, she says, it will be quiet again, and that is how she likes it.'),
    ), lang('There is no road to most of the cabins along this stretch of water.'),
        lang('rivers, boats, mail')),
    $sample_story(8, array(
        lang('Once the tour buses stop coming, the harbor town gets its kitchens back. The menus shrink, the portions grow, and the cooks start making food for their neighbors instead of for postcards.'),
        lang('At the smokehouse by the pier, fish hangs over a low fire for two days. The owner sells it wrapped in paper and will tell you exactly which boat it came from.'),
        lang('Up the hill, a café run by two sisters serves one stew a day, ladled from a pot that has been on the stove since early morning. When the pot is empty, the café closes.'),
        array('h2' => lang('Cooking for the people who stay')),
        lang('The cooks here talk about the quiet months with real affection. There is time to try a new recipe, time to sit with the regulars, time to let a sauce simmer as long as it wants.'),
        lang('Dinner is often a shared table at the fishermen\'s hall, where everyone brings a dish and nobody brings the same thing twice. The bread comes from the bakery that opens at four.'),
        lang('"In the busy months we cook fast," says one of the sisters. "Now we cook the way our mother did, slowly, for people we know by name."'),
    ), lang('Now we cook the way our mother did, slowly, for people we know by name.'),
        lang('harbors, seafood, home cooking')),
    $sample_story(9, array(
        lang('The logbooks fill four shelves in the back room of a small maritime museum. Each one covers a year at the lighthouse on the point, written in pencil by whoever kept the light.'),
        lang('A retired schoolteacher has volunteered to read them all. She comes in three days a week, opens the next book where she left off, and copies anything interesting onto index cards.'),
        lang('Most entries are short: the time the lamp was lit, the state of the sea, a ship sighted to the north. But between those lines the keepers wrote about their lives.'),
        array('h2' => lang('Between the lines')),
        lang('One keeper recorded every bird that landed on the gallery. Another noted the birthdays of his children and the day his daughter rowed to the mainland by herself.'),
        lang('The volunteer reads a few entries aloud whenever visitors stop by. People laugh at the complaints about the coffee, and grow quiet at the nights when the light was all that stood between a ship and the rocks.'),
        lang('She expects to finish in about four years. "There is no hurry," she says. "The keepers took their time writing them. I can take my time reading."'),
    ), lang('The keepers took their time writing them.'),
        lang('lighthouses, history, museums')),
    $sample_story(10, array(
        lang('I grew up near water and never learned to swim. For forty years I waded to my knees, smiled at friends on the dock and found reasons to stay on the shore.'),
        lang('The lessons took place in a small lake with a roped-off corner and a coach who had taught swimming to children, grandparents and, once, a nervous firefighter.'),
        lang('The first lesson was only about breathing: face in the water, slow bubbles out, face up, slow breath in. We did it fifty times. I was tired and very proud.'),
        array('h2' => lang('The day the water held')),
        lang('For weeks I sank whenever I tried to float. The coach said my body was waiting to be rescued. The water would hold me, she said, as soon as I stopped bracing for the fall.'),
        lang('Then one morning it did. I lay back, arms wide, ears under the surface, and the lake simply carried me. I could hear my own heartbeat and, far off, someone laughing on the dock.'),
        lang('I am not a strong swimmer yet. But I swim, slowly, from the dock to the buoy and back, and every time I reach the buoy I am forty and learning something new.'),
    ), lang('I lay back, arms wide, ears under the surface, and the lake simply carried me.'),
        lang('swimming, lakes, learning')),
    $sample_story(11, array(
        lang('A magazine is a strange thing to start in a hurried age. It arrives a few stories at a time, it asks for your attention for longer than a headline does, and it is in no rush to be finished.'),
        lang('That is why our first issue is about slowness. Not slowness as a trend, but as a way of doing things well: bread that takes three days, a table built to outlast its maker, an album heard from start to finish.'),
        lang('We do not think everything should be slow. Some things should be fast, and are. But the slow things are worth defending, and they are worth writing about properly.'),
        lang('Thank you for reading the first issue. Take as long as you like.'),
    ), lang('Take as long as you like.'),
        lang('slowness, editor\'s letter')),
    $sample_story(12, array(
        lang('The dough for tomorrow\'s bread was mixed the day before yesterday. It has rested in a cool room ever since, rising so slowly that you would not notice it moving unless you came back hours later.'),
        lang('The baker who mixed it opens her shop at seven, but her day starts at four. She shapes the loaves by hand, one at a time, and every one of them carries a cut she makes with a single blade.'),
        lang('She started baking in her apartment kitchen, with a jar of starter a neighbor gave her and a borrowed oven. The starter is still alive. It sits on a shelf above the mixer, fed twice a day.'),
        array('h2' => lang('What three days give you')),
        lang('Long fermentation, she explains, is not a trick. It is simply time letting the flour do what flour does: the crust deepens, the crumb opens, and the bread keeps for days.'),
        lang('Customers line up before the doors open. Some come for a loaf; some come to watch the last trays leave the oven through the window, the steam rising off them like breath.'),
        lang('By early afternoon the shelves are empty and the next batch is already mixed. "People ask how I find the patience," she says. "I don\'t. The bread has it. I just stay out of its way."'),
    ), lang('Some come for a loaf; some come to watch the last trays leave the oven through the window, the steam rising off them like breath.'),
        lang('bread, bakers, fermentation')),
    $sample_story(13, array(
        lang('The table in the corner of the workshop has no screws and no nails. Its legs are joined to the top with wooden pegs, and it can be taken apart and put back together without a single tool.'),
        lang('The woodworker who built it learned the joints from an old furniture maker, who learned them from his father. It took her seven years before he let her cut a joint on a piece that would be sold.'),
        lang('She builds about twelve tables a year. Each one starts with a visit to the lumberyard, where she runs her hands along the boards and chooses them the way a cook chooses fruit.'),
        array('h2' => lang('Joints that hold for generations')),
        lang('The joints are cut by hand with chisels she sharpens every morning. A good joint, she says, is tight enough to hold without glue. The glue is there only for the grandchildren\'s peace of mind.'),
        lang('She keeps a photograph of every table she has made, and a notebook of where each one went. Some have moved across the country. One has been handed down twice already.'),
        lang('"A table is where a family eats, argues and does homework," she says. "It should outlast all of that, and then some."'),
    ), lang('Its legs are joined to the top with wooden pegs, and it can be taken apart and put back together without a single tool.'),
        lang('furniture, woodworking, craft')),
    $sample_story(14, array(
        lang('Most of us now work in a state of constant switching. We write a sentence, check a message, answer it, return to the sentence and wonder what we meant to say.'),
        lang('The cost of switching is easy to miss because it arrives in small pieces: a minute here, a lost thought there. At the end of the day we feel tired, busy and oddly unfinished.'),
        lang('For this essay I tried a simple rule for a month: one thing at a time, with the door closed and the notifications off, for as long as the thing took.'),
        array('h2' => lang('What a single task feels like')),
        lang('The first days were uncomfortable. Without the small rewards of new messages, the work felt slow and heavy. Then, somewhere in the second week, the heaviness turned into something closer to calm.'),
        lang('I did not get more done in a day. I got fewer things done, more completely. Drafts needed less rewriting, and conversations felt less like interruptions and more like conversations.'),
        lang('Attention is not a muscle you build with more tabs. It is a room you protect, and the door is easier to close than we think.'),
    ), lang('It is a room you protect, and the door is easier to close than we think.'),
        lang('attention, work, focus')),
    $sample_story(15, array(
        lang('At the start of the year I made myself one rule: no shuffle and no skipping. If I wanted to hear a song, I would hear the album it belonged to, from the first track to the last.'),
        lang('The rule was harder than it sounds. Some albums opened with their best songs and faded from there. Others asked for patience through three quiet tracks before they turned into something I could not stop thinking about.'),
        lang('Slowly I started to hear albums as their makers meant them: as a sequence, with a beginning, a middle and an end, where a short song exists only to make the next one land.'),
        array('h2' => lang('Listening as a habit')),
        lang('I listened in the car, on long walks and on the floor of the living room with the lights off. I kept a notebook of every album, with one sentence about each.'),
        lang('By the end of the year the notebook held two hundred and twelve albums. About forty of them I will play again and again. Many I would never have finished any other way.'),
        lang('The rule is over, but I still listen this way most of the time. A whole album takes about forty minutes. It turns out I almost always have forty minutes.'),
    ), lang('It turns out I almost always have forty minutes.'),
        lang('music, albums, listening')),
    $sample_story(16, array(
        lang('The train leaves the city in the late afternoon and does not hurry. By dark we are crossing farmland, and the lights of small towns pass the window one at a time, far apart.'),
        lang('My sleeper compartment is the size of a large closet. The seat folds into a bed, the window takes up one whole wall, and the attendant knocks at nine to ask whether I would like it made up.'),
        lang('In the dining car I share a table with a retired rancher, a student going home and a couple traveling to their grandson\'s graduation. The conversation lasts longer than the meal.'),
        array('h2' => lang('A view that takes its time')),
        lang('On the second morning the plains open up completely. For hours the view barely changes: grass, fence, sky, a single tree, grass again. I stop waiting for something to happen and simply watch.'),
        lang('That is when the details arrive: a hawk keeping pace with the train, a line of grain elevators on the horizon, a farmer on a tractor who lifts a hand as we pass.'),
        lang('We pull into the station a little behind schedule. Nobody on board seems to mind. The trip took two days, and for once I would have been happy if it had taken three.'),
    ), lang('I stop waiting for something to happen and simply watch.'),
        lang('trains, rail travel, the plains')),
);

// Submit a Story: the author desk's form, in the private contributors
// folder (an author has view access to it). Its page owns the Magazine
// Stories form; a story is sent as Awaiting review, and only an editor sets
// it live. Addresses are ?r=<reference code> (no pretty URLs): the code
// never changes, while a pretty address would follow every title edit.
$pages[] = array(
    'key'     => 'blog_new',
    'name'    => lang('submit-a-story'),
    'folder'  => 'contributors',
    'title'   => lang('Submit a Story'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Magazine Stories'),
        'confirmation_message' => lang('Thank you. Your story has reached the editors; they will write to you on its page.'),
        'confirmation_page_id' => '{{tab:submission}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New story to review: ^^title^^'),
        'notify_page_id'       => '{{tab:email_article_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Your story reached the editors: ^^title^^'),
        'confirm_page_id'      => '{{tab:email_article_received}}',
        'pretty_urls'          => 0,
        'sample_records'       => $sample_stories,
        // The BlogPosting author is the byline readers see, not the account
        // that sent the story (the sample stories have none).
        'schema_author'        => 'field:byline',
    ),
    'tree'    => $page(array(
        $page_header(lang('Submit a Story'), lang('Write your story and send it to the editors. They read it, may ask for changes and decide when it goes live.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('article_form'))),
                ), '', '8'),
                $col(array(
                    $side_card(lang('What the editors look for'), lang('What the editors look for'), array(
                        $check_list(array(
                            lang('A title and a summary of one or two sentences'),
                            lang('A story told at its own pace, with the facts checked'),
                            lang('Your own words, and photos you may publish'),
                            lang('The name readers should see'),
                        )),
                    ), 'p-4 bg-body-tertiary border mb-4'),
                    $side_card(lang('How review works'), lang('How review works'), array(
                        $el('dl', 'small mb-3', lang('Statuses'), $status_list),
                        $para(lang('Nothing you send is shown to readers until an editor sets it live.'), 'small fw-semibold'),
                        $para(lang('Follow your story under My Stories. The editors write to you under the story, and each message also comes by e-mail. To change your story or take it back, write to the editors there.'), 'small'),
                        $para(lang('When an editor sets your story live, the editor tells you so under it: that message is the e-mail you get, and no other e-mail is sent when it goes live.'), 'small'),
                        $para(lang('The name you give decides which contributor page the story joins, and the editors check it.'), 'small'),
                        $para(lang('Search engines are also given the name on your account, not only the name you write here.'), 'small'),
                        $para(lang('A photo you upload is shown once your story is live. Until then only the editors see it, so under My Stories your story shows a placeholder in its place.'), 'small'),
                        $para(lang('A story taken off the site can still show in the site search until the search index is rebuilt.'), 'small'),
                        $link(lang('My Stories'), '{{page:my_articles}}', 'btn btn-sm btn-secondary'),
                    ), 'p-4 border'),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);

// The author desk, in the contributors folder: the author's own stories
// (an editor's list is the editors' desk) and the page of one story, where
// the author and the editors write to each other under it.
$pages[] = array(
    'key'     => 'my_articles',
    'name'    => lang('my-stories'),
    'folder'  => 'contributors',
    'title'   => lang('My Stories'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array(
        $page_header(lang('My Stories'), lang('The stories you sent, their status and the editors\' messages, newest first.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('my_articles_list')), '', '8'),
                $col(array(
                    $el('div', 'p-4 bg-body-tertiary border mb-4', lang('Write Another'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2 pm-sans', lang('Title'), array(
                            $icon('bi-pencil-square', 'text-primary'),
                            $span(lang('Another story to tell?'), '', lang('Text')),
                        )),
                        $para(lang('Send it the same way; each story has its own page and its own messages with the editors.'), 'small text-body-secondary mb-3'),
                        $link(lang('Submit a Story'), '{{page:blog_new}}', 'btn btn-sm btn-primary'),
                    )),
                    $el('div', 'p-4 border', lang('Live on the site'), array(
                        $heading('h2', lang('Live on the site'), 'h6 pm-title mb-2'),
                        $widget('my_articles_live'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'pm-band pt-0'),
    )),
);
// One story on the desk: what the author sent, its status, and the
// messages between the author and the editors. The form's confirmation
// page: an author lands here (?r=<code>) right after sending.
$pages[] = array(
    'key'      => 'submission',
    'name'     => lang('story-submission'),
    'folder'   => 'contributors',
    'title'    => lang('Story Submission'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'comments' => $desk_comments,
    'tree'     => $page(array(
        $widget('submission_view'),
        $comments_section(lang('Messages with the editors')),
    )),
);

// The editors' desk: the lists of stories, the staff directory and its
// form, behind the private folder.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 bg-body-tertiary border text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 pm-title mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '4');
};
// A list of the desk under its heading, with the anchor the jump list
// points at; $lg '' gives it the whole width.
$desk_section = function ($id, $title, $text, $widget_key, $lg = '6') use ($el, $heading, $para, $widget) {
    return $el('section', 'col-12' . ($lg !== '' ? ' col-lg-' . $lg : ''), $title, array(
        $heading('h2', $title, 'h5 pm-title mb-2'),
        $para($text, 'small text-body-secondary mb-3'),
        $widget($widget_key),
    ), array('id' => $id));
};
// How the desk works: one sentence each, with the control panel's own
// labels in quotes.
$staff_notes = array(
    lang('Authors: open the account under Users, then under Private Content Access tick the contributors folder.'),
    lang('Editors: under Content and Forms, tick the private folder and switch on Reach the data submitted through forms. Administrators, designers and managers need neither.'),
    lang('Contact messages and readers\' letters belong to the public folder, because visitors send them: to open them in the control panel, answer messages in the inbox, or correct a letter\'s name and publish it under Comments, an editor also needs the public folder ticked, which lets them change the public pages too. A reply in the inbox is signed with the first and last name on the editor\'s account, or with Member when the account has no name, so every editor should fill in their name under My Profile.'),
    lang('To publish: open the story, press Change Status, choose "Live on the site", a publication time, a placement and an issue, save, then write a message under the story. That message is the e-mail the author gets.'),
    lang('The home page shows the newest Cover story, the newest Editor\'s letter and the six newest Standard stories; Editors\' picks have their own shelf. Give each issue exactly one Cover story and one Editor\'s letter: the letter is the issue\'s card on Every issue. To launch an issue, give its stories one publication time, the Cover story one minute later and the Editor\'s letter one minute after that: an issue\'s contents put the latest time first, so they open with the letter and then the cover.'),
    lang('A new issue is added by an administrator or designer, because the visual editor opens to editors and managers for content only: add its name to the "Part of the issue" field of the Submit a Story page with Add an option, not with the bulk edit box, check that Online only is still the default, and publish the design. Use letters, digits, spaces and hyphens only: no & # ? + % = or quotes, because the name becomes part of a link.'),
    lang('Site search changes only when the index is rebuilt, which the site\'s scheduled jobs do once a day: after a scheduled story goes live, or after you take a story off the site, ask an administrator, designer or manager to run Update Search Index in the control panel. Until then search shows the old state. A story page is indexed with its More from the magazine and Most read lists, so a search for a title also finds the story pages that showed it there.'),
    lang('A story\'s structured data and the RSS feeds name the time it reached the editors, not its publication time, and the feeds order stories by that time; the structured data also names the account that sent it, not the name on the story. For a pen name, ask the writer to put the same name on their account.'),
    lang('Never rename or remove the fields named status, publish_date, placement or issue: a list whose filter names a missing field shows nothing, so every story would leave the site. Keep "Hide from the visitor (staff only)" switched on for the six office fields, or an author could set a story live. Statuses, placements and issues are words the lists filter on: only an administrator or designer can change one, in the visual editor; then publish, and set the new word on the stories that had the old one. A new list or story view of this form on another page needs the same "live" filters.'),
    lang('A photo uploaded with a story opens only for the editors while the story is not live. Before you set a story live, check the name on the story, which decides the contributor page and feed the story joins; then remove pictures that load from other sites and check every link in its text: pictures and links are printed as written, for every reader, and the pictures already load when you open the story.'),
    lang('A reader\'s letter is signed with the first and last name on the reader\'s account, or with Member when the account has no name. Before you publish a letter, open it under Comments in the control panel, check the name and change it if needed, for example to Anonymous.'),
);
// The notes in two columns from lg: the first half, then the rest.
$staff_note_cols = array();
foreach (array_chunk($staff_notes, (int)ceil(count($staff_notes) / 2)) as $half) {
    $items = array();
    foreach ($half as $text) $items[] = $el('li', 'mb-2', lang('Note'), array(), array('text' => $text));
    $staff_note_cols[] = $col(array($el('ul', 'small mb-0 ps-3', lang('Notes'), $items)), '', '6');
}
$pages[] = array(
    'key'     => 'staff',
    'name'    => lang('staff'),
    'folder'  => 'private',
    'title'   => lang('Editors\' desk'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'pm-band pb-4 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h1', lang('Editors\' desk'), 'pm-title display-6 mb-3'),
                        $para(lang('Stories waiting for you, the ones back with their writers, and what readers see now.'), 'pm-dek lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('Write a story'), '{{page:blog_new}}', 'btn btn-secondary'),
                        )),
                    ), '', '8'),
                ), '4'),
            )),
        )),
        // The jump list: plain anchors, outside any smart active menu (it
        // would mark every one of them as the current page).
        $el('nav', 'border-bottom', lang('On this page'), array(
            $el('div', 'container', lang('Container'), array(
                $el('ul', 'nav nav-underline column-gap-4 small', lang('Links'), array(
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('Waiting for review'), '#waiting', 'nav-link'))),
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('With the writer'), '#with-the-writer', 'nav-link'))),
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('Every story'), '#every-story', 'nav-link'))),
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('How the desk works'), '#how-the-desk-works', 'nav-link'))),
                )),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('On this page'))))),
        $band(lang('Desk'), array(
            $row(array(
                $desk_section('waiting', lang('Waiting for review'), lang('New stories, the oldest first.'), 'staff_queue'),
                $desk_section('being-edited', lang('Being edited'), lang('Stories an editor is reading now.'), 'staff_in_progress'),
                $desk_section('with-the-writer', lang('With the writer'), lang('Stories you sent back for changes. Look at the last activity: a quiet one may need a word.'), 'staff_with_writer'),
                $desk_section('scheduled', lang('Scheduled to go live'), lang('Live stories whose publication time has not come yet, the next first.'), 'staff_scheduled'),
                $desk_section('no-time', lang('Live, but no publication time'), lang('Set live without a publication time, so readers do not see them yet. Give each one a time.'), 'staff_no_time'),
                $desk_section('live-now', lang('Live now'), lang('What readers see, the newest first. A row opens the story as readers see it.'), 'staff_published'),
                $desk_section('every-story', lang('Every story'), lang('Every story sent to the magazine, in any status, with a search.'), 'staff_all', ''),
            ), '4', '', 'g-lg-5'),
        )),
        $band(lang('Shortcuts'), array(
            $sec_head(lang('Shortcuts')),
            $row(array(
                $staff_shortcut('bi-pencil-square', lang('Write a story'), lang('The author desk\'s form; what you send waits for review like any story.'), '{{page:blog_new}}'),
                $staff_shortcut('bi-inbox', lang('Inbox'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                $staff_shortcut('bi-person-lines-fill', lang('Staff Directory'), lang('The magazine\'s people and how to reach them.'), '{{page:staff_directory}}'),
                $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the magazine\'s directory.'), '{{page:staff_entry}}'),
                $staff_shortcut('bi-speedometer2', lang('Control panel'), lang('Statuses, publication times, issues, the newsletter and every form\'s records.'), $panel_url),
            ), '4'),
        ), 'pm-band pt-0'),
        // How the desk works: who gets access, how a story is published and
        // what must not change. Under the lists, so they come first; the
        // jump list leads here.
        $band(lang('Access Note'), array(
            $sec_head(lang('How the desk works')),
            $row($staff_note_cols, '4', '', 'g-lg-5'),
        ), 'pm-band pt-0', array('id' => 'how-the-desk-works')),
    )),
);
// The magazine's directory, for the editors only (it lists e-mails and
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
        $page_header(lang('Staff Directory'), lang('The magazine\'s editors, writers and staff, and how to reach them. Only the editors see this page.')),
        $band(lang('Directory'), array($widget('staff_directory')), 'pm-band pt-0'),
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
        'form_name'            => lang('Magazine Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('Add to the Staff Directory'), lang('Add a colleague to the magazine\'s directory. Only the editors see it.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'p-4 border bg-body', lang('Form Card'), array($widget('staff_form'))),
                ), '', '8'),
            ), '', 'center'),
        ), 'pm-band pt-0'),
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

// The forms' e-mails: each form's notification to the editors and its
// reply to whoever sent it. Their widgets show the record just sent.
$mail_pages = array(
    // The Magazine Stories form: to the editors, and to the signed-in author.
    array('email_article_new', lang('email-new-story'), lang('New Story E-mail'), 'email_article_admin'),
    array('email_article_received', lang('email-story-received'), lang('Story Received E-mail'), 'email_article_author'),
    // The contact form: to the editors, and to the sender.
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
// The comment e-mails. The System region carries the message and its "View
// or Reply" button. A message under a story sent to the magazine goes to
// its author and to every editor who follows it, with the same words for
// both and for every status (it is also how an author hears a story is
// live).
$mail_system = array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array());
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_desk_message',
    'name'  => lang('email-editors-message'),
    'title' => lang('Editors\' Message E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('New message about a story sent to the magazine')),
        $mail_para(lang('There is a new message under a story sent to the magazine.')),
        $mail_system,
    )),
));
// A new letter under a story the reader follows, or a new reply in a
// conversation with the editors.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: a story you wrote a letter under, or your conversation with the editors. Read it below and answer on the page.')),
        $mail_system,
    )),
));
// The newsletter's welcome, sent to a new subscriber.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to The Weekend Read')),
        $mail_para(lang('Thank you for subscribing. Once a week you get one long read, one essay and a letter from the editor.')),
        $mail_small(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.')),
        $mail_button(lang('Read the magazine'), '{{page:home}}'),
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
// $flag shows the link only while that widget flag is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};
// The reader's account menu. The staff directory is not in it: it lists
// the editors' e-mails and phones and is for the editors only.
$account_links = array(
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-envelope-paper', lang('Newsletter'), '{{page:newsletter}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-check', lang('Email Preferences'), '#', '__email_preferences_url');

// A card of the account page: an icon, a title, a line and its buttons.
$account_card = function ($icon_name, $title, $text, $buttons, $extra = array()) use ($el, $heading, $para, $icon) {
    return $el('div', 'p-4 border mb-4', $title, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $icon($icon_name, 'fs-2 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h5 pm-title mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        )),
    ), $extra);
};

$widgets = array(
    // The sign-in in the masthead's top row: two links for a visitor, the
    // account menu for a member. "Editors' desk" is drawn for whoever the
    // staff page's private folder lets in.
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
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-secondary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 px-0 py-1 border-0 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/4a6b53/ffffff?text=JC', '', 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url'))),
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
                    $login_page_item(lang('My Stories'), '{{page:my_articles}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Editors\' desk'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // The account page: who is signed in, the account menu, and, for whoever
    // the author desk's folder lets in, the way to the author desk (its
    // staff page is My Stories: the flag is_staff follows that folder).
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:my_articles}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 pm-paper mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 pm-title mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card('bi-pencil-square', lang('Your author desk'), lang('Send a story to the editors and follow it until it goes live.'), array(
                            $link(lang('My Stories'), '{{page:my_articles}}', 'btn btn-primary'),
                            $link(lang('Submit a Story'), '{{page:blog_new}}', 'btn btn-secondary'),
                        ), $show_when('is_staff')),
                        $account_card('bi-chat-left-text', lang('Letters and conversations'), lang('Write letters under our stories, follow your conversations with the editors and choose the e-mails you get.'), array(
                            $link(lang('Inbox'), '{{page:inbox}}', 'btn btn-primary'),
                            $link(lang('Newsletter'), '{{page:newsletter}}', 'btn btn-secondary'),
                        )),
                        $heading('h2', lang('My Orders'), 'h5 pm-title mb-3', $show_when('has_orders')),
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
            $mail_para(lang('Your reader account is ready. Write letters under our stories, follow your conversations and manage The Weekend Read from your account.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_small(lang('Thank you for reading us.')),
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

// ── The lists of stories ────────────────────────────────────────────────
// Every public list carries the live rule ($live) before its own filters.
// Lists on one page share the page_number and query parameters, so only
// one list of a page pages through its stories (max_results 0); every
// other list shows a fixed number. A list's page links come after its rows,
// inside the rows' parent (the template's rules make them span a grid).
$article_list = function ($page_key, $ipp, $max, $more, $empty, $tree, $extra = array()) use ($pub, $with_live, $widget_root) {
    $config = array_merge($pub, array('items_per_page' => $ipp, 'max_results' => $max, 'filters' => $with_live($more)), $extra);
    if ($empty !== '') $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};

// The home page. The cover, the quote and the letter show one story each:
// the newest that fits. Their rows sit in a pm-one box, so the installer's
// preview, which repeats every list three times, shows one as well.
$widgets['home_cover'] = $article_list('home', 1, 1, array($is_placement($placements[1])),
    lang('Mark a live story as the Cover story and it appears here.'),
    array($el('div', 'pm-one', $placements[1], array($loop(array($cover_card($head(1))))))));
// The latest: the six newest stories that have no place of their own on
// the home page (the cover, the letter and the picks have theirs), so no
// story shows twice.
$widgets['home_posts'] = $article_list('home', 6, 6, array(
        $not_placement($placements[1]), $not_placement($placements[2]), $not_placement($placements[3]),
    ),
    lang('No live stories yet.'),
    array($el('div', 'row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 g-lg-5', lang('The latest'), array(
        $loop(array($el('div', 'col', lang('Column'), array($portrait_card($head(3)))))),
    ))));
// A line from the newest cover story that has one.
$widgets['home_quote'] = $article_list('home', 1, 1, array($is_placement($placements[1]), $has_quote),
    lang('Give the Cover story a line to set apart and it appears here.'),
    array($el('div', 'pm-one', lang('Quote'), array($loop(array(
        $quote_card($head(1), lang('The only thing that moves is the pie, which depends on what the orchard down the road has too much of.')),
    ))))));
$widgets['home_letter'] = $article_list('home', 1, 1, array($is_placement($placements[2])),
    lang('Mark a live story as the Editor\'s letter and it appears here.'),
    array($el('div', 'pm-one', $placements[2], array($loop(array($letter_card($head(0))))))));
// The editors' picks: a shelf that scrolls sideways, a card and a part of
// the next in view. The shelf takes keyboard focus, so the arrow keys
// scroll it.
$widgets['home_picks'] = $article_list('home', 6, 6, array($is_placement($placements[3])),
    lang('Mark live stories as Editors\' pick and they appear here.'),
    array($el('div', 'row g-4 flex-nowrap overflow-x-auto pb-3 pg-snap pm-shelf', lang('Editors\' picks'), array(
        $loop(array($el('div', 'col-10 col-sm-6 col-lg-4 pg-snap-item', lang('Column'), array($landscape_card($head(2)))))),
    ), array('_attrs' => array($attr('role', 'region'), $attr('aria-label', lang('Editors\' picks')), $attr('tabindex', '0'))))));
// The newest issues: the live Editor's letters of real issues, each drawn
// as its issue (one letter per issue).
$widgets['home_issues'] = $article_list('home', 3, 3, array($is_placement($placements[2]), $in_an_issue),
    lang('Mark a live story of an issue as its Editor\'s letter and the issue appears here.'),
    array($el('div', 'row row-cols-1 row-cols-md-3 g-4 g-lg-5', lang('Recent issues'), array(
        $loop(array($el('div', 'col', lang('Column'), array($issue_card(false))))),
    ))));

// The most read: the live stories of the last 90 days that were opened
// most in the last 30 (the views the story page counts), the newest first
// among equals. Placed beside Every story and under the story.
$widgets['most_read'] = array(
    'page'   => '',
    'slug'   => lang('most-read'),
    'config' => array_merge($pub, array(
        'items_per_page'       => 5,
        'max_results'          => 5,
        'order_by_field'       => 'recent_views',
        'order_by_direction'   => 'DESC',
        'order_recent_days'    => 30,
        'order_by_2_field'     => 'publish_date',
        'order_by_2_direction' => 'DESC',
        'filters'              => $with_live(array($recent(90))),
        'empty_message'        => lang('The most read stories appear here.'),
    )),
    'tree'   => $widget_root(array(
        $el('aside', '', lang('Most read'), array(
            $el('div', 'pm-sec-head', lang('Section Header'), array(
                $el('h2', 'pm-label', lang('Title'), array(), array('id' => 'pm-most-read', 'text' => lang('Most read'))),
            )),
            $el('ol', 'pm-rank list-unstyled mb-0', lang('Ranking'), array(
                $loop(array(
                    $el('li', 'pm-rank-item pm-card d-flex gap-3 py-3 position-relative', lang('Card'), array(
                        $el('span', 'pm-rank-num', lang('Rank Number'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $el('div', 'pm-min-0', lang('Words'), array(
                            $card_kicker($head(3), 'mb-1'),
                            $card_title('h3', 'h6 mb-0', $head(3)),
                        )),
                    )),
                )),
            ), array('_attrs' => array($attr('role', 'list')))),
        ), array('_attrs' => array($attr('aria-labelledby', 'pm-most-read')))),
    )),
);

// A page for each section: the section's live stories, ten to a page, the
// newest drawn wide; each card names its issue.
foreach ($sections as $key => $s) {
    $widgets['section_' . $key] = $article_list($key, 10, 0, array($in_section($s[0])),
        lang('No live stories in this section yet.'),
        array($el('div', 'pm-river', lang('List'), array($loop(array($river_card('h2', 'issue', $head_of_section($key))))))));
}
// Every story: every live story, with a search over the title, the
// summary, the byline, the topics and the issue.
$widgets['blog_list'] = $article_list('blog', 12, 0, array(),
    lang('Nothing matched. Try other words.'),
    array($el('div', '', lang('List'), array($loop(array($index_row($head(0))))))),
    array(
        'search_enabled' => true,
        'search_fields'  => array('title', 'summary', 'byline', 'tags', 'issue'),
        'search_width'   => 'full',
        'search_label'   => lang('Search every story…'),
    ));
// An issue's contents: the live stories whose issue equals ?issue= exactly,
// numbered, latest publication time first: the staff note launches an issue
// with the cover a minute after its stories and the letter a minute after
// the cover, so the letter heads them (on equal times the story that
// reached the editors last comes first). Without a value the block
// and its list stay off the page, which keeps its heading and the issues.
// A value no live story carries does the same and keeps the page out of
// the index. The page title is the issue's name alone: it says "Issue".
$widgets['issue_list'] = $article_list('issues', 30, 30, array($f('issue', 'is equal to', '', 'url parameter', 'issue')),
    '',
    array($el('div', 'mb-5', lang('Contents'), array(
        $para(lang('Contents'), 'pm-kicker mb-2 text-center'),
        $heading('h2', $issues['small-places'], 'pm-display text-center mb-5', $bind('__filter_value')),
        $el('ol', 'pm-rank list-unstyled mb-0', lang('Ranking'), array(
            $loop(array($contents_row($head(0)))),
        ), array('_attrs' => array($attr('role', 'list')))),
    ), $show_when('has_filter_value'))));
// Every issue: the live Editor's letters of the issues, twelve to a page,
// the page's one list with page links.
$widgets['issue_index'] = $article_list('issues', 12, 0, array($is_placement($placements[2]), $in_an_issue),
    lang('No issue has been published yet.'),
    array($el('div', 'row row-cols-1 row-cols-md-3 g-4 g-lg-5 pm-issue-grid', lang('The issues so far'), array(
        $loop(array($el('div', 'col', lang('Column'), array($issue_card(true))))),
    ))));
// A contributor's own stories: the byline must equal ?author= exactly.
// Without a value the block and its list stay off the page, which keeps its
// heading and the directory. A value no live story carries does the same
// and keeps the page out of the index. While a value matches, the page
// title reads "Stories by <name>".
$widgets['author_list'] = $article_list('author', 10, 0, array($f('byline', 'is equal to', '', 'url parameter', 'author')),
    '',
    array($el('div', 'mb-5', lang('Contributor\'s Stories'), array(
        $para(lang('Author'), 'pm-kicker mb-2 text-center'),
        $heading('h2', $contributor_name('Nora Whitfield'), 'pm-display text-center mb-5', $bind('__filter_value')),
        $el('div', 'pm-river', lang('List'), array($loop(array($river_card('h3', 'section', $head(1)))))),
    ), $show_when('has_filter_value'))),
    array('filter_title' => lang('Stories by {value}')));
// Under the story: the four newest live stories (the open one may be among
// them: a list cannot leave the record of the page out). Two to a row from
// the phone up, so four cards do not stack into a long column.
$widgets['article_more'] = $article_list('blog_post', 4, 4, array(),
    lang('No live stories yet.'),
    array($el('div', 'row row-cols-2 g-3 g-lg-4', lang('List'), array(
        $loop(array($el('div', 'col', lang('Column'), array($landscape_card($head(2)))))),
    ))));

// The story: shown while it is live (the same three filters as the lists:
// anything else is "not found" and not indexed). Its title is the page's
// title; its summary, cover and section give the description, the picture
// and the category of the head and the feed. The title is centred over a
// photograph across the page; the text has the reading measure, a drop cap
// and an end mark; the line set apart stands beside the text from xl (and
// stays in view while the opening scrolls), above it below xl. It repeats a
// sentence of the text, so screen readers skip it.
$story = $head(1);
$widgets['blog_post'] = array(
    'page'   => 'blog_post',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'access_control'      => 'public',
        'filters'             => $live,
        'title_field'         => 'title',
        'not_found_message'   => lang('This story is not available. It may not be published yet, or it was removed.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'container pm-band text-center', lang('Not Found'), array(
            $icon('bi-journal-x', 'display-5 text-body-secondary'),
            $heading('h1', lang('Story not available'), 'pm-title h2 mt-3'),
            $para(lang('This story is not available. It may not be published yet, or it was removed.'), 'text-body-secondary mb-4', $bind('not_found')),
            $link(lang('Back to every story'), '{{page:blog}}', 'btn btn-primary px-4'),
        ), $show_when('record_not_found')),
        $el('article', '', lang('Magazine story'), array(
            $container(array(
                $row(array(
                    $el('header', 'col-lg-10 col-xl-8 text-center pt-5 pb-4', lang('Story Header'), array(
                        $el('div', 'mb-3 text-center', lang('Edit'), array($edit_button(lang('Change Status')))),
                        $el('p', 'pm-meta mb-3', lang('Kicker'), array(
                            $span($story['section'], 'pm-kicker', lang('Section'), $bind('section')),
                            $issue_link($story),
                        )),
                        $heading('h1', $story['title'], 'pm-display mb-4', $bind('title')),
                        $para($story['summary'], 'pm-dek lead pm-standfirst mb-4', $bind('summary')),
                        $el('p', 'pm-meta mb-0', lang('Byline and Time'), array(
                            $span(lang('Written by'), 'visually-hidden', lang('Label')),
                            $span($story['byline'], 'fw-semibold text-body', lang('Byline'), $bind('byline')),
                            $dot(),
                            $span(lang('Date'), '', lang('Date'), $bind('publish_date')),
                            $reading_time(),
                        )),
                    )),
                ), '', 'center'),
            )),
            // Across the page; the story's largest element, so it is not
            // loaded lazily.
            $el('figure', 'mb-0 pg-hide-if-empty', lang('Cover Image'), array(
                $el('span', 'pg-field-value', lang('Picture Frame'), array(
                    $image($story['cover'], $story['title'], 'pm-hero-img', array('_bindings' => array('src' => 'cover_image', 'alt' => 'title'))),
                )),
                $el('figcaption', 'container pm-meta mt-2 text-lg-end', lang('Caption'), array(
                    $el('span', 'pg-hide-if-empty', lang('Photo caption'), array(
                        $span(lang('Replace this picture with your own photo and caption.'), 'pg-field-value', lang('Value'), $bind('image_caption')),
                    )),
                    $el('span', 'pg-hide-if-empty ms-2', lang('Photo credit'), array(
                        $span(lang('Photo:'), 'me-1', lang('Label')),
                        $span(lang('Sample photo'), 'pg-field-value', lang('Value'), $bind('image_credit')),
                    )),
                )),
            )),
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'pm-measure', lang('Reading Measure'), array(
                            // The story text is rich text: a div holds what the
                            // author wrote, contained by the template's rules.
                            $el('div', 'pm-body pm-dropcap pm-endmark', lang('Story Text'), array(), array_merge(
                                array('text' => lang('The story text appears here.')), $bind('body'))),
                            $el('aside', 'pm-correction pg-hide-if-empty my-5', lang('Correction'), array(
                                $para(lang('Correction'), 'fw-semibold mb-1 pm-sans'),
                                $el('div', 'pg-field-value', lang('Value'), array(), array_merge(
                                    array('text' => lang('An earlier version of this story gave the wrong number of books in the collection.')), $bind('correction'))),
                            ), array('_attrs' => array($attr('aria-label', lang('Correction'))))),
                            $el('p', 'pm-meta pg-hide-if-empty mt-5', lang('Topics'), array(
                                $span(lang('Topics:'), 'fw-semibold me-1', lang('Label')),
                                $span(lang('diners, breakfast, small towns'), 'pg-field-value', lang('Value'), $bind('tags')),
                            )),
                            $el('p', 'pm-meta', lang('Corrections Note'), array(
                                $span(lang('Spotted an error?'), 'me-1', lang('Text')),
                                $link(lang('Ask for a correction'), '{{page:contact}}', ''),
                                $dot(),
                                $link(lang('How we work'), '{{page:about}}#how-we-work', ''),
                            )),
                            $ai_summary(),
                            $el('aside', 'd-flex gap-3 align-items-start border-top border-bottom py-4 mt-5', lang('About the writer'), array(
                                $optional_image('byline_photo', '', 'rounded-circle object-fit-cover pg-circle-60', 'flex-shrink-0', $portrait($story['byline'], 192), '', $lazy),
                                $el('div', 'pm-min-0', lang('Words'), array(
                                    $para(lang('About the writer'), 'pm-kicker mb-1'),
                                    $para($story['byline'], 'pm-title h5 mb-1', $bind('byline')),
                                    $para($story['bio'], 'pm-dek mb-2 pg-hide-if-empty', $bind('byline_bio')),
                                    $link(lang('Meet our contributors'), '{{page:author}}#contributors', 'pm-meta'),
                                )),
                            ), array('_attrs' => array($attr('aria-label', lang('About the writer'))))),
                        )),
                    ), '', '8', '', '12', 'col-xl-7 offset-xl-1'),
                    // The line set apart: after the text in the source,
                    // shown first below xl.
                    $col(array(
                        $el('div', 'sticky-xl-top pt-xl-2 pm-measure', lang('Pull Quote'), array(
                            $el('figure', 'pm-quote', lang('Quote'), array(
                                $el('blockquote', 'mb-0', lang('Quote Text'), array(
                                    $para(lang('The only thing that moves is the pie, which depends on what the orchard down the road has too much of.'), 'mb-0 pg-field-value', $bind('pull_quote')),
                                )),
                            ), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        )),
                    ), '', '', '', '12', 'col-xl-3 order-first order-xl-last pg-hide-if-empty'),
                    // From xl the text starts one column in whether or not
                    // the optional quote prints, so it stays in line with
                    // the letters under it.
                ), '4', 'center', 'g-xl-5 justify-content-xl-start'),
            ), 'pm-band pt-4'),
        ), $show_when('record_found')),
        // The letters' heading, in the reading column; the page puts the
        // letters right under it. It leaves with a missing story.
        $el('div', 'container d-print-none', lang('Letters'), array(
            $row(array(
                $col(array(
                    $el('div', 'pm-measure', lang('Reading Measure'), array(
                        $heading('h2', lang('Letters'), 'pm-label mb-2'),
                        $para(lang('Letters are read by the editors before they appear.'), 'pm-meta mb-4'),
                    )),
                ), '', '8', '', '12', 'col-xl-7 offset-xl-1'),
            ), '4', 'center', 'g-xl-5 justify-content-xl-start'),
        ), $show_when('record_found')),
    )),
);

// ── The forms ───────────────────────────────────────────────────────────

// The Magazine Stories form, on the author desk. What an author fills in
// sits in four groups; the fields after them are for the editors only
// (office use): each in a box of its own with its label and no hint, so an
// author's form keeps nothing of them. A new story stores their selected
// option or nothing: Awaiting review, Standard, Online only, no publication
// time. Every option of an office pick list is a stored answer (no empty
// prompt): the control panel prints only stored options, so an empty one
// would show the first answer and save it on the next change of status.
$widgets['article_form'] = array(
    'page'   => 'blog_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $fieldset(lang('Your story'), array(
            $field('mg-section', lang('Section'), pg_cf_with_role($select('mg-section', 'section', lang('Choose a section…'), $section_titles), 'category')),
            $field('mg-title', lang('Title'), pg_cf_with_role($input('mg-title', 'text', 'title', 'off', true, array(), array('maxlength' => '120')), 'title')),
            $field_help('mg-summary', lang('Summary'), pg_cf_with_role($textarea('mg-summary', 'summary', 3, true, '', array(), array('maxlength' => '300')), 'description'),
                lang('One or two sentences readers see under the title.')),
            $field_help('mg-body', lang('Text of the story'), $textarea('mg-body', 'body', 24, true, '', array('wysiwyg' => true)),
                lang('Start with a paragraph. Subheads, italics, links and lists are kept.')),
            $field_help('mg-pull-quote', lang('A line to set apart (optional)'), $textarea('mg-pull-quote', 'pull_quote', 2, false, '', array(), array('maxlength' => '240')),
                lang('One sentence from your story that we may print large beside it.')),
            $field_help('mg-tags', lang('Topics'), $input('mg-tags', 'text', 'tags', 'off', false), lang('Separate them with commas.')),
        )),
        $fieldset(lang('Photo'), array(
            $field_help('mg-cover', lang('Cover Image'), pg_cf_with_role($file('mg-cover', 'cover_image', 'public', 'image/*'), 'media'),
                lang('A wide photo, at least 1600 pixels across, that you may publish.')),
            $field('mg-caption', lang('Photo caption'), $input('mg-caption', 'text', 'image_caption', 'off', false)),
            $field('mg-credit', lang('Photo credit'), $input('mg-credit', 'text', 'image_credit', 'off', false, array(), array('placeholder' => lang('Who took the photo')))),
        )),
        $fieldset(lang('About you'), array(
            $field('mg-byline', lang('Your name as readers will see it'), $input('mg-byline', 'text', 'byline', 'name', true)),
            $field('mg-byline-bio', lang('One line about you (optional)'), $input('mg-byline-bio', 'text', 'byline_bio', 'off', false, array(), array('maxlength' => '160'))),
            $field('mg-byline-photo', lang('Your photo (optional)'), $file('mg-byline-photo', 'byline_photo', 'public', 'image/*')),
        )),
        $fieldset(lang('For the editors'), array(
            $field('mg-note', lang('A note for the editors (not published)'), $textarea('mg-note', 'note_to_editors', 3, false)),
        )),
        $agree('mg-rights', 'rights', lang('I wrote this story myself, I may publish its photos, and the editors may edit it.')),
        // For the editors only.
        $field('mg-status', lang('Status'), $office($select('mg-status', 'status', '', $statuses, false, $statuses[0]))),
        $field('mg-publish', lang('Publication date and time (a future time schedules the story)'), $office($input('mg-publish', 'datetime-local', 'publish_date', '', false))),
        $field('mg-placement', lang('Placement on the home page'), $office($select('mg-placement', 'placement', '', $placements, false, $placements[0]))),
        $field('mg-issue', lang('Part of the issue'), $office($select('mg-issue', 'issue', '', $issue_options, false, $online_only))),
        $field('mg-reading-time', lang('Reading time (minutes)'), $office($input('mg-reading-time', 'text', 'reading_time', 'off', false, array(), array('inputmode' => 'numeric')))),
        $field('mg-correction', lang('Correction (shown under the story)'), $office($textarea('mg-correction', 'correction', 2, false))),
        $captcha(),
        $submit(lang('Send to the editors'), 'btn btn-primary btn-lg px-4'),
        $loop(array()),
    )),
);

// The newsletter's own form, on its page.
$widgets['newsletter_form'] = array(
    'page'   => 'newsletter',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array_merge(array(
        $heading('h2', lang('Get it by e-mail'), 'h4 pm-title mb-2'),
        $para(lang('Leave your e-mail address and The Weekend Read reaches you once a week.'), 'text-body-secondary mb-4'),
    ), $newsletter_fields('mg-news'), array(
        $loop(array()),
    ))),
);
// The newsletter page's form again, as a paper band on the home page and
// under every story, so every sign-up lands in one list. It carries the
// CAPTCHA: a visitor's sign-up is checked against it whenever it is on.
// Placed once per page, so its ids are unique. Left out of a printed page.
$widgets['newsletter_band'] = array(
    'page'   => '',
    'slug'   => lang('newsletter'),
    'config' => array(
        'regionType'          => 'custom_form',
        'form_source'         => 'existing',
        'form_id'             => '{{tab:newsletter}}',
        'custom_form_page_id' => '{{tab:newsletter}}',
    ),
    'tree'   => $widget_root(array(
        $el('section', 'pm-band pm-paper d-print-none', lang('Newsletter Band'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Newsletter'), 'pm-kicker mb-2'),
                        $el('h2', 'pm-display mb-3', lang('Title'), array(), array('id' => 'pm-band-title', 'text' => lang('The Weekend Read'))),
                        $para(lang('One long read, one essay and a letter from the editor, by e-mail.'), 'pm-dek mb-4'),
                        $el('div', 'pm-measure text-start', lang('Fields'), $newsletter_fields('mg-band')),
                    ), '', '7', '', '12', 'text-center'),
                ), '', 'center'),
            )),
        ), array('_attrs' => array($attr('aria-labelledby', 'pm-band-title')))),
        $loop(array()),
    )),
);

// Contact: the fields are the page's form. A letter is what readers write
// under a story; here they write a message to the editors.
$widgets['contact_form'] = array(
    'page'   => 'contact',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $person_fields('mg-contact', false, false),
        $field('mg-contact-topic', lang('What is it about?'), $select('mg-contact-topic', 'topic', lang('Choose a topic…'), array(
            lang('A message for the editors'),
            lang('A story idea'),
            lang('Ask for an author account'),
            lang('Report an error'),
            lang('Something else'),
        ))),
        $field('mg-contact-message', lang('Your message'), $textarea('mg-contact-message', 'message', 6)),
        $captcha(),
        $submit(lang('Send the message')),
        $loop(array()),
    )),
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
                    '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the magazine…')), $attr('aria-label', lang('Search the magazine'))),
                    '_bindings' => array('value' => 'search_query'),
                )),
                $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                    '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                    '_bindings' => array('action' => 'submit_search'),
                )),
            )),
        ), array('_bindings' => array('action' => 'search_form'))),
        $para(lang('Results heading'), 'pm-meta fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
        $el('div', '', lang('Results'), array(
            $loop(array(
                $el('article', 'pm-card py-4 border-bottom position-relative', lang('Result Row'), array(
                    $span(lang('Page'), 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                    $el('h2', 'pm-title h4 mb-1', lang('Result Title'), array(
                        $link(lang('Title'), '#', 'stretched-link', array(),
                            array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                    )),
                    $para(lang('How we work'), 'pm-kicker mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                    $para(lang('Excerpt'), 'pm-dek mb-0', $bind('__result_excerpt')),
                )),
            )),
        )),
        $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
    )),
);

// ── The author desk and the editors' desk ───────────────────────────────

// A list of the desk: the Magazine Stories form's records, opening the
// submission page. These lists sit on private pages and carry no live
// rule: they show the stories in their state on the desk.
$desk_list = function ($page_key, $ipp, $max, $order, $direction, $filters, $empty, $tree, $extra = array()) use ($widget_root) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'detail_page_id'      => '{{tab:submission}}',
        'items_per_page'      => $ipp,
        'max_results'         => $max,
        'order_by_field'      => $order,
        'order_by_direction'  => $direction,
    );
    if ($filters) $config['filters'] = $filters;
    $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => array_merge($config, $extra), 'tree' => $widget_root($tree));
};
$desk_rows = function ($with_writer) use ($el, $loop, $desk_row) {
    return array($el('div', 'list-group', lang('List'), array($loop(array($desk_row($with_writer))))));
};
// My Stories shows each author their own stories only: an editor's view of
// everybody's is the editors' desk.
$own_articles = array(
    'viewer_filter'           => true,
    'viewer_filter_submitter' => true,
    'viewer_filter_watcher'   => false,
    'viewer_filter_editor'    => false,
);

// My Stories: the author's own stories in every status, newest first.
$widgets['my_articles_list'] = $desk_list('my_articles', 20, 0, 'submitted_date_and_time', 'DESC', array(),
    lang('Nothing sent yet. Your first story is one form away.'),
    $desk_rows(false), $own_articles);
// Beside it: the author's stories that readers can see now, each opening
// the public story. The flush list draws a rule between two stories only.
$widgets['my_articles_live'] = array(
    'page'   => 'my_articles',
    'slug'   => lang('form-list'),
    'config' => array_merge($pub, $own_articles, array(
        'items_per_page' => 5,
        'max_results'    => 5,
        'filters'        => $live,
        'empty_message'  => lang('None of your stories is live yet.'),
    )),
    'tree'   => $widget_root(array(
        $el('div', 'list-group list-group-flush', lang('List'), array($loop(array(
            $el('article', 'list-group-item px-0 py-2 position-relative', lang('Card'), array(
                $card_title('h3', 'h6 mb-1', $head(3)),
                $el('p', 'pm-meta mb-0', lang('Time'), array(
                    $span(lang('2 hours ago'), '', lang('Time'), $bind_date('publish_date', 'relative')),
                )),
            )),
        )))),
    )),
);

// One story on the desk, for its author (the browser that sent it, or the
// account) and the editors: everything the author sent, the note for the
// editors, the status, and the rich text in a box of its own (what an
// author writes cannot reach outside it). No drop cap and no end mark:
// this is the text under review, not the printed story.
$sample = $head(3);
$widgets['submission_view'] = array(
    'page'   => 'submission',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'access_control'      => 'submitter_only',
        'title_field'         => 'title',
        'title_suffix'        => lang('| Editors\' desk'),
        'not_found_message'   => lang('This story could not be found, or it is not yours to see.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-journal-x', 'display-5 text-body-secondary'),
                        $heading('h1', lang('Story not found'), 'h3 pm-title mt-3'),
                        $para(lang('This story could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
                        $link(lang('My Stories'), '{{page:my_articles}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Magazine story'), array_merge(
                        $sent_notices(lang('Thank you. Your story reached the editors.')),
                        array(
                            $record_toolbar(lang('My Stories'), 'my_articles', lang('Change Status')),
                            $el('div', 'card mb-4', lang('Record Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', 'pm-min-0', lang('Words'), array(
                                            $para($sample['section'], 'pm-kicker mb-1', $bind('section')),
                                            $heading('h1', $sample['title'], 'h3 pm-title mb-0', $bind('title')),
                                        )),
                                        $status_badge($statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6'),
                                    )),
                                    $el('dl', 'small mb-0 pm-sans', lang('Details'), array(
                                        $record_detail(lang('Your name as readers will see it'), $sample['byline'], 'byline', false, 'fw-semibold'),
                                        $record_detail(lang('Part of the issue'), $sample['issue'], 'issue', true),
                                        $record_detail(lang('Publication date and time'), lang('Date'), 'publish_date', true, 'fw-semibold'),
                                        $record_detail(lang('Sent'), lang('Date'), 'submitted_date_and_time'),
                                        $el('div', 'row mb-1', lang('Last activity'), array(
                                            $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Last activity'))),
                                            $el('dd', 'col-sm-8 mb-0', lang('Value'), array(), array_merge(
                                                array('text' => lang('2 hours ago')), $bind_date('newest_activity_date_and_time', 'relative'))),
                                        )),
                                        $record_detail(lang('Reference Code'), 'ABC123', 'reference_code', false, 'font-monospace'),
                                    )),
                                )),
                            )),
                            $record_words(lang('Summary'), $sample['summary'], 'summary'),
                            $record_words(lang('A line to set apart'), lang('It feels more like a living room that happens to lend books.'), 'pull_quote'),
                            $record_words(lang('A note for the editors'), lang('I can send more photos of the reading chair if you need them.'), 'note_to_editors'),
                            $record_words(lang('Correction'), lang('An earlier version of this story gave the wrong number of books in the collection.'), 'correction'),
                            $para(lang('Text of the story'), 'small fw-semibold text-body-secondary mb-2 pm-sans'),
                            $el('div', 'pm-body mw-100 p-4 border mb-4', lang('Text of the story'), array(), array_merge(
                                array('text' => lang('The story text appears here.')), $bind('body'))),
                            $el('div', 'pg-hide-if-empty mb-4', lang('Cover Image'), array(
                                $para(lang('Cover Image'), 'small fw-semibold text-body-secondary mb-2 pm-sans'),
                                $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                                    $el('div', 'ratio ratio-16x9 pm-media', lang('Picture'), array(
                                        $image($sample['cover'], $sample['title'], 'object-fit-cover',
                                            array('_bindings' => array('src' => 'cover_image', 'alt' => 'title'))),
                                    )),
                                )),
                            )),
                            // The heading of the messages the page puts
                            // right below; gone with a missing story.
                            $heading('h2', lang('Messages with the editors'), 'h4 pm-title mb-2'),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $el('div', '', lang('Text'), array(
                                    $para(lang('Write to the editors below. They answer here and by e-mail.'), 'mb-1'),
                                    $para(lang('To change your story or take it back, write to the editors below; an editor makes the change.'), 'mb-0'),
                                )),
                            )),
                        )
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'pt-5 pb-3'),
    )),
);

// The editors' desk lists. Only the last one, every story, pages through;
// the others show a fixed number. A story whose status is none of the six
// (a word typed by hand) still shows in Every story.
$widgets['staff_queue'] = $desk_list('staff', 20, 20, 'submitted_date_and_time', 'ASC',
    array($f('status', 'is equal to', $statuses[0])),
    lang('No story is waiting for review.'),
    $desk_rows(true));
$widgets['staff_in_progress'] = $desk_list('staff', 20, 20, 'submitted_date_and_time', 'ASC',
    array($f('status', 'is equal to', $statuses[1])),
    lang('No story is being edited.'),
    $desk_rows(true));
// Back with the writer: a story can wait weeks there, so the rows' last
// activity tells the editors which one has gone quiet.
$widgets['staff_with_writer'] = $desk_list('staff', 20, 20, 'submitted_date_and_time', 'ASC',
    array($f('status', 'is equal to', $statuses[2])),
    lang('No story is back with its writer.'),
    $desk_rows(true));
$widgets['staff_scheduled'] = $desk_list('staff', 10, 10, 'publish_date', 'ASC',
    array(
        $f('status', 'is equal to', $live_value),
        $f('publish_date', 'is greater than', '', 'current date and time'),
    ),
    lang('No story is scheduled to go live.'),
    $desk_rows(true));
// Live with an empty publication time: kept off the site by the live rule
// until an editor gives it a time.
$widgets['staff_no_time'] = $desk_list('staff', 10, 10, 'submitted_date_and_time', 'ASC',
    array(
        $f('status', 'is equal to', $live_value),
        $f('publish_date', 'is equal to', ''),
    ),
    lang('Every live story has a publication time.'),
    $desk_rows(true));
// What readers see now; a row opens the public story.
$widgets['staff_published'] = array(
    'page'   => 'staff',
    'slug'   => lang('form-list'),
    'config' => array_merge($pub, array(
        'items_per_page' => 10,
        'max_results'    => 10,
        'filters'        => $live,
        'empty_message'  => lang('No story is live yet.'),
    )),
    'tree'   => $widget_root($desk_rows(true)),
);
// Every story sent to the magazine in any status, newest first, with a
// search.
$widgets['staff_all'] = $desk_list('staff', 20, 0, 'submitted_date_and_time', 'DESC', array(),
    lang('No stories yet.'),
    $desk_rows(true),
    array(
        'search_enabled' => true,
        'search_fields'  => array('title', 'byline', 'section', 'status', 'issue', 'tags'),
        'search_width'   => 'full',
        'search_label'   => lang('Search by title, writer, section, issue or status…'),
    ));

// The staff directory's form: one person.
$widgets['staff_form'] = array(
    'page'   => 'staff_entry',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $row(array(
            $col(array($field('mg-staff-name', lang('Full Name'), $input('mg-staff-name', 'text', 'full_name', 'off'))), '6'),
            $col(array($field('mg-staff-title', lang('Job Title'), $input('mg-staff-title', 'text', 'job_title', 'off'))), '6'),
            $col(array($field('mg-staff-department', lang('Department'), $input('mg-staff-department', 'text', 'department', 'off', false))), '6'),
            $col(array($field('mg-staff-photo', lang('Photo'), $file('mg-staff-photo', 'photo', 'private'))), '6'),
            $col(array($field('mg-staff-email', lang('Email'), $input('mg-staff-email', 'email', 'email', 'off', false))), '6'),
            $col(array($field('mg-staff-phone', lang('Phone'), $input('mg-staff-phone', 'tel', 'phone', 'off', false))), '6'),
        ), '3'),
        $field('mg-staff-bio', lang('A few words'), $textarea('mg-staff-bio', 'bio', 3, false)),
        $submit(lang('Add')),
        $loop(array()),
    )),
);
// The directory, for the editors only.
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
        $row(array($loop(array(
            $col(array(
                $el('div', 'd-flex align-items-start gap-3 h-100 p-4 bg-body-tertiary border', lang('Person Card'), array(
                    $optional_image('photo', '', 'rounded-circle object-fit-cover pg-circle-60', 'flex-shrink-0',
                        $portrait($contributor_name('Emily Turner'), 128), '', $lazy),
                    $el('div', 'pm-min-0', lang('Details'), array(
                        $heading('h2', $contributor_name('Emily Turner'), 'h6 pm-title mb-0', $bind('full_name')),
                        $para($contributors['Emily Turner'][0], 'small fw-semibold mb-2 text-body-secondary', $bind('job_title')),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                            $icon('bi-diagram-3', 'text-primary me-1'),
                            $span(lang('Editorial office'), 'pg-field-value', lang('Value'), $bind('department')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                            $icon('bi-envelope', 'text-primary me-1'),
                            $span(lang('emily.turner@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary me-1'),
                            $span('(518) 555-0142', 'pg-field-value', lang('Value'), $bind('phone')),
                        )),
                        $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                            $span($contributors['Emily Turner'][1], 'pg-field-value', lang('Value'), $bind('bio')),
                        )),
                    )),
                )),
            ), '6', '4'),
        ))), '4'),
    )),
);

// The inbox: conversations the member started (a signed-in message from
// the contact page) or follows; the editors see them all. A conversation's
// subject is its topic.
$widgets['inbox_list'] = array(
    'page'   => 'inbox',
    'slug'   => lang('form-list'),
    'config' => array(
        'regionType'              => 'form_list_view',
        'custom_form_page_id'     => '{{tab:contact}}',
        'detail_page_id'          => '{{tab:conversation}}',
        'items_per_page'          => 20,
        'order_by_field'          => 'submitted_date_and_time',
        'order_by_direction'      => 'DESC',
        'viewer_filter'           => true,
        'viewer_filter_submitter' => true,
        'viewer_filter_watcher'   => true,
        'viewer_filter_editor'    => true,
        'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
    ),
    'tree'   => $widget_root(array(
        $el('div', 'list-group', lang('Conversation List'), array(
            $loop(array(
                $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                    $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                        $span(lang('A story idea'), 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                        $span(lang('2 hours ago'), 'small text-body-secondary text-nowrap', lang('Date'), $bind_date('submitted_date_and_time', 'relative')),
                    )),
                    $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                        $icon('bi-chat-left-text'),
                        $span('0', 'fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
                        $span(lang('replies'), '', lang('Label')),
                    )),
                ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
            )),
        )),
    )),
);

// One conversation: the first message; the answers follow as messages.
$widgets['conversation'] = array(
    'page'   => 'conversation',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:contact}}',
        'access_control'      => 'submitter_only',
        'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-chat-left-dots', 'display-5 text-body-secondary'),
                        $heading('h1', lang('Conversation not found'), 'h3 pm-title mt-3'),
                        $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                        $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Conversation'), array_merge(
                        $sent_notices(lang('Thank you, your message has reached the editors. Their answers appear on this page and in your inbox, and you can write back below.')),
                        array(
                            $record_toolbar(lang('Inbox'), 'inbox'),
                            $heading('h1', lang('A story idea'), 'h3 pm-title mb-1', $bind('topic')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4 pm-sans', lang('Conversation Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'p-4 bg-body-tertiary border', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2 pm-sans', lang('Sender'), array_merge(
                                    array($icon('bi-person-circle', 'fs-5 text-primary')), $person_name())),
                                $el('div', '', lang('Message'), array(), array_merge(
                                    array('text' => lang('Hello, I would like to write about the last ferry on our lake. May I send you the idea?')), $bind('message'))),
                            )),
                            // The heading of the answers the page puts right
                            // below; gone with a missing conversation.
                            $heading('h2', lang('Replies'), 'h4 pm-title mt-5 mb-0'),
                        )
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'pt-5 pb-3'),
    )),
);

// ── The e-mails' widgets ────────────────────────────────────────────────
// Drawn for the record just sent, which the server names when it sends the
// e-mail. Opened by its address instead (?r=), the page shows the record
// only to whoever sent it and to the editors.
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
// "Hello," and the name the record carries, in an element of its own: a
// translated page matches whole texts.
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
// Whoever sent the contact form.
$mail_person = function ($label) use ($mail_box, $mail_line, $person_name, $mail_value) {
    return $mail_box(array(
        $mail_line($label, $person_name()),
        $mail_value(lang('Email'), lang('jane.cooper@example.com'), 'email'),
        $mail_value(lang('Phone'), '(518) 555-0142', 'phone'),
    ), $label);
};
// The account the contact form opened for a visitor who had none: the
// address it signs in with, and how to choose a password (the set
// password link is shown on the conversation's page, not mailed).
$mail_new_account = function () use ($el, $span, $link, $attr, $bind, $mail_style, $show_when) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', '', array($attr('style', 'color:#4a6b53;color:var(--bs-link-color,#4a6b53)'))),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #dcd6c8;padding-top:16px'), $show_when('has_new_account')));
};
// The story's lines: all of them for the editors, the title and the
// section for the author.
$mail_story = function ($full) use ($mail_box, $mail_value, $head) {
    $s = $head(3);
    $lines = array(
        $mail_value(lang('Title'), $s['title'], 'title'),
        $mail_value(lang('Section'), $s['section'], 'section'),
    );
    if ($full) {
        $lines[] = $mail_value(lang('Name on the story'), $s['byline'], 'byline');
        $lines[] = $mail_value(lang('Summary'), $s['summary'], 'summary');
        $lines[] = $mail_value(lang('A note for the editors'), lang('I can send more photos of the reading chair if you need them.'), 'note_to_editors');
    }
    return $mail_box($lines, lang('Magazine story'));
};
$widgets['email_article_admin'] = $mail_widget('email_article_new', 'blog_new', lang('The story could not be found.'), array(
    $mail_heading(lang('A new story is waiting for review')),
    $mail_para(lang('It came from the Submit a Story page. Read it on the desk, change its status in the control panel and write to the author under it.')),
    $mail_story(true),
    $mail_button(lang('Open it on the desk'), '{{page:submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_article_author'] = $mail_widget('email_article_received', 'blog_new', lang('The story could not be found.'), array(
    $mail_heading(lang('Your story reached the editors')),
    $mail_greeting('byline', $sample['byline']),
    $mail_para(lang('The editors read every story. Follow its status under My Stories; we answer your messages on its page.')),
    $mail_story(false),
    $mail_para(lang('Nothing you send is shown to readers until an editor sets it live.')),
    $mail_button(lang('Follow my story'), '{{page:submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_admin'] = $mail_widget('email_new_message', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('A new message has arrived')),
    $mail_para(lang('It came from the contact page. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
    $mail_person(lang('Sender')),
    $mail_box(array(
        $mail_value(lang('Subject'), lang('A story idea'), 'topic'),
    ), lang('Subject')),
    $el('div', 'mb-3', lang('Message'), array(), array_merge(
        array('text' => lang('Hello, I would like to write about the last ferry on our lake. May I send you the idea?')),
        $bind('message'), $mail_style('margin:0 0 16px'))),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_sender'] = $mail_widget('email_message_received', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('Your message has reached us')),
    $mail_greeting('first_name', lang('Jane')),
    $mail_para(lang('Thank you for writing to the magazine. The editors\' answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
    $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
            array('text' => lang('A story idea'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', '', lang('Message'), array(), array_merge(
            array('text' => lang('Hello, I would like to write about the last ferry on our lake. May I send you the idea?')), $bind('message'))),
    ), lang('Your Message')),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_new_account(),
));

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
    'about' => array(
        '#who-we-are'   => array('who-we-are', lang('about us, independent magazine, long reads, who we are')),
        '#the-people'   => array('the-people', lang('editors, writers, contributors, masthead')),
        '#how-we-work'  => array('how-we-work', lang('fact checking, corrections, independence, how we work')),
        '#get-in-touch' => array('get-in-touch', lang('story ideas, questions, corrections, contact')),
    ),
    'write_for_us' => array(
        '#what-we-publish' => array('what-we-publish', lang('write for us, pitch, contribute, essays')),
        '#how-it-works'    => array('how-it-works', lang('author account, submit a story, review')),
        '#guidelines'      => array('guidelines', lang('guidelines, length, photos, rights')),
    ),
    'issues' => array(
        '#every-issue' => array('every-issue', lang('issues, back issues, archive, contents')),
    ),
    'author' => array(
        '#contributors' => array('contributors', lang('contributors, writers, editors')),
    ),
    'newsletter' => array(
        '#the-weekend-read' => array('the-weekend-read', lang('newsletter, the weekend read, e-mail')),
    ),
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail')),
    ),
    'privacy' => array(
        '#your-data' => array('your-data', lang('privacy, personal data, cookies')),
    ),
    'terms' => array(
        '#using-the-site' => array('using-the-site', lang('terms, rules, letters policy')),
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
    'name'        => lang('Long-Read Magazine'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 80,
    'icon'        => 'bi-journal-richtext',
    // The picture on the template's card and beside the designs made from
    // it: a magazine's front page (pg_design_thumb_svg()).
    'thumb'       => 'magazine',
    'description' => lang('A calm long-read magazine: a cover story, issues, sections and contributors, stories set for reading, with author submissions that editors review, schedule and publish.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'editorial',
    'palette'     => 'sage',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A cover story, an editor\'s letter and editors\' picks on the home page, with sections, issues and contributor pages.'),
        lang('Stories set for long reading: a serif column, a drop cap, a pull quote and a full-width photograph.'),
        lang('Authors send stories from their desk; editors review, schedule and publish them, and answer under each story.'),
        lang('Reader accounts with letters and an inbox, an editors\' desk and The Weekend Read newsletter.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The author desk lives in contributors, a private folder under the
    // editors': authors get view access to it, editors edit access to private.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_magazine', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'contributors' => array('name' => 'contributors', 'parent' => 'private', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('The Weekend Read'),
            'description'  => lang('The addresses left on the newsletter form of the magazine.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'masthead'     => array('name' => lang('Magazine Masthead'), 'tree' => $masthead()),
        'footer'       => array('name' => lang('Magazine Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Editors\' Bar'), 'tree' => $staff_bar()),
        'email_header' => array('name' => lang('Magazine E-mail Header'), 'tree' =>
            // The double rule is the inline style alone: Bootstrap's
            // border-bottom class would draw a single hairline over it.
            $el('div', 'px-4 py-4 text-center', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-3 link-body-emphasis text-decoration-none',
                    array($attr('style', "font-family:Georgia,'Times New Roman',serif;font-size:26px;font-weight:400;letter-spacing:-.01em;text-decoration:none;color:#1f2421;color:var(--bs-emphasis-color,#1f2421)"))),
            ), $mail_style('padding:24px;text-align:center;border-bottom:3px double #1f2421'))),
        'email_footer' => array('name' => lang('Magazine E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $para(lang('An independent magazine of long reads, essays and photography.'), 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit our website'), '{{page:home}}', '',
                    array($attr('style', 'color:#4a6b53;color:var(--bs-link-color,#4a6b53)'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #dcd6c8;font-size:13px;color:#5c5a55'))),
    ),

    // The template's own look: the blocks' rules it uses and its pm- rules
    // in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(
            array('pg-snap', 'pg-snap-item', 'pg-circle-60', 'pg-circle-160'),
            $magazine_css),
        'fonts' => json_encode($magazine_fonts),
    ),
);
