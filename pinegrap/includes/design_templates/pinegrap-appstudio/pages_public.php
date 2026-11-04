<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its public pages: home, the
 * services with the three packages, the way of working, about, the
 * questions users and clients ask, and the press kit. Included after
 * parts.php in the same scope (its builders and the developer's data);
 * returns the list of pages. The lists the home places (home_apps,
 * home_releases, home_posts) are widgets of the other page files.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// ── Pieces the pages share ──────────────────────────────────────────────

// The developer in four figures, in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pma-display display-6 fw-bold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A kind of app, linked to the app list with that kind chosen (?category=
// in the words of the Category list).
$category_card = function ($key) use ($el, $heading, $para, $col, $icon_tile, $categories, $query) {
    $c = $categories[$key];
    return $col(array(
        $el('a', 'card h-100 border-0 shadow-sm rounded-4 pma-lift text-decoration-none link-body-emphasis', $c['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($c['icon'], 'mb-3'),
                $heading('h3', $c['title'], 'h6 fw-bold mb-2'),
                $para($c['text'], 'small text-body-secondary mb-0'),
            )),
        ), array('href' => '{{page:apps}}?' . $query(array('category' => $c['title'])))),
    ), '6', '', '', '12', 'col-lg');
};

// A service card: what the developer does, its points and the way to ask
// for it (the project request with the kind of project chosen, ?type=).
$service_card = function ($key, $lg = '4') use ($el, $heading, $para, $link, $icon_tile, $check_list, $col, $searchable, $services, $project_types, $query) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $s['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3 align-self-start'),
                $heading('h3', $s['title'], 'h5 fw-bold mb-2'),
                $para($s['text'], 'text-body-secondary mb-3'),
                $check_list($s['points'], 'list-unstyled small mb-4'),
                $link(lang('Ask about this'), '{{page:hire}}?' . $query(array('type' => $project_types[$s['type']])),
                    'btn btn-outline-primary rounded-pill align-self-start mt-auto'),
            )),
        ), $searchable('service-' . $key)),
    ), '6', $lg);
};

// The way of working as a numbered timeline: the icon, the step, how long
// it takes and what happens.
$process_list = function ($class = 'list-unstyled mb-0') use ($el, $heading, $para, $span, $icon_tile, $process) {
    $items = array();
    $last = count($process) - 1;
    foreach (array_values($process) as $n => $p) {
        $items[] = $el('li', 'd-flex gap-3 ' . ($n === $last ? 'mb-0' : 'mb-4'), lang('Step'), array(
            $icon_tile($p['icon'], 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $el('div', 'd-flex flex-wrap align-items-baseline gap-2 mb-1', lang('Step Title'), array(
                    $heading('h3', ($n + 1) . '. ' . $p['title'], 'h6 fw-bold mb-0'),
                    $span($p['time'], 'badge rounded-pill text-bg-light border', lang('Time')),
                )),
                $para($p['text'], 'text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('ol', $class, lang('Process'), $items);
};

// A phone-shaped frame around a sample screenshot (9:16, kept by the
// picture's own width and height).
$phone_shot = function ($seed, $alt, $class = '') use ($el, $image, $attr, $photo) {
    return $el('div', trim('border border-4 border-dark rounded-5 overflow-hidden shadow bg-body-tertiary ' . $class), lang('Phone Frame'), array(
        $image($photo($seed, 540, 960), $alt, 'd-block w-100 h-auto', array('_attrs' => array($attr('width', '540'), $attr('height', '960'), $attr('loading', 'lazy')))),
    ));
};

// The tools the apps are made with, as badges (the services and about pages).
$tool_list = array();
foreach ($tools as $t) $tool_list[] = $span($t, 'badge rounded-pill text-bg-light border fw-normal pma-mono', lang('Tool'));

$category_cards = array();
foreach (array_keys($categories) as $key) $category_cards[] = $category_card($key);
$package_cards = array();
foreach (array_keys($packages) as $key) $package_cards[] = $package_card($key);
$testimonial_cards = array();
foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Small, calm apps for iPhone and Android, the notes of every release, and apps built for companies with the same care.'),
    'tree'             => $page(array(
        // The way in: who makes the apps, and two phones with their screens.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($developer['role'], 'pma-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Apps that do one thing, and do it well'), 'pma-display display-4 fw-bold mb-3'),
                        $para($developer['bio'], 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('See My Apps'), '{{page:apps}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 small text-body-secondary mb-0', lang('Availability'), array(
                            $icon('bi-circle-fill', 'text-success small'),
                            $span($developer['available'], '', lang('Text')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $row(array(
                            $col(array($phone_shot('home-1', $apps['tally']['name'])), '', '', '', '6'),
                            $col(array($phone_shot('home-2', $apps['stride']['name'], 'mt-5')), '', '', '', '6'),
                        ), '3', 'center'),
                    ), '', '5', '', '12', 'offset-lg-1'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Featured Apps'), array(
            $container(array(
                $section_header(lang('In the stores'), lang('My apps'), lang('All apps'), '{{page:apps}}'),
                $widget('home_apps'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Kinds of App'), array(
            $container(array(
                $section_header(lang('Browse'), lang('Apps by kind')),
                $row($category_cards, '4'),
            )),
        )),

        $el('section', 'py-5', lang('Latest Releases'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('What\'s new'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Every update, in plain words'), 'h3 fw-semibold mb-3'),
                        $para(lang('Each release says what changed and what was fixed. Want the next one before the store has it? Join the beta.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('All release notes'), '{{page:releases}}', 'btn btn-outline-primary rounded-pill px-4'),
                            $link(lang('Join the Beta'), '{{page:beta}}', 'btn btn-link link-body-emphasis'),
                        )),
                    ), '', '4'),
                    $col(array($widget('home_releases')), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Services'), array(
            $container(array(
                $section_header(lang('Client projects'), lang('I can build yours too'), lang('All services'), '{{page:services}}'),
                $row($package_cards, '4'),
                $el('p', 'small text-body-secondary text-center mt-4 mb-0', lang('Note'), array(
                    $span(lang('Not sure which one fits?'), 'me-1', lang('Text')),
                    $link(lang('See how I work'), '{{page:process}}', 'link-primary fw-semibold'),
                )),
            )),
        )),

        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('From the reviews'), lang('What users say')),
                $row($testimonial_cards, '4'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Dev Notes'), array(
            $container(array(
                $section_header(lang('From the desk'), lang('Dev Notes'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('project_band'),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$service_cards = array();
foreach (array_keys($services) as $key) $service_cards[] = $service_card($key);

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Services'),
    'meta_description' => lang('Native apps for iPhone and Android, prototypes, accounts and sync, store launches and a monthly care plan, in three packages.'),
    'tree'             => $page(array(
        $page_header(lang('Services'), lang('The same care my own apps get, for yours: from a clickable prototype to an app in both stores.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('How I Work'), '{{page:process}}', 'btn btn-outline-secondary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('What I do'), lang('From the idea to the stores')),
                $row($service_cards, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Packages'), array(
            $container(array(
                $section_header(lang('Packages'), lang('Three ways to start')),
                $row($package_cards, '4'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Every project gets a fixed price in writing after the first conversation. The prices above are where most projects start.'), '', lang('Text')),
                )),
            )),
        ), $searchable('packages', lang('price, cost, budget, quote, package'))),
        $el('section', 'py-5', lang('Tools'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Made with'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Native tools, no shortcuts'), 'h3 fw-semibold mb-3'),
                        $para(lang('Each app is written in the language its phone speaks, so it stays fast, works with the newest system features and is easy for another developer to take over.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array($el('div', 'd-flex flex-wrap gap-2', lang('Tools'), $tool_list)), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('tools', lang('Swift, Kotlin, SwiftUI, Jetpack Compose, native'))),
        $shared('project_band'),
    )),
);

// ── How I work ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-i-work'),
    'folder'           => 'public',
    'title'            => lang('How I Work'),
    'meta_description' => lang('From the first conversation to the store launch: the four steps of a client project, how long each takes and what you receive.'),
    'tree'             => $page(array(
        $page_header(lang('How I Work'), lang('Four steps, a fixed price and a new beta on your phone every week.')),
        $el('section', 'py-5', lang('Process'), array(
            $container(array(
                $row(array(
                    $col(array($process_list()), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary rounded-4', lang('What You Get'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('What you receive'), 'h5 fw-bold mb-3'),
                                $check_list(array(
                                    lang('A written plan and a fixed price before work begins'),
                                    lang('A short update and a new beta every Friday'),
                                    lang('The source code in your own repository from day one'),
                                    lang('The store accounts in your name, not mine'),
                                    lang('A month of fixes after the launch'),
                                ), 'list-unstyled small mb-4'),
                                $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('steps', lang('process, timeline, weeks, beta, launch'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What I Need'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Before we start'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What helps me help you'), 'h3 fw-semibold mb-3'),
                        $para(lang('None of these are needed to send a request; they make the first conversation shorter.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Who the app is for'), lang('The people who will use it and the one thing they should be able to do on the first day.')),
                            $step(2, lang('What exists already'), lang('Designs, a website, an older app or a service the app must talk to.')),
                            $step(3, lang('Your dates'), lang('A launch date, an event or a season the app must be ready for.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('before-we-start', lang('brief, requirements, design, deadline'))),
        $shared('project_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$experience_items = array();
foreach ($experience as $n => $x) {
    $experience_items[] = $el('li', 'd-flex gap-3 ' . ($n === count($experience) - 1 ? 'mb-0' : 'mb-3'), lang('Experience'), array(
        $span($x['years'], 'small text-body-secondary pma-mono text-nowrap pt-1', lang('Years')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $x['title'], 'h6 fw-bold mb-0'),
            $para($x['place'], 'small text-body-secondary mb-0'),
        )),
    ));
}
$skill_badges = array();
foreach ($skills as $s) $skill_badges[] = $span($s, 'badge rounded-pill text-bg-primary-subtle text-primary-emphasis fw-normal fs-6', lang('Skill'));

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('The independent developer behind the apps: the story, the skills, the tools and the years before the studio.'),
    'tree'             => $page(array(
        $page_header(lang('About'), $developer['role'] . ' · ' . $developer['city']),
        $el('section', 'py-5', lang('My Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($portrait($developer['name'], 800), $developer['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '5'),
                    $col(array(
                        $para(lang('My story'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', sprintf(lang('Hello, I am %s'), $developer['name']), 'h3 fw-semibold mb-3'),
                        $para(lang('I wrote my first iPhone app in 2012 to count the laps of my morning swim. Ten years at an agency and a ride-sharing company taught me how apps are built for millions of people; my own studio lets me build them for one person at a time.'), 'text-body-secondary'),
                        $para(lang('My apps are small on purpose. They ask for no account they do not need, collect no data they do not use, and still work when the signal drops. I build client apps the same way.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Press Kit'), '{{page:press}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('my-story', lang('story, developer, studio, independent'))),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Skills and Experience'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Skills'), 'h4 fw-bold mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Skills'), $skill_badges),
                        $heading('h2', lang('Tools'), 'h4 fw-bold mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Tools'), $tool_list),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Experience'), 'h4 fw-bold mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Experience'), $experience_items),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skills', lang('skills, experience, career, tools'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Principles'), array(
            $container(array(
                $section_header(lang('How I build'), lang('Four promises in every app')),
                $row(array(
                    $col(array($el('div', 'h-100', lang('Privacy'), array(
                        $icon_tile('bi-incognito', 'mb-3'),
                        $heading('h3', lang('Private by default'), 'h6 fw-bold mb-2'),
                        $para(lang('No tracking, no ads, and only the data the app needs to work.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Accessibility'), array(
                        $icon_tile('bi-universal-access', 'mb-3'),
                        $heading('h3', lang('Usable by everyone'), 'h6 fw-bold mb-2'),
                        $para(lang('Large text, VoiceOver and TalkBack tested on every screen.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Offline'), array(
                        $icon_tile('bi-wifi-off', 'mb-3'),
                        $heading('h3', lang('Works offline'), 'h6 fw-bold mb-2'),
                        $para(lang('The app keeps working in a tunnel and catches up when the signal returns.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Care'), array(
                        $icon_tile('bi-arrow-repeat', 'mb-3'),
                        $heading('h3', lang('Kept up to date'), 'h6 fw-bold mb-2'),
                        $para(lang('Ready for each new iOS and Android version on the day it comes out.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                ), '4'),
            )),
        ), $searchable('principles', lang('privacy, accessibility, offline, updates'))),
        $shared('project_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────
// Two groups of questions: the users of the apps and the clients of the
// studio, each an accordion of its own.

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers for app users (help, purchases, data, beta) and for clients (prices, timelines, code ownership, care after the launch).'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What users and clients ask most. Anything else? Send a support request and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('For app users'), 'h4 fw-bold mb-3'),
                        $el('div', 'accordion mb-5', lang('Questions'), array(
                            $faq(1, lang('How do I get help with an app?'), lang('Send a support request and choose the app. You can follow it, and answer my questions, from My Requests in your account.'), lang('help, support, bug, problem'), 'ma-faq-users'),
                            $faq(2, lang('How do I restore a purchase?'), lang('Open the app\'s settings and choose Restore Purchases, signed in with the same Apple or Google account you bought with.'), lang('purchase, restore, subscription, refund'), 'ma-faq-users'),
                            $faq(3, lang('How do I cancel a subscription?'), lang('Subscriptions are managed by the store: in your Apple ID settings on an iPhone, or under Payments and subscriptions in Google Play.'), lang('cancel, subscription, billing'), 'ma-faq-users'),
                            $faq(4, lang('What happens to my data?'), lang('Each app\'s page has a privacy summary, and the privacy policy says it in full. No app shows ads or sells data.'), lang('privacy, data, delete, export'), 'ma-faq-users'),
                            $faq(5, lang('How can I try new versions early?'), lang('Join the beta with your store e-mail; the invitation to TestFlight or Google Play testing comes by e-mail.'), lang('beta, testflight, early access'), 'ma-faq-users'),
                        ), array('id' => 'ma-faq-users')),
                        $heading('h2', lang('For clients'), 'h4 fw-bold mb-3'),
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How much does an app cost?'), lang('Most first versions start around the First Version package. After a short call you get a fixed price in writing.'), lang('price, cost, budget, quote'), 'ma-faq-clients'),
                            $faq(2, lang('How long does it take?'), lang('A prototype takes two to three weeks, a first version ten to fourteen. You get a new beta every week along the way.'), lang('time, timeline, weeks, deadline'), 'ma-faq-clients'),
                            $faq(3, lang('Who owns the code?'), lang('You do. The code lives in your repository and the store accounts are in your name from the first day.'), lang('code, ownership, repository, rights'), 'ma-faq-clients'),
                            $faq(4, lang('Do you build for both iPhone and Android?'), lang('Yes, natively for each, from one plan and one design, so both apps feel at home on their phones.'), lang('iOS, Android, native, cross-platform'), 'ma-faq-clients'),
                            $faq(5, lang('What happens after the launch?'), lang('The first month of fixes is included. After that the care plan keeps the app ready for new system versions.'), lang('maintenance, support, care, updates'), 'ma-faq-clients'),
                        ), array('id' => 'ma-faq-clients')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('project_band'),
    )),
);

// ── Press kit ───────────────────────────────────────────────────────────
// What a journalist needs on one page: the facts, the apps with their icons,
// screenshots, the developer's bio and the press contact. Each part is a
// search section, so a search for "logo" or "screenshots" opens it.

$press_apps = array();
foreach ($apps as $key => $a) {
    $press_apps[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $a['name'], array(
            $el('div', 'card-body d-flex gap-3 p-4', lang('Card Body'), array(
                $el('span', 'd-block flex-shrink-0 rounded-4 overflow-hidden pma-icon', lang('Icon Frame'), array(
                    $image($portrait($a['name'], 160), $a['name'], 'w-100 h-100 object-fit-cover',
                        array('_attrs' => array($attr('width', '64'), $attr('height', '64'), $attr('loading', 'lazy')))),
                )),
                $el('div', '', lang('Text'), array(
                    $heading('h3', $a['name'], 'h6 fw-bold mb-1'),
                    $para($a['tagline'], 'small text-body-secondary mb-2'),
                    $para($categories[$a['category']]['title'] . ' · ' . $platform_title($a['platforms']) . ' · ' . $a['version'], 'small mb-2'),
                    $link(lang('Download the icon'), $portrait($a['name'], 1024), 'small link-primary', array($attr('download', '')), array('target' => '_blank')),
                )),
            )),
        )),
    ), '6', '4');
}
$press_shots = array();
foreach (array('tally', 'stride', 'wayfarer', 'lingo') as $n => $key) {
    $press_shots[] = $col(array($phone_shot('press-' . ($n + 1), $apps[$key]['name'])), '3', '', '', '6');
}
$fact = function ($label, $value) use ($el, $span) {
    return $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom small', $label, array(
        $span($label, 'text-body-secondary', lang('Label')),
        $span($value, 'fw-semibold text-end', lang('Value')),
    ));
};

$public_pages[] = array(
    'key'              => 'press',
    'name'             => lang('press'),
    'folder'           => 'public',
    'title'            => lang('Press Kit'),
    'meta_description' => lang('Facts, app icons, screenshots and the developer\'s bio for writers and reviewers, with the press contact.'),
    'tree'             => $page(array(
        $page_header(lang('Press Kit'), lang('Facts, icons and screenshots, free to use when you write about the apps.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Write to the press contact'), 'mailto:' . $developer['email'] . '?subject=' . rawurlencode(lang('Press request')), 'btn btn-primary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Facts'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Facts'), 'h4 fw-bold mb-3'),
                        $el('div', '', lang('Facts'), array(
                            $fact(lang('Developer'), $developer['name']),
                            $fact(lang('Based in'), $developer['city']),
                            $fact(lang('Studio founded'), '2020'),
                            $fact(lang('Apps'), (string)count($apps)),
                            $fact(lang('Downloads'), $figures[1]['value']),
                            $fact(lang('Press contact'), $developer['email']),
                        )),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('About the developer'), 'h4 fw-bold mb-3'),
                        $para(sprintf(lang('%1$s is an independent iOS and Android developer. %2$s'), $developer['name'], $developer['bio']), 'text-body-secondary'),
                        $para(lang('Before starting the studio in 2020, they built apps at a ride-sharing company in Istanbul and a digital agency in Izmir. Their own apps have been downloaded more than a million times.'), 'text-body-secondary mb-0'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('facts', lang('facts, bio, developer, founded'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Apps'), array(
            $container(array(
                $section_header(lang('The apps'), lang('Names, lines and icons'), lang('All apps'), '{{page:apps}}'),
                $row($press_apps, '4'),
            )),
        ), $searchable('press-apps', lang('icon, logo, app names'))),
        $el('section', 'py-5', lang('Screenshots'), array(
            $container(array(
                $section_header(lang('Screenshots'), lang('Screens from the apps')),
                $row($press_shots, '4', 'center'),
            )),
        ), $searchable('screenshots', lang('screenshots, images, pictures'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Guidelines'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Using the material'), 'h4 fw-bold mb-3'),
                        $check_list(array(
                            lang('Write the app names as they are written here'),
                            lang('Use the icons and screenshots as they are, without changing their colours'),
                            lang('Ask for a promo code to review a paid app'),
                            lang('Use the official App Store and Google Play badges'),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        ), $searchable('press-guidelines', lang('promo code, review, brand, guidelines'))),
        $shared('project_band'),
    )),
);

return $public_pages;
