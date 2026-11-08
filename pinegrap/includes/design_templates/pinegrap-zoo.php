<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo and Aquarium": a zoo with an aquarium. A home page
 * that opens on the day (today's feeding times and keeper talks, the
 * animal spotlight, the habitat zones, the park map and the conservation
 * projects); today's schedule, the numbered park map, tickets,
 * memberships and adoptions said as words, about and the questions; the
 * animals by habitat and class with a page each; school and group visit
 * requests the sender follows from their account, with the status and the
 * zoo's answers; events by date from today on; tickets, memberships and
 * adoptions sold when the shop is on; a contact page whose messages become
 * conversations, the conservation News with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new animal, event, News
 * and staff directory forms and the visit requests), a help page that
 * searches the whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-zoo/ (not read
 * as templates of their own): parts.php holds the node builders, the zoo's
 * data and the small helpers; cards.php the parts every page repeats;
 * mail.php the e-mail builders; pages_public.php, animals.php, visits.php,
 * events.php, shop.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps the zoo's
 * data in $animals, $visits, $events, $tickets and $memberships, shop.php
 * the catalog in $catalog, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the Tickets,
 * Memberships and Adoptions groups ('catalog', _pg_tpl_catalog()). Only
 * the shop pages need the shop; the shared parts never link them, and the
 * tickets, memberships and adopt pages say the prices as words.
 *
 * An animal and an event are records of collection forms: the staff add
 * one on the new animal or new event page and the lists show it at once.
 * An event's date is counted from the day the template opens, so the
 * sample never shows a past date. A visit request is a record of its own
 * form: a visitor without an account gets one (auto-registration), so the
 * record's page and the account's list show it at once. Its Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments, mailed to the sender and to
 * the zoo.
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
// is kept under a _part name ($animals, $visits, $events and the shop's
// $catalog are the parts' data).
$zoo_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
include $zoo_dir . '/parts.php';
include $zoo_dir . '/cards.php';
include $zoo_dir . '/mail.php';
$public_part   = include $zoo_dir . '/pages_public.php';
$animal_part   = include $zoo_dir . '/animals.php';
$visit_part    = include $zoo_dir . '/visits.php';
$event_part    = include $zoo_dir . '/events.php';
$shop_part     = include $zoo_dir . '/shop.php';
$standard_part = include $zoo_dir . '/standard.php';
$account_part  = include $zoo_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// animals, the visit requests, the events, the shop, the standard pages,
// the account and staff pages, the e-mails last of each part.
$zoo_parts = array($public_part, $animal_part, $visit_part, $event_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($zoo_parts as $part) {
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
// Today's schedule, the map, the animals, tickets, memberships, adoptions,
// events and the questions are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, gate, lost property, school trips, press')),
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
// Two fonts: a lively grotesque with wide, friendly shapes for the
// headings, the zoo's name and the prices, a rounded sans for the rest.
$zoo_fonts = array(
    array('family' => 'Bricolage Grotesque', 'weights' => array('500', '600', '700', '800'), 'enabled' => true),
    array('family' => 'Rubik', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles, after the rules of the zoo-
// blocks its pages use (pg-zoo-time, pg-zoo-pin). Every own class is named
// pzo- (the field and section ids are zoo-); no block rule's class is
// named here. Selectors use descendants and pseudo-classes only, so they
// hold on the editor's canvas too. Colours are tokens only, so they follow
// any palette in both themes.
$zoo_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Bricolage Grotesque",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Rubik",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.02em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the grotesque at its heaviest, a little tighter.
    '.pzo-display{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.025em}',
    // The zoo's name in the header and the footer.
    '.pzo-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.015em}',
    // A price: figures of one width in the heading face.
    '.pzo-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums lining-nums}',
    // A figure, an opening time, a date's day: figures of one width, so
    // the columns line up.
    '.pzo-stat{font-variant-numeric:tabular-nums lining-nums}',
    // The leaf beside a section title leans a little, like a leaf.
    '.pzo-mark{display:inline-block;transform:rotate(-12deg)}',
    // A page's first section: a soft wash of the primary colour from the
    // lower left corner, like light through leaves, where the browser
    // mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pzo-hero{background-image:radial-gradient(ellipse 60% 90% at 0% 100%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // A habitat zone's card rises a little under the pointer.
    '.pzo-zone{transition:transform .2s ease,box-shadow .2s ease}',
    '.pzo-zone:hover,.pzo-zone:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pzo-zone{transition:none}.pzo-zone:hover,.pzo-zone:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Zoo and Aquarium'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 143,
    'icon'        => 'bi-tree',
    'description' => lang('A zoo with an aquarium: a home page that opens on today\'s feeding times and keeper talks, the animal spotlight, a numbered park map, the animals by habitat with a page each, tickets, memberships and adoptions sold when the shop is on, school and group visit requests the sender follows from their account, events by date, conservation News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'organic',
    'palette'     => 'forest',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Today\'s feeding times and keeper talks with the next one marked, the animal spotlight with its red-list status, and a numbered park map by zone.'),
        lang('The animals by habitat and class with a page each, and events by date from today on; the staff add both from a form.'),
        lang('School and group visit requests with their own page, status and the zoo\'s answers; with the shop on, tickets, memberships and adoptions are sold.'),
        lang('Conservation News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The animals', events' and News photos go to the public folder, the
    // private folder keeps what only the staff and the sender see.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_zoo', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Zoo Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the zoo.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Zoo Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Zoo Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Zoo Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Zoo Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Zoo Staff Bar'), 'tree' => $staff_bar()),
        'visit_band'   => array('name' => lang('Zoo Visit Band'), 'tree' => $visit_band()),
        'email_header' => array('name' => lang('Zoo E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Zoo E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the zoo- blocks its pages
    // build (the slot times, the habitat pins) and its pzo- rules in one
    // User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('zoo-today', 'zoo-map', 'zoo-conservation'), $zoo_css),
        'fonts' => json_encode($zoo_fonts),
    ),
);
