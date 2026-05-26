<?php
/**
 * Permissions for User 123 over User 22 ("cenex")
 */

// Defina as variáveis globais aqui para facilitar a alteração dos IDs
$GLOBALS['cenex_delegated_user_id'] = getenv('CENEX_DELEGATED_USER_ID') ? intval(getenv('CENEX_DELEGATED_USER_ID')) : 62; // O ID do usuário que vai ganhar o acesso (puxa do .env)
$GLOBALS['cenex_target_user_id'] = 22;    // O ID do dono original (ex: cenex) dos posts/páginas

// Dynamically grant capabilities for the target user's posts/pages
add_filter( 'user_has_cap', 'custom_cenex_user_permissions', 10, 4 );
function custom_cenex_user_permissions( $allcaps, $caps, $args, $user ) {
    if ( $user->ID != $GLOBALS['cenex_delegated_user_id'] ) {
        return $allcaps;
    }

    $expiration_date = strtotime( '2026-12-31 23:59:59' );
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
            if ( $post && $post->post_author == $GLOBALS['cenex_target_user_id'] ) {
                foreach ( $caps as $cap ) {
                    $allcaps[ $cap ] = true;
                }
            }
        }

        // Handle REST API (Gutenberg) checking edit_others_posts without passing the post ID
        if ( in_array( $cap_requested, array( 'edit_others_posts', 'edit_others_pages' ) ) && ! isset( $args[2] ) ) {
            $post_id = 0;
            if ( isset( $_REQUEST['post'] ) ) {
                $post_id = intval( $_REQUEST['post'] );
            } elseif ( isset( $_REQUEST['post_ID'] ) ) {
                $post_id = intval( $_REQUEST['post_ID'] );
            } else {
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if ( preg_match( '#/wp/v2/(?:posts|pages|[\w-]+)/(\d+)#', $uri, $matches ) ) {
                    $post_id = intval( $matches[1] );
                }
            }

            if ( $post_id ) {
                $post = get_post( $post_id );
                if ( $post && $post->post_author == $GLOBALS['cenex_target_user_id'] ) {
                    $allcaps[ $cap_requested ] = true;
                }
            }
        }
    }

    return $allcaps;
}

// Modify the query in WP Admin to allow user 62 to see posts/pages from user 22
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
    if ( $user_id != $GLOBALS['cenex_delegated_user_id'] ) {
        return;
    }

    $expiration_date = strtotime( '2026-12-31 23:59:59' );
    if ( time() > $expiration_date ) {
        return;
    }

    // Because the user lacks 'edit_others_posts' globally, WordPress forces 'author' to their ID.
    // We change it to allow seeing their own posts AND user 22's posts.
    $current_author = $query->get( 'author' );
    if ( (string) $current_author === (string) $user_id ) {
        $query->set( 'author', $user_id . ',' . $GLOBALS['cenex_target_user_id'] );
    }
}

// Força que os novos posts ou páginas criados pelo usuário delegado sejam do usuário alvo
add_filter( 'wp_insert_post_data', 'custom_cenex_force_post_author', 10, 2 );
function custom_cenex_force_post_author( $data, $postarr ) {
    $current_user_id = get_current_user_id();
    
    if ( $current_user_id != $GLOBALS['cenex_delegated_user_id'] ) {
        return $data;
    }

    $expiration_date = strtotime( '2026-12-31 23:59:59' );
    if ( time() > $expiration_date ) {
        return $data;
    }

    if ( isset( $data['post_type'] ) && in_array( $data['post_type'], array( 'post', 'page' ) ) ) {
        $data['post_author'] = $GLOBALS['cenex_target_user_id'];
    }

    return $data;
}
