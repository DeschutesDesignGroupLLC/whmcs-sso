<?php

add_hook('ClientAreaPage', 1, function( $vars ) {

	// Get logged in client
	$client = Menu::context('client');

	// If user is not logged in
	if ( !$client ) {
	}
});