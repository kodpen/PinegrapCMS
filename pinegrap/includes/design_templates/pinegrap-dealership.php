<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership": a dealer of new and used cars with a
 * showroom, a workshop and a finance desk. A home page with the featured
 * cars, the body styles and what customers say; the stock list with
 * filters by make, body style, fuel and condition, the new and the used
 * cars, one page per vehicle with its photos, facts and features; a test
 * drive request, a trade-in valuation and a finance request the customer
 * follows from their account, with the status and the dealer's answers;
 * the service centre, the ways to pay, the about page, visit us and the
 * questions; a contact page whose messages become conversations, Showroom
 * News (the blog) with its post page, a newsletter, the sign-in and
 * account pages, a staff area (the new vehicle form, the test drive,
 * trade-in and finance requests, the blog and staff directory forms), a
 * help center that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-dealership/
 * (not read as templates of their own): parts.php holds the node builders,
 * the dealer's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, vehicles.php, requests.php,
 * finance_requests.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps the sample
 * vehicles in $vehicles, so no part's return is kept under that name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the dealer sends the newsletter to it by
 * hand. There is no shop: a car is sold in the showroom.
 *
 * A vehicle is a record of the Vehicles form, a collection: the staff add
 * one on the new vehicle page and the lists show it at once, filtered by
 * the address (?make=, ?body=, ?fuel=, ?condition=). A test drive, a
 * trade-in and a finance request are records of their own forms; a
 * visitor without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * customer and to the dealer.
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
// is kept under a _part name ($vehicles is parts.php's sample data). The
// public part returns its pages only.
$dealership_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
include $dealership_dir . '/parts.php';
include $dealership_dir . '/mail.php';
$public_part   = include $dealership_dir . '/pages_public.php';
$vehicle_part  = include $dealership_dir . '/vehicles.php';
$request_part  = include $dealership_dir . '/requests.php';
$finance_part  = include $dealership_dir . '/finance_requests.php';
$standard_part = include $dealership_dir . '/standard.php';
$account_part  = include $dealership_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// vehicles, the test drives and trade-ins, the finance requests, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part, $vehicle_part['pages'], $request_part['pages'], $finance_part['pages'],
    $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($vehicle_part['widgets'], $request_part['widgets'], $finance_part['widgets'],
    $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the ways to pay, the about page, visit us, the questions
// and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, opening hours, showroom')),
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
// Two fonts: a firm, slightly narrow sans for the headings and the
// prices (its light weight for the showroom's large thin type), a clear
// sans for the rest.
$dealership_fonts = array(
    array('family' => 'Barlow', 'weights' => array('300', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pdl- (the
// field ids are dl-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$dealership_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Barlow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Large titles and figures: the heading font, drawn a little tighter.
    '.pdl-display{font-family:var(--pg-font-heading);letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.pdl-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The dealer's name in the header and the footer, in the heading font.
    '.pdl-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // A price: the heading font with figures of one width.
    '.pdl-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // Vehicle cards and body style tiles rise a little under the pointer.
    '.pdl-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pdl-lift:hover,.pdl-lift:focus-visible,.pdl-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pdl-lift{transition:none}.pdl-lift:hover,.pdl-lift:focus-visible,.pdl-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pdl-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
    // Grey sections (the account bar, page headers, grey bands) and cards
    // tinted with the primary (the account card): the palette's primary and
    // second colours are made readable on the page background only, and
    // across all palettes they fell to 3.9:1 there in the dark theme and to
    // 4.4:1 in the light one. Links, eyebrows, section menus and outline
    // buttons there take a tint of the primary: on grey, the primary a
    // little closer to the text colour (in the dark theme its emphasis tint,
    // as some palettes' primary is too dark there); on the primary's own
    // tint, and where the browser does not mix colours, the emphasis tint.
    // The second colour gives way to the body's second text colour.
    ':is(.bg-body-tertiary,.bg-primary-subtle){--tpl-tinted-fg:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '.bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,var(--bs-emphasis-color))}'
        . '[data-bs-theme=dark] .bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-primary,.link-primary){color:var(--tpl-tinted-fg)!important}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-primary{--bs-btn-color:var(--tpl-tinted-fg)}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .nav{--bs-nav-link-color:var(--tpl-tinted-fg);--bs-nav-link-hover-color:var(--bs-emphasis-color)}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-secondary,.link-secondary){color:var(--bs-secondary-color)!important}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
));

return array(
    'name'        => lang('Car Dealership'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 75,
    'icon'        => 'bi-car-front',
    'description' => lang('A car dealer: the stock with a page per vehicle and filters by make, body style, fuel and condition, new and used cars, test drive, trade-in and finance requests the customer follows from their account, the service centre, the ways to pay, a newsletter, Showroom News, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's blue. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'corporate',
    'palette'     => 'cobalt',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The stock with a page per vehicle, filters by make, body style, fuel and condition, and sample cars the staff replace from a form.'),
        lang('Test drive and trade-in requests with their own page, status and the dealer\'s answers.'),
        lang('Finance requests by plan, deposit and term, followed from the account, without an ID or bank number.'),
        lang('The service centre, Showroom News, a newsletter, an inbox, a staff area with the requests and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_dealership', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Showroom Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the car dealership.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'     => array('name' => lang('Dealership Account Bar'), 'tree' => $account_bar_tree()),
        'notice'          => array('name' => lang('Dealership Notice'), 'tree' => $notice()),
        'header'          => array('name' => lang('Dealership Header'), 'tree' => $header()),
        'footer'          => array('name' => lang('Dealership Footer'), 'tree' => $footer()),
        'staff_bar'       => array('name' => lang('Dealership Staff Bar'), 'tree' => $staff_bar()),
        'test_drive_band' => array('name' => lang('Dealership Test Drive Band'), 'tree' => $test_drive_band()),
        'email_header'    => array('name' => lang('Dealership E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'    => array('name' => lang('Dealership E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the palette blocks the home is
    // built on (the Car Dealership family and CTA: Sticky Bar), then its
    // pdl- rules, in one User Styles entry; and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('dealership-showroom', 'dealership-finder', 'dealership-ways', 'cta-sticky'), $dealership_css),
        'fonts' => json_encode($dealership_fonts),
    ),
);
