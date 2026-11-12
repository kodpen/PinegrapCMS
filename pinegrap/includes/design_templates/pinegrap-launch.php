<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site": a maker's site for its new phone,
 * the Orbit, in three models. A dark home page with the phone on top, its
 * highlights, colours, camera and what the press says; the features, the
 * camera, the specifications per model, the compare table, the about page
 * and the questions; a buy page that always stands (with the shop on its
 * cards add a phone to the cart, without it they lead to the launch list
 * and the contact page), the accessories with the product, cart, checkout
 * and order pages when the shop is on; trade-in and support requests the
 * customer follows from their account (each request's own page with its
 * messages, and the lists of their own); a contact page whose messages
 * become conversations, the Newsroom (the blog with its post page), the
 * launch list, the sign-in and account pages, a staff area (the requests,
 * the press release and staff directory forms) and a help center that
 * searches the whole site. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-launch/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * phone's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, shop.php, requests.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 *
 * Opening the template gives the site the Phones and Accessories groups of
 * the catalog, one product per model and storage size and one per
 * accessory ('catalog', from shop.php), and the launch list's contact
 * group ('contact_groups'), both made once and found again by their names
 * the next time (_pg_tpl_catalog(), _pg_tpl_contact_groups()). The shop
 * pages need the shop ('requires' on each page); the buy page is always
 * there.
 *
 * A trade-in or a support request is a record of the Trade-in or the
 * Support form: the model comes from the address of the button that opens
 * it, a visitor without an account gets one (auto-registration), and its
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog ('root': the site's top group)
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
// is kept under a _part name ($models is parts.php's phone).
$launch_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
include $launch_dir . '/parts.php';
include $launch_dir . '/mail.php';
$public_part   = include $launch_dir . '/pages_public.php';
$shop_part     = include $launch_dir . '/shop.php';
$request_part  = include $launch_dir . '/requests.php';
$standard_part = include $launch_dir . '/standard.php';
$account_part  = include $launch_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// shop, the requests, the standard pages, the account and staff pages, the
// e-mails last of each part.
$pages   = array_merge($public_part['pages'], $shop_part['pages'], $request_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $shop_part['widgets'], $request_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The highlights, colours, specifications, compare table, story, models to
// buy, questions and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, customer service, press office')),
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
// Two fonts: a crisp geometric sans for the headings and the figures, a
// plain sans for the rest.
$launch_fonts = array(
    array('family' => 'Manrope', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named plx- (field
// ids use lx-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes,
// except the swatches: they show the phone's own finishes. A tint mixed
// with color-mix() comes after a plain fallback.
$launch_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Manrope",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:-.02em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.plx-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The maker's name in the header and the footer, in the heading sans.
    '.plx-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.02em}',
    // A highlight's figure and every price: the heading sans with figures
    // of one width, so the prices of a list line up.
    '.plx-figure{font-family:var(--pg-font-heading);letter-spacing:-.03em;font-variant-numeric:tabular-nums}',
    '.plx-price{font-variant-numeric:tabular-nums}',
    // The launch band: a glow of the palette's two colours from its corners.
    '.plx-glow{background-color:var(--bs-body-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.plx-glow{background-image:radial-gradient(circle at 0% 100%,color-mix(in srgb,var(--bs-primary) 24%,var(--bs-body-bg)) 0%,transparent 45%),radial-gradient(circle at 100% 0%,color-mix(in srgb,var(--bs-secondary) 18%,var(--bs-body-bg)) 0%,transparent 45%)}}',
    // The colour chips: the phone's finishes, the same in both themes.
    '.plx-swatch{display:inline-block;width:2.25rem;height:2.25rem}',
    '.plx-swatch-graphite{background-color:#3f4247}',
    '.plx-swatch-glacier{background-color:#eef3f7}',
    '.plx-swatch-sage{background-color:#a3b8a0}',
    '.plx-swatch-coral{background-color:#f08a73}',
    // Cards that lead somewhere rise a little under the pointer.
    '.plx-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.plx-lift:hover,.plx-lift:focus-visible,.plx-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.plx-lift{transition:none}.plx-lift:hover,.plx-lift:focus-visible,.plx-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Phone Launch Site'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 74,
    'icon'        => 'bi-phone',
    'description' => lang('A maker\'s launch site for its new phone: three models with their features, camera, specifications and a compare table, a buy page and accessories with the shop on, trade-in and support requests the customer follows from their account, the Newsroom, a launch list, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's violet. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'minimal',
    'palette'     => 'lavender',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The phone in three models: highlights, features, the camera, the specifications of each model and a compare table, every figure written once.'),
        lang('A buy page that always stands: with the shop on, each model and storage size is a product with cart and checkout, beside the accessories.'),
        lang('Trade-in and support requests with their own page, status and messages, followed from the customer\'s account.'),
        lang('The Newsroom, a launch list, an inbox, a staff area with the requests and a help center that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_launch', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'launch_list' => array(
            'name'         => lang('Launch List'),
            'description'  => lang('The addresses left on the launch list form of the phone launch site.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Launch Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Launch Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Launch Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Launch Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Launch Staff Bar'), 'tree' => $staff_bar()),
        'launch_band'  => array('name' => lang('Launch Band'), 'tree' => $launch_band()),
        'email_header' => array('name' => lang('Launch E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Launch E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the Product Launch blocks its
    // public pages place, and its plx- rules, in one User Styles
    // entry; and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('launch-hero', 'launch-showcase', 'launch-camera', 'variant-picker', 'launch-specs', 'launch-box'), $launch_css),
        'fonts' => json_encode($launch_fonts),
    ),
);
