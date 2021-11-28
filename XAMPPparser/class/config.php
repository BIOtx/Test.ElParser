<?php

    require( $_SERVER['DOCUMENT_ROOT'].'/XAMPPparser/class/db.php');
    require( $_SERVER['DOCUMENT_ROOT'].'/XAMPPparser/class/simple_html_dom.php');

    # Errors
	error_reporting(0);
 
    # Timezone
	date_default_timezone_set('Europe/Moscow');

    # Connect
	db::conn(		
		 'Eltox_parser'		      // user
		,'&TSqgxo1'               // password				
		,'localhost'              // host				
		,'Eltox_parser'		      // bd name
		,'utf8mb4'
	);

?>