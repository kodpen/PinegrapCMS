<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Law Firm", its public pages: home, the practice areas
 * and one page for each, the lawyers, case results, fees, about and the
 * questions clients ask. Included after parts.php in the same scope (its
 * builders and the firm's data); returns the list of pages.
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

// A practice area card, linked to the area's own page.
$area_card = function ($key) use ($el, $heading, $para, $span, $icon, $icon_tile, $col, $practice_areas) {
    $a = $practice_areas[$key];
    return $col(array(
        $el('a', 'plw-lift card h-100 border-0 bg-body-tertiary rounded-3 text-decoration-none link-body-emphasis', $a['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($a['icon'], 'mb-3'),
                $heading('h3', $a['title'], 'h5 fw-semibold mb-2'),
                $para($a['text'], 'small text-body-secondary mb-3'),
                $el('span', 'd-inline-flex align-items-center gap-1 fw-semibold small text-primary', lang('Link Text'), array(
                    $span(lang('Read more'), '', lang('Text')),
                    $icon('bi-arrow-right'),
                )),
            )),
        ), array('href' => '{{page:area_' . $key . '}}')),
    ), '6', '4');
};

// The firm's figures in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'display-6 fw-semibold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A case result: its area, what was done and how it ended.
$result_card = function ($r, $md = '6', $lg = '') use ($el, $heading, $para, $span, $icon, $col, $practice_areas) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', $r['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $span($practice_areas[$r['area']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-3', lang('Badge')),
                $heading('h3', $r['title'], 'h5 fw-semibold mb-2'),
                $para($r['text'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-start gap-2 small fw-semibold mb-0', lang('Outcome'), array(
                    $icon('bi-check2-circle', 'text-primary'),
                    $span($r['outcome'], '', lang('Text')),
                )),
            )),
        )),
    ), $md, $lg);
};

// The line every page with results carries: a result is no promise.
$results_note = function () use ($el, $icon, $span) {
    return $el('div', 'alert alert-light border d-flex gap-2 small mt-4 mb-0', lang('Note'), array(
        $icon('bi-info-circle', 'mt-1'),
        $span(lang('Every matter is different; past results do not promise the outcome of another case.'), '', lang('Text')),
    ));
};

// What clients say.
$testimonial = function ($quote, $who, $what) use ($el, $para, $stars, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-3 bg-body mb-0', lang('Testimonial'), array(
            $stars(),
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// How a matter goes, from the first message to the end.
$process_steps = function () use ($el, $step) {
    return $el('ol', 'list-unstyled mb-0', lang('List'), array(
        $step(1, lang('Tell us about it'), lang('A few lines on the form: what happened, who the other side is and any deadline.')),
        $step(2, lang('First consultation'), lang('Thirty minutes by video, by phone or at the office, free of charge: your options and what they would cost.')),
        $step(3, lang('A written offer'), lang('The work, the lawyer who does it and the fee, in writing before anything starts.')),
        $step(4, lang('We keep you informed'), lang('Every step and every document under your consultation\'s page, and a lawyer who answers.')),
    ));
};

// ── Home ────────────────────────────────────────────────────────────────

$area_cards = array();
foreach (array_keys($practice_areas) as $key) $area_cards[] = $area_card($key);
$home_lawyers = array();
foreach (array_slice(array_keys($lawyers), 0, 3) as $key) $home_lawyers[] = $lawyer_card($key);
$home_results = array();
foreach (array_slice($results, 0, 3) as $r) $home_results[] = $result_card($r, '', '4');

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A law firm for businesses and families: practice areas, the lawyers, case results, clear fees and a free first consultation.'),
    'tree'             => $page(array(
        // The way in: the words and buttons on the left, the office with
        // the hours on the right.
        $el('section', 'plw-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Attorneys at law · Istanbul'), 'plw-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Clear advice when it matters most'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Companies, employers, property owners and families come to us for advice they can act on, a fee agreed in advance and a lawyer who answers.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Practice Areas'), '{{page:practice}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                                $image($photo('hero', 1200, 900), lang('A meeting room with a long table and shelves of law books'), 'object-fit-cover'),
                            )),
                            $el('div', 'card border-0 shadow position-absolute bottom-0 start-0 m-3', lang('Hours Card'), array(
                                $el('div', 'card-body py-3 px-4', lang('Card Body'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-clock', 'text-primary'),
                                        $span(lang('Office hours'), '', lang('Text')),
                                    )),
                                    $hours(),
                                )),
                            )),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Practice Areas'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Practice areas'), lang('All practice areas'), '{{page:practice}}'),
                $row($area_cards, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Working with us'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From the first message to the last document'), 'h3 fw-semibold mb-3'),
                        $para(lang('No account needed to begin: the form opens one for you, so you can follow your matter and write to your lawyer afterwards.'), 'text-body-secondary mb-4'),
                        $link(lang('How our fees work'), '{{page:fees}}', 'btn btn-outline-primary px-4'),
                    ), '', '5'),
                    $col(array($process_steps()), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-5', lang('Our Lawyers'), array(
            $container(array(
                $section_header(lang('The team'), lang('Your lawyers'), lang('All lawyers'), '{{page:lawyers}}'),
                $row($home_lawyers, '4'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Case Results'), array(
            $container(array(
                $section_header(lang('Track record'), lang('Recent results'), lang('All results'), '{{page:results}}'),
                $row($home_results, '4'),
                $results_note(),
            )),
        )),

        // What clients say: sample words for the firm to replace, and beside
        // them the firm's own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $row(array(
                    $testimonial(lang('“They explained the shareholder agreement line by line and the fee never changed from the first quote.”'), lang('Can D.'), lang('Corporate & Commercial')),
                    $testimonial(lang('“A hard divorce handled calmly. Ayşe answered every message the same day.”'), lang('Selin K.'), lang('Family & Inheritance')),
                    $testimonial(lang('“Murat settled our dispute with a former manager at mediation, without a day in court.”'), lang('Deniz Lojistik'), lang('Employment')),
                ), '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Insights'), array(
            $container(array(
                $section_header(lang('Legal updates'), lang('Insights'), lang('All insights'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('consult_band'),
    )),
);

// ── Practice Areas ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'practice',
    'name'             => lang('practice-areas'),
    'folder'           => 'public',
    'title'            => lang('Practice Areas'),
    'meta_description' => lang('Corporate, employment, real estate, family, litigation and intellectual property: what the firm does in each area.'),
    'tree'             => $page(array(
        $page_header(lang('Practice Areas'), lang('Six areas, each led by a partner or senior lawyer. Not sure where your matter belongs? Ask us; we tell you within a day.')),
        $el('section', 'py-5', lang('Practice Areas'), array(
            $container(array($row($area_cards, '4'))),
        )),
        $shared('consult_band'),
    )),
);

// ── One page for each practice area ─────────────────────────────────────
// What the area covers, the lawyers who work in it, its results, and the
// way to ask with the area already chosen on the form. The page names
// (addresses) follow the site's language.
$area_slugs = array(
    'corporate'  => lang('corporate-commercial-law'),
    'employment' => lang('employment-law'),
    'realestate' => lang('real-estate-law'),
    'family'     => lang('family-inheritance-law'),
    'disputes'   => lang('litigation-disputes'),
    'ip'         => lang('intellectual-property-technology'),
);
foreach ($practice_areas as $key => $a) {
    $area_lawyers = array();
    foreach ($lawyers as $lkey => $l) {
        if (in_array($key, $l['areas'], true)) $area_lawyers[] = $lawyer_card($lkey);
    }
    $area_results = array();
    foreach ($results as $r) {
        if ($r['area'] === $key) $area_results[] = $result_card($r);
    }
    $lead = $lawyers[$a['lead']];
    $ask = '{{page:consultation}}?' . $consultation_query(array('area' => $a['title']));

    $sections = array(
        $page_header($a['title'], $a['text'], array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Ask about this area'), $ask, 'btn btn-primary px-4'),
                $link(lang('Fees'), '{{page:fees}}', 'btn btn-outline-secondary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('What We Cover'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('What we cover'), 'plw-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Matters we handle'), 'h3 fw-semibold mb-3'),
                        $check_list($a['matters']),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary rounded-3', lang('Lead Lawyer'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $para(lang('Leads this area'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
                                $heading('h3', $lead['name'], 'h5 fw-semibold mb-1'),
                                $para($lead['role'], 'small text-primary mb-3'),
                                $para($lead['bio'], 'small mb-3'),
                                $link(lang('Ask for a consultation'), '{{page:consultation}}?' . $consultation_query(array('area' => $a['title'], 'lawyer' => $lead['name'] . ' · ' . $lead['role'])), 'btn btn-sm btn-outline-primary'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('matters', $a['topics'])),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Lawyers'), array(
            $container(array(
                $section_header(lang('The team'), lang('Lawyers in this area')),
                $row($area_lawyers, '4'),
            )),
        )),
    );
    if ($area_results) {
        $sections[] = $el('section', 'py-5', lang('Case Results'), array(
            $container(array(
                $section_header(lang('Track record'), lang('Results in this area'), lang('All results'), '{{page:results}}'),
                $row($area_results, '4'),
                $results_note(),
            )),
        ));
    }
    $sections[] = $shared('consult_band');

    $public_pages[] = array(
        'key'              => 'area_' . $key,
        'name'             => $area_slugs[$key],
        'folder'           => 'public',
        'title'            => $a['title'],
        'meta_description' => $a['text'],
        'tree'             => $page($sections),
    );
}

// ── Lawyers ─────────────────────────────────────────────────────────────
// A profile for each: areas, education, admission, languages and the way
// to ask for them by name.
$lawyer_profiles = array();
foreach ($lawyers as $key => $l) {
    $tags = array();
    foreach ($l['areas'] as $a) $tags[] = $span($practice_areas[$a]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Badge'));
    $facts = array();
    foreach (array(
        array('bi-mortarboard', lang('Education'), $l['education']),
        array('bi-bank', lang('Admitted'), $l['bar']),
        array('bi-translate', lang('Languages'), $l['languages']),
    ) as $f) {
        $facts[] = $el('dt', 'col-4 fw-normal text-body-secondary', $f[1], array($icon($f[0], 'me-1'), $span($f[1], '', lang('Text'))));
        $facts[] = $el('dd', 'col-8 mb-2', lang('Value'), array(), array('text' => $f[2]));
    }
    $lawyer_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden', $l['name'], array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-sm-4', lang('Portrait'), array(
                    $el('div', 'ratio ratio-1x1 h-100 bg-primary-subtle', lang('Picture Frame'), array(
                        $image($portrait($l['name']), $l['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                )),
                $el('div', 'col-sm-8', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-4', lang('Card Body'), array(
                        $el('div', 'd-flex flex-wrap gap-1 mb-2', lang('Badges'), $tags),
                        $heading('h2', $l['name'], 'h5 fw-semibold mb-1'),
                        $para($l['role'], 'small text-primary mb-2'),
                        $para($l['bio'], 'small text-body-secondary mb-3'),
                        $el('dl', 'row small mb-3', lang('List'), $facts),
                        $el('a', 'btn btn-sm btn-outline-primary align-self-start mt-auto', lang('Link'), array(
                            $span(lang('Ask for a consultation'), '', lang('Link Text')),
                            $span($l['name'], 'visually-hidden', lang('Name')),
                        ), array('href' => '{{page:consultation}}?' . $consultation_query(array('lawyer' => $l['name'] . ' · ' . $l['role'])))),
                    )),
                )),
            )),
        ), $searchable('lawyer-' . $key, $l['role'])),
    ), '', '6');
}

$public_pages[] = array(
    'key'              => 'lawyers',
    'name'             => lang('lawyers'),
    'folder'           => 'public',
    'title'            => lang('Our Lawyers'),
    'meta_description' => lang('The partners and associates of the firm: their practice areas, education, bar admission and working languages.'),
    'tree'             => $page(array(
        $page_header(lang('Our Lawyers'), lang('Every matter has one lawyer you can reach, with a partner who reviews the work.')),
        $el('section', 'py-5', lang('Lawyers'), array(
            $container(array(
                $row($lawyer_profiles, '4'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('No preference? Choose "No preference" on the form and the lawyer of the practice area takes your matter.'), '', lang('Text')),
                )),
            )),
        )),
        $shared('consult_band'),
    )),
);

// ── Case Results ────────────────────────────────────────────────────────
$all_results = array();
foreach ($results as $r) $all_results[] = $result_card($r);

$public_pages[] = array(
    'key'              => 'results',
    'name'             => lang('case-results'),
    'folder'           => 'public',
    'title'            => lang('Case Results'),
    'meta_description' => lang('A selection of the firm\'s recent matters: what was at stake, what we did and how it ended.'),
    'tree'             => $page(array(
        $page_header(lang('Case Results'), lang('A selection of recent matters, told with the clients\' permission and without their names.')),
        $el('section', 'py-5', lang('Case Results'), array(
            $container(array(
                $row($all_results, '4'),
                $results_note(),
            )),
        ), $searchable('results', lang('results, cases, track record, references'))),
        $el('section', 'py-4 border-top', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $shared('consult_band'),
    )),
);

// ── Fees ────────────────────────────────────────────────────────────────
$fee_cards = array();
foreach ($fee_models as $n => $f) {
    $fee_cards[] = $col(array(
        $el('div', 'card h-100 border-0 shadow-sm rounded-3', $f['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($f['icon'], 'mb-3 align-self-start'),
                $heading('h3', $f['title'], 'h5 fw-semibold mb-1'),
                $para($f['price'], 'fw-semibold text-primary mb-2'),
                $para($f['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
}

$public_pages[] = array(
    'key'              => 'fees',
    'name'             => lang('fees'),
    'folder'           => 'public',
    'title'            => lang('Fees'),
    'meta_description' => lang('How the firm charges: a free first consultation, fixed fees, hourly rates and monthly retainers, always agreed in writing.'),
    'tree'             => $page(array(
        $page_header(lang('Fees'), lang('You know the fee before the work begins: a written offer after the first consultation, and no surprises on the invoice.')),
        $el('section', 'py-5', lang('Fee Models'), array(
            $container(array($row($fee_cards, '4'))),
        ), $searchable('fee-models', lang('price, cost, fee, hourly rate, retainer, fixed fee'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What Is Included'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What every fee includes'), 'h3 fw-semibold mb-3'),
                        $check_list(array(
                            lang('A written engagement letter naming the work, the lawyer and the fee'),
                            lang('Copies of every document under your consultation\'s page'),
                            lang('Answers to your messages within one working day'),
                            lang('An itemised invoice; court fees and notary costs shown apart'),
                        )),
                    ), '', '7'),
                    $col(array($process_steps()), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('consult_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$value = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'h-100', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-semibold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('How the firm began, how we work and what clients can expect from us.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A firm founded on a simple promise: plain words, agreed fees and a lawyer who answers.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('The firm\'s lawyers around a meeting table'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Twenty years, one way of working'), 'h3 fw-semibold mb-3'),
                        $para(lang('Ayşe Yılmaz opened the office in 2006 to give families and small companies the care large firms keep for their biggest clients. Today five lawyers share the work, and many of the first clients still call.'), 'text-body-secondary'),
                        $para(lang('Each matter has one lawyer who knows it, and a partner who reads every document before it leaves the office.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, founder, history'))),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('Our principles')),
                $row(array(
                    $value('bi-chat-left-text', lang('Plain words'), lang('Advice you can act on, without jargon, in Turkish or in English.')),
                    $value('bi-tag', lang('Fees agreed first'), lang('A written offer before the work begins, and no change without your consent.')),
                    $value('bi-lock', lang('Confidentiality'), lang('What you tell us stays with us; every file is kept under professional secrecy.')),
                    $value('bi-shield-check', lang('No conflicts'), lang('We check every new matter against our clients before we take it on.')),
                ), '4'),
            )),
        ), $searchable('values', lang('confidentiality, secrecy, conflict of interest, ethics'))),
        $shared('consult_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers to what clients ask before they come to us: the first consultation, fees, documents and confidentiality.'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What clients ask before they come to us. Anything else? Write to us and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Is the first consultation really free?'), lang('Yes. The first 30 minutes are free and without obligation; we tell you whether we can help and what it would cost.'), lang('free, first meeting, consultation, cost')),
                            $faq(2, lang('What should I bring to the first consultation?'), lang('Any contract, letter or court paper about the matter, and the names of the other parties. You can attach them to the form.'), lang('documents, papers, bring, prepare')),
                            $faq(3, lang('Why do you ask for the other party\'s name?'), lang('To check that we do not already act for them. We cannot advise both sides of the same matter.'), lang('conflict of interest, other side, opposing party')),
                            $faq(4, lang('Is what I write on the form confidential?'), lang('Yes. Only the firm\'s lawyers and staff read it, and it is kept under professional secrecy whether or not we take the matter.'), lang('confidential, privacy, secrecy')),
                            $faq(5, lang('How do I follow my matter?'), lang('The form opens an account for you. Your consultation\'s page shows its status, and you can write to your lawyer there.'), lang('status, follow, account, message')),
                            $faq(6, lang('Do you work with clients outside Istanbul?'), lang('Yes. Most consultations are by video, and we work with correspondent lawyers in other cities for hearings.'), lang('online, video, another city, remote')),
                        ), array('id' => 'lw-faq')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('consult_band'),
    )),
);

return $public_pages;
