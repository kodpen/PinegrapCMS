<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", its catalogue: the staff page that
 * adds a title (the Catalogue form, a collection, with the sample titles
 * written in when it is made), the catalogue with its format and audience
 * filters and a search, the title's own page and the new arrivals the
 * home page places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A title is a record of the Catalogue form. Its format, audience, branch
 * and availability are pick lists whose answers are the words parts.php
 * gives, so a link with ?format= or ?audience= (the footer, the home, the
 * children's page, the filter rows) filters the list by the words a
 * visitor sees. The catalogue is a showcase the staff keep by hand, never
 * a link to a library system; availability is the staff's word for where
 * a title is. New arrival is the staff's choice (office use only): the
 * home page shows the new arrivals. The newest title added comes first.
 * Under the list the Library: Shelf Mark Guide reads a sample title's
 * spine label, so a visitor finds the title on the shelf.
 *
 * The pieces defined here ($lb_filter, $lb_page_link, $lb_yes_no and
 * $lb_filter_bar) serve the later lists too (events.php); the template
 * file includes this part before them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$catalogue_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$lb_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of a Yes / No list (New Arrival, Featured): the first
// marks a record for the home.
$lb_yes_no = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$lb_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// The links above a list that narrow it: one per answer, each opening
// the list ($page_key) with the filter set.
$lb_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-3', lang('Filters'), $children, array(
        '_attrs' => array(array('name' => 'aria-label', 'value' => $label)),
    ));
};

// A list of titles: the Catalogue form, the title page, the newest first,
// and the words shown when nothing matches.
$lb_item_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:item_new}}',
        'detail_page_id'      => '{{tab:item}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The title cards of a list: six in a row on a wide screen, two on a phone.
$item_grid = function () use ($widget_root, $row, $loop, $item_card) {
    return $widget_root(array($row(array($loop(array($item_card()))), '4')));
};

// Finding a title on the shelf, the Library: Shelf Mark Guide block: the
// spine of a sample title (A Year in the Kitchen Garden, 635 REE) drawn
// large and scaled in on view, a drawing screen readers skip; its label
// read part by part, the fiction mark and the shelves that stand apart
// named by the sample titles' own marks; beside it the ten subject
// classes rising in turn, the example's marked in words as well as colour.
$shelfmark_band = function () use ($el, $heading, $para, $link, $span, $container, $row, $col, $fx, $attr, $searchable, $catalogue_items) {
    $sample = $catalogue_items['garden'];
    $mark = explode(' ', $sample['shelf']);
    $prefixes = array();
    foreach (array('stars', 'river', 'quiet') as $key) {
        $words = explode(' ', $catalogue_items[$key]['shelf']);
        $prefixes[] = $words[0];
    }
    $part = function ($code, $text) use ($el, $span) {
        return $el('div', 'd-flex gap-3 mb-3', lang('Label Part'), array(
            $el('dt', 'pg-library-figure font-monospace fw-bold text-primary flex-shrink-0', lang('Term'), array($span($code, '', lang('Text')))),
            $el('dd', 'mb-0 text-body-secondary', lang('Description'), array($span($text, '', lang('Text')))),
        ));
    };
    $subjects = array();
    foreach (array(
        array('000', lang('General knowledge and computers')),
        array('100', lang('Philosophy and psychology')),
        array('200', lang('Religion')),
        array('300', lang('Society, law and education')),
        array('400', lang('Languages')),
        array('500', lang('Science and nature')),
        array('600', lang('Technology, health and cooking')),
        array('700', lang('Arts, music and sport')),
        array('800', lang('Literature and poetry')),
        array('900', lang('History, geography and travel')),
    ) as $c) {
        $example = $c[0][0] === $mark[0][0];
        $kids = array(
            $span($c[0], 'pg-library-figure font-monospace fw-bold', lang('Number')),
            $span($c[1], '', lang('Text')),
        );
        if ($example) $kids[] = $span(lang('Example'), 'badge rounded-pill text-bg-primary ms-auto', lang('Badge'));
        $subjects[] = $el('li', 'col', $c[0], array(
            $el('div', 'd-flex align-items-center gap-3 h-100 px-3 py-2 rounded-3 ' . ($example ? 'bg-primary-subtle text-primary-emphasis' : 'bg-body-tertiary'), lang('Subject'), $kids),
        ));
    }
    $spine = $fx($el('div', 'pg-library-label px-4 pt-5 pb-4 rounded-top-4 text-bg-primary shadow', lang('Spine'), array(
        $span($sample['title'], 'd-block text-center small fw-semibold text-uppercase mb-5', lang('Title')),
        $el('div', 'px-3 py-3 rounded-3 bg-body text-body-emphasis text-center font-monospace', lang('Spine Label'), array(
            $span($mark[0], 'pg-library-figure d-block fs-2 fw-bold lh-1 mb-2', lang('Subject')),
            $span($mark[1], 'd-block fs-3 fw-bold lh-1', lang('Author')),
        )),
    ), array('_attrs' => array($attr('aria-hidden', 'true')))), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 800));
    return $el('section', 'py-5 border-top', lang('Library Shelf Mark Guide'), array(
        $container(array(
            $fx($el('div', 'row g-4 align-items-end mb-5', lang('Section Header'), array(
                $el('div', 'col-lg-7', lang('Section Title'), array(
                    $para(lang('Finding a book'), 'plb-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('Every title has its own place on the shelf'), 'plb-display display-6 mb-0'),
                )),
                $el('div', 'col-lg-5', lang('Lead'), array(
                    $para(lang('The label on the spine is the address of a title: the shelf it stands on, then the first letters of its author.'), 'lead text-body-secondary mb-0'),
                )),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
            $row(array(
                $col(array(
                    $spine,
                    $heading('h3', lang('How to read the label'), 'plb-display h5 mt-4 mb-3'),
                    $el('dl', 'mb-0', lang('Label Parts'), array(
                        $part($mark[0], lang('The subject: 600 is technology, 630 farming and 635 gardening.')),
                        $part($mark[1], sprintf(lang('The author: the first three letters of the surname, as for %s.'), $sample['author'])),
                        $part('F', sprintf(lang('Fiction stands apart, from A to Z by the author\'s surname: %s.'), $catalogue_items['harbour']['shelf'])),
                        $part(implode(' ', $prefixes), lang('Picture books, large print and films stand on shelves of their own, marked in front.')),
                    )),
                ), '', '5', '', '12', 'col-xl-4'),
                $col(array(
                    $heading('h3', lang('The ten subjects'), 'plb-display h5 mb-2'),
                    $para(lang('Non-fiction runs along the shelves from 000 to 999.'), 'text-body-secondary mb-4'),
                    $fx($el('ol', 'list-unstyled row row-cols-1 row-cols-sm-2 g-2 mb-4', lang('Subjects'), $subjects),
                        array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 500, 'stagger' => 60, 'distance' => 20)),
                    $link(lang('Ask a Librarian'), '{{page:contact}}', 'btn btn-outline-primary'),
                ), '', '7', '', '12', 'offset-xl-1'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable('lib-shelf-marks', lang('shelf mark, call number, classification, where is the book, find a book, non-fiction')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every title, narrowed by format and audience from the address.
$catalogue_pages[] = array(
    'key'              => 'catalogue',
    'name'             => lang('catalogue'),
    'folder'           => 'public',
    'title'            => lang('Catalogue'),
    'meta_description' => lang('Books, large print, eBooks, audiobooks, films and magazines at our libraries: search the catalogue and reserve a title.'),
    'tree'             => $page(array(
        $page_header(lang('Catalogue'), lang('A choice of what is on our shelves and online. Narrow it by format or reader, or search for a title or an author.')),
        $el('section', 'py-5', lang('Catalogue'), array(
            $container(array(
                $lb_filter_bar('catalogue', lang('Format'), 'format', $format_titles),
                $lb_filter_bar('catalogue', lang('Who it is for'), 'audience', $audience_titles),
                $el('div', 'mt-4', lang('List'), array($widget('catalogue_list'))),
            )),
        ), $searchable('lib-catalogue', lang('books, search, find a book, author, title, novels, dvd, ebooks'))),
        $shelfmark_band(),
    )),
);

// One title: its record.
$catalogue_pages[] = array(
    'key'              => 'item',
    'name'             => lang('catalogue-title'),
    'folder'           => 'public',
    'title'            => lang('Catalogue Title'),
    'meta_description' => lang('A title in our catalogue: its author, format and shelf, which branch holds it and how to reserve it.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('item_detail'),
    )),
);

// The staff page that adds a title. The form is the Catalogue collection;
// the sample titles are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_items = array();
foreach ($catalogue_items as $key => $t) {
    $sample_items[] = array(
        'title'        => $t['title'],
        'author'       => $t['author'],
        'format'       => $item_format($key),
        'audience'     => $item_audience($key),
        'genre'        => $t['genre'],
        'shelf'        => $t['shelf'],
        'branch'       => $item_branch($key),
        'availability' => $item_availability($key),
        'summary'      => $t['summary'],
        'photo_1'      => $photo('cover-' . $key, 600, 900),
        'new_arrival'  => $t['new_arrival'] ? $lb_yes_no[0] : $lb_yes_no[1],
    );
}
$catalogue_pages[] = array(
    'key'     => 'item_new',
    'name'    => lang('new-title'),
    'folder'  => 'private',
    'title'   => lang('New Title'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Catalogue'),
        'collection'           => 1,
        'confirmation_message' => lang('The title is in the catalogue.'),
        'confirmation_page_id' => '{{tab:catalogue}}',
        'sample_records'       => $sample_items,
        // The cover is the title's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Title'), lang('Add a title with its cover; it appears in the catalogue as soon as you send it. Change its availability or mark it a new arrival from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded border', lang('Form Card'), array($widget('item_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$catalogue_widgets = array(

    // The staff's Catalogue form: one title. The format starts as a book,
    // the audience as adults, the branch as the central library, the
    // availability as on the shelf and new arrival as Yes.
    'item_form' => array(
        'page'   => 'item_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The title')),
            $field('lib-item-title', lang('Title'), pg_cf_with_role($input('lib-item-title', 'text', 'title', 'off'), 'title')),
            $field('lib-item-author', lang('Author'), $input('lib-item-author', 'text', 'author', 'off'), 'mb-3',
                lang('The author, the editor or the director of a film.')),
            $row(array(
                $col(array($field('lib-item-format', lang('Format'), pg_cf_with_role($select('lib-item-format', 'format', '', $format_titles, true, $format_titles[0]), 'category'))), '6'),
                $col(array($field('lib-item-audience', lang('Who it is for'), $select('lib-item-audience', 'audience', '', $audience_titles, true, $audience_titles[0]))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('lib-item-genre', lang('Genre'), $input('lib-item-genre', 'text', 'genre', 'off'), 'mb-3',
                    sprintf(lang('For example %s.'), $catalogue_items['harbour']['genre']))), '6'),
                $col(array($field('lib-item-shelf', lang('Shelf Mark'), $input('lib-item-shelf', 'text', 'shelf', 'off', false), 'mb-3',
                    lang('Where it stands on the shelf, as on its spine label.'))), '6'),
            ), '3'),
            $form_step(2, lang('Where it is')),
            $row(array(
                $col(array($field('lib-item-branch', lang('Branch'), $select('lib-item-branch', 'branch', '', $branch_titles, true, $branch_titles[0]))), '6'),
                $col(array($field('lib-item-availability', lang('Status on the Shelf'), $select('lib-item-availability', 'availability', '', $availability_choices, true, $availability_choices[0]))), '6'),
            ), '3'),
            $form_step(3, lang('Words and cover')),
            $field('lib-item-summary', lang('Summary'), pg_cf_with_role($textarea('lib-item-summary', 'summary', 3), 'description'), 'mb-3',
                lang('Two or three lines that make a reader want it.')),
            $field('lib-item-cover', lang('Cover'), pg_cf_with_role($file('lib-item-cover', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A picture of the front cover, taller than wide.')),
            $field('lib-item-new', lang('New Arrival'), $select('lib-item-new', 'new_arrival', '', $lb_yes_no, false, $lb_yes_no[0], array('office_use_only' => true)),
                'mb-3', lang('A new arrival is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every title, filtered by the address (format, audience), the newest
    // first, with a search over its words.
    'catalogue_list' => array(
        'page'   => 'catalogue',
        'slug'   => lang('form-list'),
        'config' => $lb_item_list(lang('No title matches yet. Choose another format or reader, or see all titles.'), array(
            'items_per_page' => 24,
            'filters'        => array(
                $lb_filter('format', 'is equal to', '', 'format'),
                $lb_filter('audience', 'is equal to', '', 'audience'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'author', 'format', 'audience', 'genre', 'shelf', 'branch', 'summary'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the catalogue: a title, an author or a subject...'),
        )),
        'tree'   => $item_grid(),
    ),

    // The home page's new arrivals, six at most.
    'home_arrivals' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $lb_item_list(lang('No new arrivals yet.'), array(
            'items_per_page' => 6,
            'max_results'    => 6,
            'filters'        => array($lb_filter('new_arrival', 'is equal to', $lb_yes_no[0])),
        )),
        'tree'   => $item_grid(),
    ),
);

// One title: the cover beside the format, the title, the author, the
// summary and the facts, its availability and the way to reserve it with
// the title chosen (?title=) or to see more of the same format.
$item_sample_key = 'harbour';
$item_sample = $catalogue_items[$item_sample_key];
$item_not_found = lang('This title could not be found. It may have been taken out of the catalogue.');
$catalogue_widgets['item_detail'] = array(
    'page'   => 'item',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:item_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $item_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-book', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Title not found'), 'h3 fw-semibold mt-3'),
                $para($item_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Titles'), '{{page:catalogue}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Title'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Catalogue'), '', lang('Link Text')),
                    ), array('href' => '{{page:catalogue}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio plb-cover rounded shadow-sm overflow-hidden bg-body-tertiary',
                            $photo('cover-' . $item_sample_key, 600, 900), lang('Cover')),
                    ), '4', '3', '', '8', 'mx-auto mx-md-0'),
                    $col(array(
                        $span($item_format($item_sample_key), 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Format'), $bind('format')),
                        $heading('h1', $item_sample['title'], 'plb-display display-6 mb-1', $bind('title')),
                        $para($item_sample['author'], 'fs-5 text-body-secondary mb-3', $bind('author')),
                        $para($item_sample['summary'], 'lead mb-4', $bind('summary')),
                        $item_facts($item_sample_key, 'list-unstyled small mb-0'),
                    ), '8', '5'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Reserve Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $para(lang('Where it is now'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                                $el('p', 'fs-5 fw-semibold mb-3', lang('Availability'), array(), array_merge(array('text' => $item_availability($item_sample_key)), $bind('availability'))),
                                $para(lang('Reserve it and collect it at the branch you choose. We tell you in your account when it is ready.'), 'small text-body-secondary mb-3'),
                                // Opens the reservation with this title
                                // chosen (?title=).
                                $link(lang('Reserve This Title'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $lb_page_link('reserve', array('title' => 'title'))),
                                // Opens the catalogue on this title's
                                // format (?format=).
                                $link(lang('More in this format'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $lb_page_link('catalogue', array('format' => 'format'))),
                            )),
                        )),
                    ), '12', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $catalogue_pages, 'widgets' => $catalogue_widgets);
