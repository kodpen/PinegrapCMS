<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Web Designer Portfolio", its public pages: home, the
 * services with the packages, the way of working, about and the questions
 * clients ask. Included after parts.php in the same scope (its builders and
 * the designer's data); returns the list of pages.
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

// A service card: what the designer does, what it holds and the link that
// opens the brief with its kind of project chosen (?type=).
$service_card = function ($key, $lg = '4', $points = true) use ($el, $heading, $para, $link, $icon_tile, $check_list, $col, $searchable, $services, $categories, $query) {
    $s = $services[$key];
    $children = array(
        $icon_tile($s['icon'], 'mb-3 align-self-start'),
        $heading('h3', $s['title'], 'h5 fw-bold mb-2'),
        $para($s['text'], 'text-body-secondary mb-3'),
    );
    if ($points) $children[] = $check_list($s['points'], 'list-unstyled small mb-4');
    $children[] = $link(lang('Start a brief'), '{{page:brief}}?' . $query(array('type' => $categories[$s['category']]['title'])),
        'btn btn-outline-primary rounded-pill align-self-start mt-auto');
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $s['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $children),
        ), $searchable('service-' . $key)),
    ), '6', $lg);
};

// The designer's figures in a row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pdp-display display-6 fw-bold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', '', lang('Figures'), array($row($cols, '4')));
};

// The way of working as numbered cards: the icon, the week, the step and
// what happens in it.
$process_cards = function () use ($el, $heading, $para, $span, $icon_tile, $row, $col, $process) {
    $cols = array();
    foreach (array_values($process) as $n => $p) {
        $cols[] = $col(array(
            $el('article', 'h-100 p-4 rounded-4 bg-body border', $p['title'], array(
                $el('div', 'd-flex align-items-center justify-content-between mb-3', lang('Step Header'), array(
                    $icon_tile($p['icon']),
                    $span(sprintf('%02d', $n + 1), 'pdp-display fs-2 fw-bold text-body-tertiary', lang('Step Number')),
                )),
                $para($p['time'], 'small text-uppercase fw-semibold text-primary mb-1'),
                $heading('h3', $p['title'], 'h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-0'),
            )),
        ), '6', '3');
    }
    return $row($cols, '4');
};

// The packages side by side.
$package_row = function () use ($row, $package_card, $packages) {
    $cards = array();
    foreach (array_keys($packages) as $key) $cards[] = $package_card($key);
    return $row($cards, '4', '', 'align-items-stretch');
};

// What clients say: the sample quotes, then the designer's own Google
// reviews, which show once Google Reviews is set up under Site Settings.
$quotes_section = function ($class) use ($el, $container, $row, $section_header, $testimonial_card, $testimonials, $widget) {
    $cards = array();
    foreach (array_keys($testimonials) as $n) $cards[] = $testimonial_card($n);
    return $el('section', $class, lang('Client Quotes'), array(
        $container(array(
            $section_header(lang('Kind words'), lang('What clients say')),
            $row($cards, '4'),
            $widget('google_reviews'),
        )),
    ));
};

// Badges in a row: the skills and the tools of the about page.
$badges = function ($items, $name, $class) use ($el, $span) {
    $children = array();
    foreach ($items as $text) $children[] = $span($text, 'badge rounded-pill fw-normal fs-6 px-3 py-2 ' . $class, lang('Badge'));
    return $el('div', 'd-flex flex-wrap gap-2', $name, $children);
};

// ── Home ────────────────────────────────────────────────────────────────

$client_names = array();
foreach ($clients as $c) $client_names[] = $span($c, 'pdp-display fs-5 fw-bold text-body-tertiary text-nowrap', lang('Client'));

$home_services = array();
foreach (array('design', 'build', 'shop', 'brand') as $key) $home_services[] = $service_card($key, '3', false);

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('An independent web designer: websites, online shops and brand identities for small companies, with case studies, packages and a way to start a project.'),
    'tree'             => $page(array(
        // The way in: who the designer is, what they make and the two ways
        // on, beside a picture of their work.
        $el('section', 'pdp-hero position-relative py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('p', 'd-inline-flex align-items-center gap-2 small fw-semibold rounded-pill bg-success-subtle text-success-emphasis px-3 py-1 mb-4', lang('Availability'), array(
                            $icon('bi-circle-fill', 'small'),
                            $span($designer['available'], '', lang('Text')),
                        )),
                        $heading('h1', lang('Websites that look as good as your work'), 'pdp-display display-3 fw-bold mb-3'),
                        $para($designer['bio'], 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('See my work'), '{{page:work}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Start a Project'), '{{page:brief}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A website design shown on a laptop and a phone'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Clients'), array(
            $container(array(
                $para(lang('Trusted by small companies in nine countries'), 'small text-uppercase fw-semibold text-body-secondary text-center mb-3'),
                $el('div', 'd-flex flex-wrap justify-content-center column-gap-5 row-gap-2', lang('Client Names'), $client_names),
            )),
        )),

        $el('section', 'py-5', lang('Selected Work'), array(
            $container(array(
                $section_header(lang('Selected work'), lang('Recent case studies'), lang('All Work'), '{{page:work}}'),
                $widget('home_projects'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Services'), array(
            $container(array(
                $section_header(lang('What I do'), lang('Design, build and look after'), lang('All services'), '{{page:services}}'),
                $row($home_services, '4'),
            )),
        )),

        $el('section', 'py-5', lang('How I Work'), array(
            $container(array(
                $section_header(lang('How I Work'), lang('From the first call to the launch in six weeks'), lang('The whole process'), '{{page:process}}'),
                $process_cards(),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Packages'), array(
            $container(array(
                $section_header(lang('Packages'), lang('A fixed price before the work begins'), lang('Compare the packages'), '{{page:services}}#packages'),
                $package_row(),
            )),
        )),

        $quotes_section('py-5 bg-body-tertiary'),

        $el('section', 'py-5', lang('Journal'), array(
            $container(array(
                $section_header(lang('Journal'), lang('Notes on design and the web'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('brief_band'),
    )),
);

// ── Services and packages ───────────────────────────────────────────────

$service_cards = array();
foreach (array_keys($services) as $key) $service_cards[] = $service_card($key);

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Services'),
    'meta_description' => lang('Web design, website builds, online shops, brand identity and a monthly care plan, with three packages at a fixed price.'),
    'tree'             => $page(array(
        $page_header(lang('Services'), lang('Design, build and care for websites and shops, and the brand around them. Every project starts with a fixed price.')),
        $el('section', 'py-5', lang('Services'), array(
            $container(array($row($service_cards, '4'))),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Packages'), array(
            $container(array(
                $section_header(lang('Packages'), lang('Three ways to start')),
                $package_row(),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Not sure which one fits? Choose "Not sure yet" on the brief and I suggest one with the proposal.'), '', lang('Text')),
                )),
            )),
        ), $searchable('packages', lang('package, price, cost, quote, budget'))),
        $el('section', 'py-5', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('In every project'), 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What every package includes'), 'h3 fw-bold mb-3'),
                        $check_list(array(
                            lang('A fixed price and a timeline in writing before we start'),
                            lang('Design for phone, tablet and desktop'),
                            lang('Two rounds of changes at every stage'),
                            lang('An hour of training and a month of free changes after the launch'),
                            lang('The site and its content are yours, with every login handed over'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Paying'), 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('How payment works'), 'h3 fw-bold mb-3'),
                        $check_list(array(
                            lang('A third when the proposal is signed'),
                            lang('A third when you approve the design'),
                            lang('The last third at the launch'),
                            lang('The care plan is paid monthly and can be stopped at any time'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('included', lang('included, payment, deposit, invoice, ownership'))),
        $shared('brief_band'),
    )),
);

// ── How I work ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-i-work'),
    'folder'           => 'public',
    'title'            => lang('How I Work'),
    'meta_description' => lang('Discover, design, build and launch: the four steps of a website project, how long each takes and what I need from you.'),
    'tree'             => $page(array(
        $page_header(lang('How I Work'), lang('Four steps, a fixed price and one person from the first call to the launch.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array($process_cards())),
        ), $searchable('steps', lang('process, timeline, weeks, steps'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Before we start'), 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What I need from you'), 'h3 fw-bold mb-3'),
                        $check_list(array(
                            lang('A short brief: what the site is for and who visits it'),
                            lang('Your logo and colours, or the wish to have them made'),
                            lang('The words of the pages, or the facts I write them from'),
                            lang('One person who answers on your side within two working days'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('At the launch'), 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What you get'), 'h3 fw-bold mb-3'),
                        $check_list(array(
                            lang('A site that is fast, accessible and found by search engines'),
                            lang('Every page editable by you, without me'),
                            lang('A short guide to your site and an hour of training'),
                            lang('A month of free changes, then a care plan if you want one'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('needs', lang('content, logo, training, handover'))),
        $el('section', 'py-5', lang('Following a Brief'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $icon_tile('bi-chat-square-text', 'mb-3'),
                        $heading('h2', lang('Follow your project from your account'), 'h3 fw-bold mb-3'),
                        $para(lang('The brief opens an account for you. Its page shows where the project stands, and we write to each other there: every message also comes by e-mail.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), array(
                            $link(lang('Start a Project'), '{{page:brief}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('My Briefs'), '{{page:my_briefs}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '8', '', '12', 'text-center'),
                ), '4', 'center'),
            )),
        ), $searchable('follow', lang('status, account, messages, brief'))),
        $shared('brief_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$experience_items = array();
foreach ($experience as $n => $x) {
    $experience_items[] = $el('li', 'd-flex flex-column flex-sm-row gap-1 gap-sm-4 py-3' . ($n > 0 ? ' border-top' : ''), $x['title'], array(
        $span($x['years'], 'small fw-semibold text-primary text-nowrap', lang('Years')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $x['title'], 'h6 fw-bold mb-0'),
            $para($x['place'], 'small text-body-secondary mb-0'),
        )),
    ));
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('The designer behind the studio: their story, skills, tools and experience, and what clients say about working together.'),
    'tree'             => $page(array(
        $page_header(lang('About'), $designer['role'] . ' · ' . $designer['city']),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($portrait($designer['name'], 800), $designer['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '5'),
                    $col(array(
                        $para(lang('Hello, I am'), 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', $designer['name'], 'pdp-display h1 fw-bold mb-3'),
                        $para($designer['bio'], 'lead mb-3'),
                        $para(lang('I spent seven years in agencies and a travel company before opening my own studio in 2021. Working alone means you talk to the person who designs and builds your site, from the first call to the launch.'), 'text-body-secondary'),
                        $para(lang('I take on few projects at a time, so each one gets the attention it needs, and many clients stay with me for years on a care plan.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Start a Project'), '{{page:brief}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('See my work'), '{{page:work}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('story', lang('designer, story, freelance, studio'))),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Skills and Tools'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Skills'), 'h4 fw-bold mb-3'),
                        $badges($skills, lang('Skills'), 'text-bg-primary'),
                        $heading('h2', lang('Tools'), 'h4 fw-bold mt-5 mb-3'),
                        $badges($tools, lang('Tools'), 'bg-body text-body border'),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Experience'), 'h4 fw-bold mb-2'),
                        $el('ol', 'list-unstyled mb-0', lang('Experience'), $experience_items),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skills', lang('skills, tools, experience, Figma, CV'))),
        $quotes_section('py-5'),
        $shared('brief_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers to what clients ask before a website project: prices, timelines, content, changes, hosting and the care plan.'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What clients ask before we work together. Anything else? Send a brief or write to me.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How much does a website cost?'), lang('The packages start at the prices on the services page. After the brief you get a proposal with a fixed price, so the cost never grows during the work.'), lang('price, cost, quote, budget')),
                            $faq(2, lang('How long does a project take?'), lang('A landing page takes two to three weeks, a company site five to seven and a shop seven to ten. The proposal gives the dates of every step.'), lang('time, timeline, weeks, deadline')),
                            $faq(3, lang('Do you write the words of the pages?'), lang('The company site package includes copy editing: you give me the facts and I write the pages with you. Full copywriting can be added to any package.'), lang('content, copy, text, writing')),
                            $faq(4, lang('Can I change the site myself afterwards?'), lang('Yes. Every page is built so you can change the words and the pictures yourself, and the launch comes with an hour of training.'), lang('edit, update, CMS, training')),
                            $faq(5, lang('Who owns the site and the design?'), lang('You do, once the last payment is made: the design files, the site, the domain and every login are handed over to you.'), lang('ownership, rights, files, domain')),
                            $faq(6, lang('Do you take care of hosting?'), lang('I can set up hosting in your name and look after it with the care plan, or put the site on the hosting you already have.'), lang('hosting, server, domain, care plan')),
                            $faq(7, lang('Do you work with clients abroad?'), lang('Yes, clients from nine countries so far. We meet by video call and keep every message on your brief\'s page.'), lang('abroad, remote, video call, language')),
                            $faq(8, lang('How do I follow my project?'), lang('The brief opens an account for you. Your brief\'s page shows its status, and we write to each other there.'), lang('status, follow, account, message')),
                        ), array('id' => 'pd-faq')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('brief_band'),
    )),
);

return $public_pages;
