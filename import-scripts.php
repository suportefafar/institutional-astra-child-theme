<?php

add_action( 'wp_head', 'add_header_custom_scripts' );

add_action( 'wp_footer', 'add_footer_custom_scripts' );

function add_header_custom_scripts(){
	?>
        <meta name="theme-color" content="#F2B600">
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<?php
}


function add_footer_custom_scripts(){
	?>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
		<script src="<?= get_stylesheet_directory_uri() ?>/assets/js/index.js" ></script>
	<?php
}
