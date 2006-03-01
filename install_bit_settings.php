<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_install/install_bit_settings.php,v 1.12 2006/03/01 20:16:12 spiderr Exp $
 * @package install
 * @subpackage functions
 */

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

// assign next step in installation process
$gBitSmarty->assign( 'next_step',$step );

function simple_set_value( $package, $feature ) {
	global $_REQUEST, $gBitSystem, $gBitSmarty;
	if( isset( $_REQUEST[$feature] ) ) {
		$gBitSystem->storeConfig( $feature, $_REQUEST[$feature], $package );
		$gBitSmarty->assign( $feature, $_REQUEST[$feature] );
	}
}

// pass all package data to template
$gBitSmarty->assign_by_ref( 'schema', $gBitInstaller->mPackages );

// settings that aren't just toggles
$formInstallValues = array(
	'bit_index'          => 'kernel',
	'kernel_server_name' => 'kernel',
	'site_title'         => 'kernel',
	'site_slogan'        => 'kernel',
	'bitlanguage'        => 'languages',
);

if( extension_loaded( 'imagick' ) && extension_loaded( 'gd' ) ) {
	$gBitSmarty->assign( 'choose_image_processor', TRUE );
	$formInstallValues[] = 'image_processor';
}

// get list of available languages
$languages = array();
$languages = $gBitLanguage->listLanguages();
$gBitSmarty->assign_by_ref("languages",$languages );

// process form
if( isset( $_REQUEST['fSubmitBitSettings'] ) ) {
	foreach( $formInstallValues as $item => $package) {
		simple_set_value( $package, $item );
	}

	if (empty($_REQUEST['bitlanguage'])) {
		$_REQUEST['bitlanguage'] = 'en';
	}

	if (!array_key_exists($_REQUEST['bitlanguage'], $languages)) {
		$languages[$_REQUEST['bitlanguage']] = '';
	}

	$gBitLanguage->setLanguage( $_REQUEST['bitlanguage'] );
	$gBitSmarty->assign( "siteLanguage",$languages[$_REQUEST['bitlanguage']] );
	// advance a step in the installer
	$app = '_done';
	$gBitSmarty->assign( 'next_step',$step + 1 );
} elseif( isset( $_REQUEST['skip'] ) ) {
	$goto = $step + 1;
	header( "Location: install.php?step=$goto" );
}

// get list of foreign packages that are ready to be installed
// @TODO this isn't working yet, since the info stuff isn't read from schema_inc.php on this page
$foreign_packages = array();
foreach( $gBitSystem->mPackages as $package ) {
	if( isset( $package['info']['install'] ) ) {
		$foreign_packages[] = $package;
	}
}
$gBitSmarty->assign( "foreign_packages", $foreign_packages );
?>
