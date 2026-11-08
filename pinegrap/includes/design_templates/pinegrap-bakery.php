<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery": an artisan bakery and patisserie. A home page
 * with the daily bake, the cakes, the baking classes and the Journal; the
 * daily bake by kind with its prices, the celebration cakes with their
 * sizes, sponges and fillings, ordering and collecting, wholesale, the
 * visit page, about and the questions; custom cake orders and wholesale
 * enquiries the visitor follows from their account, with the status and
 * the bakery's answers; baking classes with a page each and a place
 * request; bread, pastries, cakes, gift boxes and class places sold in a
 * pickup shop when the shop is on; a contact page whose messages become
 * conversations, the Journal with its post page, a newsletter, the sign-in
 * and account pages, a staff area (the new class, Journal and staff
 * directory forms, the cake orders, the class place requests and the
 * wholesale enquiries), a help page that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-bakery/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * bakery's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, classes.php, requests.php, wholesale.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set (wholesale.php builds on requests.php's $bk_…
 * builders). parts.php keeps the bakery's data in $bakes, $cakes,
 * $baking_classes, $wholesale_ranges and $testimonials, shop.php its
 * catalog in $catalog, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the Bakery
 * Shop groups with their products ('catalog', _pg_tpl_catalog()). Only
 * the shop pages need the shop; the shared parts never link them, and the
 * order and collect page links the shop only while it is on.
 *
 * A custom cake order, a class place request and a wholesale enquiry are
 * records of their own forms, requests and never sales: a visitor without
 * an account gets one (auto-registration), so the record's page and the
 * account's list show it at once. Its Status is an office-use-only field
 * the staff change in the control panel; the messages under it are the
 * page's comments, mailed to the visitor and to the bakery. A baking class
 * is a record of a collection form: the staff add one on the new class
 * page and the lists show it at once, filtered by the address (?level=).
 * The pickup day of a shop order is the checkout's requested arrival date
 * and the place a pickup shipping method, both set up by the store; the
 * template keeps no daily baking limits and no class capacity.
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
// is kept under a _part name ($bakes, $cakes, $baking_classes and the
// shop's $catalog are the parts' data).
$bakery_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
include $bakery_dir . '/parts.php';
include $bakery_dir . '/mail.php';
$public_part    = include $bakery_dir . '/pages_public.php';
$class_part     = include $bakery_dir . '/classes.php';
$request_part   = include $bakery_dir . '/requests.php';
$wholesale_part = include $bakery_dir . '/wholesale.php';
$shop_part      = include $bakery_dir . '/shop.php';
$standard_part  = include $bakery_dir . '/standard.php';
$account_part   = include $bakery_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// classes, the cake orders and class places, the wholesale enquiries, the
// shop, the standard pages, the account and staff pages, the e-mails last
// of each part.
$pages = array_merge($public_part['pages'], $class_part['pages'], $request_part['pages'], $wholesale_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $class_part['widgets'], $request_part['widgets'], $wholesale_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The daily bake, the cakes, ordering and collecting, wholesale, the visit
// page and the questions are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, bakery, opening hours, directions')),
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
// Two fonts: a high-contrast serif for the headings and the prices, like
// the lettering on a patisserie's window, and a soft sans for the rest.
$bakery_fonts = array(
    array('family' => 'Playfair Display', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pbk- (the
// field and section ids are bky-); no block rule's class is named here.
// Selectors use descendants and pseudo-classes only, so they hold on the
// editor's canvas too. Colours are tokens only, so they follow any
// palette in both themes.
$bakery_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Playfair Display",Didot,"Bodoni MT",Georgia,serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the serif, bold, a little tighter.
    '.pbk-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.pbk-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The bakery's name in the header and the footer.
    '.pbk-brand{font-family:var(--pg-font-heading);font-weight:700;font-style:italic;letter-spacing:.005em}',
    // A price: the serif with figures of one width, so a menu's prices line up.
    '.pbk-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums lining-nums}',
    // What a customer says: the serif in italics, a little larger.
    '.pbk-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.125rem;line-height:1.55}',
    // The home page's first section: a warm glow of the primary colour from
    // the upper left corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pbk-hero{background-image:radial-gradient(ellipse 70% 80% at 0% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // Cake, class and range cards rise a little under the pointer.
    '.pbk-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pbk-lift:hover,.pbk-lift:focus-visible,.pbk-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pbk-lift{transition:none}.pbk-lift:hover,.pbk-lift:focus-visible,.pbk-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Bakery'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 137,
    'icon'        => 'bi-cake2',
    'description' => lang('An artisan bakery and patisserie: the daily bake with its prices, celebration cakes with their sizes and flavours, ordering ahead and collecting, custom cake orders and wholesale enquiries the visitor follows from their account, baking classes with a page each and a place request, bread, pastries, cakes, gift boxes and class places in a pickup shop when it is on, the Journal, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's rose. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'organic',
    'palette'     => 'coral',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The daily bake by kind with its prices, celebration cakes with their sizes, sponges and fillings, and how ordering ahead and collecting work.'),
        lang('Custom cake orders and wholesale enquiries with their own page, status and the bakery\'s answers.'),
        lang('Baking classes with a page each and a place request, and sample classes the staff replace from a form.'),
        lang('With the shop on, bread, pastries, cakes, gift boxes and class places collected on the day the customer chooses.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // A cake order's inspiration photo is kept in the private folder.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_bakery', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Bakery Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the bakery.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Bakery Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Bakery Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Bakery Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Bakery Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Bakery Staff Bar'), 'tree' => $staff_bar()),
        'order_band'   => array('name' => lang('Bakery Order Band'), 'tree' => $order_band()),
        'email_header' => array('name' => lang('Bakery E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Bakery E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pbk- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $bakery_css),
        'fonts' => json_encode($bakery_fonts),
    ),
);
