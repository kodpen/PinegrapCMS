<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", its public pages: home, the services
 * and one page per service, sectors, the process, safety and quality,
 * about, the team and the questions page. Included after parts.php in the
 * same scope (its builders and the firm's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($firm, $services, $sectors,
 * $projects, $process, $team, $safety_points, $accreditations, $why_us,
 * $figures, $testimonials), so a change made there reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A picture in a framed ratio, loaded lazily unless it is the first thing
// a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-3 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pcn-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pcn-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why clients choose the firm,
// the safety points, the sectors and the accreditations.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pcn-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pcn-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening.
$figures_band = function () use ($el, $container, $figure_row) {
    return $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($figure_row())),
    ));
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$team_cols = array();
foreach (array_keys($team) as $n) $team_cols[] = $team_card($n);
$safety_tiles = array();
foreach ($safety_points as $s) $safety_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text']);
$accreditation_tiles = array();
foreach ($accreditations as $a) $accreditation_tiles[] = $feature_tile($a['icon'], $a['title'], $a['text']);
$process_items = array();
foreach ($process as $n => $s) $process_items[] = $step($n + 1, $s['title'], $s['text']);
// A sector's tile links the project list filtered on it, in the words the
// Sector list uses.
$sector_tiles = array();
foreach ($sectors as $s) {
    $sector_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text'], '4', array(
        $link(lang('See the projects'), '{{page:projects}}?' . $query(array('sector' => $s['title'])), 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A building contractor for homes, offices, schools and roads: a free site visit, a fixed price and handover on time.'),
    'tree'             => $page(array(
        // The way in: the firm's site board over a photograph of a site,
        // the two ways on and the facts a site board shows.
        $board_hero(lang('Building contractor'), lang('We build what you plan'), $firm['tagline'] . '.',
            array(
                $link(lang('Request a Quote'), '{{page:quote}}', 'btn btn-primary rounded-0 px-4'),
                $link(lang('See Our Projects'), '{{page:projects}}', 'btn btn-outline-secondary rounded-0 px-4'),
            ),
            $photo('hero', 1600, 1200), lang('A crane over a building site at sunrise'),
            array(
                lang('We build') => lang('New builds, extensions and refurbishments'),
                lang('Site visit') => lang('Free, within a week of your call'),
                lang('Our builders') => lang('Employed by us, never passed on'),
                lang('Every building') => lang('A ten-year structural warranty'),
            )),

        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('From the foundations to the keys'), lang('All Services'), '{{page:services}}'),
                $rise($row($service_cols, '4')),
            )),
        ), $searchable('cn-home-services', lang('builder, contractor, construction company, renovation, design and build'))),

        // The featured projects, read from the Projects collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Projects'), array(
            $container(array(
                $section_header(lang('Our work'), lang('Projects we are proud of'), lang('All Projects'), '{{page:projects}}'),
                $widget('home_projects'),
            )),
        )),

        // A project on site now, stage by stage.
        $site_progress('logistics_hub', 'cn-home-on-site'),

        $el('section', 'py-5 bg-body-tertiary', lang('Why Build with Us'), array(
            $container(array(
                $section_header(lang('The way we build'), lang('Why build with us'), lang('About Us'), '{{page:about}}'),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('cn-why-us', lang('fixed price, on time, project manager, safety, reliable'))),

        // What clients say: sample words for the firm to replace, and beside
        // them its own Google reviews, which show once Google Reviews is set
        // up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $rise($row($testimonial_cols, '4'), 120),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('From our sites'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('New homes, commercial buildings, renovation, civil works, design and build, and project management.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Six ways we work with you, from a single extension to a new road.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $row($service_cols, '4'),
            )),
        ), $searchable('cn-services', lang('builder, contractor, construction, renovation, civil engineering, project management'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Sectors'), array(
            $container(array(
                $section_header(lang('Who we build for'), lang('Sectors we work in'), lang('Sectors'), '{{page:sectors}}'),
                $row($sector_tiles, '4'),
            )),
        )),
        $ask_band(lang('Not sure which service you need?'), lang('Tell us about your project and an engineer calls you back within a working day.')),
    )),
);

// One page per service: what it is, what it covers, the projects built
// with it and the quote request with the service chosen. Its address is a
// translated slug, so a Turkish site reads Turkish addresses.
$service_slugs = array(
    'residential'  => lang('residential-construction'),
    'commercial'   => lang('commercial-buildings'),
    'renovation'   => lang('renovation-restoration'),
    'civil'        => lang('civil-works'),
    'design_build' => lang('design-and-build'),
    'management'   => lang('project-management'),
);
foreach ($services as $key => $s) {
    $public_pages[] = array(
        'key'              => 'service_' . $key,
        'name'             => $service_slugs[$key],
        'folder'           => 'public',
        'title'            => $s['title'],
        'meta_description' => $s['summary'],
        'tree'             => $page(array(
            $page_header($s['title'], $s['summary']),
            $el('section', 'py-5', $s['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($s['icon'], 'mb-3'),
                            $para($s['text'], 'lead mb-4'),
                            $heading('h2', lang('What it covers'), 'pcn-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Request a Quote'), '{{page:quote}}?' . $query(array('service' => $s['title'])), 'btn btn-primary px-4'),
                                $link(lang('See the projects'), '{{page:projects}}?' . $query(array('service' => $s['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1200, 900), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('cn-service-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('Our Process'), array(
                $container(array(
                    $section_header(lang('Step by step'), lang('How we run your project'), lang('Our Process'), '{{page:process}}'),
                    $el('ol', 'list-unstyled mb-0', lang('Steps'), $process_items),
                )),
            )),
        )),
    );
}

// ── Sectors ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'sectors',
    'name'             => lang('sectors'),
    'folder'           => 'public',
    'title'            => lang('Sectors'),
    'meta_description' => lang('Housing, offices and retail, education and health, industrial buildings and infrastructure.'),
    'tree'             => $page(array(
        $page_header(lang('Sectors'), lang('The buildings and works we know best, and the projects we have built in each.')),
        $el('section', 'py-5', lang('Sectors'), array(
            $container(array(
                $row($sector_tiles, '4'),
            )),
        ), $searchable('cn-sectors', lang('housing, offices, schools, hospitals, factories, roads, bridges'))),
        $figures_band(),
        $ask_band(lang('Building in another sector?'), lang('Write to us: our engineers have built almost every kind of building.')),
    )),
);

// ── Process ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('our-process'),
    'folder'           => 'public',
    'title'            => lang('Our Process'),
    'meta_description' => lang('How a project runs with us: a free site visit, a fixed-price quote, design and permits, construction and handover.'),
    'tree'             => $page(array(
        $page_header(lang('Our Process'), lang('Five steps from the first call to the keys, with one project manager all the way.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), $process_items),
                        $link(lang('Request a Quote'), '{{page:quote}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('process', 1200, 900), lang('A project manager walking a site with a client'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cn-process', lang('how it works, site visit, quote, permit, programme, handover, warranty'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What You Can Count On'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your price'), 'pcn-display h3 mb-3'),
                        $check_list(array(
                            lang('A fixed price for the work we agree, in writing before work starts'),
                            lang('Changes you ask for are priced before we make them'),
                            lang('Payments in stages, as the work is done'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Your building'), 'pcn-display h3 mb-3'),
                        $check_list(array(
                            lang('A progress report with photos every week'),
                            lang('Quality checks recorded at every stage'),
                            lang('A ten-year structural warranty from handover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cn-promises', lang('fixed price, payments, stages, warranty, guarantee, reports'))),
        $ask_band(lang('A question about how we work?'), lang('Write to us and a project manager answers, usually the same day.')),
    )),
);

// ── Safety and quality ──────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'safety',
    'name'             => lang('safety-and-quality'),
    'folder'           => 'public',
    'title'            => lang('Safety and Quality'),
    'meta_description' => lang('How we keep our sites safe and our buildings sound: safety plans, trained crews, quality checks and certificates.'),
    'tree'             => $page(array(
        $page_header(lang('Safety and Quality'), lang('Everyone on our sites goes home safe, and every building we hand over is built to last.')),
        $el('section', 'py-5', lang('Safety'), array(
            $container(array(
                $section_header(lang('On every site'), lang('How we work safely')),
                $rise($row($safety_tiles, '4')),
            )),
        ), $searchable('cn-safety', lang('health and safety, accident, training, inspection, waste, environment'))),
        // The notice every site puts up for its street.
        $street_notice('cn-safety-neighbours'),
        $el('section', 'py-5', lang('Accreditations'), array(
            $container(array(
                $section_header(lang('Certified'), lang('Our certificates')),
                $rise($row($accreditation_tiles, '4')),
            )),
        ), $searchable('cn-accreditations', lang('ISO, certificate, accreditation, licence, insurance'))),
        $figures_band(),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A building contractor started by two site engineers: a fixed price, one project manager and handover on time.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $firm['tagline'] . '.'),
        $story_band('cn-our-story', lang('Our story'), lang('One crew and a borrowed crane'),
            array($firm['story'], lang('Our own engineers, site managers and craftspeople do most of the work, so we answer for every part of it.')),
            $photo('about-site', 1200, 900), lang('The firm\'s site team in front of a finished building'), false, array(),
            lang('history, founders, company, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Build with Us'), array(
            $container(array(
                $section_header(lang('The way we build'), lang('Why build with us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('cn-about-why', lang('fixed price, on time, project manager, safety, reliable'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Team'), array(
            $container(array(
                $section_header(lang('Our people'), lang('The people who run your project'), lang('Our Team'), '{{page:team}}'),
                $row(array_slice($team_cols, 0, 3), '4'),
            )),
        )),
        $ask_band(lang('Want to work with us?'), lang('Write to us about your project, or see the jobs open on our sites.')),
    )),
);

// ── Team ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'team',
    'name'             => lang('team'),
    'folder'           => 'public',
    'title'            => lang('Our Team'),
    'meta_description' => lang('The engineers, architects and managers who lead our projects and our sites.'),
    'tree'             => $page(array(
        $page_header(lang('Our Team'), lang('The people who lead our projects, from the drawing board to the site.')),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $row($team_cols, '4'),
            )),
        ), $searchable('cn-team', lang('people, managers, engineers, architects, staff, leadership'))),
        $story_band('cn-join-us', lang('Careers'), lang('Build your career with us'),
            array(lang('We train our own people: apprentices learn a trade on site, engineers run their own projects within a few years.')),
            $photo('team-careers', 1200, 900), lang('An apprentice and a master carpenter on site'), true,
            array($link(lang('See the open jobs'), '{{page:careers}}', 'btn btn-primary px-4')),
            lang('jobs, vacancies, work for us, apprenticeship'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: before you build and during the work. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pcn-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about quotes, site visits, permits, prices, timings, warranties and working near your home.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Quotes, permits and life on site. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('cn-faq-before', lang('Before you build'), array(
                            array(lang('Is the site visit really free?'), lang('Yes. An engineer visits the site, listens to what you need and looks at the ground and the access, with no obligation.'), lang('site visit, free, survey, inspection')),
                            array(lang('How long does a quote take?'), lang('Within ten working days of the site visit you receive a detailed quote with a fixed price and a programme.'), lang('quote, estimate, price, how long')),
                            array(lang('Do you apply for the permits?'), lang('On a design-and-build project we prepare the drawings and apply for the permits for you; otherwise we help your architect with them.'), lang('permit, planning permission, licence, approval')),
                            array(lang('How do I follow my quote request?'), lang('Sending the request opens an account for you. Under My Account you see the state of each request and can write to us there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('cn-faq-during', lang('During the work'), array(
                            array(lang('Can we live in the house during a renovation?'), lang('Often yes: we close off the work with dust screens and keep a safe route through the house. We tell you at the site visit if moving out is wiser.'), lang('renovation, live in, dust, noise')),
                            array(lang('What if the work costs more than quoted?'), lang('The price we quote is fixed for the work we agreed. If you ask for a change, we price it first and you decide.'), lang('extra cost, variation, change, fixed price')),
                            array(lang('How will I know how the work is going?'), lang('Your project manager sends a progress report with photos every week and is your one point of contact.'), lang('progress, report, contact, project manager')),
                            array(lang('What warranty do you give?'), $firm['licence'] . '.', lang('warranty, guarantee, defects, insurance')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured projects (home_projects) come with
// the projects, its news (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
