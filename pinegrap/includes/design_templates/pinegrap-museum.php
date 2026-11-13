<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum": a museum or an art gallery. A home page with
 * the exhibitions now on, collection highlights, the coming events and the
 * day's opening hours; the visit page (opening hours, admission as words,
 * getting there, access and facilities); the exhibitions, current,
 * upcoming and past, with a page each; collection highlights by department
 * with a page per object; events and talks with a page each and a place
 * request the visitor follows from their account; learning sessions and
 * school visit requests followed from the teacher's account; support and
 * membership as words, the about page and the questions; with the shop on,
 * admission tickets and membership tiers to buy online; a timed entry slot
 * on the booking page; a contact page whose messages become conversations,
 * News (the blog) with its post page, a newsletter, the sign-in and account
 * pages, a staff area (the new exhibition, object and event forms, the
 * place requests, the school visits, the blog and staff directory forms),
 * a help center that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-museum/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * museum's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, exhibitions.php, objects.php, events.php,
 * learning.php, shop.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps the sample
 * data in $exhibitions, $objects, $events, $sessions and $people, and
 * shop.php its catalog in $catalog, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups') and, with the shop, the Admission and Membership
 * groups of the catalog with the tickets and the tiers as products
 * ('catalog'), made once and found again by their names the next time
 * (_pg_tpl_contact_groups(), _pg_tpl_catalog()). Only the shop pages need
 * the shop; the visit and support pages say the same prices as words.
 *
 * An exhibition, a collection highlight and an event are records of their
 * forms, collections: the staff add one on its new record page and the
 * lists show it at once, filtered by the address (?status=, ?department=,
 * ?kind=). A place at an event and a school visit are records of their own
 * forms, requests and never a confirmed booking: a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under a
 * request are the page's comments, mailed to the visitor and to the
 * museum. Timed entry is the booking widget on book_online: the museum
 * sets its entry slots up as a bookable service in Settings › Bookings.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{product_group:<key>}}         id of a group of the catalog ('root': the site's top group)
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
// is kept under a _part name ($exhibitions, $objects, $events, $sessions
// and $people are parts.php's sample data, $catalog is shop.php's).
$museum_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
include $museum_dir . '/parts.php';
include $museum_dir . '/mail.php';
$public_part     = include $museum_dir . '/pages_public.php';
$exhibition_part = include $museum_dir . '/exhibitions.php';
$object_part     = include $museum_dir . '/objects.php';
$event_part      = include $museum_dir . '/events.php';
$learning_part   = include $museum_dir . '/learning.php';
$shop_part       = include $museum_dir . '/shop.php';
$standard_part   = include $museum_dir . '/standard.php';
$account_part    = include $museum_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// exhibitions, the collection, the events, the learning, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$museum_parts = array($public_part, $exhibition_part, $object_part, $event_part, $learning_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($museum_parts as $part) {
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
// The visit, the exhibitions, the collection, the events, the learning,
// the support, the tickets, the questions and the privacy notice are
// marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, front desk, opening hours')),
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
// Two fonts: a soft old-style serif for the headings, the museum's name and
// the figures, a plain grotesque for the rest.
$museum_fonts = array(
    array('family' => 'Fraunces', 'weights' => array('400', '600', '700'), 'enabled' => true),
    array('family' => 'Work Sans', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pmz- (the
// field ids are mz-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$museum_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Fraunces",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Work Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the serif face, a little tighter.
    '.pmz-display{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals, as on a gallery label.
    '.pmz-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The museum's name in the header and the footer, in the serif face.
    '.pmz-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // A key figure: the serif face, figures of one width.
    '.pmz-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // A price: figures of one width, so the tickets line up.
    '.pmz-price{font-family:var(--pg-font-heading);font-weight:600;font-variant-numeric:tabular-nums}',
    // What a visitor says: the serif face in italics, a little larger.
    '.pmz-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.125rem;line-height:1.6}',
    // The home page's way in: a quiet wash of the primary colour from the
    // top corner, as light across a gallery wall.
    '.pmz-hero{background-image:linear-gradient(135deg,rgba(var(--bs-primary-rgb),.1),transparent 55%)}',
    // Exhibition, object and event cards rise a little under the pointer.
    '.pmz-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pmz-lift:hover,.pmz-lift:focus-visible,.pmz-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pmz-lift{transition:none}.pmz-lift:hover,.pmz-lift:focus-visible,.pmz-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Museum'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 95,
    'icon'        => 'bi-bank2',
    'description' => lang('A museum or an art gallery: the visit with opening hours and admission, exhibitions now on, coming and past with a page each, collection highlights by department with a page per object, events and talks with place requests, learning sessions and school visit requests followed from the account, support and membership, admission tickets and membership in the shop, a timed entry slot, News, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's burgundy, readable as white button
    // text. The template's own look travels in its User Styles ('assets'),
    // so it holds under any look and palette.
    'look'        => 'editorial',
    'palette'     => 'burgundy',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The visit: opening hours, admission as words, getting there, access and facilities, and a timed entry slot booked online.'),
        lang('Exhibitions now on, coming and past, and collection highlights by department, each with a page, and samples the staff replace from a form.'),
        lang('Events and talks with place requests, and school visit requests, followed from the account with the status and the museum\'s answers.'),
        lang('Admission tickets and membership with the shop on, support as words, News, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_museum', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Museum Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the museum.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Museum Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Museum Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Museum Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Museum Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Museum Staff Bar'), 'tree' => $staff_bar()),
        'visit_band'   => array('name' => lang('Museum Visit Band'), 'tree' => $visit_band()),
        'email_header' => array('name' => lang('Museum E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Museum E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Museum blocks' pg-museum- rules and its
    // pmz- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('museum-exhibition', 'museum-object', 'museum-trail'), $museum_css),
        'fonts' => json_encode($museum_fonts),
    ),
);
