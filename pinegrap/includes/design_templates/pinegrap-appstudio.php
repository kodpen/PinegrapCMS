<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer": an independent developer who
 * makes their own iOS and Android apps and builds apps for clients. A home
 * page with the apps, the latest release notes and what users say; the app
 * list with filters by category and platform, one page per app with its
 * screenshots, features, privacy summary and store buttons; the release
 * notes filtered by app, a beta sign-up; a support request the user
 * follows from their account, with the status and the developer's
 * answers; the services with three packages, the way of working and a
 * project request the client follows the same way; the about page, the
 * questions and a press kit; a contact page whose messages become
 * conversations, Dev Notes (the blog) with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new app and release
 * forms, the support and project requests, the beta testers, the blog and
 * staff directory forms), a help center that searches the whole site and
 * the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-appstudio/
 * (not read as templates of their own): parts.php holds the node builders,
 * the developer's data and the parts every page repeats; mail.php the
 * e-mail builders; pages_public.php, apps.php, releases.php, support.php,
 * projects.php, standard.php and account.php the pages and widgets. Each
 * is included here in this one scope, in that order, and uses the
 * variables the files before it set. parts.php keeps the sample apps in
 * $apps and their release notes in $releases, so no part's return is kept
 * under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the developer sends the newsletter to it by
 * hand. There is no catalog: the apps sell in the stores and a client
 * project is quoted.
 *
 * An app is a record of the Apps form and a release note a record of the
 * Releases form, both collections: the staff add one on the new app or
 * new release page and the lists show it at once, filtered by the address
 * (?category=, ?platform=, ?app=). A support request, a project request
 * and a beta sign-up are records of their own forms; a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the user and to the developer.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
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
// is kept under a _part name ($apps and $releases are parts.php's sample
// data). The public part returns its pages only.
$appstudio_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
include $appstudio_dir . '/parts.php';
include $appstudio_dir . '/mail.php';
$public_part   = include $appstudio_dir . '/pages_public.php';
$app_part      = include $appstudio_dir . '/apps.php';
$release_part  = include $appstudio_dir . '/releases.php';
$support_part  = include $appstudio_dir . '/support.php';
$project_part  = include $appstudio_dir . '/projects.php';
$standard_part = include $appstudio_dir . '/standard.php';
$account_part  = include $appstudio_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// apps, the release notes and the beta, support, the client projects, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part, $app_part['pages'], $release_part['pages'], $support_part['pages'],
    $project_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($app_part['widgets'], $release_part['widgets'], $support_part['widgets'],
    $project_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the packages, the way of working, the about page, the
// questions, the press kit, the release notes, the beta, support, the
// project request and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office hours, location')),
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
// Three fonts: a grotesque for the headings, a clear sans for the rest and
// a monospace for the version numbers and the tools.
$appstudio_fonts = array(
    array('family' => 'Space Grotesk', 'weights' => array('500', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
    array('family' => 'JetBrains Mono', 'weights' => array('400', '500'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pma- (Mobile
// App; field ids are ma-); no block rule's class is
// named here. Selectors use descendants and pseudo-classes only, so they
// hold on the editor's canvas too. Colours are tokens only, so they follow
// any palette in both themes.
$appstudio_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Space Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-mono:"JetBrains Mono",SFMono-Regular,Menlo,Consolas,monospace;'
        . '--pg-heading-weight:700;--pg-display-weight:700;--pg-heading-tracking:-.02em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Large titles, prices and figures: the heading font, drawn tighter.
    '.pma-display{font-family:var(--pg-font-heading);letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.pma-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The developer's name in the header and the footer, in the heading font.
    '.pma-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // Version numbers and tool names: monospace figures of one width.
    '.pma-mono{font-family:var(--pg-font-mono);font-variant-numeric:tabular-nums;letter-spacing:0}',
    // An app's icon: a square frame, larger in the app page's heading.
    '.pma-icon{width:4rem;height:4rem;background-color:var(--bs-tertiary-bg)}',
    'header .pma-icon{width:6rem;height:6rem}',
    // App cards rise a little under the pointer.
    '.pma-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pma-lift:hover,.pma-lift:focus-visible,.pma-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pma-lift{transition:none}.pma-lift:hover,.pma-lift:focus-visible,.pma-lift:focus-within{transform:none}}',
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
    'name'        => lang('Mobile App Developer'),
    'version'     => '1.1.1',
    'framework'   => 'bootstrap5',
    'order'       => 73,
    'icon'        => 'bi-phone',
    'description' => lang('An independent mobile app developer: their own apps with a page each and filters by category and platform, release notes, a beta sign-up, support requests and client projects the user follows from their account, services with three packages, a press kit, a newsletter, Dev Notes, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's violet. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'glass',
    'palette'     => 'lavender',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The developer\'s apps with a page each, filters by category and platform, store buttons, and five sample apps the staff replace from a form.'),
        lang('Release notes per app, a beta sign-up, and support requests with their own page, status and the developer\'s answers.'),
        lang('Services with three packages, the way of working and project requests the client follows from their account.'),
        lang('A press kit, a newsletter, Dev Notes, an inbox, a staff area with the requests and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_appstudio', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('App Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the app developer.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('App Developer Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('App Developer Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('App Developer Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('App Developer Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('App Developer Staff Bar'), 'tree' => $staff_bar()),
        'project_band' => array('name' => lang('App Developer Project Band'), 'tree' => $project_band()),
        'email_header' => array('name' => lang('App Developer E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('App Developer E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pma- rules in one User Styles entry, and
    // its three fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('appstudio-hero', 'appstudio-switch', 'appstudio-release', 'appstudio-platforms'), $appstudio_css),
        'fonts' => json_encode($appstudio_fonts),
    ),
);
