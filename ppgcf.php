<?php
/**
 * Backend do formulário de inscrição do PPGCF.
 *
 * Os uploads passam primeiro por um diretório temporário. Só depois que o
 * formulário estiver completo eles são movidos para a inscrição criada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'fafar_ppgcf_register_candidate_post_type' );
add_action( 'init', 'fafar_ppgcf_maybe_create_test_page', 20 );
add_action( 'template_redirect', 'fafar_ppgcf_protect_candidates_page' );
add_action( 'wp_ajax_ppgcf_upload_file', 'fafar_ppgcf_upload_file' );
add_action( 'wp_ajax_nopriv_ppgcf_upload_file', 'fafar_ppgcf_upload_file' );
add_action( 'wp_ajax_ppgcf_submit_form', 'fafar_ppgcf_submit_form' );
add_action( 'wp_ajax_nopriv_ppgcf_submit_form', 'fafar_ppgcf_submit_form' );
add_action( 'fafar_ppgcf_cleanup_pending_uploads', 'fafar_ppgcf_cleanup_pending_uploads' );
add_action( 'fafar_ppgcf_retry_email_notification', 'fafar_ppgcf_retry_email_notification', 10, 2 );
add_action( 'admin_post_fafar_ppgcf_download_document', 'fafar_ppgcf_download_document' );
add_action( 'admin_post_nopriv_fafar_ppgcf_download_document', 'fafar_ppgcf_download_document' );

/** Cria uma única vez a página que usa o formulário e o período de testes. */
function fafar_ppgcf_maybe_create_test_page() {
	$stored_page_id = absint( get_option( 'fafar_ppgcf_test_page_id' ) );
	if ( $stored_page_id && get_post_status( $stored_page_id ) ) {
		return;
	}

	$page = get_page_by_path( 'inscricao-ppgcf-teste', OBJECT, 'page' );
	if ( $page ) {
		update_option( 'fafar_ppgcf_test_page_id', (int) $page->ID, false );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Inscrição PPGCF — Teste',
			'post_name'    => 'inscricao-ppgcf-teste',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) && $page_id ) {
		update_option( 'fafar_ppgcf_test_page_id', (int) $page_id, false );
	}
}

/** Retorna os dois períodos configuráveis e seus valores padrão. */
function fafar_ppgcf_registration_schedules() {
	$defaults = array(
		'production' => array(
			'opens_at'  => '',
			'closes_at' => '',
		),
		'test'       => array(
			'opens_at'  => '',
			'closes_at' => '',
		),
	);
	$saved    = get_option( 'fafar_ppgcf_registration_schedules', array() );
	$saved    = is_array( $saved ) ? $saved : array();

	foreach ( $defaults as $context => $schedule ) {
		if ( isset( $saved[ $context ] ) && is_array( $saved[ $context ] ) ) {
			$defaults[ $context ]['opens_at']  = isset( $saved[ $context ]['opens_at'] ) && is_string( $saved[ $context ]['opens_at'] ) ? $saved[ $context ]['opens_at'] : '';
			$defaults[ $context ]['closes_at'] = isset( $saved[ $context ]['closes_at'] ) && is_string( $saved[ $context ]['closes_at'] ) ? $saved[ $context ]['closes_at'] : '';
		}
	}

	return $defaults;
}

/** Converte o valor de datetime-local usando o fuso horário do WordPress. */
function fafar_ppgcf_parse_schedule_datetime( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
		return false;
	}

	$date   = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
	$errors = DateTimeImmutable::getLastErrors();
	if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
		return false;
	}

	return $date;
}

/** Informa se o período está aberto, próximo, futuro, encerrado ou ausente. */
function fafar_ppgcf_registration_schedule_state( $context, $now = null ) {
	$context   = in_array( $context, array( 'production', 'test' ), true ) ? $context : 'production';
	$schedules = fafar_ppgcf_registration_schedules();
	$schedule  = $schedules[ $context ];
	$opens_at  = fafar_ppgcf_parse_schedule_datetime( $schedule['opens_at'] );
	$closes_at = fafar_ppgcf_parse_schedule_datetime( $schedule['closes_at'] );
	$now       = $now instanceof DateTimeImmutable ? $now : current_datetime();
	$status    = 'not_configured';

	if ( $opens_at && $closes_at && $opens_at < $closes_at ) {
		if ( $now >= $closes_at ) {
			$status = 'closed';
		} elseif ( $now >= $opens_at ) {
			$status = 'open';
		} elseif ( $now >= $opens_at->sub( new DateInterval( 'P3D' ) ) ) {
			$status = 'upcoming';
		} else {
			$status = 'scheduled';
		}
	}

	return array(
		'context'   => $context,
		'status'    => $status,
		'opens_at'  => $opens_at,
		'closes_at' => $closes_at,
		'values'    => $schedule,
	);
}

/** Usuários que editam páginas podem alterar os períodos de inscrição. */
function fafar_ppgcf_can_manage_schedules() {
	return current_user_can( 'edit_pages' );
}

/** Valida e salva os dois períodos enviados pela página de candidatos. */
function fafar_ppgcf_save_registration_schedules_from_request() {
	if ( empty( $_POST['fafar_ppgcf_schedule_action'] ) || 'save' !== sanitize_key( wp_unslash( $_POST['fafar_ppgcf_schedule_action'] ) ) ) {
		return null;
	}

	if ( ! fafar_ppgcf_can_manage_schedules() ) {
		return new WP_Error( 'forbidden', 'Você não tem permissão para alterar os períodos de inscrição.' );
	}

	$nonce = isset( $_POST['fafar_ppgcf_schedule_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fafar_ppgcf_schedule_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'fafar_ppgcf_save_schedules' ) ) {
		return new WP_Error( 'invalid_nonce', 'A sessão expirou. Atualize a página e tente novamente.' );
	}

	$posted = isset( $_POST['fafar_ppgcf_schedule'] ) && is_array( $_POST['fafar_ppgcf_schedule'] ) ? wp_unslash( $_POST['fafar_ppgcf_schedule'] ) : array();
	$clean  = array();

	foreach ( array( 'production', 'test' ) as $context ) {
		$context_values = isset( $posted[ $context ] ) && is_array( $posted[ $context ] ) ? $posted[ $context ] : array();
		$opens_at       = isset( $context_values['opens_at'] ) && is_string( $context_values['opens_at'] ) ? sanitize_text_field( $context_values['opens_at'] ) : '';
		$closes_at      = isset( $context_values['closes_at'] ) && is_string( $context_values['closes_at'] ) ? sanitize_text_field( $context_values['closes_at'] ) : '';

		if ( ( '' === $opens_at ) !== ( '' === $closes_at ) ) {
			return new WP_Error( 'incomplete_period', 'Preencha a abertura e o fechamento de cada período, ou deixe os dois campos vazios para desativá-lo.' );
		}

		if ( '' !== $opens_at ) {
			$open_date  = fafar_ppgcf_parse_schedule_datetime( $opens_at );
			$close_date = fafar_ppgcf_parse_schedule_datetime( $closes_at );
			if ( ! $open_date || ! $close_date ) {
				return new WP_Error( 'invalid_period', 'Informe datas e horários válidos para os períodos de inscrição.' );
			}
			if ( $open_date >= $close_date ) {
				return new WP_Error( 'invalid_order', 'O fechamento deve ser posterior à abertura em cada período.' );
			}
		}

		$clean[ $context ] = array(
			'opens_at'  => $opens_at,
			'closes_at' => $closes_at,
		);
	}

	update_option( 'fafar_ppgcf_registration_schedules', $clean, false );
	return true;
}

/**
 * Registra as inscrições como um tipo de conteúdo não público, gerenciável
 * no painel e consultável pela página protegida de candidatos.
 */
function fafar_ppgcf_register_candidate_post_type() {
	register_post_type(
		'ppgcf_candidato',
		array(
			'labels' => array(
				'name'          => 'Candidatos PPGCF',
				'singular_name' => 'Candidato PPGCF',
			),
			'public'              => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'author' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	if ( ! wp_next_scheduled( 'fafar_ppgcf_cleanup_pending_uploads' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'fafar_ppgcf_cleanup_pending_uploads' );
	}
}

/** Redireciona visitantes não autenticados para a tela de login. */
function fafar_ppgcf_protect_candidates_page() {
	if ( ! is_page( 'candidatos-ppgcf' ) ) {
		return;
	}

	nocache_headers();
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}
}

/** Entrega um documento apenas para usuários autenticados com link assinado. */
function fafar_ppgcf_download_document() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}

	$candidate_id = isset( $_GET['candidate_id'] ) ? absint( $_GET['candidate_id'] ) : 0;
	$field        = isset( $_GET['field'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', wp_unslash( $_GET['field'] ) ) : '';
	$nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if ( ! $candidate_id || ! $field || ! wp_verify_nonce( $nonce, 'fafar_ppgcf_download_' . $candidate_id . '_' . $field ) ) {
		wp_die( esc_html__( 'Link de documento inválido ou expirado.', 'institutional-astra-child-theme' ), '', array( 'response' => 403 ) );
	}

	$candidate = get_post( $candidate_id );
	$documents = get_post_meta( $candidate_id, '_ppgcf_documents', true );
	$documents = is_array( $documents ) ? $documents : array();
	if ( ! $candidate || 'ppgcf_candidato' !== $candidate->post_type || empty( $documents[ $field ]['path'] ) ) {
		wp_die( esc_html__( 'Documento não encontrado.', 'institutional-astra-child-theme' ), '', array( 'response' => 404 ) );
	}

	$base_path = realpath( fafar_ppgcf_candidate_directory( $candidate_id ) );
	$file_path = realpath( $documents[ $field ]['path'] );
	if ( ! $base_path || ! $file_path || 0 !== strpos( $file_path, trailingslashit( $base_path ) ) || ! is_readable( $file_path ) ) {
		wp_die( esc_html__( 'Documento não encontrado.', 'institutional-astra-child-theme' ), '', array( 'response' => 404 ) );
	}

	$filename = sanitize_file_name( $documents[ $field ]['name'] ?? basename( $file_path ) );
	$mime     = sanitize_mime_type( $documents[ $field ]['mime'] ?? 'application/octet-stream' );
	nocache_headers();
	header( 'Content-Type: ' . $mime );
	header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $filename ) . '"' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
	exit;
}

/** Campos de arquivo permitidos e seus formatos. */
function fafar_ppgcf_file_rules() {
	$rules = array(
		'ArquivoFoto' => array( 'jpg', 'jpeg', 'png' ),
	);

	$pdf_fields = array(
		'ArquivoCertidaoNascimentoCasadoSeparacao',
		'ArquivoLattes',
		'ArquivoPublicacoesAtividades',
		'ArquivoCertidaoQuitacaoEleitoral',
		'ArquivoComprovanteResidencia',
		'ArquivoTabelaPontuacaoCurriculo',
		'ArquivoComprovanteMilitar',
		'ArquivoIdentidade',
		'ArquivoCPF',
		'ArquivoPassaporte',
		'ArquivoDiplomaGraduacao',
		'ArquivoProjetoTese',
		'ArquivoDiplomaMestrado',
		'ArquivoHistoricoPosGraduacao',
		'ArquivoAutodeclaracaoEtnicoRacial',
		'ArquivoAutorreconhecimentoIndigena',
		'ArquivoDeclaracaoLiderancaIndigena',
		'ArquivoDeclaracaoFunai',
		'ArquivoAutodeclaracaoDeficiente',
		'ArquivoRelatorioMedico',
		'ArquivoCondicoesEspeciaisProva',
		'ArquivoExameLinguaInglesa',
	);

	foreach ( $pdf_fields as $field ) {
		$rules[ $field ] = array( 'pdf' );
	}

	return $rules;
}

/** Lista oficial das unidades federativas brasileiras para o formulário. */
function fafar_ppgcf_brazilian_states() {
	return array(
		'AC' => 'Acre',
		'AL' => 'Alagoas',
		'AP' => 'Amapá',
		'AM' => 'Amazonas',
		'BA' => 'Bahia',
		'CE' => 'Ceará',
		'DF' => 'Distrito Federal',
		'ES' => 'Espírito Santo',
		'GO' => 'Goiás',
		'MA' => 'Maranhão',
		'MT' => 'Mato Grosso',
		'MS' => 'Mato Grosso do Sul',
		'MG' => 'Minas Gerais',
		'PA' => 'Pará',
		'PB' => 'Paraíba',
		'PR' => 'Paraná',
		'PE' => 'Pernambuco',
		'PI' => 'Piauí',
		'RJ' => 'Rio de Janeiro',
		'RN' => 'Rio Grande do Norte',
		'RS' => 'Rio Grande do Sul',
		'RO' => 'Rondônia',
		'RR' => 'Roraima',
		'SC' => 'Santa Catarina',
		'SP' => 'São Paulo',
		'SE' => 'Sergipe',
		'TO' => 'Tocantins',
	);
}

function fafar_ppgcf_pending_directory() {
	$upload_dir = wp_upload_dir();
	return trailingslashit( $upload_dir['basedir'] ) . 'ppgcf-pending';
}

function fafar_ppgcf_candidate_directory( $post_id ) {
	$upload_dir = wp_upload_dir();
	return trailingslashit( $upload_dir['basedir'] ) . 'ppgcf-candidatos/' . absint( $post_id );
}

/** Remove arquivos temporários cujo token já expirou. */
function fafar_ppgcf_cleanup_pending_uploads() {
	$directory = fafar_ppgcf_pending_directory();
	$files    = is_dir( $directory ) ? glob( trailingslashit( $directory ) . '*' ) : array();
	$expires  = time() - ( 2 * HOUR_IN_SECONDS );

	if ( ! is_array( $files ) ) {
		return;
	}

	foreach ( $files as $file ) {
		if ( is_file( $file ) && @filemtime( $file ) < $expires ) {
			@unlink( $file );
		}
	}
}

/** Retorna o período identificado pela requisição AJAX. */
function fafar_ppgcf_request_schedule_context() {
	$context = isset( $_POST['schedule_context'] ) ? sanitize_key( wp_unslash( $_POST['schedule_context'] ) ) : 'production';
	return in_array( $context, array( 'production', 'test' ), true ) ? $context : 'production';
}

/**
 * Permite notificações somente quando o ambiente foi explicitamente
 * configurado como produção.
 *
 * O WordPress assume "production" quando WP_ENVIRONMENT_TYPE não existe.
 * Exigir também a constante evita que uma configuração ausente habilite o
 * envio por engano em uma instalação local ou de desenvolvimento.
 */
function fafar_ppgcf_email_delivery_enabled() {
	return defined( 'WP_ENVIRONMENT_TYPE' )
		&& 'production' === WP_ENVIRONMENT_TYPE
		&& 'production' === wp_get_environment_type();
}

/** Retorna a chave de metadados usada por cada uma das duas notificações. */
function fafar_ppgcf_email_notification_meta_key( $notification_type ) {
	$keys = array(
		'candidate'   => '_ppgcf_hermes_candidate_notification',
		'secretariat' => '_ppgcf_hermes_secretariat_notification',
	);

	return isset( $keys[ $notification_type ] ) ? $keys[ $notification_type ] : '';
}

/** Monta uma linha segura da tabela de resumo usada nos e-mails. */
function fafar_ppgcf_email_summary_row( $label, $value ) {
	return sprintf(
		'<tr><th scope="row" style="padding:8px 12px;text-align:left;border:1px solid #dcdcde;background:#f6f7f7;">%1$s</th><td style="padding:8px 12px;border:1px solid #dcdcde;">%2$s</td></tr>',
		esc_html( $label ),
		esc_html( $value )
	);
}

/** Envolve o conteúdo no HTML completo esperado pelo Hermes. */
function fafar_ppgcf_email_document( $content ) {
	return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#1d2327;line-height:1.5;">' .
		'<div style="max-width:680px;margin:0 auto;">' . $content . '</div></body></html>';
}

/** Limita e normaliza assuntos antes de enviá-los à API. */
function fafar_ppgcf_email_subject( $subject ) {
	return wp_html_excerpt( sanitize_text_field( $subject ), 240, '…' );
}

/**
 * Cria o payload da confirmação do candidato ou da notificação da secretaria.
 */
function fafar_ppgcf_build_email_notification( $post_id, $notification_type ) {
	$post_id = absint( $post_id );
	$post    = get_post( $post_id );

	if ( ! $post || 'ppgcf_candidato' !== $post->post_type ) {
		return new WP_Error( 'invalid_candidate', 'A inscrição associada à notificação não existe.' );
	}

	$data = get_post_meta( $post_id, '_ppgcf_data', true );
	if ( ! is_array( $data ) || empty( $data['NomeCompleto'] ) || empty( $data['Email'] ) || ! is_email( $data['Email'] ) ) {
		return new WP_Error( 'invalid_candidate_data', 'Os dados da inscrição não permitem montar a notificação.' );
	}

	$context      = get_post_meta( $post_id, '_ppgcf_form_context', true );
	$is_test      = 'test' === $context;
	$subject_tag  = $is_test ? '[TESTE] ' : '';
	$test_notice  = $is_test
		? '<p style="padding:12px;border-left:4px solid #dba617;background:#fcf9e8;"><strong>Atenção:</strong> esta inscrição foi enviada pela página de teste do formulário.</p>'
		: '';
	$submitted_at = get_post_meta( $post_id, '_ppgcf_submitted_at', true );
	$submitted_at = $submitted_at ? mysql2date( 'd/m/Y H:i', $submitted_at ) : get_the_date( 'd/m/Y H:i', $post_id );
	$summary_rows  = fafar_ppgcf_email_summary_row( 'Protocolo', '#' . $post_id );
	$summary_rows .= fafar_ppgcf_email_summary_row( 'Candidato(a)', $data['NomeCompleto'] );
	$summary_rows .= fafar_ppgcf_email_summary_row( 'Data da inscrição', $submitted_at );
	$summary_rows .= fafar_ppgcf_email_summary_row( 'Nível da vaga', $data['NivelDaVaga'] ?? '—' );
	$summary_rows .= fafar_ppgcf_email_summary_row( 'Tipo de vaga', $data['TipoDaVaga'] ?? '—' );
	$summary       = '<table role="presentation" style="border-collapse:collapse;width:100%;margin:20px 0;">' . $summary_rows . '</table>';

	if ( 'candidate' === $notification_type ) {
		$content  = '<h1 style="font-size:22px;">Confirmação de inscrição no PPGCF</h1>';
		$content .= $test_notice;
		$content .= '<p>Olá, <strong>' . esc_html( $data['NomeCompleto'] ) . '</strong>.</p>';
		$content .= '<p>Sua inscrição no processo seletivo do Programa de Pós-Graduação em Ciências Farmacêuticas foi recebida com sucesso.</p>';
		$content .= $summary;
		$content .= '<p>Guarde esta mensagem como comprovante do recebimento da inscrição.</p>';
		$content .= '<p>Atenciosamente,<br>Secretaria do Programa de Pós-Graduação em Ciências Farmacêuticas</p>';

		return array(
			'to_addresses' => array( $data['Email'] ),
			'reply_to'     => 'colposfar@farmacia.ufmg.br',
			'subject'      => fafar_ppgcf_email_subject( $subject_tag . 'Confirmação de inscrição — PPGCF — Protocolo #' . $post_id ),
			'body_html'    => fafar_ppgcf_email_document( $content ),
		);
	}

	if ( 'secretariat' === $notification_type ) {
		$secretariat_rows  = $summary_rows;
		$secretariat_rows .= fafar_ppgcf_email_summary_row( 'E-mail', $data['Email'] );
		$secretariat_rows .= fafar_ppgcf_email_summary_row( 'Telefone', $data['Telefone'] ?? '—' );
		$summary           = '<table role="presentation" style="border-collapse:collapse;width:100%;margin:20px 0;">' . $secretariat_rows . '</table>';
		$content  = '<h1 style="font-size:22px;">Nova inscrição no PPGCF</h1>';
		$content .= $test_notice;
		$content .= '<p>Uma nova inscrição foi registrada pelo formulário do PPGCF.</p>';
		$content .= $summary;
		$content .= '<p><a href="' . esc_url( home_url( '/candidatos-ppgcf/' ) ) . '">Acessar a área protegida de candidatos</a></p>';

		return array(
			'to_addresses' => array( 'colposfar@farmacia.ufmg.br' ),
			'reply_to'     => $data['Email'],
			'subject'      => fafar_ppgcf_email_subject( $subject_tag . 'Nova inscrição PPGCF — #' . $post_id . ' — ' . $data['NomeCompleto'] ),
			'body_html'    => fafar_ppgcf_email_document( $content ),
		);
	}

	return new WP_Error( 'invalid_notification_type', 'Tipo de notificação inválido.' );
}

/** Enfileira uma mensagem diretamente na API interna do Hermes. */
function fafar_ppgcf_send_hermes_message( $payload, $idempotency_key ) {
	if ( ! fafar_ppgcf_email_delivery_enabled() ) {
		return new WP_Error( 'email_delivery_disabled', 'O envio de e-mails está desabilitado neste ambiente.' );
	}

	$api_url   = defined( 'HERMES_API_URL' ) ? HERMES_API_URL : '';
	$api_token = defined( 'HERMES_API_TOKEN' ) ? HERMES_API_TOKEN : '';

	if ( ! is_string( $api_url ) || '' === trim( $api_url ) || ! is_string( $api_token ) || '' === trim( $api_token ) ) {
		return new WP_Error( 'hermes_not_configured', 'O Hermes não está configurado para este site.' );
	}

	if ( ! wp_is_uuid( $idempotency_key, 4 ) ) {
		return new WP_Error( 'invalid_idempotency_key', 'A chave de idempotência da notificação é inválida.' );
	}

	$response = wp_remote_post(
		rtrim( $api_url, '/' ) . '/api/v1/messages',
		array(
			'headers'     => array(
				'Authorization'   => 'Bearer ' . $api_token,
				'Content-Type'    => 'application/json; charset=utf-8',
				'Idempotency-Key' => $idempotency_key,
			),
			'body'        => wp_json_encode( $payload ),
			'data_format' => 'body',
			'timeout'     => 10,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'hermes_connection_error', 'Não foi possível conectar ao Hermes.', $response->get_error_message() );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 202 !== $response_code ) {
		return new WP_Error( 'hermes_rejected_message', 'O Hermes recusou a mensagem.', array( 'status' => $response_code ) );
	}

	$response_data = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'id'     => is_array( $response_data ) && ! empty( $response_data['id'] ) ? sanitize_text_field( $response_data['id'] ) : '',
		'status' => 'accepted',
	);
}

/** Agenda outra tentativa, com intervalos progressivos e no máximo cinco envios. */
function fafar_ppgcf_schedule_email_retry( $post_id, $notification_type, $attempts ) {
	$delays = array(
		5 * MINUTE_IN_SECONDS,
		15 * MINUTE_IN_SECONDS,
		HOUR_IN_SECONDS,
		4 * HOUR_IN_SECONDS,
	);

	if ( ! fafar_ppgcf_email_delivery_enabled() || $attempts >= 5 ) {
		return;
	}

	$args = array( absint( $post_id ), $notification_type );
	if ( wp_next_scheduled( 'fafar_ppgcf_retry_email_notification', $args ) ) {
		return;
	}

	$delay = isset( $delays[ $attempts - 1 ] ) ? $delays[ $attempts - 1 ] : end( $delays );
	wp_schedule_single_event( time() + $delay, 'fafar_ppgcf_retry_email_notification', $args );
}

/** Processa uma das notificações e persiste seu estado para auditoria e retry. */
function fafar_ppgcf_process_email_notification( $post_id, $notification_type ) {
	$meta_key = fafar_ppgcf_email_notification_meta_key( $notification_type );
	if ( ! $meta_key ) {
		return new WP_Error( 'invalid_notification_type', 'Tipo de notificação inválido.' );
	}

	$state = get_post_meta( $post_id, $meta_key, true );
	$state = is_array( $state ) ? $state : array();
	if ( 'accepted' === ( $state['status'] ?? '' ) ) {
		return $state;
	}

	$idempotency_key = isset( $state['idempotency_key'] ) && wp_is_uuid( $state['idempotency_key'], 4 )
		? $state['idempotency_key']
		: wp_generate_uuid4();
	$attempts        = isset( $state['attempts'] ) ? absint( $state['attempts'] ) + 1 : 1;
	$state           = array_merge(
		$state,
		array(
			'idempotency_key' => $idempotency_key,
			'status'          => 'sending',
			'attempts'        => $attempts,
			'last_attempt_at' => current_time( 'mysql' ),
		)
	);

	// A chave precisa estar persistida antes da chamada HTTP para tornar retries seguros.
	if ( false === update_post_meta( $post_id, $meta_key, $state ) ) {
		$result = new WP_Error( 'notification_state_error', 'Não foi possível preparar o controle da notificação.' );
		error_log( sprintf( '[PPGCF Hermes] Could not persist notification state for application #%d (%s).', absint( $post_id ), $notification_type ) );
		return $result;
	}

	$payload = fafar_ppgcf_build_email_notification( $post_id, $notification_type );
	$result  = is_wp_error( $payload ) ? $payload : fafar_ppgcf_send_hermes_message( $payload, $idempotency_key );

	if ( is_wp_error( $result ) ) {
		$error_data                = $result->get_error_data();
		$state['status']           = 'failed';
		$state['last_error']       = substr( sanitize_text_field( $result->get_error_message() ), 0, 500 );
		$state['last_error_code']  = sanitize_key( $result->get_error_code() );
		$state['last_http_status'] = is_array( $error_data ) && isset( $error_data['status'] ) ? absint( $error_data['status'] ) : 0;
		update_post_meta( $post_id, $meta_key, $state );
		error_log( sprintf( '[PPGCF Hermes] Notification failed for application #%d (%s): %s', absint( $post_id ), $notification_type, $result->get_error_code() ) );
		fafar_ppgcf_schedule_email_retry( $post_id, $notification_type, $attempts );
		return $result;
	}

	$state['status']            = 'accepted';
	$state['hermes_message_id'] = $result['id'];
	$state['accepted_at']       = current_time( 'mysql' );
	$state['last_error']        = '';
	$state['last_error_code']   = '';
	$state['last_http_status']  = 202;
	if ( false === update_post_meta( $post_id, $meta_key, $state ) ) {
		$result = new WP_Error( 'notification_state_error', 'O Hermes aceitou a mensagem, mas não foi possível atualizar o controle da notificação.' );
		error_log( sprintf( '[PPGCF Hermes] Could not persist accepted state for application #%d (%s).', absint( $post_id ), $notification_type ) );
		fafar_ppgcf_schedule_email_retry( $post_id, $notification_type, $attempts );
		return $result;
	}

	return $state;
}

/** Callback das tentativas agendadas; o bloqueio por ambiente é revalidado. */
function fafar_ppgcf_retry_email_notification( $post_id, $notification_type ) {
	if ( ! fafar_ppgcf_email_delivery_enabled() ) {
		return;
	}

	fafar_ppgcf_process_email_notification( absint( $post_id ), sanitize_key( $notification_type ) );
}

/** Dispara as duas mensagens ou registra que foram suprimidas fora de produção. */
function fafar_ppgcf_dispatch_email_notifications( $post_id ) {
	$notification_types = array( 'candidate', 'secretariat' );

	if ( ! fafar_ppgcf_email_delivery_enabled() ) {
		$environment = defined( 'WP_ENVIRONMENT_TYPE' ) ? wp_get_environment_type() : 'undefined';
		foreach ( $notification_types as $notification_type ) {
			update_post_meta(
				$post_id,
				fafar_ppgcf_email_notification_meta_key( $notification_type ),
				array(
					'status'      => 'suppressed_non_production',
					'environment' => $environment,
					'updated_at'  => current_time( 'mysql' ),
				)
			);
		}
		return;
	}

	foreach ( $notification_types as $notification_type ) {
		fafar_ppgcf_process_email_notification( $post_id, $notification_type );
	}
}

/** Envia uma resposta de erro consistente, opcionalmente ligada a um campo. */
function fafar_ppgcf_send_json_error( $message, $status = 400, $field = '', $code = '' ) {
	$data = array( 'message' => $message );

	if ( $field ) {
		$data['field'] = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $field );
	}
	if ( $code ) {
		$data['code'] = sanitize_key( $code );
	}

	wp_send_json_error( $data, $status );
}

/** Valida o nonce específico do formulário de produção ou de testes. */
function fafar_ppgcf_verify_nonce() {
	$context = fafar_ppgcf_request_schedule_context();
	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ppgcf_form_' . $context ) ) {
		fafar_ppgcf_send_json_error( 'A sessão do formulário expirou. Atualize a página e tente novamente.', 403, '', 'session_expired' );
	}

	return $context;
}

/** Impede uploads e inscrições fora do período configurado. */
function fafar_ppgcf_verify_registration_period( $context ) {
	$state = fafar_ppgcf_registration_schedule_state( $context );
	if ( 'open' === $state['status'] ) {
		return;
	}

	$message = 'O formulário de inscrição não está disponível neste momento.';
	if ( 'upcoming' === $state['status'] || 'scheduled' === $state['status'] ) {
		$message = 'O período de inscrições ainda não começou.';
	} elseif ( 'closed' === $state['status'] ) {
		$message = 'O prazo para envio expirou. A inscrição não foi aceita porque o período de inscrições foi encerrado.';
	}

	fafar_ppgcf_send_json_error( $message, 403, '', 'registration_period_closed' );
}

/** Recebe um arquivo individual antes da submissão do formulário. */
function fafar_ppgcf_upload_file() {
	$context = fafar_ppgcf_verify_nonce();
	fafar_ppgcf_verify_registration_period( $context );

	$field = isset( $_POST['field_name'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', wp_unslash( $_POST['field_name'] ) ) : '';
	$rules = fafar_ppgcf_file_rules();

	if ( ! $field || ! isset( $rules[ $field ] ) || empty( $_FILES['file'] ) ) {
		fafar_ppgcf_send_json_error( 'Arquivo ou campo inválido.', 400, $field, 'invalid_upload' );
	}

	$file = $_FILES['file'];
	if ( ! empty( $file['error'] ) ) {
		fafar_ppgcf_send_json_error( 'Não foi possível receber o arquivo.', 400, $field, 'upload_receive_error' );
	}

	if ( ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > 8 * MB_IN_BYTES ) {
		fafar_ppgcf_send_json_error( 'Cada arquivo deve ter no máximo 8 MB.', 400, $field, 'upload_too_large' );
	}

	$original_name = sanitize_file_name( wp_unslash( $file['name'] ) );
	$file_type     = wp_check_filetype( $original_name );
	$extension     = strtolower( (string) $file_type['ext'] );
	$allowed       = $rules[ $field ];

	if ( ! in_array( $extension, $allowed, true ) ) {
		fafar_ppgcf_send_json_error( 'O formato do arquivo não é aceito para este campo.', 400, $field, 'invalid_upload_extension' );
	}

	$finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : false;
	$mime  = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : $file_type['type'];
	if ( $finfo ) {
		finfo_close( $finfo );
	}

	$allowed_mimes = 'ArquivoFoto' === $field ? array( 'image/jpeg', 'image/png' ) : array( 'application/pdf' );
	if ( ! in_array( $mime, $allowed_mimes, true ) ) {
		fafar_ppgcf_send_json_error( 'O conteúdo do arquivo não corresponde ao formato informado.', 400, $field, 'invalid_upload_mime' );
	}

	$directory = fafar_ppgcf_pending_directory();
	if ( ! wp_mkdir_p( $directory ) ) {
		fafar_ppgcf_send_json_error( 'Não foi possível preparar o armazenamento temporário.', 500, $field, 'upload_storage_error' );
	}

	$token   = wp_generate_uuid4();
	$filename = wp_unique_filename( $directory, $token . '-' . $original_name );
	$path     = trailingslashit( $directory ) . $filename;

	if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
		fafar_ppgcf_send_json_error( 'Não foi possível armazenar o arquivo.', 500, $field, 'upload_storage_error' );
	}

	set_transient(
		'fafar_ppgcf_upload_' . $token,
		array(
			'field'          => $field,
			'path'           => $path,
			'original_name'  => $original_name,
			'mime'           => $mime,
			'size'           => (int) $file['size'],
			'uploaded_at'    => time(),
		),
		2 * HOUR_IN_SECONDS
	);

	wp_send_json_success(
		array(
			'token' => $token,
			'name'  => $original_name,
			'size'  => (int) $file['size'],
		)
	);
}

/**
 * Desfaz uma inscrição parcial e restaura os uploads que já foram movidos.
 *
 * Os tokens temporários permanecem válidos quando a restauração é possível,
 * permitindo uma nova tentativa sem obrigar o candidato a selecionar tudo.
 */
function fafar_ppgcf_rollback_submission( $post_id, $moved_uploads, $destination ) {
	foreach ( array_reverse( $moved_uploads ) as $moved_upload ) {
		$source   = $moved_upload['source'] ?? '';
		$target   = $moved_upload['target'] ?? '';
		$token    = $moved_upload['token'] ?? '';
		$restored = false;

		if ( $source && $target && is_file( $target ) ) {
			$restored = @rename( $target, $source );
			if ( ! $restored ) {
				$restored = @copy( $target, $source );
				if ( $restored ) {
					@unlink( $target );
				}
			}
		}

		if ( ! $restored ) {
			if ( $target && is_file( $target ) ) {
				@unlink( $target );
			}
			if ( $token ) {
				delete_transient( 'fafar_ppgcf_upload_' . $token );
			}
		}
	}

	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $destination && is_dir( $destination ) ) {
		@rmdir( $destination );
	}
}

function fafar_ppgcf_request_data( $key ) {
	$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	$data  = json_decode( $value, true );
	return is_array( $data ) ? $data : array();
}

function fafar_ppgcf_clean_candidate_data( $data ) {
	$clean = array();
	foreach ( $data as $key => $value ) {
		$key = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $key );
		if ( is_scalar( $value ) ) {
			$clean[ $key ] = 'Email' === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
		}
	}
	return $clean;
}

function fafar_ppgcf_required_fields( $data ) {
	$required = array( 'NivelDaVaga', 'TipoDaVaga', 'NomeCompleto', 'DataDeNascimento', 'Email', 'Sexo', 'Telefone', 'Naturalidade', 'Raca', 'CEP', 'Logradouro', 'NumeroResidencia', 'Bairro', 'Cidade', 'Estado' );
	$type = isset( $data['TipoDaVaga'] ) ? $data['TipoDaVaga'] : '';

	if ( 'Estrangeiro' === $type ) {
		$required[] = 'Passaporte';
	} else {
		$required = array_merge( $required, array( 'CPF', 'RG', 'OrgaoEmissorRG' ) );
	}

	return $required;
}

function fafar_ppgcf_required_files( $data ) {
	$files = array(
		'ArquivoFoto',
		'ArquivoCertidaoNascimentoCasadoSeparacao',
		'ArquivoLattes',
		'ArquivoPublicacoesAtividades',
		'ArquivoCertidaoQuitacaoEleitoral',
		'ArquivoComprovanteResidencia',
		'ArquivoTabelaPontuacaoCurriculo',
		'ArquivoDiplomaGraduacao',
		'ArquivoExameLinguaInglesa',
	);

	$type  = isset( $data['TipoDaVaga'] ) ? $data['TipoDaVaga'] : '';
	$level = isset( $data['NivelDaVaga'] ) ? $data['NivelDaVaga'] : '';
	$sex   = isset( $data['Sexo'] ) ? $data['Sexo'] : '';

	if ( 'Estrangeiro' === $type ) {
		$files[] = 'ArquivoPassaporte';
	} else {
		$files = array_merge( $files, array( 'ArquivoIdentidade', 'ArquivoCPF' ) );
		if ( 'Masculino' === $sex ) {
			$files[] = 'ArquivoComprovanteMilitar';
		}
	}

	if ( 'Doutorado' === $level ) {
		$files = array_merge( $files, array( 'ArquivoProjetoTese', 'ArquivoDiplomaMestrado', 'ArquivoHistoricoPosGraduacao' ) );
	}

	$vacancy_files = array(
		'Ampla concorrência'               => array( 'ArquivoAutodeclaracaoEtnicoRacial' ),
		'Estrangeiro'                      => array( 'ArquivoAutodeclaracaoEtnicoRacial' ),
		'Reserva de vagas para negros'     => array( 'ArquivoAutodeclaracaoEtnicoRacial' ),
		'Reserva de vagas para indígenas'  => array( 'ArquivoAutorreconhecimentoIndigena', 'ArquivoDeclaracaoLiderancaIndigena', 'ArquivoDeclaracaoFunai' ),
		'Reserva de vagas para pessoas com deficiência' => array( 'ArquivoAutodeclaracaoDeficiente', 'ArquivoRelatorioMedico', 'ArquivoCondicoesEspeciaisProva' ),
	);

	if ( isset( $vacancy_files[ $type ] ) ) {
		$files = array_merge( $files, $vacancy_files[ $type ] );
	}

	return $files;
}

/** Salva os dados somente depois que todos os tokens de arquivo forem validados. */
function fafar_ppgcf_submit_form() {
	$context = fafar_ppgcf_verify_nonce();
	fafar_ppgcf_verify_registration_period( $context );

	$data           = fafar_ppgcf_clean_candidate_data( fafar_ppgcf_request_data( 'candidate_data' ) );
	$uploaded_files = fafar_ppgcf_request_data( 'uploaded_files' );
	$rules          = fafar_ppgcf_file_rules();

	foreach ( fafar_ppgcf_required_fields( $data ) as $field ) {
		if ( empty( $data[ $field ] ) ) {
			fafar_ppgcf_send_json_error( 'Este campo é obrigatório.', 400, $field, 'required_field' );
		}
	}

	$valid_levels = array( 'Doutorado' );
	$valid_types  = array( 'Ampla concorrência', 'Estrangeiro', 'Reserva de vagas para negros', 'Reserva de vagas para indígenas', 'Reserva de vagas para pessoas com deficiência' );
	$valid_sexes  = array( 'Feminino', 'Masculino' );
	if ( ! in_array( $data['NivelDaVaga'], $valid_levels, true ) ) {
		fafar_ppgcf_send_json_error( 'Nível de vaga inválido.', 400, 'NivelDaVaga', 'invalid_option' );
	}
	if ( ! in_array( $data['TipoDaVaga'], $valid_types, true ) ) {
		fafar_ppgcf_send_json_error( 'Tipo de vaga inválido.', 400, 'TipoDaVaga', 'invalid_option' );
	}
	if ( ! in_array( $data['Sexo'], $valid_sexes, true ) ) {
		fafar_ppgcf_send_json_error( 'Opção de sexo inválida.', 400, 'Sexo', 'invalid_option' );
	}

	if ( ! is_email( $data['Email'] ) ) {
		fafar_ppgcf_send_json_error( 'Informe um e-mail válido.', 400, 'Email', 'invalid_email' );
	}

	if ( ! array_key_exists( $data['Estado'], fafar_ppgcf_brazilian_states() ) ) {
		fafar_ppgcf_send_json_error( 'Selecione um estado válido.', 400, 'Estado', 'invalid_state' );
	}

	$required_files = fafar_ppgcf_required_files( $data );
	foreach ( $required_files as $field ) {
		if ( empty( $uploaded_files[ $field ] ) ) {
			fafar_ppgcf_send_json_error( 'Faça o upload deste documento antes de enviar.', 400, $field, 'required_upload' );
		}
	}

	$documents = array();
	foreach ( $uploaded_files as $field => $token ) {
		if ( ! isset( $rules[ $field ] ) || ! is_string( $token ) || ! preg_match( '/^[a-f0-9-]{36}$/i', $token ) ) {
			fafar_ppgcf_send_json_error( 'O upload deste campo é inválido. Envie o documento novamente.', 400, $field, 'invalid_upload_token' );
		}

		$upload = get_transient( 'fafar_ppgcf_upload_' . $token );
		if ( ! is_array( $upload ) || $upload['field'] !== $field || empty( $upload['path'] ) || ! file_exists( $upload['path'] ) ) {
			fafar_ppgcf_send_json_error( 'Este upload expirou. Envie o documento novamente.', 400, $field, 'upload_expired' );
		}

		$documents[ $field ] = $upload;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'ppgcf_candidato',
			'post_status' => 'publish',
			'post_title'  => wp_strip_all_tags( $data['NomeCompleto'] ),
			'post_author' => get_current_user_id(),
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_send_json_error( array( 'message' => 'Não foi possível registrar a inscrição.' ), 500 );
	}

	$destination = fafar_ppgcf_candidate_directory( $post_id );
	if ( ! wp_mkdir_p( $destination ) ) {
		wp_delete_post( $post_id, true );
		wp_send_json_error( array( 'message' => 'Não foi possível preparar os documentos da inscrição.' ), 500 );
	}

	$stored_documents = array();
	$moved_uploads    = array();
	foreach ( $documents as $field => $upload ) {
		$filename = wp_unique_filename( $destination, $upload['original_name'] );
		$target   = trailingslashit( $destination ) . $filename;
		$moved    = @rename( $upload['path'], $target );
		if ( ! $moved ) {
			$moved = @copy( $upload['path'], $target );
			if ( $moved ) {
				@unlink( $upload['path'] );
			}
		}

		if ( ! $moved ) {
			if ( is_file( $target ) ) {
				@unlink( $target );
			}
			fafar_ppgcf_rollback_submission( $post_id, $moved_uploads, $destination );
			fafar_ppgcf_send_json_error( 'Não foi possível concluir o armazenamento deste documento.', 500, $field, 'document_storage_error' );
		}

		$moved_uploads[] = array(
			'source' => $upload['path'],
			'target' => $target,
			'token'  => $uploaded_files[ $field ],
		);
		$stored_documents[ $field ] = array(
			'name' => $upload['original_name'],
			'path' => $target,
			'mime' => $upload['mime'],
			'size' => $upload['size'],
		);
	}

	$data_saved         = update_post_meta( $post_id, '_ppgcf_data', $data );
	$documents_saved    = update_post_meta( $post_id, '_ppgcf_documents', $stored_documents );
	$submitted_at_saved = update_post_meta( $post_id, '_ppgcf_submitted_at', current_time( 'mysql' ) );
	$context_saved      = update_post_meta( $post_id, '_ppgcf_form_context', $context );

	if ( false === $data_saved || false === $documents_saved || false === $submitted_at_saved || false === $context_saved ) {
		fafar_ppgcf_rollback_submission( $post_id, $moved_uploads, $destination );
		fafar_ppgcf_send_json_error( 'Não foi possível concluir a gravação da inscrição. Tente novamente.', 500, '', 'persistence_error' );
	}

	foreach ( $uploaded_files as $token ) {
		delete_transient( 'fafar_ppgcf_upload_' . $token );
	}

	// A inscrição já está concluída; falhas de e-mail não devem apagá-la nem
	// induzir o candidato a reenviar o formulário e criar uma duplicidade.
	fafar_ppgcf_dispatch_email_notifications( $post_id );

	wp_send_json_success(
		array(
			'candidate_id' => $post_id,
			'message'      => 'Inscrição registrada com sucesso.',
		)
	);
}
