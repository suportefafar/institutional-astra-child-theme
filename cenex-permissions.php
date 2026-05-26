<?php
/**
 * Permissions for User 123 over User 22 ("cenex")
 */

// Dynamically grant capabilities to user 123 for user 22's posts/pages
add_filter( 'user_has_cap', 'custom_cenex_user_permissions', 10, 4 );
function custom_cenex_user_permissions( $allcaps, $caps, $args, $user ) {
    if ( $user->ID != 123 ) {
        return $allcaps;
    }

    $expiration_date = strtotime( '2026-12-01 23:59:59' );
    if ( time() > $expiration_date ) {
        return $allcaps;
    }

    // Base capabilities so the user can access wp-admin, media, and post/page menus
    $base_caps = array(
        'read'                   => true,
        'edit_posts'             => true,
        'edit_pages'             => true,
        'publish_posts'          => true,
        'publish_pages'          => true,
        'edit_published_posts'   => true,
        'edit_published_pages'   => true,
        'delete_posts'           => true,
        'delete_pages'           => true,
        'delete_published_posts' => true,
        'delete_published_pages' => true,
        'upload_files'           => true,
    );
    
    foreach ( $base_caps as $cap => $value ) {
        $allcaps[ $cap ] = true;
    }

    // Dynamically grant capabilities for a specific post/page if it belongs to user 22
    if ( ! empty( $args ) && isset( $args[0] ) ) {
        $cap_requested = $args[0];
        $target_caps = array( 'edit_post', 'delete_post', 'read_post', 'edit_page', 'delete_page', 'read_page' );
        
        if ( in_array( $cap_requested, $target_caps ) && isset( $args[2] ) ) {
            $post = get_post( $args[2] );
            if ( $post && $post->post_author == 22 ) {
                foreach ( $caps as $cap ) {
                    $allcaps[ $cap ] = true;
                }
            }
        }
    }

    return $allcaps;
}

// Modify the query in WP Admin to allow user 123 to see posts/pages from user 22
add_action( 'pre_get_posts', 'custom_cenex_admin_posts_query' );
function custom_cenex_admin_posts_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    global $pagenow;
    if ( 'edit.php' !== $pagenow ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( $user_id != 123 ) {
        return;
    }

    $expiration_date = strtotime( '2026-12-01 23:59:59' );
    if ( time() > $expiration_date ) {
        return;
    }

    // Because the user lacks 'edit_others_posts' globally, WordPress forces 'author' to their ID.
    // We change it to allow seeing their own posts AND user 22's posts.
    $current_author = $query->get( 'author' );
    if ( (string) $current_author === (string) $user_id ) {
        $query->set( 'author', $user_id . ',22' );
    }
}
