<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Campsite": a family-run campsite and holiday park by a
 * lake and a forest. A home page with the kinds of stay, the featured
 * pitches and cabins, the facilities, the activities, the figures and what
 * guests say; the places to stay with a page per kind (tent pitches,
 * touring pitches, glamping tents, cabins), the Pitches and Cabins
 * collection with filters by kind and a page per unit; the facilities, the
 * activities and the area, seasons and prices as words, getting here, the
 * park rules, the about page and the questions; a stay request and a group
 * enquiry the guest follows from their account, with the status and the
 * park's answers; a contact page whose messages become conversations, the
 * News (the blog) with its post page, a newsletter, the sign-in and account
 * pages, a staff area (the new unit form, the stay requests, the group
 * enquiries, the blog and staff directory forms), a help that searches the
 * whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-campsite/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * park's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, units.php, stays.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 * parts.php keeps the sample data in $kinds, $units, $seasons and the
 * like, and its page shell in $page, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the park sends the newsletter to it by hand.
 * There is no catalog: the park takes no payment online, and only the
 * address book needs the shop.
 *
 * A pitch or a cabin is a record of the Pitches and Cabins form, a
 * collection: the staff add one on the new unit page and the lists show it
 * at once, filtered by the address (?kind=). A stay request and a group
 * enquiry are records of their own forms, requests and never reservations:
 * a guest without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * guest and to the park. The template keeps no availability and no prices
 * per date: the prices per season are words.
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
// is kept under a _part name ($kinds, $units and $seasons are parts.php's
// sample data, $page its page shell).
$campsite_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite';
include $campsite_dir . '/parts.php';
include $campsite_dir . '/mail.php';
$public_part   = include $campsite_dir . '/pages_public.php';
$unit_part     = include $campsite_dir . '/units.php';
$stay_part     = include $campsite_dir . '/stays.php';
$standard_part = include $campsite_dir . '/standard.php';
$account_part  = include $campsite_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// pitches and cabins, the stay requests and group enquiries, the standard
// pages, the account and staff pages, the e-mails last of each part.
$campsite_parts = array($public_part, $unit_part, $stay_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($campsite_parts as $part) {
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
// The kinds of stay, the prices, the facilities, the activities, getting
// here, the rules, the questions, the groups and the privacy notice are
// marked where they are built (cp- ids).
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, reception, park office, opening hours')),
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
// Two fonts: a sturdy slab serif for the headings, the park's name and the
// figures, a friendly sans for the rest.
$campsite_fonts = array(
    array('family' => 'Bitter', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Source Sans 3', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pcp- (the
// field ids are cp-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$campsite_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Bitter",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Source Sans 3",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:0;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the slab serif.
    '.pcp-display{font-family:var(--pg-font-heading);font-weight:600}',
    // The small line over a title: spaced capitals.
    '.pcp-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The park's name in the header and the footer, in the slab serif.
    '.pcp-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // A price: figures of one width, so the season table's prices line up.
    '.pcp-price{font-variant-numeric:tabular-nums}',
    // A key figure: the slab serif, figures of one width.
    '.pcp-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // What a guest says: the slab serif, a little larger.
    '.pcp-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.6}',
    // The home page's way in: a soft wash of the palette's own colour.
    '.pcp-hero{background:linear-gradient(180deg,var(--bs-primary-bg-subtle),var(--bs-body-bg))}',
    // Stay-kind and unit cards rise a little under the pointer.
    '.pcp-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pcp-lift:hover,.pcp-lift:focus-visible,.pcp-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pcp-lift{transition:none}.pcp-lift:hover,.pcp-lift:focus-visible,.pcp-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pcp-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Campsite'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 99,
    'icon'        => 'bi-tree',
    'description' => lang('A campsite and holiday park: tent and touring pitches, glamping tents and cabins with a page per kind and per unit, the facilities, the activities and the area, seasons and prices, getting here and the park rules, stay requests and group enquiries the guest follows from their account with the park\'s answers, News, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's forest green, readable as white
    // button text. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'elegant',
    'palette'     => 'forest',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Tent and touring pitches, glamping tents and cabins with a page per kind, and the seasons with their prices as words.'),
        lang('Pitches and cabins as a collection: a page per unit with its photos and facts, a filter by kind, and sample units the staff replace from a form.'),
        lang('Stay requests by unit, kind and season, and group enquiries, each with its own page, status and the park\'s answers, followed from the guest\'s account.'),
        lang('The facilities, the activities and the area, getting here, the park rules, News, a newsletter, an inbox, a staff area and a help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_campsite', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Campsite Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the campsite.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Campsite Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Campsite Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Campsite Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Campsite Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Campsite Staff Bar'), 'tree' => $staff_bar()),
        'stay_band'    => array('name' => lang('Campsite Stay Band'), 'tree' => $stay_band()),
        'email_header' => array('name' => lang('Campsite E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Campsite E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Campsite blocks' pg-campsite- rules and
    // its pcp- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('campsite-hero', 'campsite-comfort', 'campsite-pitch'), $campsite_css),
        'fonts' => json_encode($campsite_fonts),
    ),
);
