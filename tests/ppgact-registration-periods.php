<?php
/** Testes isolados: php tests/ppgact-registration-periods.php */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('ABSPATH', dirname(__DIR__) . '/');
$GLOBALS['options'] = [];
$GLOBALS['can_edit'] = true;
$GLOBALS['now'] = new DateTimeImmutable('2026-11-03T10:00', new DateTimeZone('America/Sao_Paulo'));
function add_action(...$args) {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$args) { $GLOBALS['options'][$key] = $value; return true; }
function wp_timezone() { return new DateTimeZone('America/Sao_Paulo'); }
function current_datetime() { return $GLOBALS['now']; }
function current_user_can($cap) { return $GLOBALS['can_edit']; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_verify_nonce($nonce, $action) { return $nonce === $action; }
class WP_Error { public function __construct(public $code, public $message) {} public function get_error_message() { return $this->message; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
class JsonResponse extends RuntimeException { public function __construct(public $data, public $status) {} }
function wp_send_json_error($data, $status) { throw new JsonResponse($data, $status); }
function check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
function expect_json($callback, $code, $field = null) {
    try { $callback(); throw new RuntimeException('A requisição deveria ter sido bloqueada.'); }
    catch (JsonResponse $response) { check($response->data['code'] === $code && ($field === null || ($response->data['field'] ?? '') === $field), 'Erro inesperado: ' . json_encode($response->data)); }
}
function period($enabled, $opens = '2026-11-01T08:00', $closes = '2026-11-10T18:00') { return ['enabled'=>$enabled, 'opens_at'=>$opens, 'closes_at'=>$closes]; }
function saved($master = true, $doctor = true) {
    return ['production'=>['Mestrado'=>period($master), 'Doutorado'=>period($doctor,'2026-11-05T08:00','2026-11-20T18:00')], 'test'=>['Mestrado'=>period(false), 'Doutorado'=>period(false)]];
}
function settings_request($value) {
    $posted = $value;
    foreach ($posted as &$context) { foreach ($context as &$level) { if ($level['enabled']) { $level['enabled']='1'; } else { unset($level['enabled']); } } unset($level); } unset($context);
    $_POST = ['fafar_ppgact_schedule_action'=>'save', 'fafar_ppgact_schedule_nonce'=>'fafar_ppgact_save_schedules', 'fafar_ppgact_schedule_format'=>'per_level', 'fafar_ppgact_schedule'=>$posted];
}
require dirname(__DIR__) . '/ppgact.php';
require dirname(__DIR__) . '/ppgcf.php';
$key='fafar_ppgact_registration_schedules';
$GLOBALS['options'][$key] = ['production'=>['opens_at'=>'2026-11-01T08:00','closes_at'=>'2026-11-10T18:00'], 'test'=>['opens_at'=>'','closes_at'=>'']];
$migrated=fafar_ppgact_registration_schedules();
check($migrated['production']['Doutorado']===period(true), 'Prazo legado não foi preservado.');
check($migrated['production']['Mestrado']['enabled']===false, 'Migração abriu mestrado.');
check(fafar_ppgact_registration_schedule_state('production')['open_levels']===['Doutorado'], 'Comportamento legado alterado.');
echo "PASS: migração preserva o prazo anterior sem abrir mestrado.\n";
$GLOBALS['options'][$key]=saved();
$timeline=[
 ['2026-10-20T10:00', [], 'scheduled'],
 ['2026-10-30T10:00', [], 'upcoming'],
 ['2026-11-01T08:00', ['Mestrado'], 'open'],
 ['2026-11-05T08:00', ['Mestrado','Doutorado'], 'open'],
 ['2026-11-10T18:00', ['Doutorado'], 'open'],
 ['2026-11-20T18:00', [], 'closed'],
];
foreach($timeline as [$time,$levels,$status]) {
    $GLOBALS['now']=new DateTimeImmutable($time,wp_timezone());
    $state=fafar_ppgact_registration_schedule_state('production');
    check($state['open_levels']===$levels && $state['status']===$status, 'Disponibilidade incorreta em '.$time);
}
check(fafar_ppgact_registration_schedule_state('test')['status']==='disabled','Formulário de testes abriu junto.');
$GLOBALS['now']=new DateTimeImmutable('2026-11-06T10:00',wp_timezone());
foreach ([[false,true,['Doutorado']],[true,false,['Mestrado']]] as [$master,$doctor,$expected]) {
    $GLOBALS['options'][$key]=saved($master,$doctor);
    check(fafar_ppgact_registration_schedule_state('production')['open_levels']===$expected,'Checkbox não desativa apenas o nível selecionado.');
}
$GLOBALS['options'][$key]=saved();
$GLOBALS['options'][$key]['production']['Mestrado']['closes_at']='2026-11-03T18:00';
$GLOBALS['now']=new DateTimeImmutable('2026-11-04T10:00',wp_timezone());
$gap=fafar_ppgact_registration_schedule_state('production');
check($gap['status']==='upcoming' && $gap['opens_at']->format('Y-m-d\TH:i')==='2026-11-05T08:00','Intervalo entre períodos não anuncia a próxima abertura.');
$GLOBALS['options'][$key]=saved(false,false);
$GLOBALS['options'][$key]['test']['Mestrado']=period(true);
check(fafar_ppgact_registration_schedule_state('production')['status']==='disabled' && fafar_ppgact_registration_schedule_state('test')['open_levels']===['Mestrado'],'Período de testes interfere na produção.');
$GLOBALS['options'][$key]=saved();
echo "PASS: só mestrado, ambos, só doutorado e encerramento nos limites exatos; testes independentes.\n";
$GLOBALS['now']=new DateTimeImmutable('2026-11-11T10:00',wp_timezone());
expect_json(fn()=>fafar_ppgact_verify_registration_period('production','Mestrado'),'registration_period_closed','NivelDaVaga');
fafar_ppgact_verify_registration_period('production','Doutorado');
$_POST=['nonce'=>'ppgact_form_production','registration_level'=>'Mestrado'];
expect_json(fn()=>fafar_ppgact_upload_file(),'registration_period_closed','NivelDaVaga');
$_POST['registration_level']='Doutorado';
expect_json(fn()=>fafar_ppgact_upload_file(),'invalid_upload');
unset($_POST['registration_level']);
expect_json(fn()=>fafar_ppgact_upload_file(),'invalid_option','NivelDaVaga');
foreach(['Mestrado','Doutorado'] as $level) {
    $data=array_fill_keys(fafar_ppgact_required_fields(['TipoDaVaga'=>'Ampla concorrência']),'Teste');
    $data=array_merge($data,['NivelDaVaga'=>$level,'TipoDaVaga'=>'Ampla concorrência','Sexo'=>'Feminino','Email'=>'teste@example.test','Estado'=>'MG']);
    $_POST=['nonce'=>'ppgact_form_production','candidate_data'=>json_encode($data),'uploaded_files'=>'{}'];
    expect_json(fn()=>fafar_ppgact_submit_form(),$level==='Mestrado'?'registration_period_closed':'required_upload',$level==='Mestrado'?'NivelDaVaga':'ArquivoFormularioInscricao');
}
$master_files=fafar_ppgact_required_files(['NivelDaVaga'=>'Mestrado']);
$doctor_files=fafar_ppgact_required_files(['NivelDaVaga'=>'Doutorado']);
check(!in_array('ArquivoProjetoTese',$master_files,true) && in_array('ArquivoProjetoTese',$doctor_files,true),'Documentos de doutorado exigidos no mestrado.');
echo "PASS: uploads e submissão do mestrado encerrado bloqueados mesmo com doutorado aberto.\n";
settings_request(saved());
check(fafar_ppgact_save_registration_schedules_from_request()===true,'Não salvou configuração.');
check($GLOBALS['options'][$key]===saved(),'Quatro períodos não foram persistidos de forma independente.');
$before=$GLOBALS['options'][$key];
$_POST['fafar_ppgact_schedule']['test']['Doutorado']['closes_at']='2026-11-01T07:00';
check(is_wp_error(fafar_ppgact_save_registration_schedules_from_request()) && $GLOBALS['options'][$key]===$before,'Configuração inválida gravou parcialmente.');
settings_request(saved());
$_POST['fafar_ppgact_schedule']['production']['Mestrado']['opens_at']='2026-02-30T08:00';
check(is_wp_error(fafar_ppgact_save_registration_schedules_from_request()),'Data impossível aceita.');
settings_request(saved());
$GLOBALS['can_edit']=false;
check(is_wp_error(fafar_ppgact_save_registration_schedules_from_request()) && $GLOBALS['options'][$key]===$before,'Conta sem permissão alterou configuração.');
$GLOBALS['can_edit']=true;
$_POST['fafar_ppgact_schedule_nonce']='incorreto';
check(is_wp_error(fafar_ppgact_save_registration_schedules_from_request()),'Nonce inválido aceito.');
settings_request(saved());unset($_POST['fafar_ppgact_schedule_format']);
check(is_wp_error(fafar_ppgact_save_registration_schedules_from_request()) && $GLOBALS['options'][$key]===$before,'Formulário antigo sobrescreveu os novos prazos.');
settings_request(saved(false,false));
check(fafar_ppgact_save_registration_schedules_from_request()===true && fafar_ppgact_registration_schedule_state('production')['status']==='disabled','Desmarcar ambos não desativou.');
check(fafar_ppgcf_registration_schedules()['production']===['opens_at'=>'','closes_at'=>''],'PPGCF foi alterado.');
echo "PASS: quatro períodos salvos atomicamente; permissões, nonce, datas inválidas e formulário antigo verificados.\n";
// Renderização dos templates com banco e rede simulados.
function get_stylesheet_directory() { return dirname(__DIR__); }
function get_stylesheet_directory_uri() { return 'https://example.test/tema'; }
function wp_get_upload_dir() { return ['baseurl'=>'https://example.test/uploads']; }
function trailingslashit($v) { return rtrim($v,'/').'/'; }
function nocache_headers() {}
function wp_enqueue_style(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function admin_url($p) { return '/wp-admin/'.$p; }
function home_url($p) { return 'https://example.test'.$p; }
function wp_create_nonce($action) { return $action; }
function wp_nonce_field(...$args) {}
function wp_timezone_string() { return 'America/Sao_Paulo'; }
function checked($checked,$current=true,$echo=true) { $s=$checked==$current?'checked="checked"':'';if($echo)echo $s;return $s; }
function get_header() {}
function get_footer() {}
function astra_page_layout() { return 'no-sidebar'; }
function astra_primary_class() {}
function astra_primary_content_top() {}
function astra_primary_content_bottom() {}
function have_posts() { return $GLOBALS['remaining_posts']-- > 0; }
function the_post() {}
function astra_entry_before() {}
function astra_entry_after() {}
function astra_entry_top() {}
function astra_entry_bottom() {}
function astra_entry_header_class() {}
function astra_banner_elements_order() {}
function astra_entry_content_before() {}
function astra_entry_content_after() {}
function get_the_ID() { return 1; }
function get_post_class() { return []; }
function astra_attr(...$args) { return ''; }
function wp_kses_post($v) { return $v; }
function esc_url($v) { return htmlspecialchars($v,ENT_QUOTES,'UTF-8'); }
function esc_attr($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function wp_date($format,$time,$zone) { return (new DateTimeImmutable('@'.$time))->setTimezone($zone)->format($format); }
function is_user_logged_in() { return true; }
function wp_reset_postdata() {}
class WP_Query { public $found_posts=0; public function __construct($args) {} public function have_posts() { return false; } }
function render_template($file) { $GLOBALS['remaining_posts']=1; ob_start();require dirname(__DIR__).'/'.$file;return ob_get_clean(); }
function xpath($html) { $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NOERROR|LIBXML_NOWARNING);return new DOMXPath($dom); }
$_POST=[];$GLOBALS['options'][$key]=saved();
foreach($timeline as [$time,$expected,$status]) {
    $GLOBALS['now']=new DateTimeImmutable($time,wp_timezone());
    $html=render_template('page-inscricao-ppgact.php');
    $xml=xpath($html);$actual=[];
    foreach($xml->query('//input[@name="NivelDaVaga"]') as $node) { $actual[]=$node->getAttribute('value'); }
    check($actual===$expected,'Formulário exibe nível fora do período em '.$time);
    if($expected) check($xml->query('//input[@name="NivelDaVaga" and @checked]')->length===1,'Nenhum nível inicialmente selecionado.');
}
$GLOBALS['now']=new DateTimeImmutable('2026-11-06T10:00',wp_timezone());
$html=render_template('page-candidatos-ppgact.php');$xml=xpath($html);
check($xml->query('//input[@type="checkbox"]')->length===4,'Faltam checkboxes.');
check($xml->query('//input[@type="datetime-local"]')->length===8,'Faltam oito campos de data/hora.');
foreach(['production','test'] as $ctx) foreach(['mestrado','doutorado'] as $slug) foreach(['opens','closes'] as $action) {
    check($xml->query('//input[@id="fafar-'.$ctx.'-'.$slug.'-'.$action.'-at"]')->length===1,'ID de data duplicado ou ausente.');
}
check(str_contains(file_get_contents(dirname(__DIR__).'/assets/js/inscricao-ppgact.js'),"requestData.append('registration_level', checkedValue('NivelDaVaga'))"),'Upload não informa nível.');
echo "PASS: formulário acompanha o calendário; gerenciador renderiza quatro checkboxes e oito campos de data/hora.\n";
