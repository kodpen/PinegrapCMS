<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue": a wedding planner with its
 * own garden venue by the sea. A home page with the packages, the spaces,
 * Real Weddings and what couples say; the planning services and the venue
 * packages with their prices, the venue's spaces with their capacity, the
 * gallery, the planning timeline from twelve months before to the day, the
 * about page and the questions; Real Weddings with filters by space and
 * season and a page each; a wedding enquiry the couple follows from their
 * account, with the status and the planner's answers; a venue visit or a
 * first consultation booked at a free time; date-hold deposits and gift
 * cards in the shop when the shop is on; a contact page whose messages
 * become conversations, the Wedding Journal (the blog) with its post page,
 * a newsletter, the sign-in and account pages, a staff area (the Real
 * Weddings form, the wedding enquiries, the blog and staff directory
 * forms), a help that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-wedding/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * planner's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, weddings.php, enquiries.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the sample weddings in $weddings, so no
 * part's return is kept under that name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the planner sends the newsletter to it by
 * hand. With the shop on it also gets the deposits and gift cards group
 * and its products ('catalog', _pg_tpl_catalog()); only the shop pages
 * need the shop, and the shared parts never link them.
 *
 * A Real Wedding is a record of the Real Weddings form, a collection: the
 * staff add one on its staff page and the lists show it at once, filtered
 * by the address (?space=, ?season=). A wedding enquiry is a record of its
 * own form, a request and never a booking of the date: a couple without an
 * account gets one (auto-registration), so the enquiry's page and My
 * Enquiries show it at once. Its Status is an office-use-only field the
 * staff change in the control panel; the messages under it are the page's
 * comments, mailed to the couple and to the planner. The template keeps no
 * calendar of the venue's dates.
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
// is kept under a _part name ($weddings is parts.php's sample data).
$wedding_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding';
include $wedding_dir . '/parts.php';
include $wedding_dir . '/mail.php';
$public_part   = include $wedding_dir . '/pages_public.php';
$wedding_part  = include $wedding_dir . '/weddings.php';
$enquiry_part  = include $wedding_dir . '/enquiries.php';
$shop_part     = include $wedding_dir . '/shop.php';
$standard_part = include $wedding_dir . '/standard.php';
$account_part  = include $wedding_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, Real
// Weddings, the enquiries and the venue visit, the shop, the standard
// pages, the account and staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $wedding_part['pages'], $enquiry_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $wedding_part['widgets'], $enquiry_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The about page, the services, the packages, the venue, the timeline, the
// questions and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office hours, wedding planner')),
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
// Two fonts: an engraved-looking serif for the headings, the prices and the
// couples' words, a soft sans for the rest.
$wedding_fonts = array(
    array('family' => 'Marcellus', 'weights' => array('400'), 'enabled' => true),
    array('family' => 'Lato', 'weights' => array('400', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pwd- (the
// field ids are wd-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$wedding_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Marcellus",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Lato",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:400;--pg-display-weight:400;--pg-heading-tracking:.01em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the serif, with a little air between the letters.
    '.pwd-display{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.01em}',
    // The small line over a title: spaced capitals.
    '.pwd-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The planner's name in the header and the footer, in the serif.
    '.pwd-brand{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.04em}',
    // A price: figures of one width, so the packages' prices line up.
    '.pwd-price{font-variant-numeric:tabular-nums}',
    // What a couple says: the serif, a little larger.
    '.pwd-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.6}',
    // Service, package, space and wedding cards rise a little under the
    // pointer.
    '.pwd-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pwd-lift:hover,.pwd-lift:focus-visible,.pwd-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pwd-lift{transition:none}.pwd-lift:hover,.pwd-lift:focus-visible,.pwd-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pwd-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Wedding Planner and Venue'),
    'version'     => '2.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 87,
    'icon'        => 'bi-balloon-heart',
    'description' => lang('A wedding planner with its own garden venue: planning services and venue packages with their prices, the spaces with their capacity, a gallery, Real Weddings, the planning timeline, wedding enquiries the couple follows from their account, venue visits booked at a free time, date-hold deposits and gift cards with the shop on, the Wedding Journal, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's greens. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'luxe',
    'palette'     => 'sage',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Planning services and venue packages with their prices, and the venue\'s spaces with their capacity.'),
        lang('Real Weddings with a page each, filters by space and season, and sample stories the staff replace from a form.'),
        lang('Wedding enquiries with their own page, status and the planner\'s answers, and venue visits booked at a free time.'),
        lang('Date-hold deposits and gift cards with the shop on, the Wedding Journal, a newsletter, an inbox, a staff area and a help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_wedding', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Wedding Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the wedding planner.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Wedding Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Wedding Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Wedding Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Wedding Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Wedding Staff Bar'), 'tree' => $staff_bar()),
        'date_band'    => array('name' => lang('Wedding Date Band'), 'tree' => $date_band()),
        'email_header' => array('name' => lang('Wedding E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Wedding E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Wedding blocks' pg-wedding- rules and its
    // pwd- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('wedding-hero', 'wedding-story', 'wedding-checklist'), $wedding_css),
        'fonts' => json_encode($wedding_fonts),
    ),
);
