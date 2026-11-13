<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm": a building contractor. A home page
 * with the services, the featured projects, the way the firm works and
 * what clients say; a page per service, the sectors, the process, safety
 * and quality, the team, the about page and the questions; the projects
 * portfolio with filters by sector and service and a page per project with
 * its photos, facts, scope and the client's words; a quote request the
 * client follows from their account, with the status, the site visit and
 * the firm's answers; careers with job openings and job applications; a
 * contact page whose messages become conversations, News (the blog) with
 * its post page, a newsletter, the sign-in and account pages, a staff area
 * (the new project and job opening forms, the quote requests, the job
 * applications, the blog and staff directory forms), a help center that
 * searches the whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-construction/
 * (not read as templates of their own): parts.php holds the node builders,
 * the firm's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, projects.php, quotes.php, careers.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the sample data in $services, $projects,
 * $jobs and $team, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the firm sends the newsletter to it by hand.
 * There is no catalog: a contractor sells nothing online, and only the
 * address book needs the shop.
 *
 * A project and a job opening are records of their forms, collections: the
 * staff add one on the new project or new job opening page and the lists
 * show it at once, filtered by the address (?sector=, ?service=). A quote
 * request and a job application are records of their own forms, requests
 * and never contracts: a visitor without an account gets one
 * (auto-registration), so the request's page and the account's list show
 * it at once. A request's Status is an office-use-only field the staff
 * change in the control panel; the messages under it are the page's
 * comments, mailed to the client and to the firm.
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
$construction_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction';
include $construction_dir . '/parts.php';
include $construction_dir . '/mail.php';
$public_part   = include $construction_dir . '/pages_public.php';
$project_part  = include $construction_dir . '/projects.php';
$quote_part    = include $construction_dir . '/quotes.php';
$career_part   = include $construction_dir . '/careers.php';
$standard_part = include $construction_dir . '/standard.php';
$account_part  = include $construction_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// projects, the quote requests, the careers, the standard pages, the
// account and staff pages, the e-mails last of each part.
$construction_parts = array($public_part, $project_part, $quote_part, $career_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($construction_parts as $part) {
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
// The services, the process, safety, the team, the questions and the
// privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office, opening hours')),
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
// Two fonts: a strong grotesque for the headings, the firm's name and the
// figures, a plain sans for the rest.
$construction_fonts = array(
    array('family' => 'Archivo', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pcn- (the
// field ids are cn-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$construction_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Archivo",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.015em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the heading font, heavy and a little tighter.
    '.pcn-display{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.015em}',
    // The small line over a title: spaced capitals.
    '.pcn-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The firm's name in the header and the footer, in the heading font.
    '.pcn-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.01em}',
    // A key figure: the heading font, figures of one width.
    '.pcn-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;letter-spacing:-.02em}',
    // What a client says: a little larger, in italics.
    '.pcn-quote{font-style:italic;font-size:1.125rem;line-height:1.6}',
    // The home page's way in: a bar of the primary colour along its top.
    '.pcn-hero{border-top:.375rem solid var(--bs-primary)}',
    // Project, service and job cards rise a little under the pointer.
    '.pcn-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pcn-lift:hover,.pcn-lift:focus-visible,.pcn-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pcn-lift{transition:none}.pcn-lift:hover,.pcn-lift:focus-visible,.pcn-lift:focus-within{transform:none}}',
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
    'name'        => lang('Construction Firm'),
    'version'     => '2.0.3',
    'framework'   => 'bootstrap5',
    'order'       => 89,
    'icon'        => 'bi-bricks',
    'description' => lang('A building contractor: services with a page each, a projects portfolio with filters by sector and service and a page per project, quote requests the client follows from their account with the site visit and the firm\'s answers, careers with job openings and job applications, the process, safety and the team, News, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are a darker shade of this palette's amber, readable
    // as white button text. The template's own look travels in its User
    // Styles ('assets'), so it holds under any look and palette.
    'look'        => 'bold',
    'palette'     => 'amber',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Services with a page each, the sectors, the process, safety and quality, the team and the questions.'),
        lang('A projects portfolio as a collection: a page per project with its photos, facts and scope, filters by sector and service, and sample projects the staff replace from a form.'),
        lang('Quote requests by service, with plans and photos, their own page, status and the firm\'s answers, followed from the client\'s account.'),
        lang('Careers with job openings and job applications, News, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_construction', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Construction Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the construction firm.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Construction Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Construction Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Construction Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Construction Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Construction Staff Bar'), 'tree' => $staff_bar()),
        'quote_band'   => array('name' => lang('Construction Quote Band'), 'tree' => $quote_band()),
        'email_header' => array('name' => lang('Construction E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Construction E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Construction blocks' pg-cn- rules and
    // its pcn- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('construction-hero', 'construction-progress', 'construction-notice'), $construction_css),
        'fonts' => json_encode($construction_fonts),
    ),
);
