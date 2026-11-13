<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency": an agency that finds people work
 * and finds employers their staff. A home page with the sectors, the
 * featured jobs, how it works for candidates and for employers, its
 * figures, what people say and the newest guides; the sectors with a page
 * for each, the cities, the pages for candidates and for employers, about,
 * the consultants and the questions; the jobs by sector, city and contract
 * type with a page for each job; applications (with or without a job, so
 * also a registered CV) and employers' staff requests, each followed from
 * the account with its status and the consultant's messages; a contact
 * page whose messages become conversations, Career Advice (the blog) with
 * its post page, job alerts (the newsletter), the sign-in and account
 * pages, a staff area (the new job form, the two request lists, the Career
 * Advice and staff directory forms), a help center that searches the whole
 * site and the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-recruitment/
 * (not read as templates of their own): parts.php holds the node builders,
 * the agency's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, jobs.php, applications.php, employers.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set (employers.php builds with the record pieces of
 * applications.php). parts.php keeps the agency's data in $sectors,
 * $cities, $jobs, $consultants and others, so every part's return is kept
 * under a _part name.
 *
 * Opening the template gives the site the job alerts' contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). The agency sells nothing online: no page but
 * the address book needs the shop.
 *
 * A job is a record of the Jobs collection form: the staff add one on the
 * new job page and the lists show it at once, filtered by the address
 * (?sector=, ?city=, ?type=). An application (?job=) and a staff request
 * (?sector=) are records of their own forms: a visitor without an account
 * gets one (auto-registration), so the record's page and the account's
 * list show it at once. A record's Status is an office-use-only field the
 * staff change in the control panel; the messages under it are the page's
 * comments, mailed to the sender and to the agency. The CV and the job
 * description stay in the template's private folder.
 *
 * The parts of the pages the site search points a result at (the sectors,
 * the cities, the pages for candidates and employers, the jobs, the
 * consultants, the questions, the privacy notice, the office's details)
 * are marked where they are built ($searchable in parts.php).
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
// is kept under a _part name.
$recruitment_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
include $recruitment_dir . '/parts.php';
$public_part      = include $recruitment_dir . '/pages_public.php';
$jobs_part        = include $recruitment_dir . '/jobs.php';
include $recruitment_dir . '/mail.php';
$applications_part = include $recruitment_dir . '/applications.php';
$employers_part   = include $recruitment_dir . '/employers.php';
$standard_part    = include $recruitment_dir . '/standard.php';
$account_part     = include $recruitment_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// jobs, the applications, the staff requests, the standard pages, the
// account and staff pages, the e-mails last of each part. pages_public.php
// places widgets it does not build (the home's jobs, guides and Google
// reviews come from jobs.php and standard.php).
$parts = array($public_part, $jobs_part, $applications_part, $employers_part, $standard_part, $account_part);
$pages = array();
$widgets = array();
foreach ($parts as $part) {
    $pages = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a friendly geometric sans for the headings and the figures, a
// plain sans for the rest.
$recruitment_fonts = array(
    array('family' => 'Plus Jakarta Sans', 'weights' => array('500', '600', '700', '800'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named prc- (the
// field ids are rc-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$recruitment_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.015em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the geometric sans, bold and drawn a little tighter.
    '.prc-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.prc-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The agency's name in the header and the footer.
    '.prc-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.02em}',
    // A figure of the agency and a salary band: figures of one width.
    '.prc-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;letter-spacing:-.02em}',
    '.prc-salary{font-variant-numeric:tabular-nums}',
    // What a candidate or an employer says: a little larger, in the
    // heading face.
    '.prc-quote{font-family:var(--pg-font-heading);font-size:1.075rem;line-height:1.55}',
    // The home page's first section: a soft wash of the primary colour from
    // the upper left corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.prc-hero{background-image:radial-gradient(ellipse 70% 80% at 0% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // Job, sector, city, service and consultant cards rise a little under
    // the pointer.
    '.prc-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.prc-lift:hover,.prc-lift:focus-visible,.prc-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.prc-lift{transition:none}.prc-lift:hover,.prc-lift:focus-visible,.prc-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.prc-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Recruitment Agency'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 137,
    'icon'        => 'bi-person-workspace',
    'description' => lang('A recruitment agency: jobs by sector, city and contract type with a page for each job, applications and CVs, staff requests from employers, both followed from the account, the consultants, Career Advice, job alerts, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline accent follows the palette's primary colour where the mail
    // client reads it. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'modern-soft',
    'palette'     => 'emerald',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Jobs by sector, city and contract type with a page for each job, and sample jobs the staff replace from a form.'),
        lang('Applications for a job or an open CV, followed from the account with their status and the consultant\'s messages.'),
        lang('Staff requests from employers with their own page, status and messages.'),
        lang('Sectors, cities and the consultants, Career Advice, job alerts, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The CVs and job descriptions go to the private folder.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_recruitment', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Recruitment Agency Job Alerts'),
            'description'  => lang('The addresses left on the job alerts form of the recruitment agency.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Recruitment Agency Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Recruitment Agency Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Recruitment Agency Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Recruitment Agency Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Recruitment Agency Staff Bar'), 'tree' => $staff_bar()),
        'call_band'    => array('name' => lang('Recruitment Agency Call Band'), 'tree' => $call_band()),
        'email_header' => array('name' => lang('Recruitment Agency E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Recruitment Agency E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its prc- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $recruitment_css),
        'fonts' => json_encode($recruitment_fonts),
    ),
);
