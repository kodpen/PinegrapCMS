<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio": a film and video production studio. A
 * home page with the showreel, the services, the latest projects, how a
 * production runs and what clients say; the services with a page each;
 * the work as a Projects collection by industry and service with a page
 * per project (the film's link, stills, facts, the credits and the
 * client's words); how a production runs, the studio's stages and suites
 * with a hire request, the crew, about and the questions; project
 * enquiries and studio hire requests followed from the account with their
 * status and the studio's answers; crew calls with applications; a
 * contact page whose messages become conversations, the Journal (the
 * blog) with its post page, a newsletter, the sign-in and account pages,
 * a staff area (the new project, crew call, Journal and staff directory
 * forms, the enquiries, hire requests and applications), a help that
 * searches the whole site and the e-mails. No shop: a studio quotes per
 * production. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-filmstudio/
 * (not read as templates of their own): parts.php holds the node builders,
 * the studio's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, projects.php, enquiries.php, careers.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps its sample data in $services, $projects,
 * $spaces, $crew, $jobs and $testimonials, so no part's return is kept
 * under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the studio sends the newsletter to it by
 * hand. Only the address book needs the shop; the shared parts never link
 * it.
 *
 * A project and a crew call are records of the Projects and Jobs forms,
 * collections: the staff add one on the new project or new crew call page
 * and the lists show it at once. A project enquiry, a studio hire request
 * and an application are records of their own forms, requests and never
 * contracts or confirmed bookings: a sender without an account gets one
 * (auto-registration), so the request's page and the account's list show
 * it at once. A request's Status is an office-use-only field the staff
 * change in the control panel; the messages under it are the page's
 * comments, mailed to the sender and to the studio. The template keeps no
 * studio calendar and takes no payment.
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
 * The home, the services and the project page place the palette's Film
 * Studio blocks (Showreel Hero, Title Card and Credits, Cuts for Every
 * Screen) and the general Timeline: Horizontal with the studio's own data;
 * their rules ship in User Styles from the block source, and their motion
 * is node interactions (props._fx) only: the template carries no script.
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
// is kept under a _part name ($services, $projects, $spaces, $crew, $jobs
// and $testimonials are parts.php's sample data).
$filmstudio_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio';
include $filmstudio_dir . '/parts.php';
include $filmstudio_dir . '/mail.php';
$public_part   = include $filmstudio_dir . '/pages_public.php';
$project_part  = include $filmstudio_dir . '/projects.php';
$enquiry_part  = include $filmstudio_dir . '/enquiries.php';
$career_part   = include $filmstudio_dir . '/careers.php';
$standard_part = include $filmstudio_dir . '/standard.php';
$account_part  = include $filmstudio_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// projects, the enquiries and studio hire requests, the crew calls, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part['pages'], $project_part['pages'], $enquiry_part['pages'], $career_part['pages'],
    $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $project_part['widgets'], $enquiry_part['widgets'], $career_part['widgets'],
    $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The showreel, the services, the process, the spaces, the crew, the
// questions, the crew calls and the privacy notice are marked where they
// are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details')   => array('contact-details', lang('address, phone, e-mail, office hours, studio, visit')),
        lang('Studio Hire Note')  => array('studio-hire-note', lang('hire, rent, stage, green screen, edit suite')),
        lang('Inbox Note')        => array('inbox-note', lang('answer, reply, conversation, inbox')),
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
// Two fonts: a tall condensed sans for the headings, the project names
// and the studio's name, like a title card; an even sans for the rest.
$filmstudio_fonts = array(
    array('family' => 'Oswald', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pfm- (the
// field ids are fm-); no block rule's class is named here: the blocks'
// rules come from the block source. Selectors use descendants and
// pseudo-classes only, so they hold on the editor's canvas too. Colours are
// tokens only, so they follow any palette in both themes.
$filmstudio_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Oswald","Arial Narrow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:.01em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Page and section titles and service names: the condensed heading face
    // in capitals, a title card's line. A name typed into a record (a
    // project, a person, a crew call) keeps the case it was typed in: under
    // a Turkish page capitals would print a dotted capital I in it.
    '.pfm-display{font-family:var(--pg-font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.02em}',
    // The small line over a title: widely spaced capitals.
    '.pfm-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The studio's name in the header and the footer, in the case it was
    // typed in Site Settings: capitals would print a dotted capital I in an
    // English name under a Turkish page.
    '.pfm-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // The home's opening band and the enquiry band: a wash of the
    // palette's subtle tones from the top corner, like a lit set.
    '.pfm-hero{background:radial-gradient(ellipse at top left,var(--bs-primary-bg-subtle),var(--bs-body-bg) 70%)}',
    // A figure of the studio: the heading face, figures of one width.
    '.pfm-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;line-height:1}',
    // What a client says: a little larger, with room between the lines.
    '.pfm-quote{font-size:1.125rem;line-height:1.6}',
    // The play mark over a project's still: a soft shadow keeps it seen on
    // a light still; it grows a little under the pointer.
    '.pfm-play{pointer-events:none;filter:drop-shadow(0 2px 6px rgba(0,0,0,.45));transition:transform .2s ease}',
    '.pfm-lift:hover .pfm-play,.pfm-lift:focus-within .pfm-play{transform:translate(-50%,-50%) scale(1.12)}',
    // Project, service and crew call cards rise a little under the pointer.
    '.pfm-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pfm-lift:hover,.pfm-lift:focus-visible,.pfm-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pfm-lift,.pfm-play{transition:none}.pfm-lift:hover,.pfm-lift:focus-visible,.pfm-lift:focus-within{transform:none}'
        . '.pfm-lift:hover .pfm-play,.pfm-lift:focus-within .pfm-play{transform:translate(-50%,-50%)}}',
));

return array(
    'name'        => lang('Film Studio'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 127,
    'icon'        => 'bi-film',
    'description' => lang('A film and video production studio: the showreel, the services with a page each, the projects by industry and service with the film, its stills and credits, the studio\'s stages for hire, the crew, project enquiries and studio hire requests the client follows from their account, crew calls with applications, the Journal, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'bold',
    'palette'     => 'midnight',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The showreel on the home page, the services with a page each and sample projects the staff replace from a form, filtered by industry and service.'),
        lang('A page per project with the film\'s link, its stills, the facts, the credits and the client\'s words.'),
        lang('Project enquiries and studio hire requests, each with its own page, status and the studio\'s answers, followed from the account.'),
        lang('The studio\'s stages and suites, the crew, crew calls with applications, the Journal, a newsletter, an inbox and a staff area.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_filmstudio', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Film Studio Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the film studio.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Film Studio Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Film Studio Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Film Studio Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Film Studio Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Film Studio Staff Bar'), 'tree' => $staff_bar()),
        'enquiry_band' => array('name' => lang('Film Studio Enquiry Band'), 'tree' => $enquiry_band()),
        'email_header' => array('name' => lang('Film Studio E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Film Studio E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the blocks its pages place and
    // its pfm- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('filmstudio-reel', 'filmstudio-credits', 'filmstudio-formats', 'timeline-horizontal'), $filmstudio_css),
        'fonts' => json_encode($filmstudio_fonts),
    ),
);
