<?php

function people_content_handler($people) {

    global $wpdb;

    $people_table_itens = "";

    foreach ( $people as $person ) {

        $nome_formatado = mb_strtolower( $person->nome, 'UTF-8' );
        $nome_formatado = ucwords( $nome_formatado ); 

        $setor = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM sitefafar.setores WHERE id = ' . $person->setor ) );
        $nome_setor = $setor->descricao;            

        $people_table_itens .= '<tr class="small">';
        $people_table_itens .= '<th scope="row">' . $nome_formatado . '</th>';
        $people_table_itens .= '<td>' . $nome_setor . '</td>';
        $people_table_itens .= '<td>' . $person->email . '</td>';
        $people_table_itens .= '<td>' . $person->ramal . '</td>';
        $people_table_itens .= '</tr>';

    }

    if(!$people)
        $people_table_itens = "<tr><td>Nenhum resultado encontrado.<br/>Por favor, tente novamente mais tarde</td></tr>";

    echo '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr class="small">
                        <th scope="col">Nome</th>
                        <th scope="col">Setor</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Ramal</th>
                    </tr>
                </thead>
                <tbody>' .
                    $people_table_itens .
                '</tbody>
            </table>
        </div>';

}


function mapa_salas_content_js_handler() {

    echo '<script src="' . get_stylesheet_directory_uri() . '/assets/js/mapa-de-sala.js"></script>';

}

function parse_weekday_to_name( $number ) {

    if( ! $number ) return 'N/A';

    if( ! is_numeric($number) ) return $number;

    return array(
        0 => 'Dom',
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sáb',
    )[(int)$number] ?? '--';
}

function mapa_salas_content_handler() {

    global $wpdb;

    $reservas = $wpdb->get_results("SELECT * FROM `intranet_wp`.`wp_fafar_cf7crud_submissions` WHERE `object_name` = 'reservation' AND `is_active` = '1'" , 'ARRAY_A' );

    //echo count($reservas);

    //print_r($reservas[0]);

    $line_objs = [];

    foreach ( $reservas as $reserva ) {

        $reserva['data'] = json_decode( $reserva['data'], true );

        if( ! $reserva['data']['class_subject'] || ! $reserva['data']['place'] ) continue;


        // Obtendo disciplina
        $disciplina = $wpdb->get_results("SELECT * FROM `intranet_wp`.`wp_fafar_cf7crud_submissions` WHERE `id` = '" . $reserva['data']['class_subject'][0] . "'", 'ARRAY_A' );

        if( ! $disciplina ) continue;

        $disciplina['data'] = json_decode( $disciplina[0]['data'], true );


        // Obtendo sala de aula
        $sala = $wpdb->get_results("SELECT * FROM `intranet_wp`.`wp_fafar_cf7crud_submissions` WHERE `id` = '" . $reserva['data']['place'][0] . "'", 'ARRAY_A' );

        if( ! $sala ) continue;

        $sala['data'] = json_decode( $sala[0]['data'], true );


        
        $dias_da_semana = implode( ', ', array_map( function( $weekday ) { return parse_weekday_to_name( $weekday ); }, (array) $reserva['data']['weekdays'] ) );

        $desc = $disciplina['data']['code'] . ' ' . $disciplina['data']['name_of_subject'] . ' (' . $disciplina['data']['group'] . ')';
        
        if( isset( $reserva['data']['desc'] ) && $reserva['data']['desc'] ) {
            $code   = explode( ' ', $reserva['data']['desc'] )[0];
            $groups = explode( ' ', $reserva['data']['desc'] )[1];
            $desc   = $code . ' ' . $disciplina['data']['name_of_subject'] . $groups;
        }

        $line_objs[] = array(
            'desc_reserva'     => $desc,
            'desc_sala'        => $sala['data']['number'] . ' <br />Bloco: ' . $sala['data']['block'] . ' <br />Andar: ' . $sala['data']['floor'] . 'º',
            'dias_semana'      => $dias_da_semana,
            'hora_inicio'      => $reserva['data']['start_time'],
            'hora_fim'         => $reserva['data']['end_time'],
        );

    }

    // Ordenar pelo código da disciplina
    usort( $line_objs, function( $a, $b ) {
        return $a['desc_reserva'] <=> $b['desc_reserva']; // Sort ascending
    } );

    $lines = "";

    foreach( $line_objs as $line_obj ) {

        $lines .= '<tr class="small">' .
                    '<th scope="row">' . $line_obj['desc_reserva']. '</th>' .
                    '<td>' . $line_obj['desc_sala'] . '</td>' .
                    '<td>' . $line_obj['dias_semana'] . '</td>' .
                    '<td>' . $line_obj['hora_inicio'] . '</td>' .
                    '<td>' . $line_obj['hora_fim'] . '</td>' .
                '</tr>';

    }


    $html = '<div class="mt-5 d-flex flex-column gap-3">
                <div class="d-flex gap-2">
                    <input class="form-control mr-sm-2 bg-white rounded-0" id="input_mapa_sala" type="search" placeholder="Código da disciplina" aria-label="Search">
                    <button class="btn btn-outline-success my-2 my-sm-0 rounded-0" id="button_mapa_sala" type="submit">Pesquisar</button>
                </div>
                <div class="d-flex justify-content-center d-none" id="loading_container_mapa_sala">
                    <img src="' . get_stylesheet_directory_uri() . '/img/loading.gif" alt="Loading gif" width="64" />
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr class="small">
                                <th scope="col">Disciplina</th>
                                <th scope="col">Sala</th>
                                <th scope="col">Dia da Semana</th>
                                <th scope="col">Hr. Início</th>
                                <th scope="col">Hr. Fim</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_mapa_sala">' .
                            $lines .
                        '</tbody>
                    </table>
                </div>
            </div>';

    echo $html;

}

function separe_unique( $arr ) {

    $arr_u = array();

    $inicio_semestre = get_timestamp_by_date( '2024-09-23' );
    foreach ( $arr as $item ) {

        if ( ( (int) $item->inicio ) < ( $inicio_semestre * 1000) ) continue;

        if ( ( (int) $item->frequencia ) == 0 ) continue;

        $key_item = $item->sala . "." .
                    $item->cod_disciplina . "." .
                    $item->diasemana . "." .
                    $item->horainicio . "." .
                    $item->horafim;

        $val_item = $item->id . "." .
                    $item->inicio . "." .
                    $item->fim;
                    
        if( !isset($arr_u[$key_item]) ) $arr_u[$key_item] = $val_item;  

    }

    return $arr_u;
}

function obterObjetoPorID( $id, $arr ) {

    foreach ( $arr as $item ) {

        if ( $id === $item->id ) return $item;

    }
    
    return false;

}

function get_timestamp_by_date( $date ) {

    $d = date_create( $date, new DateTimeZone('America/Sao_Paulo') );
    return $d->getTimestamp();

}

function intranet_fafar_api_get_hours_by_timestamp( $timestamp ) {

    $d = date_create( "now", new DateTimeZone('America/Sao_Paulo') );
    $d->setTimestamp((int) $timestamp);

    return $d->format('H:i');

}

function ementas_content_handler() {
    echo "EMENTAS";
}

function baixar_ementas_content_js_handler() {

    echo '<script src="' . get_stylesheet_directory_uri() . '/assets/js/baixar-ementas.js"></script>';

}

function baixar_ementas_content_handler() {

    global $wpdb;

    $versions = $wpdb->get_results( "SELECT * FROM tipo_ementas WHERE ativo = 1 ORDER BY id DESC" );

    $versions_options = "";

    $disciplines_table_itens = "";

    foreach ( $versions as $version ) {

        $versions_options .= '<option value="' . $version->descricao . '">' . $version->descricao . '</option>';

    }

    foreach ( $versions as $version ) {

        $disciplines = $wpdb->get_results( "SELECT * FROM ementas WHERE versao= $version->id ORDER BY cod_disciplina, nome" );

        foreach ( $disciplines as $discipline ) {

            $disciplines_table_itens .= '<tr class="small">' .
                                            '<th scope="row">' . $version->descricao . '</th>' .
                                            '<td>' . $discipline->cod_disciplina . '</td>' .
                                            '<td>' . $discipline->nome . '</td>' .
                                            '<td> <a href="' . $discipline->arquivo_ementa . '" class="btn" target="_blank"><i class="bi bi-download"></i></a> </td>' .
                                        '</tr>';

        } 

    }


    echo '
        <div class="d-flex justify-content-end m-0 p-0">
            <p class="p-0 m-0">Ou <a href="https://www.farmacia.ufmg.br/validarementa/">valide</a> ementas</p>
        </div>

        <hr class="my-4" />
        
        <div class="d-flex flex-column gap-3">
            <select
            class="form-select form-select rounded-0"
            id="select_baixar_ementas"
            >
                <option value="" selected>Versão Curricular</option>
                ' . $versions_options . '
            </select>

            <input class="form-control mr-sm-2 bg-white rounded-0" id="input_baixar_ementas" type="search" placeholder="Código da disciplina" aria-label="Search">
        
            <button
            class="btn btn-outline-primary my-2 my-sm-0 rounded-0"
            id="button_baixar_ementas"
            >
            Filtrar
            </button>

            <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr class="small">
                                <th scope="col">Versão</th>
                                <th scope="col">Código</th>
                                <th scope="col">Nome</th>
                                <th scope="col">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_baixar_ementas">' .
                            $disciplines_table_itens .
                        '</tbody>
                    </table>
                </div>
        </div>';

}

function validar_ementas_content_js_handler() {

    echo '<script src="' . get_stylesheet_directory_uri() . '/assets/js/verificar-ementa.js"></script>';

}

function validar_ementas_content_handler() {

    global $wpdb;

    if( isset( $_GET["codigo"] ) ) {

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM ementas a JOIN tipo_ementas b ON a.versao = b.id WHERE cod_ementa = %s",
                $_GET["codigo"]));

        if ( ! $result ) {

            echo  "<div id='alert-to-collapse' class='alert alert-danger' role='alert'>
                        Código de ementa inválido! ('" . $_GET["codigo"] . "')
                    </div>";

        } else {

            echo    "<div class='table-responsive'>
                        <table class='table'>
                            <tbody>
                                <tr>
                                    <td>Código</td>
                                    <th scope='row'>" . $result->cod_disciplina . "</th>
                                </tr>
                                <tr>
                                    <td>Nome</td>
                                    <th scope='row'>" . $result->nome . "</th>
                                </tr>
                                <tr>
                                    <td>Subnome</td>
                                    <th scope='row'>" . $result->subnome . "</th>
                                </tr>
                                <tr>
                                    <td>Tipo de Ementa</td>
                                    <th scope='row'>" . $result->descricao . "</th>
                                </tr>
                                <tr>
                                    <td>Link</td>
                                    <th scope='row'><a href='" . $result->arquivo_ementa . "' target='_blank'>" . $result->arquivo_ementa . "</a></th>
                                </tr>               
                            </tbody>
                        </table>
                    </div>
                    <hr class='my-5' />";
                    
        }

    }

    echo "";

}

function emitir_certificados_content_js_handler() {

    echo '<script src="' . get_stylesheet_directory_uri() . '/assets/js/emitir-certificados.js"></script>';

}

function onFormRequestHandler() {

    global $wpdb;

    $matricula = $_POST['documento'];
    $nome = $_POST['nome'];
    $senha = $_POST['senha'];
    $senhasha = sha1($senha);

    $result = $wpdb->get_results("SELECT * FROM newIntranet.Atividade_Participante a JOIN  newIntranet.Atividade b ON a.Atividade = b.idAtividade");

    echo "<table class='table table-hover'>";
    echo "<tr>";
    echo "<th><b>Curso</b></th>";
    echo "<th><b>Certificado</b></th>";
    echo "</tr>";

    foreach( $result as $row ) {
        $nome = $row->participante;
        $curso = $row->Titulo; 
        $codigo = $row->Codigo;
        echo "<tr><td>$curso</td><td><a target='_blank' href='http://www.farmacia.ufmg.br/certificado-fitoterpia-2021?codigo=$codigo'><img width='20' weight='20'  title='Imprimir Certificado' src='http://www.farmacia.ufmg.br/Intranet/imagens/CERTIFICADO-digital-icon.jpg'></tr>";
    }

    echo "</table>";

}

function emitir_certificados_content_handler() {

    echo '<form class="mb-3" id="form_emitir_certificados" action="/emitir-certificados" method="POST">
            <div class="mb-3">
                <select class="form-select" aria-label="Selecione um evento" id="select_evento" name="evento" required>
                    <option selected>Selecione um evento</option>
                    <option value="10">2021 - Fitoterapia</option>
                    <option value="9">2019 - SIFPICS</option>
                    <option value="8">2019 - SAEF</option>
                    <option value="7">2019 - VII Seminário Discussão DCN</option>
                    <option value="6">2019 - Simpósio VCEAF</option>
                    <option value="5">2018 - SAEF</option>
                    <option value="4">2018 - V Seminário Discussão DCN</option>
                    <option value="3">2017 - SIMDII</option>
                    <option value="1">2017 - SAEF </option>
                    <option value="2">2016 - Semana do Conhecimento</option>
                </select> 
            </div>
            <div class="mb-3">
                <label for="input_nome">Nome</label>
                <input type="text" class="form-control" id="input_nome" name="nome" aria-describedby="nomeHelp" required>
                <small id="nomeHelp" class="form-text text-muted">Conforme informado na inscrição</small>
            </div>
            <div class="mb-3">
                <label for="input_documento">Documento</label>
                <input type="text" class="form-control" id="input_documento" name="documento" aria-describedby="documentoHelp">
                <small id="documentoHelp" class="form-text text-muted">Número de matrícula ou CPF</small>
            </div>
            <div class="mb-3">
                <label for="input_senha">Senha</label>
                <input type="password" class="form-control" id="input_senha" name="senha" aria-describedby="senhaHelp">
                <small id="senhaHelp" class="form-text text-muted">Caso tenha cadastrado</small>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>';

        //if( isset( $_POST["evento"] ) && isset( $_POST["nome"] ) ) onFormRequestHandler();

}

function tecnicos_administrativos_content_handler() {
    
    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel = 1 and ativo = 1 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_administrativo_act_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=1 and ativo=1 and setor = 1 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_administrativo_alm_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=1 and ativo=1 and setor = 2 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_administrativo_fas_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=1 and ativo=1 and setor = 3 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_administrativo_pfa_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=1 and ativo=1 and setor = 4 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_docente_act_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=2 and ativo=1 and setor = 1 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_docente_alm_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=2 and ativo=1 and setor = 2 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_docente_fas_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=2 and ativo=1 and setor = 3 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_docente_pfa_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=2 and ativo=1 and setor = 4 ORDER BY nome' );

    people_content_handler($taes);

}

function corpo_docente_all_content_handler() {

    global $wpdb;

    $taes = $wpdb->get_results( 'SELECT * FROM sitefafar.pessoas WHERE nivel=2 and ativo=1 ORDER BY nome' );

    people_content_handler($taes);

}

function dynamic_pages_handler(){

    if( is_page( "mapa-de-salas" ) || is_page( "mapa-de-salas-2" ) ) {

        mapa_salas_content_handler();

        add_action( 'astra_body_bottom', 'mapa_salas_content_js_handler' );

    } else if( is_page( "emitir-certificados" ) ) {

        //emitir_certificados_content_handler();

        //add_action( 'astra_body_bottom', 'emitir_certificados_content_js_handler' );

        echo "Em manutenção...";

    } else if( is_page( "ementas" ) ) {

        ementas_content_handler();

        //add_action( 'astra_body_bottom', 'baixar_ementas_content_js_handler' );

    } else if( is_page( "baixar-ementas" ) ) {

        baixar_ementas_content_handler();

        add_action( 'astra_body_bottom', 'baixar_ementas_content_js_handler' );

    } else if( is_page( "validarementa" ) ) {

        validar_ementas_content_handler();

        add_action( 'astra_body_bottom', 'validar_ementas_content_js_handler' );

    } else if( is_page( "baixar-ementas-biomedicina" ) ) {

        baixar_ementas_content_handler();

        add_action( 'astra_body_bottom', 'baixar_ementas_content_js_handler' );

    } else if( is_page( "tecnicos-administrativos" ) ) {

        tecnicos_administrativos_content_handler();
        
    } else if( is_page( "corpo-administrativo-act" ) ) {

        corpo_administrativo_act_content_handler();

    } else if( is_page( "corpo-administrativo-alm" ) ) {

        corpo_administrativo_alm_content_handler();

    } else if( is_page( "corpo-administrativo-fas" ) ) {

        corpo_administrativo_fas_content_handler();

    } else if( is_page( "corpo-administrativo-pfa" ) ) {

        corpo_administrativo_pfa_content_handler();

    } else if( is_page( "corpo-docente-act" ) ) {

        corpo_docente_act_content_handler();

    } else if( is_page( "corpo-docente-alm" ) ) {

        corpo_docente_alm_content_handler();

    } else if( is_page( "corpo-docente-2" ) ) {

        corpo_docente_fas_content_handler();

    } else if( is_page( "corpo-docente-pfa" ) ) {

        corpo_docente_pfa_content_handler();

    } else if( is_page( "corpo-docente" ) ) {

        corpo_docente_all_content_handler();

    } else {

        // Any other page

    }
}

add_action( 'astra_entry_content_before', 'dynamic_pages_handler' );
