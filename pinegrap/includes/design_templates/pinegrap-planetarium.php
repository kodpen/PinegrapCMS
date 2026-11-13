<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium": a planetarium with a public observatory.
 * A home page that opens on tonight's sky and the telescope's status, then
 * today's dome shows, the next telescope nights, the moon phases and the
 * News; the dome shows by date from today on, filtered by audience, with a
 * page each; the telescope nights by date with their status, called by the
 * staff on the day; tonight's sky, the visit, tickets, memberships, the
 * observatory and the questions; school and group visit requests the
 * teacher follows from their account, with the status and the
 * planetarium's answers; tickets and memberships sold when the shop is on;
 * a contact page whose messages become conversations, the News with its
 * post page, a newsletter, the sign-in and account pages, a staff area
 * (the visit requests, the new dome show, telescope night, News and staff
 * directory forms), a help page that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-planetarium/
 * (not read as templates of their own): parts.php holds the node builders,
 * the planetarium's data and the small helpers; cards.php the parts every
 * page repeats, the cards and the form fields; mail.php the e-mail
 * builders; pages_public.php, shows.php, nights.php, visits.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the planetarium's data in $shows,
 * $nights, $tickets, $memberships and $visits, shop.php the catalog in
 * $catalog, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the Tickets
 * and Memberships groups with their products ('catalog', _pg_tpl_catalog()).
 * Only the shop pages need the shop; the shared parts and the public pages
 * never link them, and the tickets and memberships pages say the prices as
 * words.
 *
 * A dome show and a telescope night are records of collection forms: the
 * staff add one on the new show or new night page and the lists show it at
 * once. A night's status (On, Called Off, Moved) is an office-use-only
 * field the staff change in the control panel. A visit request is a record
 * of its own form: a teacher without an account gets one
 * (auto-registration), so the record's page and My Visits show it at once;
 * its status is an office field too, and the messages under it are the
 * page's comments, mailed to the teacher and to the planetarium.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{product_group:<key>}}         id of a group of the catalog
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
// is kept under a _part name ($shows, $nights and the shop's $catalog are
// the parts' data).
$planetarium_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
include $planetarium_dir . '/parts.php';
include $planetarium_dir . '/cards.php';
$public_part   = include $planetarium_dir . '/pages_public.php';
$show_part     = include $planetarium_dir . '/shows.php';
$night_part    = include $planetarium_dir . '/nights.php';
include $planetarium_dir . '/mail.php';
$visit_part    = include $planetarium_dir . '/visits.php';
$shop_part     = include $planetarium_dir . '/shop.php';
$standard_part = include $planetarium_dir . '/standard.php';
$account_part  = include $planetarium_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// dome shows, the telescope nights, the school visits, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$planetarium_parts = array($public_part, $show_part, $night_part, $visit_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($planetarium_parts as $part) {
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
// The visit, tickets, memberships, the observatory and the questions are
// marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, front desk, opening hours, box office, observatory')),
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
// Two fonts: a wide geometric sans for the headings, the planetarium's
// name, the times and the prices, a plain technical sans for the rest.
$planetarium_fonts = array(
    array('family' => 'Sora', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'IBM Plex Sans', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named ppm- (the
// field and section ids are plm-); no block rule's class is named here.
// Selectors use descendants and pseudo-classes only, so they hold on the
// editor's canvas too. Colours are tokens only, so they follow any
// palette in both themes.
$planetarium_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Sora",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles, show and night names: the geometric sans, a little tighter.
    '.ppm-display{font-family:var(--pg-font-heading);letter-spacing:-.015em}',
    // The small line over a title: spaced capitals.
    '.ppm-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The planetarium's name in the header and the footer.
    '.ppm-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // Showtimes, dates and hours: figures of one width, so a column of
    // times lines up.
    '.ppm-time{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums lining-nums}',
    // A price on a card or in the shop: figures of one width, kept on one
    // line.
    '.ppm-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums lining-nums;white-space:nowrap}',
    // A page header: a faint glow of the primary colour from the top, like
    // the sky over the horizon, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.ppm-hero{background-image:radial-gradient(ellipse 80% 90% at 50% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // A section title's line: its title sits close to the line.
    '.ppm-rule{line-height:1.2}',
    // Show, night, visit and post cards rise a little under the pointer.
    '.ppm-card{transition:transform .2s ease,box-shadow .2s ease}',
    '.ppm-card:hover,.ppm-card:focus-visible,.ppm-card:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.ppm-card{transition:none}.ppm-card:hover,.ppm-card:focus-visible,.ppm-card:focus-within{transform:none}}',
    // The header's eight links share one row with the name: from 1200 px on
    // they are set a size smaller and closer together.
    '@media (min-width:1200px){.ppm-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    // The account bar is drawn in the dark theme on a light site too: the
    // theme attribute sits on the grey bar itself, which the rules above (a
    // grey section inside a dark theme) do not reach.
    '.bg-body-tertiary[data-bs-theme=dark] :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-body-tertiary[data-bs-theme=dark] .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '.bg-body-tertiary[data-bs-theme=dark] :is(.text-primary,.link-primary){color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))!important}'
        . '.bg-body-tertiary[data-bs-theme=dark] .btn-outline-primary{--bs-btn-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    '.bg-body-tertiary[data-bs-theme=dark] .link-secondary{color:var(--bs-secondary-color)!important}',
    '.bg-body-tertiary[data-bs-theme=dark] .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
));

return array(
    'name'        => lang('Planetarium'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 145,
    'icon'        => 'bi-stars',
    'description' => lang('A planetarium with a public observatory: tonight\'s sky, dome shows by date and audience with a page each, telescope nights with the staff\'s call on the day, the visit, tickets and memberships as words and as products with the shop on, school visit requests the teacher follows from their account, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'minimal',
    'palette'     => 'midnight',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A home page that opens on tonight\'s sky, today\'s dome shows, the next telescope nights and the moon phases.'),
        lang('Dome shows and telescope nights by date with a page each, kept by the staff from a form; a night\'s status is the staff\'s call.'),
        lang('School visit requests with their own page, status and the planetarium\'s answers; tickets and memberships sold with the shop on.'),
        lang('The observatory, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_planetarium', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Planetarium Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the planetarium.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Planetarium Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Planetarium Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Planetarium Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Planetarium Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Planetarium Staff Bar'), 'tree' => $staff_bar()),
        'email_header' => array('name' => lang('Planetarium E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Planetarium E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the planetarium- blocks its
    // pages place and its ppm- rules in one User Styles entry, and its two
    // fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('planetarium-tonight', 'planetarium-shows',
            'planetarium-objects', 'planetarium-moon'), $planetarium_css),
        'fonts' => json_encode($planetarium_fonts),
    ),
);
