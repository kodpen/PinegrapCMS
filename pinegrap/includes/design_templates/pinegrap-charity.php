<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity": a nonprofit's site. A home page with the
 * mission, the featured campaigns, the impact figures, the events and the
 * News; about, our work by cause, impact, transparency (the spending split
 * and the reports) and the questions; the campaigns with a cause filter
 * and a page per campaign with its progress and updates; the donate page
 * (gift amounts, a bank transfer box and a pledge form, followed on its own
 * page), and with the shop on, gifts as donation products in the cart and
 * the checkout; volunteering, each offer followed from the account; the
 * events with a short sign-up form; a contact page whose messages become
 * conversations, the News (the blog) and its post page, a newsletter, the
 * sign-in and account pages, a staff area (the volunteers, the pledges, the
 * campaign, event, report, News and staff directory forms), a search over
 * the whole site and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-charity/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * charity's figures (causes, campaigns, gift amounts, impact, spending) and
 * the parts every page repeats; mail.php the e-mail builders; forms.php the
 * form-record builders; pages_public.php, donate.php, campaigns.php,
 * volunteer.php, events.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set.
 *
 * Opening the template gives the site the Donate group of the catalog with
 * a gift to where it is needed most and one per campaign ('catalog', from
 * donate.php), and the newsletter's contact group ('contact_groups'), both
 * made once and found again by their names the next time
 * (_pg_tpl_catalog(), _pg_tpl_contact_groups()). A gift is a product with
 * the donation selection type: the visitor types the amount in the cart.
 * Only the shop pages and the gift receipt need the shop ('requires' on
 * each page, and on the address book); the donate page stands without it.
 *
 * A campaign is a record of the Campaigns collection; its progress is the
 * percent the staff keep on the record. A pledge and a volunteer's offer
 * are records of their own forms: a visitor without an account gets one,
 * the Status is an office-use-only field the staff change, and the
 * messages under it are the record page's comments. The reports are a
 * collection whose files go to the public reports folder.
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
// is kept under a _part name ($causes and $campaigns are parts.php's
// figures).
$charity_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
include $charity_dir . '/parts.php';
include $charity_dir . '/mail.php';
include $charity_dir . '/forms.php';
$public_part    = include $charity_dir . '/pages_public.php';
$donate_part    = include $charity_dir . '/donate.php';
$campaign_part  = include $charity_dir . '/campaigns.php';
$volunteer_part = include $charity_dir . '/volunteer.php';
$event_part     = include $charity_dir . '/events.php';
$standard_part  = include $charity_dir . '/standard.php';
$account_part   = include $charity_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// giving, the campaigns, the volunteers, the events and reports, the
// standard pages, the account and staff pages, the e-mails last of each
// part. The search sections are marked where the parts build them.
$charity_parts = array($public_part, $donate_part, $campaign_part, $volunteer_part, $event_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($charity_parts as $part) {
    $pages   = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a rounded sans for the headings, the charity's name and the
// figures, a plain sans for the rest.
$charity_fonts = array(
    array('family' => 'Nunito', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Public Sans', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pch- (field
// ids use ch-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
// A tint mixed with color-mix() comes after a plain fallback.
$charity_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Nunito",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Public Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.pch-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The charity's name in the header and the footer, in the heading font.
    '.pch-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.01em}',
    // Every amount and figure: figures of one width, so they line up.
    '.pch-figure{font-variant-numeric:tabular-nums}',
    // A campaign's progress: a rounded bar in the palette's colour on a
    // quiet track, the same in every browser.
    '.pch-progress{appearance:none;-webkit-appearance:none;display:block;height:.6rem;border:0;border-radius:var(--bs-border-radius-pill);background-color:var(--bs-secondary-bg);color:var(--bs-primary);overflow:hidden}',
    '.pch-progress::-webkit-progress-bar{background-color:var(--bs-secondary-bg);border-radius:var(--bs-border-radius-pill)}',
    '.pch-progress::-webkit-progress-value{background-color:var(--bs-primary);border-radius:var(--bs-border-radius-pill)}',
    '.pch-progress::-moz-progress-bar{background-color:var(--bs-primary);border-radius:var(--bs-border-radius-pill)}',
    // The home page's and the donate page's top: a soft light of the
    // palette's colour from a corner.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pch-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // The donate band: the same light from the other corner.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pch-band{background-image:radial-gradient(ellipse 60% 90% at 0% 100%,color-mix(in srgb,var(--bs-primary) 12%,transparent) 0%,transparent 70%)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.pch-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pch-lift:hover,.pch-lift:focus-visible,.pch-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pch-lift{transition:none}.pch-lift:hover,.pch-lift:focus-visible,.pch-lift:focus-within{transform:none}}',
    // Grey sections (the account bar, page headers, grey bands) and cards
    // tinted with the primary (the account card): the palette's primary and
    // second colours are made readable on the page background only, and
    // across all palettes they fell to 3.9:1 there in the dark theme and to
    // 4.4:1 in the light one. Links, eyebrows, section menus and outline
    // buttons there take a tint of the primary: on grey, the primary a
    // little closer to the text colour (in the dark theme its emphasis tint,
    // as some palettes' primary is too dark there); on the primary's own
    // tint, and where the browser does not mix colours, the emphasis tint.
    // The second colour gives way to the body's second text colour.
    ':is(.bg-body-tertiary,.bg-primary-subtle){--tpl-tinted-fg:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '.bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,var(--bs-emphasis-color))}'
        . '[data-bs-theme=dark] .bg-body-tertiary{--tpl-tinted-fg:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-primary,.link-primary){color:var(--tpl-tinted-fg)!important}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-primary{--bs-btn-color:var(--tpl-tinted-fg)}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .nav{--bs-nav-link-color:var(--tpl-tinted-fg);--bs-nav-link-hover-color:var(--bs-emphasis-color)}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) :is(.text-secondary,.link-secondary){color:var(--bs-secondary-color)!important}',
    ':is(.bg-body-tertiary,.bg-primary-subtle) .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
));

return array(
    'name'        => lang('Charity'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 81,
    'icon'        => 'bi-heart',
    'description' => lang('A nonprofit\'s site: causes and campaigns with their progress, a donate page with gift amounts, bank transfer and pledges, gifts in the cart with the shop on, volunteering and pledges followed from the account, events, reports on spending, the News, a newsletter, an inbox, a staff area and a search over the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's green. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'modern-soft',
    'palette'     => 'emerald',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Causes and campaigns as a collection: a goal, what is raised and a progress bar the staff keep up to date, a cause filter and updates under each campaign.'),
        lang('A donate page that stands without the shop: gift amounts, a bank transfer box and a pledge followed from the account; with the shop on, gifts of any amount in the cart.'),
        lang('Volunteering with its own page, status and messages, events with a sign-up form, and a transparency page with the spending split and the reports.'),
        lang('The News, a newsletter, an inbox, a staff area and a search over the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The reports' files go to a public folder of their own.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_charity', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'reports'      => array('name' => 'reports', 'parent' => 'root', 'access' => 'public'),
    ),

    'catalog'        => $donate_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Charity Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the charity.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Charity Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Charity Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Charity Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Charity Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Charity Staff Bar'), 'tree' => $staff_bar()),
        'donate_band'  => array('name' => lang('Charity Donate Band'), 'tree' => $donate_band()),
        'email_header' => array('name' => lang('Charity E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Charity E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pch- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $charity_css),
        'fonts' => json_encode($charity_fonts),
    ),
);
