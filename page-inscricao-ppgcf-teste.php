<?php
/**
 * Página de testes do formulário de inscrição do PPGCF.
 *
 * Reutiliza integralmente o formulário de produção, mas consulta o período
 * independente configurado para testes.
 *
 * @package Institutional_Astra_Child_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fafar_ppgcf_schedule_context = 'test';
require get_stylesheet_directory() . '/page-inscricao-ppgcf.php';
