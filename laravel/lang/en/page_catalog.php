<?php

declare(strict_types=1);

/*
 | Public animal catalog (/animali, UI-6b): search, filters with counts, sorting, active
 | filter chips, continuous loading and empty states.
 */
return [
    'title' => 'Animals for adoption',
    'intro' => 'Dogs, cats and other animals waiting for a family at verified organizations. Search by name or filter to find the right match.',

    'search' => [
        'label' => 'Search for an animal',
        'placeholder' => 'Name, breed or personality',
        'help' => 'Results update as you type.',
        'submit' => 'Search',
    ],

    'facets' => [
        'species' => 'Species',
        'sex' => 'Sex',
        'size' => 'Size',
        'region' => 'Region',
        'organization' => 'Organization',
    ],

    'filters' => [
        'title' => 'Filters',
        'description' => 'The numbers show how many animals you get with each choice.',
        'open' => 'Filters',
        'open_active' => '{1}:count active filter|[2,*]:count active filters',
        'all' => 'All',
        'all_region' => 'All regions',
        'all_organization' => 'All organizations',
        'option' => ':label (:count)',
        'apply' => 'Apply filters',
        'show_results' => '{0}No results|{1}Show :count animal|[2,*]Show :count animals',
        'reset' => 'Clear filters',
    ],

    'sort' => [
        'label' => 'Sort by',
        'apply' => 'Sort',
        'relevance' => 'Recommended',
        'newest' => 'Recently arrived',
        'waiting' => 'Waiting the longest',
        'name' => 'Name (A–Z)',
    ],

    'chips' => [
        'label' => 'Active filters',
        'search' => '“:search”',
        'search_facet' => 'Search',
        'described' => ':facet: :value',
        'reset' => 'Clear all',
    ],

    'results' => [
        'count' => '{0}No animals found|{1}:count animal found|[2,*]:count animals found',
        'range' => ':summary, showing :from to :to',
        'loading' => 'Loading more animals…',
    ],

    'more' => [
        'progress' => 'You have seen :shown of :total animals',
        'action' => 'Load more animals',
    ],

    'pagination' => [
        'label' => 'Catalog pages',
    ],

    'empty' => [
        'filtered_title' => 'No animals match these filters',
        'filtered_description' => 'Try removing a filter or searching for another name: organizations add new animals every week.',
        'catalog_title' => 'The catalog is empty, for now',
        'catalog_description' => 'Organizations are preparing new profiles. In the meantime, meet the shelters and associations on the platform.',
        'catalog_action' => 'Meet the organizations',
        'past_title' => 'This page does not exist',
        'past_description' => 'The catalog has fewer pages than that: some animals may have already found a home.',
        'past_action' => 'Back to the first page',
    ],

    'end' => [
        'title' => 'Still looking for your companion?',
        'description' => 'Meet the organizations caring for these animals: every shelter has its own story and other ways to help.',
        'action' => 'Meet the organizations',
    ],
];
