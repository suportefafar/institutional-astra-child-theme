<?php
/**
 * Formulário de inscrição do PPGCF.
 *
 * Este arquivo também é reutilizado pela página /inscricao-ppgcf-teste/.
 * O contexto informado pelo template define qual período controla a página.
 *
 * @package Institutional_Astra_Child_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fafar_ppgcf_schedule_context = isset( $fafar_ppgcf_schedule_context ) && 'test' === $fafar_ppgcf_schedule_context ? 'test' : 'production';
$fafar_ppgcf_schedule_state   = fafar_ppgcf_registration_schedule_state( $fafar_ppgcf_schedule_context );
$fafar_ppgcf_form_is_open     = 'open' === $fafar_ppgcf_schedule_state['status'];
$form_style_path              = get_stylesheet_directory() . '/assets/css/inscricao-ppgcf.css';
$form_script_path             = get_stylesheet_directory() . '/assets/js/inscricao-ppgcf.js';

nocache_headers();

wp_enqueue_style(
	'fafar-inscricao-ppgcf',
	get_stylesheet_directory_uri() . '/assets/css/inscricao-ppgcf.css',
	array( 'astra-theme-css' ),
	file_exists( $form_style_path ) ? filemtime( $form_style_path ) : '1.0.0'
);
if ( $fafar_ppgcf_form_is_open ) {
	wp_enqueue_script(
		'fafar-inscricao-ppgcf',
		get_stylesheet_directory_uri() . '/assets/js/inscricao-ppgcf.js',
		array(),
		file_exists( $form_script_path ) ? filemtime( $form_script_path ) : '1.0.0',
		true
	);
	wp_localize_script(
		'fafar-inscricao-ppgcf',
		'fafarPpgcfConfig',
		array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'ppgcf_form_' . $fafar_ppgcf_schedule_context ),
			'scheduleContext'  => $fafar_ppgcf_schedule_context,
			'uploadTimeoutMs'  => 120000,
			'submitTimeoutMs'  => 60000,
		)
	);
}

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

				<div class="entry-content clear fafar-inscricao-page-content">
					<?php astra_entry_content_before(); ?>
					<?php if ( $fafar_ppgcf_form_is_open ) : ?>
					<form class="fafar-inscricao-form" id="fafar-inscricao-ppgcf" action="" method="post" enctype="multipart/form-data" novalidate>
						<p class="fafar-form-intro">Preencha os dados abaixo e anexe os documentos solicitados. Os arquivos são enviados individualmente antes da inscrição. Os campos marcados com <span aria-hidden="true">*</span> são obrigatórios.</p>

						<fieldset class="fafar-form-section">
							<legend>Vaga</legend>

							<div class="fafar-field">
								<span class="fafar-label">Nível da vaga <span aria-hidden="true">*</span></span>
								<div class="fafar-choice-list fafar-choice-list--inline">
									<label class="fafar-choice"><input type="radio" name="NivelDaVaga" value="Doutorado" checked required> <span>Doutorado</span></label>
								</div>
							</div>

							<div class="fafar-field">
								<span class="fafar-label">Tipo de vaga <span aria-hidden="true">*</span></span>
								<div class="fafar-choice-list">
									<label class="fafar-choice"><input type="radio" name="TipoDaVaga" value="Ampla concorrência" checked required> <span>Ampla concorrência</span></label>
									<label class="fafar-choice"><input type="radio" name="TipoDaVaga" value="Estrangeiro" required> <span>Estrangeiro</span></label>
									<label class="fafar-choice"><input type="radio" name="TipoDaVaga" value="Reserva de vagas para negros" required> <span>Reserva de vagas para negros</span></label>
									<label class="fafar-choice"><input type="radio" name="TipoDaVaga" value="Reserva de vagas para indígenas" required> <span>Reserva de vagas para indígenas</span></label>
									<label class="fafar-choice"><input type="radio" name="TipoDaVaga" value="Reserva de vagas para pessoas com deficiência" required> <span>Reserva de vagas para pessoas com deficiência</span></label>
								</div>
							</div>
						</fieldset>

						<fieldset class="fafar-form-section">
							<legend>Dados pessoais</legend>
							<div class="fafar-form-grid">
								<div class="fafar-field fafar-field--full">
									<label for="NomeCompleto">Nome completo <span aria-hidden="true">*</span></label>
									<input id="NomeCompleto" name="NomeCompleto" type="text" autocomplete="name" required>
								</div>
								<div class="fafar-field">
									<label for="DataDeNascimento">Data de nascimento <span aria-hidden="true">*</span></label>
									<input id="DataDeNascimento" name="DataDeNascimento" type="date" min="1900-01-01" required>
								</div>
								<div class="fafar-field">
									<label for="Email">E-mail <span aria-hidden="true">*</span></label>
									<input id="Email" name="Email" type="email" autocomplete="email" required>
								</div>
								<div class="fafar-field">
									<span class="fafar-label">Sexo <span aria-hidden="true">*</span></span>
									<div class="fafar-choice-list fafar-choice-list--inline">
										<label class="fafar-choice"><input type="radio" name="Sexo" value="Feminino" checked required> <span>Feminino</span></label>
										<label class="fafar-choice"><input type="radio" name="Sexo" value="Masculino" required> <span>Masculino</span></label>
									</div>
								</div>
								<div class="fafar-field">
									<label for="Telefone">Telefone <span aria-hidden="true">*</span></label>
									<input id="Telefone" name="Telefone" type="tel" inputmode="tel" autocomplete="off" data-fafar-mask="telefone" placeholder="" required>
								</div>
								<div class="fafar-field">
									<label for="Naturalidade">Naturalidade <span aria-hidden="true">*</span></label>
									<input id="Naturalidade" name="Naturalidade" type="text" required>
								</div>
								<div class="fafar-field">
									<label for="Raca">Raça <span aria-hidden="true">*</span></label>
									<select id="Raca" name="Raca" required>
										<option value="Não Desejo Declarar">Não Desejo Declarar</option>
										<option value="Amarela">Amarela</option>
										<option value="Branca">Branca</option>
										<option value="Indígena">Indígena</option>
										<option value="Parda">Parda</option>
										<option value="Preta">Preta</option>
									</select>
								</div>
							</div>

							<div class="fafar-conditional fafar-conditional--grid" data-condition="brasileiro" hidden>
								<div class="fafar-field"><label for="CPF">CPF <span aria-hidden="true">*</span></label><input id="CPF" name="CPF" type="text" inputmode="numeric" autocomplete="off" data-fafar-mask="cpf" data-required="true"></div>
								<div class="fafar-field"><label for="RG">RG <span aria-hidden="true">*</span></label><input id="RG" name="RG" type="text" data-required="true"></div>
								<div class="fafar-field"><label for="OrgaoEmissorRG">Órgão emissor do RG <span aria-hidden="true">*</span></label><input id="OrgaoEmissorRG" name="OrgaoEmissorRG" type="text" data-required="true"></div>
							</div>

							<div class="fafar-conditional" data-condition="estrangeiro" hidden>
								<div class="fafar-field"><label for="Passaporte">Passaporte <span aria-hidden="true">*</span></label><input id="Passaporte" name="Passaporte" type="text" data-required="true"></div>
							</div>
						</fieldset>

						<fieldset class="fafar-form-section">
							<legend>Endereço</legend>
							<div class="fafar-form-grid">
								<div class="fafar-field"><label for="CEP">CEP <span aria-hidden="true">*</span></label><input id="CEP" name="CEP" type="text" inputmode="numeric" autocomplete="postal-code" data-fafar-mask="cep" required></div>
								<div class="fafar-field fafar-field--wide"><label for="Logradouro">Logradouro <span aria-hidden="true">*</span></label><input id="Logradouro" name="Logradouro" type="text" autocomplete="street-address" required></div>
								<div class="fafar-field"><label for="NumeroResidencia">Número <span aria-hidden="true">*</span></label><input id="NumeroResidencia" name="NumeroResidencia" type="number" min="0" required></div>
								<div class="fafar-field"><label for="ComplementoResidencia">Complemento</label><input id="ComplementoResidencia" name="ComplementoResidencia" type="text"></div>
								<div class="fafar-field"><label for="Bairro">Bairro <span aria-hidden="true">*</span></label><input id="Bairro" name="Bairro" type="text" required></div>
								<div class="fafar-field"><label for="Cidade">Cidade <span aria-hidden="true">*</span></label><input id="Cidade" name="Cidade" type="text" required></div>
								<div class="fafar-field"><label for="Estado">Estado <span aria-hidden="true">*</span></label><select id="Estado" name="Estado" autocomplete="address-level1" required><option value="">Selecione</option><?php foreach ( fafar_ppgcf_brazilian_states() as $uf => $state_name ) : ?><option value="<?php echo esc_attr( $uf ); ?>"><?php echo esc_html( $uf . ' — ' . $state_name ); ?></option><?php endforeach; ?></select></div>
							</div>
						</fieldset>

						<fieldset class="fafar-form-section fafar-documentation">
							<legend>Documentação</legend>
							<p class="fafar-help">Tamanho máximo: 8 MB por arquivo. Formatos aceitos conforme indicado em cada campo.</p>

							<div class="fafar-file-grid">
								<div class="fafar-file-field"><label for="ArquivoFoto">Foto atual 3x4 <span aria-hidden="true">*</span> <small>(.jpg, .jpeg ou .png)</small></label><input id="ArquivoFoto" name="ArquivoFoto" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoCertidaoNascimentoCasadoSeparacao">Cópia da certidão de nascimento, casamento ou separação <span aria-hidden="true">*</span></label><input id="ArquivoCertidaoNascimentoCasadoSeparacao" name="ArquivoCertidaoNascimentoCasadoSeparacao" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoLattes">Curriculum vitae ou Curriculum Lattes (modelo da <a href="http://lattes.cnpq.br/" target="_blank" rel="noopener noreferrer">Plataforma Lattes/CNPq</a>) <span aria-hidden="true">*</span></label><input id="ArquivoLattes" name="ArquivoLattes" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoPublicacoesAtividades">Comprovantes das publicações/atividades, organizados na ordem numérica do currículo e reunidos em arquivo único <span aria-hidden="true">*</span></label><input id="ArquivoPublicacoesAtividades" name="ArquivoPublicacoesAtividades" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoCertidaoQuitacaoEleitoral">Cópia da certidão de quitação eleitoral a ser <a href="https://www.tse.jus.br/eleitor/certidoes/certidao-de-quitacao-eleitoral" target="_blank" rel="noopener noreferrer">obtida aqui</a> <span aria-hidden="true">*</span></label><input id="ArquivoCertidaoQuitacaoEleitoral" name="ArquivoCertidaoQuitacaoEleitoral" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoComprovanteResidencia">Cópia do comprovante de residência <span aria-hidden="true">*</span></label><input id="ArquivoComprovanteResidencia" name="ArquivoComprovanteResidencia" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
								<div class="fafar-file-field"><label for="ArquivoTabelaPontuacaoCurriculo">Tabela de pontuação do currículo devidamente preenchida e assinada, disponível no site do programa <span aria-hidden="true">*</span></label><input id="ArquivoTabelaPontuacaoCurriculo" name="ArquivoTabelaPontuacaoCurriculo" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="militar" hidden>
								<div class="fafar-file-field"><label for="ArquivoComprovanteMilitar">Comprovante de estar em dia com as obrigações militares, no caso de brasileiro e do sexo masculino. Carimbo no documento militar e/ou no verso do diploma, como no <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/08/modelo_carimbo.jpg" target="_blank" rel="noopener noreferrer">exemplo</a> <span aria-hidden="true">*</span></label><input id="ArquivoComprovanteMilitar" name="ArquivoComprovanteMilitar" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="arquivos-brasileiro" hidden>
								<div class="fafar-file-field"><label for="ArquivoIdentidade">Identidade <span aria-hidden="true">*</span></label><input id="ArquivoIdentidade" name="ArquivoIdentidade" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoCPF">CPF <span aria-hidden="true">*</span></label><input id="ArquivoCPF" name="ArquivoCPF" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="arquivos-estrangeiro" hidden>
								<div class="fafar-file-field"><label for="ArquivoPassaporte">Passaporte <span aria-hidden="true">*</span></label><input id="ArquivoPassaporte" name="ArquivoPassaporte" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-file-grid">
								<div class="fafar-file-field"><label for="ArquivoDiplomaGraduacao">Cópia, frente e verso em arquivo único, do diploma de graduação em Farmácia ou em áreas afins reconhecido pelo MEC <span aria-hidden="true">*</span></label><input id="ArquivoDiplomaGraduacao" name="ArquivoDiplomaGraduacao" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="doutorado" hidden>
								<div class="fafar-file-field"><label for="ArquivoProjetoTese">Pré-projeto de tese <span aria-hidden="true">*</span></label><input id="ArquivoProjetoTese" name="ArquivoProjetoTese" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoDiplomaMestrado">Cópia do diploma de mestrado, frente e verso em arquivo único, ou ata de defesa da dissertação de mestrado <span aria-hidden="true">*</span></label><input id="ArquivoDiplomaMestrado" name="ArquivoDiplomaMestrado" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoHistoricoPosGraduacao">Histórico escolar do curso de pós-graduação <span aria-hidden="true">*</span></label><input id="ArquivoHistoricoPosGraduacao" name="ArquivoHistoricoPosGraduacao" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="edital-regular" hidden>
								<div class="fafar-file-field"><label for="ArquivoAutodeclaracaoEtnicoRacial"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Autodeclaracao-negros_modeloDRCA1-1.pdf" target="_blank" rel="noopener noreferrer">Formulário</a> de autodeclaração étnico-racial preenchido <span aria-hidden="true">*</span></label><input id="ArquivoAutodeclaracaoEtnicoRacial" name="ArquivoAutodeclaracaoEtnicoRacial" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="indigenas" hidden>
								<div class="fafar-file-field"><label for="ArquivoAutorreconhecimentoIndigena"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Autoreconhecimento_Indigena.pdf" target="_blank" rel="noopener noreferrer">Formulário</a> de autorreconhecimento indígena <span aria-hidden="true">*</span></label><input id="ArquivoAutorreconhecimentoIndigena" name="ArquivoAutorreconhecimentoIndigena" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoDeclaracaoLiderancaIndigena"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Liderancas-Indigenas.pdf" target="_blank" rel="noopener noreferrer">Formulário</a> de declaração assinada por pelo menos três lideranças indígenas <span aria-hidden="true">*</span></label><input id="ArquivoDeclaracaoLiderancaIndigena" name="ArquivoDeclaracaoLiderancaIndigena" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoDeclaracaoFunai"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Residencia_Indigena.pdf" target="_blank" rel="noopener noreferrer">Formulário</a> de declaração da Fundação Nacional dos Povos Indígenas (Funai), atestando que o candidato reside em comunidade indígena <span aria-hidden="true">*</span></label><input id="ArquivoDeclaracaoFunai" name="ArquivoDeclaracaoFunai" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-conditional fafar-file-grid" data-condition="pcds" hidden>
								<div class="fafar-file-field"><label for="ArquivoAutodeclaracaoDeficiente"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/Autodeclaracao-Pessoa-com-Deficiencia.pdf" target="_blank" rel="noopener noreferrer">Formulário</a> de autodeclaração de pessoa com deficiência <span aria-hidden="true">*</span></label><input id="ArquivoAutodeclaracaoDeficiente" name="ArquivoAutodeclaracaoDeficiente" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoRelatorioMedico"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/MODELO_RELATORIO_MEDICO_ASSISTENTE.docx" target="_blank" rel="noopener noreferrer">Relatório</a> do médico assistente (enviar em PDF) <span aria-hidden="true">*</span></label><input id="ArquivoRelatorioMedico" name="ArquivoRelatorioMedico" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
								<div class="fafar-file-field"><label for="ArquivoCondicoesEspeciaisProva"><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/FORMULARIO_SOLICITACAO_CONDICOES_ESPECIAIS_REALIZACAO-PROVA.docx" target="_blank" rel="noopener noreferrer">Formulário</a> de solicitação de condições especiais para realizar a prova <span aria-hidden="true">*</span></label><input id="ArquivoCondicoesEspeciaisProva" name="ArquivoCondicoesEspeciaisProva" type="file" accept=".pdf,application/pdf" data-max-size="8388608" data-required="true"></div>
							</div>

							<div class="fafar-file-grid">
								<div class="fafar-file-field"><label for="ArquivoExameLinguaInglesa">Comprovação de proficiência em língua inglesa <span aria-hidden="true">*</span></label><input id="ArquivoExameLinguaInglesa" name="ArquivoExameLinguaInglesa" type="file" accept=".pdf,application/pdf" data-max-size="8388608" required></div>
							</div>
						</fieldset>

						<div class="fafar-form-errors" id="fafar-form-errors" role="alert" aria-labelledby="fafar-form-errors-title" hidden>
							<p id="fafar-form-errors-title"><strong>Verifique os campos com erro:</strong></p>
							<ul id="fafar-form-errors-list"></ul>
						</div>

						<div class="fafar-form-actions">
							<button class="fafar-submit" type="submit" disabled aria-disabled="true">Enviar</button>
							<p class="fafar-form-status" id="fafar-form-status" role="status" aria-live="polite" hidden></p>
						</div>
					</form>
					<?php else : ?>
						<section class="fafar-registration-availability is-<?php echo esc_attr( $fafar_ppgcf_schedule_state['status'] ); ?>" aria-live="polite">
							<?php if ( 'upcoming' === $fafar_ppgcf_schedule_state['status'] ) : ?>
								<h2>Em breve</h2>
								<p>Em breve as inscrições irão começar. O formulário estará disponível a partir de <time datetime="<?php echo esc_attr( $fafar_ppgcf_schedule_state['opens_at']->format( DATE_ATOM ) ); ?>"><?php echo esc_html( wp_date( 'd/m/Y \à\s H:i', $fafar_ppgcf_schedule_state['opens_at']->getTimestamp(), wp_timezone() ) ); ?></time>.</p>
							<?php elseif ( 'scheduled' === $fafar_ppgcf_schedule_state['status'] ) : ?>
								<h2>Inscrições ainda não abertas</h2>
								<p>O período de inscrições começará em <time datetime="<?php echo esc_attr( $fafar_ppgcf_schedule_state['opens_at']->format( DATE_ATOM ) ); ?>"><?php echo esc_html( wp_date( 'd/m/Y \à\s H:i', $fafar_ppgcf_schedule_state['opens_at']->getTimestamp(), wp_timezone() ) ); ?></time>.</p>
							<?php elseif ( 'closed' === $fafar_ppgcf_schedule_state['status'] ) : ?>
								<h2>Inscrições encerradas</h2>
								<p>O período informado para as inscrições foi encerrado em <time datetime="<?php echo esc_attr( $fafar_ppgcf_schedule_state['closes_at']->format( DATE_ATOM ) ); ?>"><?php echo esc_html( wp_date( 'd/m/Y \à\s H:i', $fafar_ppgcf_schedule_state['closes_at']->getTimestamp(), wp_timezone() ) ); ?></time>.</p>
							<?php else : ?>
								<h2>Inscrições indisponíveis</h2>
								<p>O período de inscrições ainda não foi configurado.</p>
							<?php endif; ?>
						</section>
					<?php endif; ?>
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
