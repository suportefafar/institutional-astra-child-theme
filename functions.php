<?php
/**
 * astra-fafar Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package astra-fafar
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_FAFAR_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'astra-fafar-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ASTRA_FAFAR_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );


/*
 *	
 *	
 *	
 *	
 *	
 *	
 *	
 *	
 *	
 *	    <<<<<<<<<<<<< START >>>>>>>>>>>
 *		ADDED BY Setor de Suporte e T.I. 
*/

// 
require_once get_stylesheet_directory() . '/shortcodes.php';
// 
require_once get_stylesheet_directory() . '/import-scripts.php';
// Register Custom Navigation Walker
require_once get_stylesheet_directory() . '/class-wp-fafar-menu-walker.php';
// Register FAFAR Custom Banner
require_once get_stylesheet_directory() . '/banner.php';
// Carregar funções customizadas
require_once get_stylesheet_directory() . '/hooks.php';

/*
 * Changing "Read More" button text  
 */
function translate_read_more_button() { return __( 'Leia Mais »', 'astra' ); }

add_filter( 'astra_post_read_more', 'translate_read_more_button' );

/*
 * Changing "Read More" button text  
 */
function translate_next_page() { return __( 'Próximo »', 'astra' ); }

add_filter( 'astra_single_post_navigation', 'translate_next_page' );

/*
 * Dynamic Pages Handler
 * Theme hooks: astra_entry_content_before, astra_entry_content_after, astra_entry_bottom
 */
function register_dynamic_pages_handler(){
	require_once get_theme_file_path() . '/dynamic-pages.php';
}
add_action( 'after_setup_theme', 'register_dynamic_pages_handler' );


/*
 * Desabilita os links que o Astra Theme coloca para 
 * o post anterior e próximo, no final da página de um post. 
 */
add_action( 'wp', function() {
    if (is_single()) {
        remove_action( 'astra_entry_after', 'astra_single_post_navigation_markup' );
    }
} );