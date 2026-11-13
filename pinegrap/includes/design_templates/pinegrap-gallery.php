<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery": a contemporary art gallery. A home page
 * with the exhibitions now on and coming, the artists it represents, the
 * Journal and what collectors say; the artists with a page each and their
 * works, the exhibitions (now on, coming soon, past) with a page each, its
 * works and its opening night, the works with a page each and their wall
 * label; an enquiry about a work and a private viewing request the visitor
 * follows from their account, with the status and the gallery's answers;
 * art advisory, the visit, the gallery, the questions; signed editions,
 * small works and exhibition catalogues in the shop when the shop is on; a
 * contact page whose messages become conversations, the Journal (the blog,
 * filtered by topic) with its post page, a newsletter, the sign-in and
 * account pages, a staff area (the new artist, exhibition and work forms,
 * the enquiries and viewings, the Journal and staff directory forms), a
 * help page that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-gallery/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * gallery's data and the small helpers; cards.php the shared parts and the
 * cards; mail.php the e-mail builders; pages_public.php, artists.php,
 * exhibitions.php, requests.php, shop.php, standard.php and account.php
 * the pages and widgets. Each is included here in this one scope, in that
 * order, and uses the variables the files before it set (standard.php
 * reads artists.php's list pieces, account.php requests.php's list).
 * parts.php keeps its sample data in $gallery, $artists, $exhibitions,
 * $works, $advisory, $topics and $testimonials, so no part's return is
 * kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the gallery sends the newsletter to it by
 * hand. With the shop on it also gets the Signed Editions, Small Works and
 * Exhibition Catalogues groups and their products ('catalog',
 * _pg_tpl_catalog()); only the shop pages need the shop, and the shared
 * parts never link them.
 *
 * An artist, an exhibition and a work are records of the Artists,
 * Exhibitions and Works forms, collections: the staff add one on its new
 * record page and the lists show it at once; a work names its artist and
 * its exhibition by reference. An enquiry and a private viewing request
 * are records of their own forms, never a sale or a booked slot: a visitor
 * without an account gets one (auto-registration), so the request's page
 * and the account's list show it at once. A request's Status and a work's
 * Availability are office-use-only fields the staff change in the control
 * panel; a request's messages are the page's comments, mailed to the
 * visitor and to the gallery. The template keeps no edition counter, no
 * online offer and no provenance register.
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
// is kept under a _part name ($gallery, $artists, $exhibitions, $works,
// $advisory, $topics and $testimonials are parts.php's sample data).
$gallery_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
include $gallery_dir . '/parts.php';
include $gallery_dir . '/cards.php';
include $gallery_dir . '/mail.php';
$public_part     = include $gallery_dir . '/pages_public.php';
$artist_part     = include $gallery_dir . '/artists.php';
$exhibition_part = include $gallery_dir . '/exhibitions.php';
$request_part    = include $gallery_dir . '/requests.php';
$shop_part       = include $gallery_dir . '/shop.php';
$standard_part   = include $gallery_dir . '/standard.php';
$account_part    = include $gallery_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// artists and the works, the exhibitions, the enquiries and viewings, the
// shop, the standard pages, the account and staff pages, the e-mails last
// of each part.
$pages = array_merge($public_part['pages'], $artist_part['pages'], $exhibition_part['pages'],
    $request_part['pages'], $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $artist_part['widgets'], $exhibition_part['widgets'],
    $request_part['widgets'], $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The artists, the works, the exhibitions, the visit, advisory, buying, the
// questions, the shop, the Journal and the privacy notice are marked where
// they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, opening hours, private viewing, enquiries')),
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
// Two fonts: a high-contrast display serif for the headings, the prices
// and the gallery's name, a clean sans for the rest and the wall labels.
$gallery_fonts = array(
    array('family' => 'DM Serif Display', 'weights' => array('400'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pgl- (the
// field ids are gl-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$gallery_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"DM Serif Display",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:400;--pg-display-weight:400;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading);font-weight:400}',
    'body{font-family:var(--pg-font-body)}',
    // Titles, artist and work names: the display serif, in its one weight.
    '.pgl-display{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:-.005em}',
    // The small line over a title: spaced capitals.
    '.pgl-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The gallery's name in the header and the footer.
    '.pgl-brand{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.01em}',
    // The home page's opening band: a white wall with a hairline under it.
    '.pgl-hero{background:var(--bs-body-bg);border-bottom:1px solid var(--bs-border-color)}',
    // A work on the wall: a thin frame round the picture.
    '.pgl-frame{border:1px solid var(--bs-border-color);outline:6px solid var(--bs-body-bg);outline-offset:-7px}',
    // The wall label under a work: artist, title, year and medium in small,
    // even type.
    '.pgl-caption{font-size:.8125rem;line-height:1.45;letter-spacing:.01em}',
    // A collector's or an artist's words.
    '.pgl-quote{font-family:var(--pg-font-heading);font-size:1.25rem;line-height:1.4}',
    // A price on a card, a work's label or in the shop: figures of one
    // width, so the prices of a column line up.
    '.pgl-price{font-variant-numeric:tabular-nums;white-space:nowrap}',
    // Artist, exhibition, work and product cards rise a little under the
    // pointer.
    '.pgl-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pgl-lift:hover,.pgl-lift:focus-visible,.pgl-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow-sm)}',
    '@media (prefers-reduced-motion:reduce){.pgl-lift{transition:none}.pgl-lift:hover,.pgl-lift:focus-visible,.pgl-lift:focus-within{transform:none}}',
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
    '@supports (color:color-mix(in srgb,red 50%,blue)){.bg-body-tertiary{--pgl-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 90%,black);--pgl-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 90%,black)}}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary{--pgl-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,white);--pgl-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 85%,white)}}',
    '.bg-body-tertiary :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--pgl-band-primary,var(--pg-primary-fg,var(--bs-primary)))!important}',
    '.bg-body-tertiary :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--pgl-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))!important}',
    '.bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--pgl-band-primary,var(--pg-primary-fg,var(--bs-primary)))}',
    '.bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--pgl-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))}',
    '.bg-body-tertiary .nav{--bs-nav-link-color:var(--pgl-band-primary,var(--bs-link-color))}',
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
    'name'        => lang('Art Gallery'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 133,
    'icon'        => 'bi-easel2',
    'description' => lang('A contemporary art gallery: the artists it represents with a page each and their works, exhibitions now on, coming soon and past with their opening nights, the works with their wall labels, enquiries about a work and private viewing requests the visitor follows from their account, art advisory, signed editions, small works and catalogues with the shop on, the Journal, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'minimal',
    'palette'     => 'graphite',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The artists the gallery represents with a page each, and the works with their wall label and availability, added by the staff from a form.'),
        lang('Exhibitions now on, coming soon and past, each with its works, its opening night and a private viewing request.'),
        lang('Enquiries about a work and private viewing requests with their own page, status and the gallery\'s answers, and art advisory.'),
        lang('Signed editions, small works and catalogues with the shop on, the Journal by topic, a newsletter, an inbox, a staff area and a help page.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_gallery', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Gallery Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the art gallery.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Gallery Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Gallery Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Gallery Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Gallery Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Gallery Staff Bar'), 'tree' => $staff_bar()),
        'viewing_band' => array('name' => lang('Gallery Viewing Band'), 'tree' => $viewing_band()),
        'email_header' => array('name' => lang('Gallery E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Gallery E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pgl- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $gallery_css),
        'fonts' => json_encode($gallery_fonts),
    ),
);
