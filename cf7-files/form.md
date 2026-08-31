<h4> Vaga </h4>

<label>* Nível da Vaga </label>
[radio NivelDaVaga use_label_element default:1 "Doutorado"]

<label>* Tipo de Vaga </label>
   [radio TipoDaVaga use_label_element default:1 "Ampla concorrência" "Estrangeiro" "Reserva de vagas para negros" "Reserva de vagas para indígenas" "Reserva de vagas para portadores de necessidades especiais"]

<hr/>
<h4> Dados Pessoais </h4>

<label>* Nome Completo
    [text* NomeCompleto autocomplete:name] </label>

<label>* Data De Nascimento
    [date* DataDeNascimento min:1900-01-01] </label>

<label>* E-mail
    [email* Email autocomplete:email] </label>

<p><b>* Sexo</b><br>
    [radio Sexo use_label_element default:1 "Feminino" "Masculino"]</p>

<label>* Telefone
    [tel* Telefone class:phone] </label>

<label>* Naturalidade
    [text* Naturalidade] </label>

<label>* Raça
    [select Raca "Não Desejo Declarar" "Amarela" "Branca" "Indígena" "Parda" "Preta"] </label>

[group documento-identificacao-brasileiro clear_on_hide]

   <label>* CPF
       [text* CPF class:cpf] </label>

   <label>* RG
       [text* RG] </label>

   <label>* Órgão Emissor do RG
       [text* OrgaoEmissorRG] </label>

[/group]

[group documento-identificacao-estrangeiro clear_on_hide]

   <label>* Passaporte
       [text* Passaporte] </label>

[/group]

<hr/>

<label>* CEP
    [text* CEP class:cep] </label>

<label>* Logradouro
    [text* Logradouro] </label>

<label>* Número
    [number* NumeroResidencia] </label>

<label> Complemento
    [text ComplementoResidencia] </label>

<label>* Bairro
    [text* Bairro] </label>

<label>* Cidade
    [text* Cidade] </label>

<label>* Estado
    [text* Estado] </label>

<hr/>

<h4> Documentação </h4>
<small><em> Tamanho máx.: 8MB(individual) </em></small>

<label>* Foto atual 3x4 (Extensões Aceitas: .jpg/.jpeg/.png) </label>
   [file* ArquivoFoto limit:8mb filetypes:jpg|jpeg|png]

<label>* Cópia da certidão de nascimento ou de casamento ou separação </label>
   [file* ArquivoCertidaoNascimentoCasadoSeparacao limit:8mb filetypes:pdf]

<label>* Curriculum no modelo Lattes/CNPq </label>
   [file* ArquivoLattes limit:8mb filetypes:pdf]

<label>* Comprovantes das publicações/atividades listadas no Lattes em arquivo único </label>
   [file* ArquivoPublicacoesAtividades limit:8mb filetypes:pdf]

<label>* Cópia de certidão de quitação eleitoral a ser <a href="https://www.tse.jus.br/eleitor/certidoes/certidao-de-quitacao-eleitoral" target="_blank">obtida aqui</a> </label>
   [file* ArquivoCertidaoQuitacaoEleitoral limit:8mb filetypes:pdf]

<label>* Cópia do comprovante de residência </label>
   [file* ArquivoComprovanteResidencia limit:8mb filetypes:pdf]

<label>* Tabela de pontuação do currículo devidamente preenchida e assinada disponível no site do programa </label>
   [file* ArquivoTabelaPontuacaoCurriculo limit:8mb filetypes:pdf]

[group arquivo-masculino-brasileiro clear_on_hide]

   <label>* Comprovante de estar em dia com as obrigações militares, no caso de brasileiro e do sexo masculino. Carimbo, no documento militar e/ou no verso do diploma, da comunicação da conclusão da graduação à autoridade militar, como no <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/08/modelo_carimbo.jpg" target="_blank">exemplo</a> </label>
      [file* ArquivoComprovanteMilitar limit:8mb filetypes:pdf]

[/group]

[group arquivos-brasileiro clear_on_hide]

   <label>* Identidade </label>
      [file* ArquivoIdentidade limit:8mb filetypes:pdf]

   <label>* CPF </label>
      [file* ArquivoCPF limit:8mb filetypes:pdf]

[/group]

[group arquivos-estrangeiro clear_on_hide]

   <label>* Passaporte </label>
      [file* ArquivoPassaporte limit:8mb filetypes:pdf]

   <label>* Comprovante conhecimento de língua portuguesa </label>
      [file* ArquivoComprovanteLinguaPortuguesa limit:8mb filetypes:pdf]

[/group]

   <label>* Cópia frente e verso diploma de graduação ou documento que comprove que o candidato está em condições de concluir a graduação </label>
      [file* ArquivoDiplomaGraduacao limit:8mb filetypes:pdf]

[group arquivos-mestrado clear_on_hide]

   <label>* Cópia do histórico escolar do curso de graduação </label>
      [file* ArquivoHistoricoGraduacao limit:8mb filetypes:pdf]

[/group]

[group arquivos-doutorado clear_on_hide]

   <label>* Pré-projeto de Tese </label>
      [file* ArquivoProjetoTese limit:8mb filetypes:pdf]

   <label>* Cópia do diploma de Mestrado frente e verso </label>
      [file* ArquivoDiplomaMestrado limit:8mb filetypes:pdf]

   <label>* Histórico escolar do curso de pós-graduação </label>
      [file* ArquivoHistoricoPosGraduacao limit:8mb filetypes:pdf]

[/group]

[group arquivos-vagas-negros clear_on_hide]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Autodeclaracao-negros_modeloDRCA1-1pdf" target="_blank">Formulário</a> de autodeclaração étnico-racial preenchido </label>
      [file* ArquivoAutodeclaracaoEtnicoRacial limit:8mb filetypes:pdf]

[/group]

[group arquivos-vagas-indigenas clear_on_hide]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Autoreconhecimento_Indigenapdf" target="_blank">Declaração</a> de autorreconhecimento indígena </label>
      [file* ArquivoAutorreconhecimentoIndigena limit:8mb filetypes:pdf]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Liderancas-Indigenaspdf" target="_blank">Declaração</a> assinada por pelo menos três lideranças indígenas </label>
      [file* ArquivoDeclaracaoLiderancaIndigena limit:8mb filetypes:pdf]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/PG_Declaracao-de-Residencia_Indigenapdf" target="_blank">Declaração</a> da Funai atestando residência em comunidade indígena </label>
      [file* ArquivoDeclaracaoFunai limit:8mb filetypes:pdf]

[/group]

[group arquivos-vagas-pcds clear_on_hide]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/Autodeclaracao-Pessoa-com-Deficienciapdf" target="_blank">Autodeclaração</a> de pessoa com deficiência </label>
      [file* ArquivoAutodeclaracaoDeficiente limit:8mb filetypes:pdf]

   <label>* <a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/MODELO_RELATORIO_MEDICO_ASSISTENTE.docx" target="_blank">Relatório</a> do médico assistente </label>
      [file* ArquivoRelatorioMedico limit:8mb filetypes:pdf]

   <label><a href="https://www.farmacia.ufmg.br/wp-content/uploads/2022/11/FORMULARIO_SOLICITACAO_CONDICOES_ESPECIAIS_REALIZACAO-PROVA.docx" target="_blank">Formulário</a> de solicitação de condições especiais para realizar a prova </label>
      [file ArquivoCondicoesEspeciaisProva limit:8mb filetypes:pdf]

[/group]

<label> Comprovante de aprovação em exame de conhecimento de língua inglesa </label>
   [file ArquivoExameLinguaInglesa limit:8mb filetypes:pdf]

[submit "Enviar"]