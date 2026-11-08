<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services": one company that cleans, repairs and
 * looks after homes (cleaning, plumbing, electrical work, handyman jobs).
 * A home page with the service categories, how a job goes, why customers
 * choose the company and what they say; the services by category with a
 * page per service and its customer reviews; the price guide, the service
 * areas with their visit days, the team, the questions and the reviews;
 * a visit booking with a day and a time window, and a free estimate
 * request with photos, each followed from the account with its status and
 * the company's answers; care plans and gift cards in the shop when the
 * shop is on; a contact page whose messages become conversations, the
 * Tips blog with its post page, a newsletter, the sign-in and account
 * pages, a staff area (the new service and team member forms, the visit
 * bookings, the estimate requests, the blog and staff directory forms), a
 * customer help that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-homeservices/
 * (not read as templates of their own): parts.php holds the node builders,
 * the company's data and the parts every page repeats; mail.php the
 * e-mail builders; pages_public.php, services.php, visits.php,
 * estimates.php, shop.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps its sample
 * data in $services, $areas, $team, $care_plans and $testimonials, so no
 * part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the company sends the newsletter to it by
 * hand. With the shop on it also gets the Care Plans group and its
 * products ('catalog', _pg_tpl_catalog()); only the shop pages need the
 * shop, and the shared parts never link them.
 *
 * A service and a team member are records of the Services and Team forms,
 * collections: the staff add one on the new service or new team member
 * page and the lists show it at once. A visit booking and an estimate
 * request are records of their own forms, requests and never confirmed
 * slots: a customer without an account gets one (auto-registration), so
 * the request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * customer and to the company, the estimate itself among them. The
 * template keeps no technician calendar and takes no payment for a visit.
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
// is kept under a _part name ($services, $areas, $team, $care_plans and
// $testimonials are parts.php's sample data).
$homeservices_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
include $homeservices_dir . '/parts.php';
include $homeservices_dir . '/mail.php';
$public_part   = include $homeservices_dir . '/pages_public.php';
$service_part  = include $homeservices_dir . '/services.php';
$visit_part    = include $homeservices_dir . '/visits.php';
$estimate_part = include $homeservices_dir . '/estimates.php';
$shop_part     = include $homeservices_dir . '/shop.php';
$standard_part = include $homeservices_dir . '/standard.php';
$account_part  = include $homeservices_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// services and the team, the visit bookings, the estimates, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part['pages'], $service_part['pages'], $visit_part['pages'], $estimate_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $service_part['widgets'], $visit_part['widgets'], $estimate_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The service areas, the price guide, the questions, the about page and
// the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, emergency line, e-mail, opening hours, visits, estimates')),
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
// Two fonts: a firm geometric sans for the headings, the prices and the
// company's name, an easy-reading sans for the rest.
$homeservices_fonts = array(
    array('family' => 'Manrope', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Source Sans 3', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named phs- (the
// field ids are hs-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$homeservices_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Manrope",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Source Sans 3",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles and service names: the heading sans, a little tighter.
    '.phs-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.phs-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The company's name in the header and the footer.
    '.phs-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.02em}',
    // A price in the guide, on a card or in the shop: figures of one
    // width, so the prices of a column line up.
    '.phs-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;white-space:nowrap}',
    // What a customer says: a little larger, with room between the lines.
    '.phs-quote{font-size:1.125rem;line-height:1.6}',
    // Service, category, team, product and staff cards rise a little under
    // the pointer.
    '.phs-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.phs-lift:hover,.phs-lift:focus-visible,.phs-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.phs-lift{transition:none}.phs-lift:hover,.phs-lift:focus-visible,.phs-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Home Services'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 123,
    'icon'        => 'bi-tools',
    'description' => lang('A home services company: the services by category with a page per service and customer reviews, a price guide, the service areas, the team, visit bookings and free estimate requests with photos the customer follows from their account, care plans and gift cards with the shop on, home care tips, a newsletter, an inbox, a staff area and a customer help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'modern-sharp',
    'palette'     => 'steel',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The services by category with a page per service, its price and customer reviews, and sample services the staff replace from a form.'),
        lang('Visit bookings with a day and a time window, and free estimate requests with photos, each with its own page, status and the company\'s answers.'),
        lang('A price guide, the service areas with their visit days, the team and the customers\' words.'),
        lang('Care plans and gift cards with the shop on, home care tips, a newsletter, an inbox, a staff area and a customer help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_homeservices', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Home Services Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the home services company.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Home Services Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Home Services Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Home Services Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Home Services Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Home Services Staff Bar'), 'tree' => $staff_bar()),
        'visit_band'   => array('name' => lang('Home Services Visit Band'), 'tree' => $visit_band()),
        'email_header' => array('name' => lang('Home Services E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Home Services E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its phs- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $homeservices_css),
        'fonts' => json_encode($homeservices_fonts),
    ),
);
