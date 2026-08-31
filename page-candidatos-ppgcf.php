<?php
/**
 * Lista de candidatos inscritos no PPGCF.
 *
 * @package Institutional_Astra_Child_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	auth_redirect();
	exit;
}

$schedule_save_result = fafar_ppgcf_save_registration_schedules_from_request();
$schedules             = fafar_ppgcf_registration_schedules();
$schedule_states       = array(
	'production' => fafar_ppgcf_registration_schedule_state( 'production' ),
	'test'       => fafar_ppgcf_registration_schedule_state( 'test' ),
);
$schedule_labels       = array(
	'not_configured' => 'Desativado',
	'scheduled'      => 'Agendado',
	'upcoming'       => 'Abre em breve',
	'open'           => 'Ativo agora',
	'closed'         => 'Encerrado',
);

$candidates_style_path = get_stylesheet_directory() . '/assets/css/candidatos-ppgcf.css';
wp_enqueue_style(
	'fafar-candidatos-ppgcf',
	get_stylesheet_directory_uri() . '/assets/css/candidatos-ppgcf.css',
	array( 'astra-theme-css' ),
	file_exists( $candidates_style_path ) ? filemtime( $candidates_style_path ) : '1.0.0'
);

$candidates = new WP_Query(
	array(
		'post_type'      => 'ppgcf_candidato',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$candidate_labels = array(
	'NivelDaVaga'       => 'Nível da vaga',
	'TipoDaVaga'        => 'Tipo de vaga',
	'NomeCompleto'      => 'Nome completo',
	'DataDeNascimento'  => 'Data de nascimento',
	'Email'             => 'E-mail',
	'Sexo'              => 'Sexo',
	'Telefone'          => 'Telefone',
	'Naturalidade'      => 'Naturalidade',
	'Raca'              => 'Raça',
	'CPF'               => 'CPF',
	'RG'                => 'RG',
	'OrgaoEmissorRG'    => 'Órgão emissor do RG',
	'Passaporte'        => 'Passaporte',
	'CEP'               => 'CEP',
	'Logradouro'        => 'Logradouro',
	'NumeroResidencia'  => 'Número',
	'ComplementoResidencia' => 'Complemento',
	'Bairro'            => 'Bairro',
	'Cidade'            => 'Cidade',
	'Estado'            => 'Estado',
);

$document_labels = array(
	'ArquivoFoto'                            => 'Foto atual 3x4',
	'ArquivoCertidaoNascimentoCasadoSeparacao' => 'Certidão de nascimento, casamento ou separação',
	'ArquivoLattes'                          => 'Curriculum vitae ou Curriculum Lattes',
	'ArquivoPublicacoesAtividades'           => 'Comprovantes das publicações/atividades do currículo',
	'ArquivoCertidaoQuitacaoEleitoral'       => 'Certidão de quitação eleitoral',
	'ArquivoComprovanteResidencia'           => 'Comprovante de residência',
	'ArquivoTabelaPontuacaoCurriculo'        => 'Tabela de pontuação do currículo',
	'ArquivoComprovanteMilitar'              => 'Comprovante de obrigações militares',
	'ArquivoIdentidade'                      => 'Identidade',
	'ArquivoCPF'                             => 'CPF (documento)',
	'ArquivoPassaporte'                      => 'Passaporte (documento)',
	'ArquivoDiplomaGraduacao'                => 'Diploma de graduação',
	'ArquivoProjetoTese'                     => 'Pré-projeto de tese',
	'ArquivoDiplomaMestrado'                 => 'Diploma de mestrado ou ata de defesa',
	'ArquivoHistoricoPosGraduacao'           => 'Histórico escolar da pós-graduação',
	'ArquivoAutodeclaracaoEtnicoRacial'      => 'Autodeclaração étnico-racial',
	'ArquivoAutorreconhecimentoIndigena'     => 'Declaração de autorreconhecimento indígena',
	'ArquivoDeclaracaoLiderancaIndigena'     => 'Declaração de lideranças indígenas',
	'ArquivoDeclaracaoFunai'                 => 'Declaração da Funai',
	'ArquivoAutodeclaracaoDeficiente'        => 'Autodeclaração de pessoa com deficiência',
	'ArquivoRelatorioMedico'                 => 'Relatório do médico assistente',
	'ArquivoCondicoesEspeciaisProva'         => 'Formulário de condições especiais para a prova',
	'ArquivoExameLinguaInglesa'              => 'Comprovação de proficiência em língua inglesa',
);

get_header();
?>

<?php if ( astra_page_layout() === 'left-sidebar' ) : ?>
	<?php get_sidebar(); ?>
<?php endif; ?>

	<div id="primary" <?php astra_primary_class(); ?>>
		<?php astra_primary_content_top(); ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<?php astra_entry_before(); ?>
			<article <?php echo wp_kses_post( astra_attr( 'article-page', array( 'id' => 'post-' . get_the_ID(), 'class' => implode( ' ', get_post_class() ) ) ) ); ?>>
				<?php astra_entry_top(); ?>

				<header class="entry-header <?php astra_entry_header_class(); ?>">
					<?php astra_banner_elements_order(); ?>
				</header>

				<div class="entry-content clear fafar-candidatos-page-content">
					<?php astra_entry_content_before(); ?>
					<p class="fafar-candidatos-intro">Esta área é restrita a usuários autenticados e apresenta as inscrições recebidas pelo formulário do PPGCF.</p>

					<section class="fafar-schedule-section" aria-labelledby="fafar-schedule-title">
						<h2 id="fafar-schedule-title">Períodos dos formulários de inscrição</h2>
						<p>Defina quando cada formulário ficará disponível. As datas e os horários usam o fuso <strong><?php echo esc_html( wp_timezone_string() ); ?></strong>. Deixe os dois campos de um período vazios para desativá-lo.</p>

						<?php if ( true === $schedule_save_result ) : ?>
							<p class="fafar-schedule-notice is-success" role="status">Períodos atualizados com sucesso.</p>
						<?php elseif ( is_wp_error( $schedule_save_result ) ) : ?>
							<p class="fafar-schedule-notice is-error" role="alert"><?php echo esc_html( $schedule_save_result->get_error_message() ); ?></p>
						<?php endif; ?>

						<?php if ( fafar_ppgcf_can_manage_schedules() ) : ?>
							<form class="fafar-schedule-form" method="post" action="">
								<?php wp_nonce_field( 'fafar_ppgcf_save_schedules', 'fafar_ppgcf_schedule_nonce' ); ?>
								<input type="hidden" name="fafar_ppgcf_schedule_action" value="save">

								<div class="fafar-schedule-grid">
									<?php
									$schedule_sections = array(
										'production' => array(
											'title' => 'Formulário principal',
											'url'   => home_url( '/inscricao-ppgcf/' ),
										),
										'test'       => array(
											'title' => 'Formulário de testes',
											'url'   => home_url( '/inscricao-ppgcf-teste/' ),
										),
									);
									?>
									<?php foreach ( $schedule_sections as $context => $section ) : ?>
										<fieldset class="fafar-schedule-card">
											<legend><?php echo esc_html( $section['title'] ); ?></legend>
											<p class="fafar-schedule-status">Situação: <strong class="is-<?php echo esc_attr( $schedule_states[ $context ]['status'] ); ?>"><?php echo esc_html( $schedule_labels[ $schedule_states[ $context ]['status'] ] ); ?></strong> · <a href="<?php echo esc_url( $section['url'] ); ?>" target="_blank" rel="noopener noreferrer">abrir página</a></p>
											<div class="fafar-schedule-fields">
												<div>
													<label for="fafar-<?php echo esc_attr( $context ); ?>-opens-at">Abertura</label>
													<input id="fafar-<?php echo esc_attr( $context ); ?>-opens-at" name="fafar_ppgcf_schedule[<?php echo esc_attr( $context ); ?>][opens_at]" type="datetime-local" step="60" value="<?php echo esc_attr( $schedules[ $context ]['opens_at'] ); ?>">
												</div>
												<div>
													<label for="fafar-<?php echo esc_attr( $context ); ?>-closes-at">Fechamento</label>
													<input id="fafar-<?php echo esc_attr( $context ); ?>-closes-at" name="fafar_ppgcf_schedule[<?php echo esc_attr( $context ); ?>][closes_at]" type="datetime-local" step="60" value="<?php echo esc_attr( $schedules[ $context ]['closes_at'] ); ?>">
												</div>
											</div>
										</fieldset>
									<?php endforeach; ?>
								</div>

								<button class="fafar-schedule-submit" type="submit">Salvar períodos</button>
							</form>
						<?php else : ?>
							<p class="fafar-schedule-notice">Sua conta pode consultar as inscrições, mas não pode alterar os períodos.</p>
						<?php endif; ?>
					</section>

					<?php if ( $candidates->have_posts() ) : ?>
						<p class="fafar-candidatos-count"><strong><?php echo esc_html( $candidates->found_posts ); ?></strong> candidato(s) inscrito(s).</p>
						<div class="fafar-candidatos-accordion">
							<?php while ( $candidates->have_posts() ) : $candidates->the_post(); ?>
								<?php
								$candidate_id   = get_the_ID();
								$candidate_data = get_post_meta( $candidate_id, '_ppgcf_data', true );
								$documents      = get_post_meta( $candidate_id, '_ppgcf_documents', true );
								$candidate_data = is_array( $candidate_data ) ? $candidate_data : array();
								$documents      = is_array( $documents ) ? $documents : array();
								$city_state     = trim( ( $candidate_data['Cidade'] ?? '' ) . ( ! empty( $candidate_data['Estado'] ) ? ' / ' . $candidate_data['Estado'] : '' ) );
								?>
								<details class="fafar-candidate-item">
									<summary>
										<span class="fafar-candidate-name"><?php the_title(); ?></span>
										<span class="fafar-candidate-summary"><?php echo esc_html( ( $candidate_data['NivelDaVaga'] ?? '—' ) . ' · ' . ( $city_state ?: 'cidade não informada' ) ); ?></span>
										<span class="fafar-candidate-received-summary">Recebida em <?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></span>
									</summary>
									<div class="fafar-candidate-panel">
										<section class="fafar-candidate-section">
											<h2>Dados do candidato</h2>
											<dl class="fafar-candidate-data">
												<?php foreach ( $candidate_labels as $field => $label ) : ?>
													<?php if ( array_key_exists( $field, $candidate_data ) ) : ?>
														<dt><?php echo esc_html( $label ); ?></dt>
														<dd>
															<?php if ( 'Email' === $field && is_email( $candidate_data[ $field ] ) ) : ?>
																<a href="mailto:<?php echo esc_attr( $candidate_data[ $field ] ); ?>"><?php echo esc_html( $candidate_data[ $field ] ); ?></a>
															<?php else : ?>
																<?php echo esc_html( $candidate_data[ $field ] ?: '—' ); ?>
															<?php endif; ?>
														</dd>
													<?php endif; ?>
												<?php endforeach; ?>
												<?php foreach ( array_diff_key( $candidate_data, $candidate_labels ) as $field => $value ) : ?>
													<?php if ( is_scalar( $value ) ) : ?>
														<dt><?php echo esc_html( $field ); ?></dt>
														<dd><?php echo esc_html( $value ?: '—' ); ?></dd>
													<?php endif; ?>
												<?php endforeach; ?>
											</dl>
										</section>

										<section class="fafar-candidate-section">
											<h2>Documentos</h2>
											<div class="fafar-candidate-documents">
												<?php foreach ( $document_labels as $field => $label ) : ?>
												<?php $document = isset( $documents[ $field ] ) && is_array( $documents[ $field ] ) && ! empty( $documents[ $field ]['path'] ) && is_file( $documents[ $field ]['path'] ) ? $documents[ $field ] : null; ?>
													<div class="fafar-document-row">
														<span class="fafar-document-label"><?php echo esc_html( $label ); ?></span>
														<?php if ( $document ) : ?>
															<?php
															$download_url = add_query_arg(
																array(
																	'action'       => 'fafar_ppgcf_download_document',
																	'candidate_id' => $candidate_id,
																	'field'        => $field,
																),
																admin_url( 'admin-post.php' )
															);
															$download_url = wp_nonce_url( $download_url, 'fafar_ppgcf_download_' . $candidate_id . '_' . $field );
															?>
															<a class="fafar-document-link" href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer">Abrir documento</a>
															<span class="fafar-document-filename"><?php echo esc_html( $document['name'] ?? '' ); ?></span>
														<?php else : ?>
															<span class="fafar-document-missing">Não enviado</span>
														<?php endif; ?>
													</div>
												<?php endforeach; ?>
											</div>
										</section>
									</div>
								</details>
							<?php endwhile; ?>
						</div>
					<?php else : ?>
						<p class="fafar-candidatos-empty">Ainda não há candidatos inscritos.</p>
					<?php endif; ?>

					<?php wp_reset_postdata(); ?>
					<?php astra_entry_content_after(); ?>
				</div>

				<?php astra_entry_bottom(); ?>
			</article>
			<?php astra_entry_after(); ?>
		<?php endwhile; ?>

		<?php astra_primary_content_bottom(); ?>
	</div>

<?php if ( astra_page_layout() === 'right-sidebar' ) : ?>
	<?php get_sidebar(); ?>
<?php endif; ?>

<?php get_footer(); ?>
