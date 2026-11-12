<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre": a theatre or performing-arts venue with its
 * season. A home page with the productions now playing, the next
 * performances, the box office hours, venue hire, what audiences say and
 * the news; the season (productions now playing, coming to the stage and
 * past) with a page per production, filtered by genre and state; the
 * performances from today with a page each, filtered by production and by
 * access performance, and the past ones; your visit (the box office,
 * getting there, access, the auditorium as words), support and friends as
 * words, the about page and the questions; venue hire with an enquiry,
 * learning with school group requests and youth theatre places, each
 * followed from the account with the status and the theatre's answers;
 * tickets, friends membership and gift vouchers in the shop; a contact
 * page whose messages become conversations, News (the blog) with its post
 * page, a newsletter, the sign-in and account pages, a staff area (the new
 * production and new performance forms, the three request lists, the blog
 * and staff directory forms), a help center that searches the whole site
 * and the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-theatre/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * theatre's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, productions.php, performances.php, hire.php,
 * learning.php, shop.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set. parts.php keeps the sample
 * data in $productions, $performances, $tickets, $tiers, $youth_groups,
 * $workshops, $spaces and $people, and shop.php its catalog in $catalog,
 * so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the theatre sends the newsletter to it by
 * hand. The catalog (ticket bands, friends tiers, gift vouchers) is made
 * only on a site with the shop; the shop's pages carry 'requires' and
 * leave a site without it.
 *
 * A production and a performance are records of their forms, collections:
 * the staff add one on the new production or new performance page and the
 * lists show it at once, filtered by the address (?genre=, ?status=,
 * ?production=, ?access=); the performance lists count from today. A hire
 * enquiry, a school group request and a youth theatre place are records of
 * their own forms, requests and never a confirmed booking: a visitor
 * without an account gets one (auto-registration), so the request's page
 * and the account's list show it at once. A request's Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under a request are the page's comments, mailed to the visitor
 * and to the theatre.
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
// is kept under a _part name ($productions, $performances and $spaces are
// parts.php's sample data, $catalog is shop.php's).
$theatre_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
include $theatre_dir . '/parts.php';
include $theatre_dir . '/mail.php';
$public_part      = include $theatre_dir . '/pages_public.php';
$production_part  = include $theatre_dir . '/productions.php';
$performance_part = include $theatre_dir . '/performances.php';
$hire_part        = include $theatre_dir . '/hire.php';
$learning_part    = include $theatre_dir . '/learning.php';
$shop_part        = include $theatre_dir . '/shop.php';
$standard_part    = include $theatre_dir . '/standard.php';
$account_part     = include $theatre_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// season, the performances, venue hire, the learning, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$theatre_parts = array($public_part, $production_part, $performance_part, $hire_part, $learning_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($theatre_parts as $part) {
    $pages   = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The box office, the season, the performances, the visit, venue hire,
// the learning, the support, the tickets, the questions and the privacy
// notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, box office, stage door, opening hours')),
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
// Two fonts: a high-contrast display serif, as on a playbill, for the
// headings, the theatre's name and the dates; a plain grotesque for the
// rest.
$theatre_fonts = array(
    array('family' => 'Playfair Display', 'weights' => array('500', '700', '800'), 'enabled' => true),
    array('family' => 'Karla', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pth- (the
// field ids are th-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$theatre_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Playfair Display",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Karla",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the playbill serif, heavy and a little tighter.
    '.pth-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals, as on a ticket stub.
    '.pth-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The theatre's name in the header and the footer, in the serif face.
    '.pth-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:.01em}',
    // A key figure: the serif face, figures of one width.
    '.pth-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // A price or a box office time: figures of one width, so they line up.
    '.pth-price{font-variant-numeric:tabular-nums}',
    // A performance's date and curtain-up: the serif face, figures of one
    // width, a fixed column in the rows so the productions line up.
    '.pth-date{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    '@media (min-width:768px){.pth-date{min-width:9rem}}',
    // What an audience member says: the serif face in italics, larger.
    '.pth-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.2rem;line-height:1.55}',
    // The home page's way in: the primary colour rising from the foot of
    // the band, as stage light on a curtain.
    '.pth-hero{background-image:radial-gradient(ellipse at 50% 120%,rgba(var(--bs-primary-rgb),.16),transparent 60%)}',
    // Production and space cards rise a little under the pointer.
    '.pth-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pth-lift:hover,.pth-lift:focus-visible,.pth-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pth-lift{transition:none}.pth-lift:hover,.pth-lift:focus-visible,.pth-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pth-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Theatre'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 107,
    'icon'        => 'bi-ticket-perforated',
    'description' => lang('A theatre or performing-arts venue: the season with productions now playing, coming and past and a page each, performances from today with access performances, your visit with the box office hours, venue hire enquiries, school group requests and youth theatre places followed from the account, support and friends, tickets, friends membership and gift vouchers in the shop, News, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's crimson, readable as white button
    // text. The template's own look travels in its User Styles ('assets'),
    // so it holds under any look and palette.
    'look'        => 'luxe',
    'palette'     => 'crimson',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The season as a collection: productions now playing, coming and past with a page each, filters by genre and state, and the cast and creatives.'),
        lang('Performances from today with a page each, relaxed, captioned, audio described and signed performances marked, and the ticket state.'),
        lang('Venue hire enquiries, school group requests and youth theatre places followed from the account with the status and the theatre\'s answers.'),
        lang('Tickets, friends membership and gift vouchers with the shop on, your visit, News, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_theatre', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Theatre Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the theatre.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'     => array('name' => lang('Theatre Account Bar'), 'tree' => $account_bar_tree()),
        'notice'          => array('name' => lang('Theatre Notice'), 'tree' => $notice()),
        'header'          => array('name' => lang('Theatre Header'), 'tree' => $header()),
        'footer'          => array('name' => lang('Theatre Footer'), 'tree' => $footer()),
        'staff_bar'       => array('name' => lang('Theatre Staff Bar'), 'tree' => $staff_bar()),
        'box_office_band' => array('name' => lang('Theatre Box Office Band'), 'tree' => $box_office_band()),
        'email_header'    => array('name' => lang('Theatre E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'    => array('name' => lang('Theatre E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pth- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $theatre_css),
        'fonts' => json_encode($theatre_fonts),
    ),
);
