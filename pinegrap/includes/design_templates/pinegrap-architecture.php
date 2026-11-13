<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio": an architecture and
 * interior design studio. A home page with the featured projects, the
 * services, the studio's figures and what clients say; a page per service,
 * the studio, the team, how we work, awards and press and the questions;
 * the projects portfolio with filters by type and location and a page per
 * project with its gallery, facts, brief and credits; a project enquiry and
 * a consultation request the client follows from their account, with the
 * status and the studio's answers; careers with job openings and
 * applications with a portfolio; a contact page whose messages become
 * conversations, the Journal (the blog) with its article page, a
 * newsletter, the sign-in and account pages, a staff area (the new project
 * and job opening forms, the enquiries, the consultations, the
 * applications, the Journal and staff directory forms), a help center that
 * searches the whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-architecture/
 * (not read as templates of their own): parts.php holds the node builders,
 * the studio's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, projects.php, enquiries.php, careers.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the sample data in $services, $projects,
 * $jobs and $team, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the studio sends the newsletter to it by
 * hand. There is no catalog: a studio sells its time, quoted per project,
 * and only the address book needs the shop.
 *
 * A project and a job opening are records of their forms, collections: the
 * staff add one on the new project or new job opening page and the lists
 * show it at once, filtered by the address (?type=, ?location=). A project
 * enquiry, a consultation request and a job application are records of
 * their own forms, requests and never contracts: a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the client and to the studio.
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
// is kept under a _part name ($services, $projects, $jobs and $team are
// parts.php's sample data).
$architecture_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture';
include $architecture_dir . '/parts.php';
include $architecture_dir . '/mail.php';
$public_part   = include $architecture_dir . '/pages_public.php';
$project_part  = include $architecture_dir . '/projects.php';
$enquiry_part  = include $architecture_dir . '/enquiries.php';
$career_part   = include $architecture_dir . '/careers.php';
$standard_part = include $architecture_dir . '/standard.php';
$account_part  = include $architecture_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// projects, the enquiries and consultations, the careers, the standard
// pages, the account and staff pages, the e-mails last of each part.
$architecture_parts = array($public_part, $project_part, $enquiry_part, $career_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($architecture_parts as $part) {
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
// The services, the process, the team, the awards, the questions and the
// privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, studio, opening hours')),
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
// Two fonts: a geometric sans for the headings, the studio's name and the
// figures, a quiet grotesque for the rest.
$architecture_fonts = array(
    array('family' => 'Jost', 'weights' => array('400', '500', '600'), 'enabled' => true),
    array('family' => 'Karla', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pai- (the
// field ids are ai-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$architecture_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Jost",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Karla",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:500;--pg-display-weight:500;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the geometric face, light and a little tighter.
    '.pai-display{font-family:var(--pg-font-heading);font-weight:500;letter-spacing:-.01em}',
    // The small line over a title: widely spaced capitals, as on a drawing.
    '.pai-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The studio's name in the header and the footer: spaced capitals.
    '.pai-brand{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:.12em;text-transform:uppercase}',
    // A key figure: the heading face, figures of one width.
    '.pai-stat{font-family:var(--pg-font-heading);font-weight:500;font-variant-numeric:tabular-nums;letter-spacing:-.02em}',
    // The numbers of an index (the steps, the awards): small capitals,
    // figures of one width.
    '.pai-index{font-family:var(--pg-font-heading);font-variant-caps:all-small-caps;font-variant-numeric:tabular-nums;letter-spacing:.08em}',
    // What a client says: the heading face, a little larger.
    '.pai-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.6}',
    // The home page's way in: a thin rule under it, as on a drawing sheet.
    '.pai-hero{border-bottom:1px solid var(--bs-border-color)}',
    // Project, service and job cards rise a little under the pointer.
    '.pai-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pai-lift:hover,.pai-lift:focus-visible,.pai-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pai-lift{transition:none}.pai-lift:hover,.pai-lift:focus-visible,.pai-lift:focus-within{transform:none}}',
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
    'name'        => lang('Architecture & Interiors Studio'),
    'version'     => '2.0.3',
    'framework'   => 'bootstrap5',
    'order'       => 97,
    'icon'        => 'bi-rulers',
    'description' => lang('An architecture and interior design studio: services with a page each, a projects portfolio with filters by type and location and a page per project with its gallery and credits, project enquiries and consultation requests the client follows from their account with the studio\'s answers, careers with job openings and applications, the studio, the team, awards and press, the Journal, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's graphite, readable as white button
    // text. The template's own look travels in its User Styles ('assets'),
    // so it holds under any look and palette.
    'look'        => 'minimal',
    'palette'     => 'graphite',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Services with a page each, the studio, the team, how we work, awards and press, and the questions.'),
        lang('A projects portfolio as a collection: a page per project with its gallery, facts, brief and credits, filters by type and location, and sample projects the staff replace from a form.'),
        lang('Project enquiries with drawings and photos of the site, and consultation requests, each with its own page, status and the studio\'s answers, followed from the client\'s account.'),
        lang('Careers with job openings and applications with a portfolio, the Journal, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_architecture', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Architecture Studio Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the architecture studio.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Architecture Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Architecture Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Architecture Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Architecture Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Architecture Staff Bar'), 'tree' => $staff_bar()),
        'enquiry_band' => array('name' => lang('Architecture Enquiry Band'), 'tree' => $enquiry_band()),
        'email_header' => array('name' => lang('Architecture E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Architecture E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Architecture blocks' pg-arch- rules and
    // its pai- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('architecture-hero', 'architecture-sheet', 'architecture-materials'), $architecture_css),
        'fonts' => json_encode($architecture_fonts),
    ),
);
