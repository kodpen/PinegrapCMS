<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", its public pages:
 * home, the services and one page per service, the studio, the team, how
 * we work, awards and press, and the questions page. Included after
 * parts.php in the same scope (its builders and the studio's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($studio, $services, $project_types,
 * $locations, $projects, $stages, $principles, $team, $awards, $press,
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


// A picture in a framed ratio, square-cornered like the rest of the
// template, loaded lazily unless it is the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pai-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
        $heading('h2', $title, 'pai-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '5', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line, under a thin rule: the
// principles and the project types.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 border-top border-dark-subtle pt-4', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pai-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5 border-top', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pai-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-outline-primary rounded-0 px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening.
$figures_band = function () use ($el, $container, $figure_row) {
    return $el('section', 'py-5 border-top border-bottom', lang('Figures'), array(
        $container(array($figure_row())),
    ));
};

// The stages of work as a numbered list, each number in the index face.
$stage_list = function ($class = 'list-unstyled mb-0') use ($el, $heading, $para, $span, $stages) {
    $items = array();
    foreach ($stages as $n => $s) {
        $items[] = $el('li', 'd-flex gap-4 py-3 border-bottom', lang('Step'), array(
            $span(sprintf('%02d', $n + 1), 'pai-index fs-5 text-body-secondary flex-shrink-0', lang('Step Number')),
            $el('div', '', lang('Text'), array(
                $heading('h3', $s['title'], 'pai-display h6 fw-semibold mb-1'),
                $para($s['text'], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('ol', $class, lang('Steps'), $items);
};

// The cards and tiles of the shared data, in their order.
$principle_tiles = array();
foreach ($principles as $p) $principle_tiles[] = $feature_tile($p['icon'], $p['title'], $p['text']);
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$team_cols = array();
foreach (array_keys($team) as $n) $team_cols[] = $team_card($n);
$award_items = array();
foreach (array_keys($awards) as $n) $award_items[] = $award_card($n);
$press_cols = array();
foreach (array_keys($press) as $n) $press_cols[] = $press_card($n);
// A project type's tile links the project list filtered on it, in the
// words the Type list uses.
$type_tiles = array();
foreach ($project_types as $t) {
    $type_tiles[] = $feature_tile($t['icon'], $t['title'], $t['text'], '4', array(
        $link(lang('See the projects'), '{{page:projects}}?' . $query(array('type' => $t['title'])), 'd-inline-block small fw-semibold link-body-emphasis mt-3'),
    ));
}
// The places the studio has built, each opening the project list there.
$place_links = array();
foreach ($locations as $title) {
    $place_links[] = $link($title, '{{page:projects}}?' . $query(array('location' => $title)), 'btn btn-outline-primary rounded-0 btn-sm px-3');
}
$place_row = $el('div', 'd-flex flex-wrap gap-2', lang('Locations'), $place_links);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('An architecture and interior design studio: houses, workplaces, hotels and public places, designed around light and the people who use them.'),
    'tree'             => $page(array(
        // The way in: the studio in a sentence above one wide photograph,
        // captioned like a plate with the project it shows.
        $plate_hero(lang('Architecture and interiors'), lang('Buildings shaped by light, place and the people inside'), $studio['tagline'] . '.', array(
            $link(lang('See Our Projects'), '{{page:projects}}', 'btn btn-primary rounded-0 px-4'),
            $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-outline-primary rounded-0 px-4'),
        ), $photo('hero', 1800, 770), lang('The courtyard of a stone house in the late afternoon light'), array(
            'no'    => '01',
            'name'  => $projects['courtyard_house']['name'],
            'place' => $projects['courtyard_house']['place'],
            'href'  => '{{page:projects}}?' . $query(array('location' => $locations[$projects['courtyard_house']['location']])),
        )),

        // The featured projects, read from the Projects collection.
        $el('section', 'py-5', lang('Featured Projects'), array(
            $container(array(
                $section_header(lang('Selected work'), lang('Recent projects'), lang('All Projects'), '{{page:projects}}'),
                $widget('home_projects'),
            )),
        )),

        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('From the first sketch to the last detail'), lang('All Services'), '{{page:services}}'),
                $rise($row($service_cols, '4')),
            )),
        ), $searchable('ai-home-services', lang('architect, interior designer, renovation, extension, landscape, furniture'))),

        // What the buildings are made of: the studio's materials on a board.
        $material_board('ai-home-materials'),

        $el('section', 'py-5', lang('How We Work'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'pai-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', lang('From a conversation to the keys'), 'pai-display h2 mb-4'),
                        $rise($stage_list('list-unstyled mb-4')),
                        $link(lang('How We Work'), '{{page:process}}', 'btn btn-outline-primary rounded-0 px-4'),
                    ), '', '6'),
                    $col(array($fx($framed($photo('home-process', 1200, 1500), lang('Sketches and a cardboard model on a studio table'), '4x5'),
                        array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ai-home-process', lang('how it works, brief, concept, planning, tender, site, handover'))),

        // What clients say: sample words for the studio to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $row($testimonial_cols, '4', '', 'g-lg-5'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('Awards and Press'), array(
            $container(array(
                $section_header(lang('Recognition'), lang('Awards and press'), lang('Awards and Press'), '{{page:awards}}'),
                $rise($el('ul', 'list-unstyled border-top mb-0', lang('Awards'), array_slice($award_items, 0, 3))),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Journal'), array(
            $container(array(
                $section_header(lang('Journal'), lang('Notes from the studio'), lang('All Posts'), '{{page:blog}}'),
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
    'title'            => lang('Services'),
    'meta_description' => lang('Architecture, interior design, adaptive reuse, masterplanning, landscape architecture, and furniture and objects.'),
    'tree'             => $page(array(
        $page_header(lang('Services'), lang('Six ways we work with you, from one room to a whole neighbourhood.')),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $row($service_cols, '4'),
            )),
        ), $searchable('ai-services', lang('architect, interior design, renovation, conversion, urban design, garden design'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Project Types'), array(
            $container(array(
                $section_header(lang('What we design'), lang('Projects by type'), lang('All Projects'), '{{page:projects}}'),
                $row($type_tiles, '4'),
            )),
        ), $searchable('ai-project-types', lang('houses, offices, hotels, restaurants, libraries, schools, squares, parks'))),
        $el('section', 'py-5', lang('Locations'), array(
            $container(array(
                $section_header(lang('Where we work'), lang('Projects by place')),
                $place_row,
            )),
        )),
        $ask_band(lang('Not sure where to start?'), lang('Tell us about your site and your plans, and an architect calls you back.')),
    )),
);

// One page per service: what it is, what it covers, the projects designed
// with it and the enquiry with the service chosen. Its address is a
// translated slug, so a Turkish site reads Turkish addresses.
$service_slugs = array(
    'architecture'   => lang('architecture'),
    'interiors'      => lang('interior-design'),
    'adaptive_reuse' => lang('adaptive-reuse'),
    'masterplanning' => lang('masterplanning'),
    'landscape'      => lang('landscape-architecture'),
    'furniture'      => lang('furniture-and-objects'),
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
                            $heading('h2', lang('What it covers'), 'pai-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Start a Project'), '{{page:enquiry}}?' . $query(array('service' => $s['title'])), 'btn btn-primary rounded-0 px-4'),
                                $link(lang('Book a Consultation'), '{{page:consultation}}?' . $query(array('service' => $s['title'])), 'btn btn-outline-primary rounded-0 px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1200, 900), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('ai-service-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('How We Work'), array(
                $container(array(
                    $section_header(lang('Step by step'), lang('How a project runs with us'), lang('How We Work'), '{{page:process}}'),
                    $stage_list(),
                )),
            )),
            $el('section', 'py-5', lang('Projects'), array(
                $container(array(
                    $section_header(lang('Our work'), lang('Projects of every kind'), lang('All Projects'), '{{page:projects}}'),
                    $place_row,
                )),
            )),
        )),
    );
}

// ── Studio ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'studio',
    'name'             => lang('studio'),
    'folder'           => 'public',
    'title'            => lang('About the Studio'),
    'meta_description' => lang('An architecture and interior design studio started by two architects in a former print works.'),
    'tree'             => $page(array(
        $page_header(lang('About the Studio'), $studio['tagline'] . '.'),
        $story_band('ai-our-story', lang('Our story'), lang('Two drawing tables in an old print works'),
            array($studio['story'], lang('We keep the studio small on purpose: a partner leads every project from the first meeting to the last site visit.')),
            $photo('studio', 1400, 1050), lang('The studio\'s drawing room under the old print works roof lights'), false, array(),
            lang('history, founders, who we are, office')),
        $figures_band(),
        $el('section', 'py-5', lang('Our Principles'), array(
            $container(array(
                $section_header(lang('How we think'), lang('What guides every drawing')),
                $row($principle_tiles, '4'),
            )),
        ), $searchable('ai-studio-principles', lang('sustainable, daylight, natural materials, energy, approach'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Team'), array(
            $container(array(
                $section_header(lang('Our people'), lang('The partners and the people who lead your project'), lang('Our Team'), '{{page:team}}'),
                $row(array_slice($team_cols, 0, 3), '4'),
            )),
        )),
        $el('section', 'py-5', lang('Awards'), array(
            $container(array(
                $section_header(lang('Recognition'), lang('Awards'), lang('Awards and Press'), '{{page:awards}}'),
                $el('ul', 'list-unstyled border-top mb-0', lang('Awards'), $award_items),
            )),
        )),
        $ask_band(lang('Want to work with us?'), lang('Write to us about your project, or see the places open in the studio.')),
    )),
);

// ── Team ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'team',
    'name'             => lang('team'),
    'folder'           => 'public',
    'title'            => lang('Our Team'),
    'meta_description' => lang('The architects, interior designers and landscape architects of the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Our Team'), lang('Architects, interior designers and landscape architects who work side by side.')),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $row($team_cols, '4', '', 'g-lg-5'),
            )),
        ), $searchable('ai-team', lang('people, partners, architects, designers, staff'))),
        $story_band('ai-join-us', lang('Careers'), lang('Draw with us'),
            array(lang('Young architects and designers join a project team from their first week and follow their projects onto site.')),
            $photo('team-careers', 1200, 900), lang('Two designers working on a large model'), true,
            array($link(lang('See the open jobs'), '{{page:careers}}', 'btn btn-primary rounded-0 px-4')),
            lang('jobs, vacancies, work for us, internship'), 'py-5 bg-body-tertiary'),
    )),
);

// ── How we work ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-we-work'),
    'folder'           => 'public',
    'title'            => lang('How We Work'),
    'meta_description' => lang('How a project runs with the studio: brief, concept, permits, technical design, the work on site and aftercare.'),
    'tree'             => $page(array(
        $page_header(lang('How We Work'), lang('Six stages from the first conversation to the years after handover, with one partner all the way.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $stage_list('list-unstyled mb-4'),
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary rounded-0 px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('process', 1200, 1500), lang('An architect and a client walking a building site'), '4x5')), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('ai-process', lang('how it works, brief, concept, planning permission, tender, site, handover'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Fees'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Our fees'), 'pai-display h3 mb-3'),
                        $check_list(array(
                            lang('A written fee proposal after the first meeting, before any work starts'),
                            lang('A fixed fee for each stage, or a share of the building cost for larger projects'),
                            lang('You can stop after any stage and keep the drawings you have paid for'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Working with builders'), 'pai-display h3 mb-3'),
                        $check_list(array(
                            lang('We help you choose a builder through a fair tender'),
                            lang('We check the work on site every week and approve each payment'),
                            lang('We stay at your side through the first year after handover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('ai-fees', lang('fees, cost, price, payment, builder, contractor, tender'))),
        $ask_band(lang('A question about how we work?'), lang('Write to us and a partner answers, usually within two working days.')),
    )),
);

// ── Awards and press ────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'awards',
    'name'             => lang('awards-and-press'),
    'folder'           => 'public',
    'title'            => lang('Awards and Press'),
    'meta_description' => lang('The prizes our projects have won and been shortlisted for, and what the press has written about the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Awards and Press'), lang('The prizes our projects have received and what has been written about them.')),
        $el('section', 'py-5', lang('Awards'), array(
            $container(array(
                $section_header(lang('Recognition'), lang('Awards')),
                $el('ul', 'list-unstyled border-top mb-0', lang('Awards'), $award_items),
            )),
        ), $searchable('ai-awards', lang('prize, award, winner, shortlist, competition'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Press'), array(
            $container(array(
                $section_header(lang('In the press'), lang('Press')),
                $row($press_cols, '4', '', 'g-lg-5'),
            )),
        ), $searchable('ai-press', lang('press, magazine, article, interview, review, publication'))),
        $ask_band(lang('Writing about architecture?'), lang('For images, drawings and interviews, write to the studio.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: before we start and during the project. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pai-display h4 mb-3'),
        $el('div', 'accordion accordion-flush border-top', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about the first consultation, fees, permits, builders, timings and working with the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Fees, permits and life on site. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('ai-faq-before', lang('Before we start'), array(
                            array(lang('Is the first consultation really free?'), lang('Yes. We meet at the studio, on a video call or on your site, listen to what you need and tell you honestly how we could help, with no obligation.'), lang('consultation, free, first meeting')),
                            array(lang('How are your fees worked out?'), lang('After the first meeting we send a written fee proposal: a fixed fee for each stage, or a share of the building cost for larger projects.'), lang('fees, price, cost, percentage')),
                            array(lang('Do you take on small projects?'), lang('Yes: a kitchen, an extension or a single room gets the same care as a building. For very small jobs we may suggest a few hours of design advice instead.'), lang('small project, kitchen, extension, room')),
                            array(lang('How do I follow my enquiry?'), lang('Sending an enquiry opens an account for you. Under My Account you see the state of each enquiry and can write to us there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('ai-faq-during', lang('During the project'), array(
                            array(lang('Do you apply for planning permission?'), lang('Yes. We prepare the drawings and the application, and meet the planners and conservation officers with you.'), lang('planning permission, permit, approval, listed building')),
                            array(lang('Do you build the project too?'), lang('No: we design and supervise, and a builder you choose builds. We help you choose one through a fair tender and check the work every week.'), lang('builder, contractor, construction, tender')),
                            array(lang('How long does a project take?'), lang('A house usually takes six to nine months to design and a year to build; an interior a few months each. We agree a programme at the start.'), lang('time, how long, programme, schedule')),
                            array(lang('Who will I work with?'), lang('One partner leads your project from the first meeting to handover, with a small team you will get to know.'), lang('architect, partner, team, contact')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured projects (home_projects) come with
// the projects, its journal (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
