<?php 

/*  
 * Trata do Custom Post Type de e-books para a biblioteca, 
 * usado em https://www.farmacia.ufmg.br/e-books/
 */

function institutional_fafar_ebook_cpt_register() {
    $labels = array(
        'name' => __( 'E-Books', 'institutional_fafar' ),
        'singular_name' => __( 'E-Book', 'institutional_fafar' ),
        'add_new' => __( 'Novo E-Book', 'institutional_fafar' ),
        'add_new_item' => __( 'Adicionar E-Book', 'institutional_fafar' ),
        'edit_item' => __( 'Editar E-Book', 'institutional_fafar' ),
        'new_item' => __( 'Novo E-Book', 'institutional_fafar' ),
        'view_item' => __( 'Ver E-Books', 'institutional_fafar' ),
        'search_items' => __( 'Procurar E-Books', 'institutional_fafar' ),
        'not_found' =>  __( 'Nenhum E-Books Encontrado', 'institutional_fafar' ),
        'not_found_in_trash' => __( 'Nenhum E-Books Encontrado na Lixeira', 'institutional_fafar' ),
    );

    $args = array(
        'labels' => $labels,
        'has_archive' => true,
        'public' => true,
        'hierarchical' => false,
        'supports' => array(
            'title',
            // 'editor',
            // 'excerpt',
            // 'custom-fields',
            // 'thumbnail',
            // 'page-attributes'
        ),
        'taxonomies' => 'category',
        'rewrite'   => array( 'slug' => 'ebook' ),
        'show_in_rest' => true
    );

	register_post_type('fafar_bib_ebook', $args);
}
add_action('init', 'institutional_fafar_ebook_cpt_register');

/* Adiciona a Meta Box */
function institutional_fafar_ebook_add_meta_box() {
    add_meta_box(
        'ebook_fields', // Unique ID
        'Detalhes do E-book', // Title
        'institutional_fafar_ebook_meta_box_callback', // Callback function to display the fields
        'fafar_bib_ebook', // Post type to display on
        'normal', // Context
        'default' // Priority
    );
}
add_action( 'add_meta_boxes', 'institutional_fafar_ebook_add_meta_box' );

/* Campos Html do Meta Box */
function institutional_fafar_ebook_meta_box_callback( $post ) {
    // Adicionar um campo de nonce para segurança
    wp_nonce_field( 'ebook_save_meta', 'ebook_meta_nonce' );

    $knowledge_area = get_post_meta( $post->ID, '_ebook_knowledge_area', true );
    $is_available   = get_post_meta( $post->ID, '_ebook_available', true );
    $download_link  = get_post_meta( $post->ID, '_ebook_download_link', true );
    $cover_url      = get_post_meta( $post->ID, '_ebook_cover_image_url', true );

    ?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="ebook_available">E-book Disponível?</label>
                </th>
                <td>
                    <input type="checkbox" id="ebook_available" class="postbox" name="ebook_available" value="1" <?= checked( $is_available, '1', false ) ?> />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ebook_download_link">Área de Conhecimento:</label>
                </th>
                <td>
                    <select name="ebook_knowledge_area">
                        <option value="">Selecione uma opção</option>
                        <option value="ALIMENTOS E BROMATOLOGIA" <?= selected( $knowledge_area, 'ALIMENTOS E BROMATOLOGIA' ) ?> >ALIMENTOS E BROMATOLOGIA</option>
                        <option value="BIOESTATÍSTICA" <?= selected( $knowledge_area, 'BIOESTATÍSTICA' ) ?> >BIOESTATÍSTICA</option>
                        <option value="BIOQUÍMICA" <?= selected( $knowledge_area, 'BIOQUÍMICA' ) ?> >BIOQUÍMICA</option>
                        <option value="EPIDEMIOLOGIA" <?= selected( $knowledge_area, 'EPIDEMIOLOGIA' ) ?> >EPIDEMIOLOGIA</option>
                        <option value="FARMÁCIA HOSPITALAR" <?= selected( $knowledge_area, 'FARMÁCIA HOSPITALAR' ) ?> >FARMÁCIA HOSPITALAR</option>
                        <option value="FARMACOLOGIA E FARMACOCINÉTICA" <?= selected( $knowledge_area, 'FARMACOLOGIA E FARMACOCINÉTICA' ) ?> >FARMACOLOGIA E FARMACOCINÉTICA</option>
                        <option value="HEMATOLOGIA" <?= selected( $knowledge_area, 'HEMATOLOGIA' ) ?> >HEMATOLOGIA</option>
                        <option value="HISTOLOGIA E BIOLOGIA CELULAR E MOLECULAR" <?= selected( $knowledge_area, 'HISTOLOGIA E BIOLOGIA CELULAR E MOLECULAR' ) ?> >HISTOLOGIA E BIOLOGIA CELULAR E MOLECULAR</option>
                        <option value="MICROBIOLOGIA E PARASITOLOGIA" <?= selected( $knowledge_area, 'MICROBIOLOGIA E PARASITOLOGIA' ) ?> >MICROBIOLOGIA E PARASITOLOGIA</option>
                        <option value="QUÍMICA GERAL, ORGÂNICA E ANALÍTICA" <?= selected( $knowledge_area, 'QUÍMICA GERAL, ORGÂNICA E ANALÍTICA' ) ?> >QUÍMICA GERAL, ORGÂNICA E ANALÍTICA</option>
                        <option value="TOXICOLOGIA" <?= selected( $knowledge_area, 'TOXICOLOGIA' ) ?> >TOXICOLOGIA</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ebook_download_link">Link para Download:</label>
                </th>
                <td>
                    <input type="url" id="ebook_download_link" class="postbox" name="ebook_download_link" value="<?= esc_attr( $download_link ) ?>" size="50" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="ebook_cover_image_url">URL da Capa:</label>
                </th>
                <td>
                    <input type="url" id="ebook_cover_image_url" class="postbox" name="ebook_cover_image_url" value="<?= esc_attr( $cover_url ) ?>" size="50" />
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

/* Salvar os dados do Meta Box */
function institutional_fafar_ebook_save_meta( $post_id ) {
    // Checagem de segurança primeiro
    if ( ! isset( $_POST['ebook_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ebook_meta_nonce'], 'ebook_save_meta' ) ) {
        return $post_id;
    }

    // Checa se o usuário atual tem permissão para editar o post
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return $post_id;
    }

    $available_status = isset( $_POST['ebook_available'] ) ? '1' : '0';
    update_post_meta( $post_id, '_ebook_available', $available_status );

    if ( isset( $_POST['ebook_download_link'] ) ) {
        $link = sanitize_url( $_POST['ebook_download_link'] );
        update_post_meta( $post_id, '_ebook_download_link', $link );
    }

    if ( isset( $_POST['ebook_cover_image_url'] ) ) {
        $url = sanitize_url( $_POST['ebook_cover_image_url'] );
        update_post_meta( $post_id, '_ebook_cover_image_url', $url );
    }

    if ( isset( $_POST['ebook_knowledge_area'] ) ) {
        $knowledge_area = sanitize_text_field( $_POST['ebook_knowledge_area'] );
        update_post_meta( $post_id, '_ebook_knowledge_area', $knowledge_area );
    }
}
add_action( 'save_post', 'institutional_fafar_ebook_save_meta' );

// Cria um shortcode para mostrar os e-books
function institutional_fafar_ebook_show() {
    $args = array(
        'post_type'      => 'fafar_bib_ebook',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_ebook_available',
                'value'   => '1',
                'compare' => '=',
            ),
        ),
    );

    $loop = new WP_Query($args);

    $ebooks = institutional_fafar_sort_ebooks( $loop );

    $current_knowledge_area = '';
?>
    <?php if ( ! empty( $ebooks ) ) : ?>
        
        <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Livro</th>
                            <th>Nome</th>
                        </tr>
                    </thead>
                    <tbody>
        
        <?php 
            foreach ( $ebooks as $ebook ) :
                
                // Se o livro não estiver disponível, pula, né não?
                if ( $ebook['is_available'] !== '1' ) continue;

                if ( $current_knowledge_area !== $ebook['knowledge_area'] ) : 
                    $current_knowledge_area = $ebook['knowledge_area'];
        ?>
                        <tr>
                            <td colspan="2" class="text-center fw-bold">
                                <?= $ebook['knowledge_area'] ?>
                            </td>
                        </tr>
                        
                <?php endif; ?>

                        <tr>
                            <td>
                                <img src="<?= $ebook['cover_image_url'] ?>" alt="Capa do livro <?= $ebook['title'] ?>" width="64" />
                            </td>
                            <td>
                                <a href="<?= $ebook['download_link'] ?>" title="Baixe <?= $ebook['title'] ?>" class="btn btn-link" target="_blank">
                                    <?= $ebook['title'] ?>
                                </a>
                            </td>
                        </tr>

        <?php endforeach; ?>

                  </tbody>
                </table>
            </div>
        
        <?php else: ?>

        <tr>
            <td colspan="4">Nenhum e-book encontrado</td>
        </tr>

    <?php 
        endif;
}
add_shortcode( 'institutional_fafar_ebook_show', 'institutional_fafar_ebook_show' );

function institutional_fafar_sort_ebooks( $ebook_loop ) {
    if( empty( $ebook_loop ) ) {
        return [];
    }

    if ( ! $ebook_loop->have_posts() ) {
        return [];
    }

    $ebooks = [];
    while ( $ebook_loop->have_posts() ) {
        $ebook_loop->the_post();

        $ebook_id        = get_the_ID();
        $is_available    = get_post_meta( $ebook_id, '_ebook_available', true );
        $download_link   = get_post_meta( $ebook_id, '_ebook_download_link', true );
        $cover_image_url = get_post_meta( $ebook_id, '_ebook_cover_image_url', true );
        $knowledge_area  = get_post_meta( $ebook_id, '_ebook_knowledge_area', true );

        $ebooks[] = [
            'id'              => $ebook_id,
            'title'           => the_title( display: false ),
            'is_available'    => $is_available,
            'download_link'   => $download_link,
            'cover_image_url' => $cover_image_url,
            'knowledge_area'  => $knowledge_area,
        ];
    }

    // Reseta os dados do post depois do loop
    wp_reset_postdata(); 

    /*
     * Ordena por 'área de conhecimento' e 'título', alfabeticamente, 
     * No entanto, a 'área de conhecimento' tem mais peso.
     * Dessa forma, todos os e-books de 'Alimento' ficariam antes de todos os 
     * e-books de 'Medicamento'.
     */
    usort( $ebooks, function ( $a, $b ) {
        $comp_1 = strcasecmp( $a['knowledge_area'], $b['knowledge_area'] );
        $comp_2 = strcasecmp( $a['title'], $b['title'] );

        if ( $comp_1 === 0 && $comp_2 === 0) return 0;
        elseif ( $comp_1 === 0 ) return $comp_2;
        elseif ( $comp_1 !== 0 ) return $comp_1;
    } );
    
    return $ebooks;
}