<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace": a market of independent makers whose
 * goods the site owner sells in one cart, one checkout and one order. A
 * home page with the search, the categories, the featured makers, the
 * products and the Journal; the categories, how it works, sell with us,
 * about, the questions and a gift guide; the makers' list with category
 * and city filters and a page per maker with its reviews; the shop (a
 * group per category and one per maker), the product, cart, checkout and
 * order pages when the shop is on; a maker's application and a help
 * request about an order, each followed from the account on its own page
 * with its messages; a contact page whose messages become conversations,
 * the Journal (the blog, with the makers' stories) and its post page, a
 * newsletter, the sign-in and account pages, a staff area (the
 * applications, the help requests, the new maker, Journal and staff
 * directory forms), a search that finds products, makers and pages alike
 * and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-market/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * market's data (categories, makers, products, prices) and the parts every
 * page repeats; mail.php the e-mail builders; pages_public.php, shop.php,
 * sellers.php, requests.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set.
 *
 * Opening the template gives the site the Marketplace group of the catalog
 * with a group per category, the Shops group with a group per maker, and
 * the products, each in its category and in its maker's shop ('catalog',
 * from shop.php), and the newsletter's contact group ('contact_groups'),
 * both made once and found again by their names the next time
 * (_pg_tpl_catalog(), _pg_tpl_contact_groups()). The shop pages need the
 * shop ('requires' on each page); the categories, the makers and their
 * reviews stand without it.
 *
 * A maker is a record of the Sellers form, a collection, never an account:
 * the staff add one by hand (New Maker) once an application is approved.
 * An application and a help request are records of their own forms: a
 * visitor without an account gets one (auto-registration), and its Status
 * is an office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog ('root': the site's top group)
 *   {{product_group_path:<key>}}    address name of a group of the catalog
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
// is kept under a _part name ($sellers and $market_products are parts.php's
// samples).
$market_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
include $market_dir . '/parts.php';
include $market_dir . '/mail.php';
$public_part   = include $market_dir . '/pages_public.php';
$shop_part     = include $market_dir . '/shop.php';
$seller_part   = include $market_dir . '/sellers.php';
$request_part  = include $market_dir . '/requests.php';
$standard_part = include $market_dir . '/standard.php';
$account_part  = include $market_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// shop, the makers, the requests, the standard pages, the account and staff
// pages, the e-mails last of each part.
$pages   = array_merge($public_part['pages'], $shop_part['pages'], $seller_part['pages'], $request_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $shop_part['widgets'], $seller_part['widgets'], $request_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The categories, steps, reasons to sell, gift ideas, questions, shop and
// the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, customer service, makers\' desk')),
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
// Two fonts: a soft serif with some warmth for the headings, the market's
// name and the prices, a plain sans for the rest.
$market_fonts = array(
    array('family' => 'Fraunces', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'DM Sans', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pmk- (field
// ids use mk-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
// A tint mixed with color-mix() comes after a plain fallback.
$market_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Fraunces",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"DM Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.pmk-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The market's name in the header and the footer, in the heading serif.
    '.pmk-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // Every price: figures of one width, so the prices of a list line up.
    '.pmk-price{font-variant-numeric:tabular-nums}',
    // The search box: a field wide enough for a few words, never wider than
    // the line.
    '.pmk-search{max-width:32rem}',
    // The home page's and the sell page's top: a soft light of the
    // palette's colour from a corner.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pmk-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // The maker band: the same light from the other corner.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pmk-band{background-image:radial-gradient(ellipse 60% 90% at 0% 100%,color-mix(in srgb,var(--bs-secondary) 12%,transparent) 0%,transparent 70%)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.pmk-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pmk-lift:hover,.pmk-lift:focus-visible,.pmk-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pmk-lift{transition:none}.pmk-lift:hover,.pmk-lift:focus-visible,.pmk-lift:focus-within{transform:none}}',
    // A long site name ends with an ellipsis instead of widening the page:
    // the brand may shrink in the open bar, and on a phone it leaves room
    // for the menu button on the same line.
    '.navbar-brand{min-width:0;max-width:calc(100% - 5rem)}',
    // A palette's text colour is made readable on the page background only:
    // on the tertiary bands (the account bar, the page headers, the grey
    // sections) Coral, Amber and Electric fall just under 4.5:1 in the light
    // theme, Midnight and Burgundy to 3.9:1 in the dark one. Small coloured
    // text, links, section menus and outline buttons there are mixed 10%
    // darker in the light theme and 15% lighter in the dark one, 4.8:1 or
    // more with every palette; a hovered or focused link keeps its own
    // shade.
    '@supports (color:color-mix(in srgb,red 50%,blue)){.bg-body-tertiary{--pmk-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 90%,black);--pmk-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 90%,black)}}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary{--pmk-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,white);--pmk-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 85%,white)}}',
    '.bg-body-tertiary :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--pmk-band-primary,var(--pg-primary-fg,var(--bs-primary)))!important}',
    '.bg-body-tertiary :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--pmk-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))!important}',
    '.bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--pmk-band-primary,var(--pg-primary-fg,var(--bs-primary)))}',
    '.bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--pmk-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))}',
    '.bg-body-tertiary .nav{--bs-nav-link-color:var(--pmk-band-primary,var(--bs-link-color))}',
    // On the palette's soft surface (bg-primary-subtle: the call to action
    // bands, the account card) the colour as text falls to 3.2:1 in the
    // light theme (Graphite's secondary); there it takes the emphasis tint
    // the surface is made for, 4.5:1 or more with every palette in both
    // themes.
    '.bg-primary-subtle :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-primary-subtle :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--bs-secondary-text-emphasis)!important}',
    '.bg-primary-subtle .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
));

return array(
    'name'        => lang('Marketplace'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 77,
    'icon'        => 'bi-shop',
    'description' => lang('A market of independent makers sold in one cart: categories, a page per maker with reviews, the shop by category and by maker with the shop on, applications from makers and order help requests followed from the account, a gift guide, the Journal, a newsletter, an inbox, a staff area and a search that finds products, makers and pages.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's burnt orange. The template's own
    // look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'organic',
    'palette'     => 'sunset',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Makers as a collection: a list with category and city filters, a page per maker with its story, its shop and its reviews, featured makers on the home page.'),
        lang('With the shop on, one cart for every maker: a group per category and one per maker, each product in both.'),
        lang('Applications from makers and help requests about orders, each with its own page, status and messages, followed from the account.'),
        lang('A gift guide, the Journal with maker stories, a newsletter, an inbox, a staff area and a search that finds products, makers and pages.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_market', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Marketplace Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the marketplace.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Marketplace Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Marketplace Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Marketplace Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Marketplace Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Marketplace Staff Bar'), 'tree' => $staff_bar()),
        'maker_band'   => array('name' => lang('Marketplace Maker Band'), 'tree' => $maker_band()),
        'email_header' => array('name' => lang('Marketplace E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Marketplace E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pmk- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $market_css),
        'fonts' => json_encode($market_fonts),
    ),
);
