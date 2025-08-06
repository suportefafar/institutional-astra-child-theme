<?php

add_shortcode( 'site_fafar_custom_sidebar_menu', 'site_fafar_custom_sidebar_menu' );

add_shortcode( 'site_fafar_highlight_area', 'site_fafar_highlight_area' );


function site_fafar_custom_sidebar_menu() {
    

    // Get menu by user as default option
    $menu = get_menu_by_current_content();

    if ( is_home() ) {

        $menu = get_secondary_menu();

    }

    /* 
        Rendering the sidemenu with custom Menu Walker
    */

    echo wp_nav_menu(array(
        'menu'            => $menu,
        'depth'           => 2,
        'menu_class'      => 'm-0 list-group rounded-0',
        'item_classes'    => array( 'list-group-item','list-group-item-action','rounded-0','fafar-side-menu-item', 'd-flex', 'align-items-center' ),
        'walker'          => new FAFAR_Menu_Walker(),
    ));
}

/*
    Was defined that:
        The Primary Menu would be the header menu;
        The Secondary Menu would be the home page sidemenu;
    
    So...
*/

function get_institucional_menu() {

    $INSTITUCIONAL_MENU_ID = 4;

    return $INSTITUCIONAL_MENU_ID;

}

function get_secondary_menu() {
    /*
        Gets wherever menu was set as secondary to be
        shown at home page
    */
    
    $menu = array();
    $menu_locations = get_nav_menu_locations();
    $secondary_menu_location = 'secondary_menu';
    if ( isset( $menu_locations[ $secondary_menu_location ] ) ) {

        $secondary_menu_id = $menu_locations[ $secondary_menu_location ];
        $menu = wp_get_nav_menu_object( $secondary_menu_id );

    } else {
        // 'Secondary Menu' not found
        $menu = get_institucional_menu();
        
    }

    return $menu;
}

function get_menu_by_current_content() {

    $category = get_the_category();
    if( isset( $category[0] ) ) { // get_queried_object()->post_type == "post"
        
        return $category[0]->slug;

    } 

    if ( get_post_field( 'post_author' ) != "" ) { // get_queried_object()->post_type == "page"

        $post_author_id = get_post_field( 'post_author' );
        $user = get_user_by( 'id', $post_author_id );
        return $user->data->user_nicename;

    }
    
    return get_institucional_menu();

}


/*
 * Essa função é responsável por 
 * retornar a Área de Destaque na página principal
 */
function site_fafar_highlight_area() {

    if ( ! is_user_logged_in() || ! is_home() ) return '';

    if ( have_posts() ) {

        query_posts('post_type=servicos&showposts=40&orderby=date&order=ASC');

        $itens = '';

        while ( have_posts() ) {

            the_post(); 
            
            $title       = the_title( $before = '', $after = '', $display = false );

            $guid        = get_the_guid();

            $url_servico = get_field('link_serviço');

            $id_img      = get_field('imagem_servico');
            $url_img     = wp_get_attachment_url($id_img);
            
            $itens .= 
                    '<div class="col col-sm-6 col-md-4 highlight-card">
                        <a href="' . $url_servico . '" target="_blank" title="Ir para ' . $title . '">
                            <img src="' . $url_img . '" alt="Logo da ' . $title . '" />
                        </a>
                    </div>';

        }

    }

    return '
    <div class="container w-100 highlight-container">
        <div class="row">
            <h6>Serviços</h6>
        </div>
        <div class="row">
        ' . $itens . '
        </div>
    </div>';

}