<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Real Estate Agency": an estate agency that sells and
 * lets homes. A home page with a property search, the newest listings, the
 * neighbourhoods, the agents and what clients say; the listings to buy and
 * to rent with filters by type and neighbourhood, one page per listing,
 * five neighbourhoods with a page each, the agents, the services (sell,
 * let, manage) with the fees, the about page, the questions and a buyer's
 * guide; a viewing request and a valuation request the client follows from
 * their account, with the status and the agent's messages; a contact page
 * whose messages become conversations, Market News (the blog) with its
 * post page, listing alerts by e-mail, the sign-in and account pages, a
 * staff area (the new listing form, the requests, the blog and staff
 * directory forms), a help center that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-realestate/
 * (not read as templates of their own): parts.php holds the node builders,
 * the agency's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, listings.php, requests.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 * parts.php keeps the sample listings in $listings, so no part's return is
 * kept under that name.
 *
 * Opening the template gives the site the listing alerts' contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the staff send the new listings to it by
 * hand. There is no catalog: a home is not bought through a cart.
 *
 * A listing is a record of the Listings form, a collection: the staff add
 * one on the new listing page and the lists show it at once, filtered by
 * the address (?deal=, ?type=, ?area=). A viewing or a valuation is a
 * record of its own form; a visitor without an account gets one
 * (auto-registration), so the request's page and the account's list show
 * it at once. Its Status is an office-use-only field the staff change in
 * the control panel; the messages under it are the page's comments,
 * mailed to the client and to the agency.
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
// is kept under a _part name ($listings is parts.php's sample data). The
// public part returns its pages only.
$realestate_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate';
include $realestate_dir . '/parts.php';
include $realestate_dir . '/mail.php';
$public_part   = include $realestate_dir . '/pages_public.php';
$listing_part  = include $realestate_dir . '/listings.php';
$request_part  = include $realestate_dir . '/requests.php';
$standard_part = include $realestate_dir . '/standard.php';
$account_part  = include $realestate_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// listings, the requests, the standard pages, the account and staff pages,
// the e-mails last of each part.
$pages   = array_merge($public_part, $listing_part['pages'], $request_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($listing_part['widgets'], $request_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the neighbourhoods, the agents, the fees, the about page,
// the guide, the questions and the privacy notice are marked where they
// are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office hours, location')),
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
// Two fonts: a warm serif for the headings, a clear sans for the rest and
// the prices.
$realestate_fonts = array(
    array('family' => 'Fraunces', 'weights' => array('600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pre- (Bootstrap
// owns pe-, its padding utilities); no block rule's class is named here.
// Selectors use descendants and pseudo-classes only, so they hold on the
// editor's canvas too. Colours are tokens only, so they follow any palette
// in both themes; a tint mixed with color-mix() comes after a plain
// fallback.
$realestate_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Fraunces",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a title: spaced capitals.
    '.pre-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The agency's name in the header, in the heading serif.
    '.pre-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // A listing's price: the body font with figures of one width, so the
    // prices of a list line up.
    '.pre-price{font-family:var(--pg-font-body);font-variant-numeric:tabular-nums;letter-spacing:-.01em}',
    // The home page's first section: a soft wash of the palette's colour.
    '.pre-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pre-hero{background-image:linear-gradient(165deg,color-mix(in srgb,var(--bs-primary) 10%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 70%);background-color:var(--bs-body-bg)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.pre-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pre-lift:hover,.pre-lift:focus-visible,.pre-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pre-lift{transition:none}.pre-lift:hover,.pre-lift:focus-visible,.pre-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pre-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Real Estate Agency'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 69,
    'icon'        => 'bi-house-door',
    'description' => lang('An estate agency: homes to buy and to rent with a page each and filters by type and neighbourhood, five neighbourhoods, the agents, the services and fees, viewing and valuation requests the client follows from their account, listing alerts, Market News, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's teal. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'modern-sharp',
    'palette'     => 'ocean',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Homes to buy and to rent with a page each, filters by type and neighbourhood, and six sample listings the staff replace from a form.'),
        lang('Five neighbourhoods with a page each, the agents with the areas they know, the services and the fees, and a buyer\'s guide.'),
        lang('Viewing and valuation requests with their own page, status and the agent\'s messages, followed from the client\'s account.'),
        lang('Listing alerts by e-mail, Market News, an inbox, a staff area with the requests and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_realestate', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'listing_alerts' => array(
            'name'         => lang('Listing Alerts'),
            'description'  => lang('The addresses left on the listing alerts form of the estate agency.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'    => array('name' => lang('Real Estate Account Bar'), 'tree' => $account_bar_tree()),
        'notice'         => array('name' => lang('Real Estate Notice'), 'tree' => $notice()),
        'header'         => array('name' => lang('Real Estate Header'), 'tree' => $header()),
        'footer'         => array('name' => lang('Real Estate Footer'), 'tree' => $footer()),
        'staff_bar'      => array('name' => lang('Real Estate Staff Bar'), 'tree' => $staff_bar()),
        'valuation_band' => array('name' => lang('Real Estate Valuation Band'), 'tree' => $valuation_band()),
        'email_header'   => array('name' => lang('Real Estate E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'   => array('name' => lang('Real Estate E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pre- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $realestate_css),
        'fonts' => json_encode($realestate_fonts),
    ),
);
