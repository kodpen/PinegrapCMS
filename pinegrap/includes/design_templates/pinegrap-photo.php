<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio": a small studio for weddings,
 * portraits, families and brands. A home page with the featured galleries,
 * the categories, how a shoot works and what clients say; the galleries
 * filtered by category, one page per gallery with its photos and story;
 * the session packages with their prices, how a shoot works, the about
 * page and the questions; a shoot booking request the client follows from
 * their account, and studio mini sessions booked at a free time online;
 * a client area with a private proofing gallery, its downloads and the
 * proof selection the client sends; shoot deposits and gift vouchers in
 * the shop when the shop is on; a contact page whose messages become
 * conversations, the Photo Journal (the blog) with its post page, a
 * newsletter, the sign-in and account pages, a staff area (the new gallery
 * form, the shoot bookings, the proof selections, the blog and staff
 * directory forms), a client help that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-photo/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * studio's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, galleries.php, bookings.php, clients.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample galleries in
 * $galleries and the packages in $packages, so no part's return is kept
 * under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the studio sends the newsletter to it by
 * hand. With the shop on it also gets the Photo Sessions group and its
 * products ('catalog', _pg_tpl_catalog()); only the shop pages need the
 * shop, and the shared parts never link them.
 *
 * A gallery is a record of the Galleries form, a collection: the staff add
 * one on the new gallery page and the list shows it at once, filtered by
 * the address (?category=). A shoot booking and a proof selection are
 * records of their own forms, requests and never reservations: a client
 * without an account gets one (auto-registration), so the request's page
 * and the account's list show it at once. A request's Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments, mailed to the client and to
 * the studio. A client's proofing gallery is a page in a private folder:
 * the studio duplicates the sample folder for each client and gives that
 * client the folder.
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
// is kept under a _part name ($galleries and $packages are parts.php's
// sample data).
$photo_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo';
include $photo_dir . '/parts.php';
include $photo_dir . '/mail.php';
$public_part   = include $photo_dir . '/pages_public.php';
$gallery_part  = include $photo_dir . '/galleries.php';
$booking_part  = include $photo_dir . '/bookings.php';
$client_part   = include $photo_dir . '/clients.php';
$shop_part     = include $photo_dir . '/shop.php';
$standard_part = include $photo_dir . '/standard.php';
$account_part  = include $photo_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// galleries, the shoot bookings, the client area, the shop, the standard
// pages, the account and staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $gallery_part['pages'], $booking_part['pages'], $client_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $gallery_part['widgets'], $booking_part['widgets'], $client_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The home page, the packages, how a shoot works, the about page, the
// questions, the client area, online booking, the vouchers and the privacy
// notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, studio, opening hours, visit')),
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
// Two fonts: a display serif for the headings, the figures and the
// clients' words, a clean sans for the rest.
$photo_fonts = array(
    array('family' => 'DM Serif Display', 'weights' => array('400'), 'enabled' => true),
    array('family' => 'Manrope', 'weights' => array('400', '500', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pph- (the
// field ids are ph-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$photo_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"DM Serif Display",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Manrope",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:400;--pg-display-weight:400;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the display serif in its one weight.
    '.pph-display{font-family:var(--pg-font-heading);font-weight:400}',
    // The small line over a title: spaced capitals.
    '.pph-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The studio's name in the header and the footer, in the serif.
    '.pph-brand{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.02em}',
    // A price or a frame number: figures of one width, so they line up.
    '.pph-price{font-variant-numeric:tabular-nums}',
    // What a client says: the serif, a little larger.
    '.pph-quote{font-family:var(--pg-font-heading);font-size:1.15rem;line-height:1.55}',
    // The home page's opening: a soft fade from the tinted background into
    // the page, so the photo beside it reads as a print on paper.
    '.pph-hero{background-image:linear-gradient(180deg,var(--bs-tertiary-bg),var(--bs-body-bg))}',
    // Gallery, package and voucher cards rise a little under the pointer.
    '.pph-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pph-lift:hover,.pph-lift:focus-visible,.pph-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pph-lift{transition:none}.pph-lift:hover,.pph-lift:focus-visible,.pph-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Photography Studio'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 85,
    'icon'        => 'bi-camera',
    'description' => lang('A photography studio: galleries by category with a page each, session packages with their prices, how a shoot works, shoot booking requests the client follows from their account, studio mini sessions booked online, a client area with a private proofing gallery and photo selections, shoot deposits and gift vouchers with the shop on, the Photo Journal, a newsletter, an inbox, a staff area and a client help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's cool greys and blue. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'minimal',
    'palette'     => 'steel',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Galleries by category with a page each, and sample galleries the staff replace from a form.'),
        lang('Session packages with their prices, and shoot booking requests with their own page, status and the studio\'s answers.'),
        lang('A client area with a private proofing gallery, downloads and the photo selection the client sends.'),
        lang('Shoot deposits and gift vouchers with the shop on, the Photo Journal, a newsletter, an inbox, a staff area and a client help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_photo', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        // The sample client's proofing gallery: a private folder of its own
        // beside the staff's, so giving a client this folder (or a copy of
        // it) never opens the staff area. The studio duplicates it per client.
        'client_sample' => array('name' => 'client_sample', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Studio Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the photography studio.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Studio Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Studio Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Studio Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Studio Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Studio Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Studio Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Studio E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Studio E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pph- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $photo_css),
        'fonts' => json_encode($photo_fonts),
    ),
);
