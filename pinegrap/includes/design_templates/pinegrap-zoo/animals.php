<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo", its animals: the staff page that adds an animal
 * (the Animals form, a collection, with the sample animals written in when
 * it is made), the animals page with the habitats and the animal classes
 * that narrow its list (?habitat=, ?class=), the animal's page, and the
 * featured animals the home page places. Included after parts.php and
 * cards.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * An animal's habitat, class and conservation level are pick lists whose
 * answers are their own words, so the links of the habitat cards and the
 * class pills filter the list in the words the record keeps.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$animal_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list: a fixed value, or the value of the address
// parameter $param when one is named.
$zan_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list of the Animals collection: the animal's page opens a record, the
// list runs by name.
$zan_record_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:animal_new}}',
        'detail_page_id'      => '{{tab:animal}}',
        'order_by_field'      => 'title',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};

// The animal cards of a list, in rows of three.
$animal_grid = function () use ($widget_root, $row, $loop, $animal_card) {
    return $widget_root(array($row(array($loop(array($animal_card()))), '4')));
};

$zan_featured_choices = array(lang('Yes'), lang('No'));

// ── Pages ───────────────────────────────────────────────────────────────

// The animals: the habitats first (each opens the list on it), then every
// animal narrowed by habitat and class from the address.
$zan_habitat_cards = array();
foreach (array_keys($habitats) as $zan_key) $zan_habitat_cards[] = $habitat_card($zan_key);
$animal_pages[] = array(
    'key'              => 'animals',
    'name'             => lang('animals'),
    'folder'           => 'public',
    'title'            => lang('Animals'),
    'meta_description' => lang('Every animal of the zoo and the aquarium by habitat and class, with its conservation status and where to find it in the park.'),
    'tree'             => $page(array(
        $page_header(lang('Animals'), lang('Six habitats and an aquarium. Choose a habitat or a class of animal to narrow the list.')),
        $el('section', 'py-5', lang('Habitats'), array(
            $container(array(
                $section_header(lang('By habitat'), lang('Choose a habitat to see who lives there.'), lang('Park Map'), '{{page:map}}'),
                $rise($row($zan_habitat_cards, '4')),
            )),
        ), $searchable('zoo-animal-habitats', lang('habitat, zone, savanna, rainforest, polar, aquarium, australia, farm'))),
        $el('section', 'py-5 bg-body-tertiary', lang('All Animals'), array(
            $container(array(
                $section_header(lang('All animals'), lang('Choose a class of animal, or search by name.')),
                $el('nav', 'mb-4', lang('Animal Classes'), array($class_pills()), array('_attrs' => array($attr('aria-label', lang('Animal class'))))),
                $widget('animals_list'),
            )),
        ), $searchable('zoo-animal-list', lang('mammal, bird, reptile, amphibian, fish, invertebrate, endangered, species'))),
    )),
);

// One animal.
$animal_pages[] = array(
    'key'              => 'animal',
    'name'             => lang('animal'),
    'folder'           => 'public',
    'title'            => lang('Animal'),
    'meta_description' => lang('An animal of the zoo: its species, habitat, diet, lifespan, conservation status and what its keepers want you to know.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('animal_detail'),
    )),
);

// The staff page that adds an animal. The form is the Animals collection;
// the sample animals are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_animals = array();
foreach ($animals as $zan_key => $zan_animal) {
    $sample_animals[] = array(
        'title'               => $zan_animal['title'],
        'species'             => $zan_animal['species'],
        'habitat'             => $animal_habitat($zan_key),
        'habitat_number'      => $zan_animal['number'],
        'animal_class'        => $animal_class($zan_key),
        'conservation_status' => $animal_status($zan_key),
        'diet'                => $zan_animal['diet'],
        'lifespan'            => $zan_animal['lifespan'],
        'summary'             => $zan_animal['summary'],
        'details'             => $details_text($zan_animal['details']),
        'photo_1'             => $photo('animal-' . $zan_key, 800, 600),
        'featured'            => $zan_animal['featured'] ? $zan_featured_choices[0] : $zan_featured_choices[1],
    );
}
$animal_pages[] = array(
    'key'     => 'animal_new',
    'name'    => lang('new-animal'),
    'folder'  => 'private',
    'title'   => lang('New Animal'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Animals'),
        'collection'           => 1,
        'confirmation_message' => lang('The animal is published.'),
        'confirmation_page_id' => '{{tab:animals}}',
        'sample_records'       => $sample_animals,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Animal'), lang('Add an animal with its habitat, class, conservation status and photo; it appears in the list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4 p-4', lang('Form Card'), array($widget('animal_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$animal_widgets = array(

    // The staff's Animals form: one animal, where it lives, its facts, its
    // photo and whether the home shows it.
    'animal_form' => array(
        'page'   => 'animal_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The animal')),
            $row(array(
                $col(array($field('zoo-animal-title', lang('Common name'), pg_cf_with_role($input('zoo-animal-title', 'text', 'title', 'off'), 'title'))), '6'),
                $col(array($field('zoo-animal-species', lang('Species'), $input('zoo-animal-species', 'text', 'species', 'off'), 'mb-3',
                    sprintf(lang('The Latin name, for example %s.'), $animals['giraffe']['species']))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('zoo-animal-class', lang('Animal class'), pg_cf_with_role($select('zoo-animal-class', 'animal_class', lang('Choose a class'), $class_titles), 'category'))), '6'),
                $col(array($field('zoo-animal-status', lang('Conservation status'), $select('zoo-animal-status', 'conservation_status', lang('Choose a level'), $status_titles), 'mb-3',
                    lang('The level of the red list of threatened species.'))), '6'),
            ), '3'),
            $field('zoo-animal-summary', lang('Summary'), pg_cf_with_role($input('zoo-animal-summary', 'text', 'summary', 'off'), 'description'), 'mb-3',
                lang('One sentence for the cards of the lists.')),
            $field('zoo-animal-details', lang('Details'), $textarea('zoo-animal-details', 'details', 4), 'mb-3',
                lang('One line per point: feeding times, its story, what visitors should know.')),
            $form_step(2, lang('Where it lives')),
            $row(array(
                $col(array($field('zoo-animal-habitat', lang('Habitat'), $select('zoo-animal-habitat', 'habitat', lang('Choose a habitat'), $habitat_titles))), '8'),
                $col(array($field('zoo-animal-number', lang('Habitat number'), $input('zoo-animal-number', 'text', 'habitat_number', 'off'), 'mb-3',
                    lang('Its number on the park map.'))), '4'),
            ), '3'),
            $row(array(
                $col(array($field('zoo-animal-diet', lang('Diet'), $input('zoo-animal-diet', 'text', 'diet', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $animals['giraffe']['diet']))), '6'),
                $col(array($field('zoo-animal-lifespan', lang('Lifespan'), $input('zoo-animal-lifespan', 'text', 'lifespan', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $animals['giraffe']['lifespan']))), '6'),
            ), '3'),
            $form_step(3, lang('Photo and home page')),
            $field('zoo-animal-photo', lang('Photo'), pg_cf_with_role($file('zoo-animal-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $field('zoo-animal-featured', lang('Featured'), $select('zoo-animal-featured', 'featured', '', $zan_featured_choices, false, $zan_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured animal is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every animal by name, filtered by the address (habitat and class),
    // with a search over the animals' names and summaries.
    'animals_list' => array(
        'page'   => 'animals',
        'slug'   => lang('form-list'),
        'config' => $zan_record_list(lang('No animal matches this choice. Choose another habitat or class, or see every animal.'), array(
            'items_per_page' => 24,
            'filters'        => array(
                $zan_filter('habitat', 'is equal to', '', 'habitat'),
                $zan_filter('animal_class', 'is equal to', '', 'class'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'species', 'summary'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the animals: a name, a species...'),
        )),
        'tree'   => $animal_grid(),
    ),

    // The home page's animals: the featured ones, three at most.
    'home_animals' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $zan_record_list(lang('No animal is featured yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($zan_filter('featured', 'is equal to', $zan_featured_choices[0])),
        )),
        'tree'   => $animal_grid(),
    ),
);

// One animal: the photo with its habitat pin, the names, the conservation
// level, the facts, the ways to adopt it and find it on the map, and the
// details line by line.
$animal_sample_key = 'giraffe';
$animal_sample = $animals[$animal_sample_key];
$animal_not_found = lang('This animal could not be found. It may have moved to another zoo of the programme.');
$animal_widgets['animal_detail'] = array(
    'page'   => 'animal',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:animal_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $animal_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-binoculars', 'display-5 text-body-secondary'),
                $heading('h2', lang('Animal not found'), 'pzo-display h3 mt-3'),
                $para($animal_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Animals'), '{{page:animals}}', 'btn btn-success rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Animal'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-success text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Animals'), '', lang('Link Text')),
                    ), array('href' => '{{page:animals}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary',
                            $photo('animal-' . $animal_sample_key, 800, 600), lang('Photo')),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'd-inline-flex align-items-center gap-2 rounded-pill bg-success-subtle text-success-emphasis ps-1 pe-3 py-1 small fw-semibold mb-3', lang('Habitat Tag'), array(
                            $span($animal_sample['number'], 'pg-zoo-pin ' . $habitats[$animal_sample['habitat']]['pin'], lang('Habitat Number'), $bind('habitat_number')),
                            $span($animal_habitat($animal_sample_key), '', lang('Habitat'), $bind('habitat')),
                        )),
                        $heading('h1', $animal_sample['title'], 'pzo-display display-5 fw-bold mb-1', $bind('title')),
                        $span($animal_sample['species'], 'd-block fst-italic text-body-secondary mb-3', lang('Species'),
                            array_merge($bind('species'), array('_attrs' => array($attr('lang', 'la'))))),
                        $el('div', 'mb-3', lang('Status'), array($status_badge($animal_status($animal_sample_key)))),
                        $para($animal_sample['summary'], 'lead mb-4', $bind('summary')),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                            $link(lang('Adopt an Animal'), '{{page:adopt}}#zoo-adopt-animals', 'btn btn-success rounded-pill px-4'),
                            $link(lang('Find it on the map'), '{{page:map}}#zoo-map', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center mb-5'),
                $el('div', 'p-4 rounded-4 bg-body-tertiary mb-5', lang('Fact Sheet'), array(
                    $animal_facts($animal_sample_key),
                )),
                $heading('h2', lang('Good to know'), 'pzo-display h4 mb-3'),
                $el('div', 'pg-field-value lh-lg', lang('Details'), array(), array_merge(array('text' => $details_text($animal_sample['details'])), $bind('details'))),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $animal_pages, 'widgets' => $animal_widgets);
