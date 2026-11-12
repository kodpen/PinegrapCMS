<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant": one chef-led restaurant. A home page with
 * the featured dishes, the tasting menus, the coming events and what
 * guests say; the menu in one tab per course, one page per dish; the
 * tasting menus, the wine list, the about page with the team, the
 * questions and getting here; a table reservation request the guest
 * follows from their account, with the status and the restaurant's
 * answers; the chef's table booked at a free time; the events (wine
 * dinners, cooking classes) with a page each; private dining with the
 * rooms and an enquiry followed from the account; gift vouchers in the
 * shop when the shop is on; a contact page whose messages become
 * conversations, the Kitchen Journal (the blog) with its post page, a
 * newsletter, the sign-in and account pages, a staff area (the new dish
 * and event forms, the reservation requests, the enquiries, the blog and
 * staff directory forms), a guest help that searches the whole site and
 * the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-restaurant/
 * (not read as templates of their own): parts.php holds the node builders,
 * the restaurant's data and the parts every page repeats; mail.php the
 * e-mail builders; pages_public.php, menu.php, reservations.php,
 * private.php, shop.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps its sample
 * data in $dishes, $tastings, $rooms, $events and $vouchers, so no part's
 * return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the restaurant sends the newsletter to it by
 * hand. With the shop on it also gets the Gift Vouchers group and its
 * products ('catalog', _pg_tpl_catalog()); only the shop pages need the
 * shop, and the shared parts never link them.
 *
 * A dish and an event are records of the Menu and Events forms,
 * collections: the staff add one on the new dish or new event page and
 * the lists show it at once. A reservation request and a private dining
 * enquiry are records of their own forms, requests and never confirmed
 * tables: a guest without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * guest and to the restaurant. Only the chef's table uses the booking
 * widget (one table, one sitting an evening). The template keeps no table
 * stock and takes no deposit.
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
// is kept under a _part name ($dishes, $tastings, $rooms, $events and
// $vouchers are parts.php's sample data).
$restaurant_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant';
include $restaurant_dir . '/parts.php';
include $restaurant_dir . '/mail.php';
$public_part      = include $restaurant_dir . '/pages_public.php';
$menu_part        = include $restaurant_dir . '/menu.php';
$reservation_part = include $restaurant_dir . '/reservations.php';
$private_part     = include $restaurant_dir . '/private.php';
$shop_part        = include $restaurant_dir . '/shop.php';
$standard_part    = include $restaurant_dir . '/standard.php';
$account_part     = include $restaurant_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// menu and the events, the reservations, private dining, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part['pages'], $menu_part['pages'], $reservation_part['pages'], $private_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $menu_part['widgets'], $reservation_part['widgets'], $private_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The menu, the tasting menus, the wine list, the about page, private
// dining, getting here, the questions and the privacy notice are marked
// where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, reservations, allergies, opening hours')),
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
// Two fonts: a refined serif for the headings, the prices and the guests'
// words, a clean geometric sans for the rest.
$restaurant_fonts = array(
    array('family' => 'Cormorant Garamond', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Jost', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named prt- (the
// field ids are rt-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$restaurant_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Cormorant Garamond",Garamond,Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Jost",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:500;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles and dish names: the serif, a little lighter.
    '.prt-display{font-family:var(--pg-font-heading);font-weight:500}',
    // The small line over a title: spaced capitals.
    '.prt-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The restaurant's name in the header and the footer, in the serif.
    '.prt-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // The home page's opening band: a warm wash of the palette's colour
    // from the top corner.
    '.prt-hero{background-image:radial-gradient(ellipse at 0 0,rgba(var(--bs-primary-rgb),.1),transparent 60%)}',
    // A price on the menu or the wine list: figures of one width, so the
    // prices of a column line up.
    '.prt-price{font-variant-numeric:tabular-nums;white-space:nowrap}',
    // What a guest says: the serif in italics.
    '.prt-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.25rem;line-height:1.55}',
    // Dish, tasting menu, room and voucher cards rise a little under the
    // pointer.
    '.prt-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.prt-lift:hover,.prt-lift:focus-visible,.prt-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.prt-lift{transition:none}.prt-lift:hover,.prt-lift:focus-visible,.prt-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.prt-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
));

return array(
    'name'        => lang('Restaurant'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 109,
    'icon'        => 'bi-egg-fried',
    'description' => lang('A chef-led restaurant: the menu by course with a page per dish, tasting menus and the wine list, table reservation requests the guest follows from their account, the chef\'s table, events, private dining enquiries, gift vouchers with the shop on, the Kitchen Journal, a newsletter, an inbox, a staff area and a guest help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline accent is this palette's burgundy. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'elegant',
    'palette'     => 'burgundy',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The menu by course with a page per dish, diet tags, and sample dishes the staff replace from a form.'),
        lang('Table reservation requests with their own page, status and the restaurant\'s answers, and the chef\'s table booked at a free time.'),
        lang('Tasting menus, the wine list, events, and private dining enquiries followed from the account.'),
        lang('Gift vouchers with the shop on, the Kitchen Journal, a newsletter, an inbox, a staff area and a guest help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_restaurant', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Restaurant Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the restaurant.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Restaurant Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Restaurant Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Restaurant Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Restaurant Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Restaurant Staff Bar'), 'tree' => $staff_bar()),
        'reserve_band' => array('name' => lang('Restaurant Reserve Band'), 'tree' => $reserve_band()),
        'email_header' => array('name' => lang('Restaurant E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Restaurant E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its prt- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $restaurant_css),
        'fonts' => json_encode($restaurant_fonts),
    ),
);
