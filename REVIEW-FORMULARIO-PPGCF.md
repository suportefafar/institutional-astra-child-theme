# Review do formulário de inscrição do PPGCF

Data da revisão: 31/08/2026

## Resultado geral

O fluxo principal funciona ponta a ponta no ambiente local. Na revisão original foram identificados um problema crítico de segurança, cinco links obrigatórios quebrados e pontos importantes de confiabilidade, validação, privacidade e experiência do usuário.

Os itens **2, 5, 6, 7 e 9** foram corrigidos e retestados em 31/08/2026. O formulário ainda não deve ser considerado pronto para produção enquanto o item 1, sobre acesso público direto aos documentos, permanecer aberto.

A URL pública `/inscricao-ppgcf/` estava mostrando **“Inscrições encerradas”** durante a revisão. Por isso, o teste ponta a ponta do formulário novo foi executado no WordPress local, em `farmacia.local`.

## Testes executados

O formulário foi aberto e operado com uma instância real do Chrome em modo headless.

Foram validados:

- carregamento do template específico do formulário;
- preenchimento dos campos de texto;
- digitação e aplicação das máscaras de telefone, CPF e CEP;
- funcionamento dos grupos condicionais brasileiro e estrangeiro;
- presença das 27 unidades federativas no campo Estado;
- bloqueio inicial do botão Enviar;
- falha de rede simulada durante um upload;
- exibição do campo que apresentou erro;
- ausência da mensagem técnica `Unexpected token '<'`;
- upload assíncrono dos 13 documentos obrigatórios do cenário padrão;
- liberação do botão somente após os uploads obrigatórios;
- submissão completa da inscrição;
- persistência de 20 campos, 13 documentos e horário de recebimento;
- redirecionamento de visitantes de `/candidatos-ppgcf/` para o login;
- comportamento responsivo básico em resoluções desktop e mobile;
- ausência de exceções JavaScript durante o fluxo;
- sintaxe dos arquivos PHP e JavaScript;
- verificação de formatação com `git diff --check`.

A inscrição e os arquivos sintéticos criados durante o E2E foram removidos depois da validação.

## Reteste após as correções

Um segundo E2E foi executado no contexto independente de testes, usando a matriz atual de 15 documentos obrigatórios. Foram confirmados:

- interface individual de upload criada para os 23 campos de documento;
- cancelamento de upload em andamento;
- retentativa do mesmo arquivo depois do cancelamento;
- remoção do arquivo selecionado;
- timeout controlado e mensagem associada ao campo;
- retentativa do mesmo arquivo depois do timeout;
- resposta de token expirado associada exatamente ao documento afetado;
- resumo de erros com o nome correto do documento expirado;
- nova liberação do botão somente depois do reenvio do documento;
- submissão real com 15 documentos persistidos;
- desabilitação de todos os controles depois do sucesso;
- impossibilidade de reativar o botão por eventos de edição posteriores;
- rollback de post, diretório, arquivo e metadados em uma persistência parcial;
- preservação do token temporário quando o arquivo pôde ser restaurado;
- ausência de exceções e erros de console no navegador.

O período de testes foi restaurado para a configuração que estava salva antes da automação. A inscrição, os arquivos permanentes e os uploads temporários sintéticos foram removidos ao final.

## Pontos que funcionaram corretamente

- As regras condicionais principais do frontend correspondem às regras do backend.
- Os cenários de ampla concorrência, estrangeiro, mestrado, doutorado e reservas possuem matrizes coerentes de documentos obrigatórios.
- O backend verifica extensão e MIME real dos arquivos.
- O tamanho máximo de 8 MB é validado no frontend e no backend.
- Os tokens de upload são validados antes da criação da inscrição.
- O resumo de erros acumula falhas de diferentes campos.
- Falhas de conexão são apresentadas com mensagens amigáveis.
- O handler de download verifica nonce e impede travessia de diretórios.
- A página de candidatos redireciona visitantes não autenticados para o login.
- O layout não apresentou estouro horizontal na resolução mobile testada.

## Problemas encontrados

### 1. Crítico — documentos acessíveis diretamente sem login

Os documentos são armazenados dentro de:

```text
wp-content/uploads/ppgcf-candidatos/{id-da-inscricao}/
```

Esse diretório é publicamente acessível pelo servidor web. Um documento sintético foi solicitado diretamente por sua URL, sem autenticação, e o servidor respondeu `HTTP 200` com `Content-Type: application/pdf`.

O endpoint assinado de download não impede esse acesso direto ao arquivo estático.

Referências:

- `ppgcf.php:175`
- `ppgcf.php:180`
- `ppgcf.php:424`

Recomendação:

- armazenar os documentos fora da raiz pública do WordPress; ou
- bloquear completamente os diretórios `ppgcf-candidatos` e `ppgcf-pending` no Caddy/Apache e entregar os arquivos somente pelo handler PHP autorizado.

Esse problema deve ser corrigido antes da abertura das inscrições.

### 2. Corrigido — links obrigatórios dos formulários

Na revisão original, os seguintes links retornavam `HTTP 404` porque faltava o ponto antes da extensão `pdf`:

- autodeclaração étnico-racial;
- declaração de autorreconhecimento indígena;
- declaração de lideranças indígenas;
- declaração de residência indígena;
- autodeclaração de pessoa com deficiência.

Referências:

- `page-inscricao-ppgcf.php:202`
- `page-inscricao-ppgcf.php:206`
- `page-inscricao-ppgcf.php:207`
- `page-inscricao-ppgcf.php:208`
- `page-inscricao-ppgcf.php:212`

O código atual utiliza as URLs com `.pdf`. Os cinco endereços corrigidos foram testados e responderam `HTTP 200` com `Content-Type: application/pdf`.

### 3. Alta — endpoint público sujeito a abuso de armazenamento

Os endpoints de upload e submissão são públicos. O nonce está disponível para qualquer visitante que abrir o formulário e, portanto, não funciona como proteção contra robôs ou abuso de recursos.

Não existem:

- limite de uploads por IP ou sessão;
- limite de inscrições por período;
- honeypot ou proteção equivalente;
- CAPTCHA ou mecanismo antiautomação;
- limite específico de corpo da requisição no proxy.

Cada upload pode ter até 8 MB. O ambiente PHP está configurado com `upload_max_filesize` e `post_max_size` de 3 GB, aumentando o impacto de configurações incorretas ou novos endpoints.

Referências:

- `ppgcf.php:15`
- `ppgcf.php:16`
- `ppgcf.php:17`
- `ppgcf.php:18`
- `ppgcf.php:211`

### 4. Alta — validação do backend incompleta

O backend valida obrigatoriedade, e-mail, estado e as opções principais. Porém, ainda aceita valores arbitrários para:

- CPF;
- telefone;
- CEP;
- data de nascimento;
- número residencial;
- raça;
- RG e passaporte;
- comprimento dos campos textuais.

Também são preservadas chaves adicionais enviadas manualmente, desde que contenham caracteres aceitos e valores escalares.

As validações HTML e JavaScript podem ser contornadas e não substituem a validação do servidor.

Referências:

- `ppgcf.php:286`
- `ppgcf.php:292`
- `ppgcf.php:303`
- `ppgcf.php:361`

Recomendação: criar um schema único de campos, com sanitização, enumeração, tamanho máximo e validação específica para cada valor.

### 5. Corrigido — bloqueio após uma inscrição bem-sucedida

Na revisão original, o E2E expôs o seguinte comportamento incorreto:

1. a inscrição é registrada com sucesso;
2. o botão fica desabilitado;
3. o formulário recebe `data-submitted="true"`;
4. qualquer alteração posterior em um campo executa novamente a lógica de disponibilidade;
5. o botão Enviar volta a ficar habilitado.

O comportamento original foi corrigido. `updateSubmitAvailability()` agora considera os estados `data-submitting` e `data-submitted`. Depois do sucesso, todos os inputs, selects, textareas e botões são desabilitados, e o botão passa a exibir “Inscrição enviada”.

Referências:

- `assets/js/inscricao-ppgcf.js:613`
- `assets/js/inscricao-ppgcf.js:673`

O E2E tentou disparar uma nova edição e um novo clique depois do sucesso. O formulário permaneceu bloqueado e a mensagem de confirmação foi preservada.

### 6. Corrigido — uploads expirados identificam o campo

Os tokens de upload expiram após duas horas. Antes da correção, o backend respondia apenas:

```text
Um dos uploads expirou. Envie o documento novamente.
```

A resposta do backend agora contém `field` e `code`. O frontend localiza o input correspondente, invalida somente aquele token, apresenta a mensagem ao lado do documento e inclui seu nome no resumo de erros.

O reteste simulou a expiração da certidão de nascimento, casamento ou separação. Somente esse documento foi marcado e, depois da retentativa, o envio voltou a ser liberado.

Referência:

- `ppgcf.php:646`

Os demais erros de campos obrigatórios, e-mail, estado, opções e tokens inválidos também utilizam o mesmo contrato estruturado.

### 7. Corrigido — timeout e controles individuais de upload

Os uploads agora utilizam `AbortController` e timeout padrão de 120 segundos. A submissão final possui timeout independente de 60 segundos.

Cada campo de arquivo agora possui:

- botão para cancelar o upload em andamento;
- botão para remover o documento escolhido;
- botão explícito de retentativa, inclusive para o mesmo arquivo;
- status individual de envio, sucesso, cancelamento, timeout ou falha;
- mensagens acessíveis por `aria-live` e associadas ao input por `aria-describedby`.

O reteste confirmou cancelamento, remoção, timeout e retentativa do mesmo arquivo sem depender de um novo evento `change` do seletor nativo.

Referências:

- `assets/js/inscricao-ppgcf.js:272`
- `assets/js/inscricao-ppgcf.js:486`

### 8. Média — uploads substituídos ficam órfãos temporariamente

Quando o candidato substitui um documento, altera uma condição ou abandona a página, arquivos já enviados podem permanecer em `ppgcf-pending` até a limpeza agendada.

O cleanup existe, mas depende do WP-Cron e remove os arquivos apenas depois de duas horas.

Referências:

- `ppgcf.php:185`
- `ppgcf.php:264`

Recomendação: invalidar e excluir imediatamente o token anterior quando um campo receber outro arquivo, mantendo o cron como proteção adicional.

### 9. Corrigido — persistência com rollback

Os retornos das quatro gravações de metadados agora são verificados: dados, documentos, horário e contexto do formulário.

Os transients só são apagados depois que todas as gravações terminam com sucesso. Em qualquer falha, o código exclui o post parcial, restaura os arquivos para suas origens temporárias, remove o diretório de destino e preserva os tokens que ainda podem ser reutilizados.

Referências:

- `ppgcf.php:485`
- `ppgcf.php:706`
- `ppgcf.php:709`

O teste específico de rollback confirmou post inexistente, destino removido, arquivo restaurado e token preservado.

### 10. Média — autorização ampla para dados sensíveis

A página de candidatos e o download dos documentos exigem apenas que o usuário esteja autenticado. Qualquer conta logada, inclusive uma conta de baixo privilégio, pode potencialmente consultar todos os candidatos.

Isso corresponde ao requisito inicial de “usuários logados”, mas é uma permissão ampla para CPF, identidade, raça e documentos médicos.

Referências:

- `ppgcf.php:52`
- `ppgcf.php:64`
- `page-candidatos-ppgcf.php:12`

Recomendação: criar uma capacidade específica, por exemplo `manage_ppgcf_candidates`, e atribuí-la somente aos responsáveis pelo processo seletivo.

### 11. Privacidade e LGPD

O formulário coleta dados pessoais e dados potencialmente sensíveis, mas atualmente não possui:

- aviso de privacidade;
- finalidade e base legal informadas;
- confirmação de ciência ou consentimento quando aplicável;
- prazo de retenção;
- rotina de exclusão;
- registro de acesso aos documentos;
- análise antimalware dos arquivos.

Esses pontos devem ser definidos antes do uso com candidatos reais.

### 12. Acessibilidade e experiência

O reteste confirmou `aria-invalid`, status individual de upload e associação desse status ao campo por `aria-describedby`. Ainda podem ser melhorados:

- transformar cada erro em link para o campo correspondente;
- mover o foco para o resumo após uma submissão inválida;
- associar também as demais mensagens de validação por `aria-describedby`;
- estruturar cada grupo de radio buttons com `fieldset` e `legend` próprios;

### 13. Escalabilidade da página de candidatos

A consulta utiliza:

```php
'posts_per_page' => -1
```

Isso carrega todas as inscrições e verifica todos os documentos em uma única requisição. Com volume maior, a página ficará lenta e consumirá mais memória.

Referência:

- `page-candidatos-ppgcf.php:25`

Recomendação: adicionar paginação, pesquisa por nome/CPF/e-mail e filtros por nível e tipo de vaga.

## Ordem recomendada de correção

Pendentes, em ordem de prioridade:

1. Bloquear o acesso público direto aos documentos (item 1).
2. Adicionar rate limiting e proteção contra automação (item 3).
3. Fortalecer toda a validação do backend (item 4).
4. Excluir uploads substituídos imediatamente (item 8).
5. Restringir a consulta de candidatos por capacidade específica (item 10).
6. Definir os requisitos de privacidade, retenção e auditoria (item 11).
7. Concluir as melhorias de acessibilidade e adicionar paginação administrativa (itens 12 e 13).

Concluídos e retestados: correção dos links de PDFs, bloqueio definitivo após o sucesso, identificação do campo com token expirado, timeout e controles individuais de upload e rollback de persistência (itens 2, 5, 6, 7 e 9).

## Conclusão

A base funcional está consistente: regras condicionais, uploads assíncronos, submissão, persistência e listagem funcionaram no E2E. Os itens 2, 5, 6, 7 e 9 foram corrigidos e retestados. Entretanto, a exposição direta dos documentos descrita no item 1 impede o uso seguro em produção e deve ser tratada como bloqueadora.

Depois da correção dos itens críticos e de alta prioridade restantes, recomenda-se repetir o E2E completo, incluindo limite de requisições e acesso aos documentos com diferentes perfis de usuário. Expiração de token e falha parcial de persistência já possuem cobertura específica no reteste desta revisão.
