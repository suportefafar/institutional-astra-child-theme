<?php
/**
 * WP Bootstrap Navwalker
 *
 * @package WP-Bootstrap-Navwalker
 *
 * @wordpress-plugin
 * Plugin Name: WP Bootstrap Navwalker
 * Plugin URI:  https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * Description: A custom WordPress nav walker class to implement the Bootstrap 4 navigation style in a custom theme using the WordPress built in menu manager.
 * Author: Edward McIntyre - @twittem, WP Bootstrap, William Patton - @pattonwebz, IanDelMar - @IanDelMar
 * Version: 4.3.0
 * Author URI: https://github.com/wp-bootstrap
 * GitHub Plugin URI: https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * GitHub Branch: master
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 */

// Check if Class Exists.
if ( ! class_exists( 'FAFAR_Menu_Walker' ) ) :
	/**
	 * FAFAR_Menu_Walker class.
	 */
	class FAFAR_Menu_Walker extends Walker_Nav_Menu {
	  
		static private $incremental_sub_menu_id = 0;

		function start_lvl(&$output, $depth = 0, $args = null)
		{
			$output .= "<ul class='collapse list-group rounded-0 m-0' id='sub-menu-" . @$this->incremental_sub_menu_id . "'>";
			@$this->incremental_sub_menu_id += 1;
		}
	  
		function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
		{
			$dropdown_icon = "<svg 
						class='ast-arrow-svg' 
						xmlns='http://www.w3.org/2000/svg' 
						xmlns:xlink='http://www.w3.org/1999/xlink' 
						version='1.1' 
						x='0px' 
						y='0px' 
						width='26px' 
						height='16.043px' 
						viewBox='57 35.171 26 16.043' 
						enable-background='new 57 35.171 26 16.043' 
						xml:space='preserve'>
                			<path 
							d='M57.5,38.193l12.5,12.5l12.5-12.5l-2.5-2.5l-10,10l-10-10L57.5,38.193z'
							>
							</path>
                		</svg>";
			$right_arrow = "<small class='me-4'></small>";


			$href 	  = ( $item->url && $item->url != "#" && !$args->walker->has_children ) ? $item->url : "#sub-menu-" . @$this->incremental_sub_menu_id . "" ;
			
			$classes  = implode( " ", $args->item_classes );
			$classes .= " ";
			$classes .= ( $depth > 0 ) ? "fafar-side-menu-sub-item" : "";
			$classes .= " ";
			$classes .= ( $args->walker->has_children ) ? "justify-content-between" : "";
			$classes .= " ";

			
			$attrs  = ( $args->walker->has_children ) ? 
				"data-bs-toggle='collapse' role='button' aria-expanded='false' aria-controls='sub-menu-" . @$this->incremental_sub_menu_id . "' " 
				: 
				" aria-current='true'";
			$attrs .= " ";
			

			$caret  = ( $args->walker->has_children ) ? $dropdown_icon : "";
			$caret .= " ";

			$output .= "<a href='" . $href . "'";
			$output .= " ";
			
			$output .= $attrs;
			$output .= " ";
			
			$output .= "class='" . $classes. "'";
			$output .= " ";

			$output .= "aria-current='true'>";
			$output .= " ";

        	$output .= ( $depth > 0 ) ? $right_arrow : "";
			$output .= " ";

        	$output .= $item->title;
			$output .= " ";

			$output .= $caret;
			$output .= " ";

			$output .= "</a>";
		}
	}

endif;
