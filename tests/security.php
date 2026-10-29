<?php
function add_action(...$args) {}
function wp_unslash($s) { return stripslashes($s); }
function sanitize_text_field($s) { return strip_tags($s); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return preg_match('~^https?://~i',$s) ? htmlspecialchars($s,ENT_QUOTES,'UTF-8') : ''; }
class FakeDatabase {
 public $row=null; public $bound;
 function prepare($query,...$values) { $this->bound=$values;return $query; }
 function get_row($query) { return $this->row; }
}
$wpdb=new FakeDatabase();
require dirname(__DIR__).'/dynamic-pages.php';
function render() { ob_start();validar_ementas_content_handler();return ob_get_clean(); }
$_GET['codigo']="'><svg/onload=alert(1)>";
$html=render();assert(strpos($html,'<svg')===false);assert(strpos($html,'&#039;')!==false);
$_GET['codigo']=['invalid'];assert(render()==='');
$_GET['codigo']='normal';
$wpdb->row=(object)array_fill_keys(['cod_disciplina','nome','subnome','descricao'],"<img src=x onerror=alert(1)>");
$wpdb->row->arquivo_ementa="javascript:alert(1)' onclick='alert(2)";
$html=render();assert(strpos($html,'<img')===false);assert(strpos($html,"href='javascript:")===false);assert(strpos($html,"' onclick='")===false);
$wpdb->row->arquivo_ementa='https://www.farmacia.ufmg.br/doc.pdf';
$html=render();assert(strpos($html,"href='https://www.farmacia.ufmg.br/doc.pdf'")!==false);assert($wpdb->bound===['normal']);
echo "PASS: reflected and stored XSS, array input, bound lookup, valid document link\n";
