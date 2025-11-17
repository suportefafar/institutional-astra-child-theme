<?php

function institutional_fafar_get_category() {

    if ( is_home() ) {
        return null;
    }

    if ( is_category() ) {
        return get_queried_object();
    }

    $categories = get_the_category();

    if ( empty( $categories ) ) {
        return null;
    }

    /**
     * Almost always, a post has only one category.
     * But in case to have two, we have to decide.
     * The way decided was to use the category on the permalink: farmacia.ufmg.br/CATEGORY/post-name
     * In order to do this, we sort the categories by term_id in ascending order (Lowest ID first)
     * This replicates the default WordPress core behavior for permalink category selection.
     * The category with the lowest ID (first in the sorted list) is the one
     * that WordPress will use in the permalink structure.
     */
    $sorted_categories = wp_list_sort( $categories, [ 'term_id' => 'ASC' ] );

    return $sorted_categories[0];
}