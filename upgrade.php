<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_install/upgrade.php,v 1.1.1.1.2.4 2005/09/24 10:16:32 squareing Exp $
 * @package install
 * @subpackage upgrade
 */

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

/**
 * required setup
 */
require_once( 'install_inc.php' );

// this variable will be appended to the template file called - useful for displaying messages after data input
$app = '';

// work out where in the installation process we are
if( !isset( $_REQUEST['step'] ) ) {
	$_REQUEST['step'] = 0;
}
$step = $_REQUEST['step'];

// updating $install_file name
$i = 0;
$install_file[$i]['file'] = 'welcome';
$install_file[$i++]['name'] = 'Welcome';
$install_file[$i]['file'] = 'database';
$install_file[$i++]['name'] = 'Database Connection';
$install_file[$i]['file'] = 'packages';
$install_file[$i++]['name'] = 'Upgrade Selection';
$install_file[$i]['file'] = 'final';
$install_file[$i]['name'] = 'Upgrade Complete';

// currently i can't think of a better way to secure the upgrade pages
// redirect to the installer if we aren't sent here by the installer and the upgrade session variable hasn't been set
if( !isset( $_SESSION['upgrade'] ) || $_SESSION['upgrade'] != TRUE ||
	!isset( $_SERVER['HTTP_REFERER'] ) || 
	isset( $_SERVER['HTTP_REFERER'] ) &&
	( ( !strpos( $_SERVER['HTTP_REFERER'],'install/install.php' ) ) && ( !strpos( $_SERVER['HTTP_REFERER'],'install/upgrade.php' ) ) ) 
) {
	header( 'Location: '.INSTALL_PKG_URL.'install.php' );
	die;
}

// finally we are ready to include the actual php file
include_once( 'upgrade_'.$install_file[$step]['file'].'.php' );

$install_file = set_menu( $install_file, $step );

$gBitSmarty->assign( 'menu_file', 'upgrade.php' );
$gBitSmarty->assign( 'section', 'Upgrade' );

$gBitSmarty->assign( 'install_file', INSTALL_PKG_PATH."templates/upgrade_".$install_file[$step]['file'].$app.".tpl" );
$gBitInstaller->display( INSTALL_PKG_PATH.'templates/install.tpl', $install_file[$step]['name'] );

// -------------------------------------------------------------------------------------------------------- //
/*
global $gUpgradeFrom, $gUpgradeTo, $gBitSystem;

$step=0; // not used but keeps warnings down
require_once( 'install_inc.php' );

$upgradePath = array (
	'TikiWiki 1.8' => array( 'TIKIWIKI18' => 'BONNIE', 'BONNIE' => 'CLYDE' ),
	'BONNIE' => array( 'BONNIE' => 'CLYDE' ),
);


if ( isset( $_REQUEST['fSubmitWelcome'] ) ) {
	$install_file = 'install_database';
} elseif ( isset( $_REQUEST['fSubmitDbInfo'] ) ) {
	create_config($_REQUEST['db'],$_REQUEST['host'], $_REQUEST['user'],$_REQUEST['pass'],$_REQUEST['name'],$_REQUEST['prefix'],$_REQUEST['baseurl']);
	include_once( '../kernel/config_inc.php' ); // relative, but we know we are in the installer here...
	$gBitInstaller->scanPackages( 'admin/upgrade_inc.php' );
	$install_file = 'upgrade_ready';
} elseif( !empty( $_REQUEST['upgrade'] ) ) {

	if( isset( $upgradePath[$_REQUEST['upgrade_from']] ) ) {

		if( !empty( $gDebug ) || !empty( $_REQUEST['debug'] ) ) {
			$gBitInstaller->debug();
		}

		foreach( $upgradePath[$_REQUEST['upgrade_from']] as $from=>$to ) {
			global $gUpgradeFrom, $gUpgradeTo;
			$gUpgradeFrom = $from;  
			$gUpgradeTo = $to;

			$gBitInstaller->scanPackages( 'admin/upgrade_inc.php', FALSE );
			$firstPackages = array_flip( array( 'kernel', 'users', 'liberty', 'wiki', 'blogs' ) );
			$secondPackages = array_flip( array_keys( $gBitSystem->mUpgrades ) );
			
			// upgrade the ones that are order critical first
			foreach( array_keys( $firstPackages ) as $package ) {
				$gBitInstaller->upgradePackage( $package );
				unset( $secondPackages[$package] );
			}
			
			// upgrade remaining packages
			foreach( array_keys( $secondPackages ) as $package ) {
				$gBitInstaller->upgradePackage( $package );
			}
			unset( $gBitInstaller->mUpgrades );
		}
	}

	$install_file = 'upgrade_results';
} else {
	$install_file= 'upgrade_welcome';
	$gBitSmarty->assign( 'upgradeFrom', $gUpgradeFrom );
	$gBitSmarty->assign( 'upgradeTo', $gUpgradeTo );
}

$gBitSmarty->assign( 'install_file', INSTALL_PKG_PATH."templates/".$install_file.".tpl" );
if( file_exists( $install_file.'.php' ) ) {
	// finally we are ready to include the actual php file
	include_once( $install_file.'.php' );
}

$gBitSmarty->display( INSTALL_PKG_PATH.'templates/install.tpl' );
*/

?>
