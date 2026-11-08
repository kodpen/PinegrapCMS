<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy": a community pharmacy. A home page with the
 * services, the health checks, the duty nights, the pharmacists and the
 * shelves; the services with a page each (prescriptions, vaccinations,
 * health checks, medicine reviews, travel health, home delivery), the
 * health checks with their prices, being on duty and how to find the
 * pharmacy on duty when this one is closed, the about page, the questions
 * and getting here; the pharmacists with a page each and a vaccination or
 * a health check booked at a free time; a prescription request and a
 * question to the pharmacist the customer follows from their account,
 * with the status and the pharmacists' answers; health products in the
 * shop when the shop is on, never prescription medicine; a contact page
 * whose messages become conversations, Health Advice (the blog) filtered
 * by health topic with its post page, a newsletter, the sign-in and
 * account pages, a staff area (the new pharmacist form, the prescription
 * requests and the questions, the article and staff directory forms), a
 * help page that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-pharmacy/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * pharmacy's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, team.php, requests.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the pharmacy's data in $services,
 * $checks, $pharmacists, $ranges and $topics, so each part's return is
 * kept under a _part name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the health
 * products ('catalog', _pg_tpl_catalog()). Only the shop pages need the
 * shop, and the shared parts never link them.
 *
 * A pharmacist is a record of the Pharmacists form, a collection: the
 * staff add one on the new pharmacist page and the lists show it at once,
 * filtered by the address (?service=). A prescription request and a
 * question are records of their own forms, requests and never orders: a
 * visitor without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. The original
 * prescription is shown at the counter; nothing is dispensed without it.
 * A request's Status is an office-use-only field the staff change in the
 * control panel; the messages under it are the page's comments, mailed to
 * the customer and to the pharmacy. The template keeps no patient record,
 * no medication history and no rota by date.
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
// is kept under a _part name.
$pharmacy_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy';
include $pharmacy_dir . '/parts.php';
include $pharmacy_dir . '/mail.php';
$public_part   = include $pharmacy_dir . '/pages_public.php';
$team_part     = include $pharmacy_dir . '/team.php';
$request_part  = include $pharmacy_dir . '/requests.php';
$shop_part     = include $pharmacy_dir . '/shop.php';
$standard_part = include $pharmacy_dir . '/standard.php';
$account_part  = include $pharmacy_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// pharmacists, the requests, the shop, the standard pages, the account and
// staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $team_part['pages'], $request_part['pages'], $shop_part['pages'],
    $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $team_part['widgets'], $request_part['widgets'], $shop_part['widgets'],
    $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the health checks, being on duty, the about page, the
// questions, getting here, the team and booking are marked where they are
// built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('rx-contact-details', lang('address, phone, e-mail, counter, opening hours, delivery line')),
        lang('Inbox Note')      => array('rx-inbox-note', lang('answer, reply, conversation, inbox')),
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
// Two fonts: a round, open sans for the headings, easy to read at a
// glance, and a plain sans for the rest.
$pharmacy_fonts = array(
    array('family' => 'Lexend', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Source Sans 3', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named prx- (the
// field ids are rx-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
// The sector is a quiet one: nothing moves but a card under the pointer.
$pharmacy_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Lexend",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Source Sans 3",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the heading sans, a little tighter.
    '.prx-display{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.prx-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The pharmacy's name in the header and the footer.
    '.prx-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The cross beside the name: a small square tile.
    '.prx-cross{width:2rem;height:2rem;flex-shrink:0;font-size:1.1rem}',
    // A price or an hour: figures of one width, so a list's figures line up.
    '.prx-price{font-variant-numeric:tabular-nums;white-space:nowrap}',
    // The home page's first section: a soft wash of the primary colour in
    // one corner.
    '.prx-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,rgba(var(--bs-primary-rgb),.1),transparent 70%)}',
    // Service, check and pharmacist cards rise a little under the pointer.
    '.prx-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.prx-lift:hover,.prx-lift:focus-visible,.prx-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.prx-lift{transition:none}.prx-lift:hover,.prx-lift:focus-visible,.prx-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Pharmacy'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 115,
    'icon'        => 'bi-capsule',
    'description' => lang('A community pharmacy: services with a page each, health checks with their prices, the duty nights and how to find the pharmacy on duty, pharmacists with a page each, vaccinations and health checks booked online, prescription requests and questions to the pharmacist followed from the account, health products in the shop, Health Advice, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's teal. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'modern-soft',
    'palette'     => 'ocean',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Services with a page each, health checks with their prices and the duty nights with the way to the pharmacy on duty.'),
        lang('Pharmacists with a page each, filtered by service, and vaccinations and health checks booked at a free time.'),
        lang('Prescription requests and questions to the pharmacist followed from the account, with the status and the answers.'),
        lang('Health products in the shop, Health Advice by topic, a newsletter, an inbox, a staff area and a help page.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_pharmacy', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Pharmacy Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the pharmacy.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Pharmacy Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Pharmacy Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Pharmacy Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Pharmacy Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Pharmacy Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Pharmacy Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Pharmacy E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Pharmacy E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its prx- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $pharmacy_css),
        'fonts' => json_encode($pharmacy_fonts),
    ),
);
