<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Private School": a private school or kindergarten. A
 * home page with the programmes, the coming events, the figures and what
 * parents say; a page per programme (nursery, kindergarten, primary,
 * after-school clubs, summer school) with its ages, group size and hours,
 * a day at school, the approach, the campus, the teachers, the about page,
 * the fees as words and the questions; the school's events with a page
 * per event, filtered by programme; an admission application the parent
 * follows from their account, with the status and the school office's
 * answers, and a school visit request; a contact page whose messages
 * become conversations, News (the blog) with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new event form, the
 * admission applications, the visit requests, the blog and staff
 * directory forms), a help center that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-school/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * school's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, events.php, admissions.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 * parts.php keeps the sample data in $programmes, $events, $staff and
 * $classes, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the school sends the newsletter to it by
 * hand. There is no catalog: a school takes no payment online, and only
 * the address book needs the shop.
 *
 * An event is a record of its form, a collection: the staff add one on the
 * new event page and the list shows it at once, filtered by the address
 * (?programme=). An admission application and a visit request are records
 * of their own forms, requests and never an enrolment: a visitor without an
 * account gets one (auto-registration), so the application's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under an
 * application are the page's comments, mailed to the parent and to the
 * school.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components, 'catalog' and 'requires'.
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

// The parts set variables of their own in this scope, so what each returns
// is kept under a _part name ($programmes, $events, $staff and $classes are
// parts.php's sample data).
$school_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
include $school_dir . '/parts.php';
include $school_dir . '/mail.php';
$public_part    = include $school_dir . '/pages_public.php';
$event_part     = include $school_dir . '/events.php';
$admission_part = include $school_dir . '/admissions.php';
$standard_part  = include $school_dir . '/standard.php';
$account_part   = include $school_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// events, the admissions, the standard pages, the account and staff pages,
// the e-mails last of each part.
$school_parts = array($public_part, $event_part, $admission_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($school_parts as $part) {
    $pages   = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The programmes, the day, the approach, the campus, the questions and the
// privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, school office, opening hours')),
        lang('Inbox Note')      => array('inbox-note', lang('answer, reply, conversation, inbox')),
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

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a rounded, friendly face for the headings, the school's name
// and the figures, a plain sans for the rest.
$school_fonts = array(
    array('family' => 'Nunito', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named psh- (the
// field ids are sh-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$school_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Nunito",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:800;--pg-display-weight:800;--pg-heading-tracking:-.005em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the rounded heading face, heavy.
    '.psh-display{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.005em}',
    // The small line over a title: spaced capitals.
    '.psh-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The school's name in the header and the footer, in the heading face.
    '.psh-brand{font-family:var(--pg-font-heading);font-weight:800}',
    // A key figure: the heading face, figures of one width.
    '.psh-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // What a parent says: a little larger, in italics.
    '.psh-quote{font-style:italic;font-size:1.125rem;line-height:1.6}',
    // The home page's way in: a soft wash of the primary colour from the
    // top corner.
    '.psh-hero{background-image:radial-gradient(circle at 15% 0,rgba(var(--bs-primary-rgb),.12),transparent 60%)}',
    // Programme and event cards rise a little under the pointer.
    '.psh-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.psh-lift:hover,.psh-lift:focus-visible,.psh-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.psh-lift{transition:none}.psh-lift:hover,.psh-lift:focus-visible,.psh-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.psh-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
    // Dark theme, grey sections (the account bar, page headers, grey bands):
    // the palette's primary and second colours are made readable on the
    // page background and fall to about 4:1 on this lighter grey. Links,
    // eyebrows and outline buttons there take the primary's emphasis tint,
    // lightened a fifth towards the text colour where the browser mixes
    // colours (some palettes' tint is as dark as the primary itself), and
    // the second colour gives way to the body's second text colour.
    '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))!important}'
        . '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    '[data-bs-theme=dark] .bg-body-tertiary .link-secondary{color:var(--bs-secondary-color)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
    // Pills, tabs and underlined links there (filters, the staff bar) read
    // the link colour: they take the same tint.
    '[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    // The primary's soft tint (the account card, soft bands): a palette's
    // primary and second colours are made readable on the page background
    // and fall under 4.5:1 here with several palettes (Coral 3.41:1, the
    // second colour of Graphite 3.16:1). Text, links and outline buttons
    // take the colour's emphasis tint, Bootstrap's pair for a soft tint.
    '.bg-primary-subtle :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-primary-subtle :is(.btn-outline-primary,.btn-link){--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '.bg-primary-subtle .link-secondary{color:var(--bs-secondary-text-emphasis)!important}',
    '.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
));

return array(
    'name'        => lang('Private School'),
    'version'     => '2.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 93,
    'icon'        => 'bi-backpack',
    'description' => lang('A private school or kindergarten: programmes from nursery to after-school clubs with a page each, a day at school, the approach, the campus and the teachers, school events with a page each, admission applications the parent follows from their account, school visit requests, fees as words, News, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are a darker shade of this palette's orange, readable
    // as white button text. The template's own look travels in its User
    // Styles ('assets'), so it holds under any look and palette.
    'look'        => 'playful',
    'palette'     => 'sunset',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Programmes from nursery to summer school with a page each: ages, group size, hours and what a day holds.'),
        lang('A day at school, the approach, the campus, the teachers, the fees as words and the questions.'),
        lang('School events as a collection: a page per event, a filter by programme, and sample events the staff replace from a form.'),
        lang('Admission applications and school visit requests followed from the parent\'s account, with the status and the school office\'s answers; News, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_school', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('School Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the school.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'     => array('name' => lang('School Account Bar'), 'tree' => $account_bar_tree()),
        'notice'          => array('name' => lang('School Notice'), 'tree' => $notice()),
        'header'          => array('name' => lang('School Header'), 'tree' => $header()),
        'footer'          => array('name' => lang('School Footer'), 'tree' => $footer()),
        'staff_bar'       => array('name' => lang('School Staff Bar'), 'tree' => $staff_bar()),
        'admissions_band' => array('name' => lang('School Admissions Band'), 'tree' => $admissions_band()),
        'email_header'    => array('name' => lang('School E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'    => array('name' => lang('School E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the School blocks' pg-school- rules and its
    // psh- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('school-hero', 'school-ages', 'school-year'), $school_css),
        'fonts' => json_encode($school_fonts),
    ),
);
