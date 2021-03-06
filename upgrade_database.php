<?php
/**
 * @version $Header$
 * @package install
 * @subpackage upgrade
 */

/**
 * required setup
 */
$app = '';
include_once( INSTALL_PKG_PATH.'install_database.php' );
// update the MySQL settings in the db server array
if( function_exists( 'mysql_connect' ) ) {
	// check version of mysql server
	if( preg_match( '/[4-9]\./', @mysql_get_server_info() ) ) {
		$dbtodsn['mysql'] = 'MySQL '.mysql_get_server_info();
	} elseif( preg_match( '/3\./', @mysql_get_server_info() ) ) {
		$dbtodsn['mysql'] = 'MySQL '.mysql_get_server_info().' WARNING!!!';
	} else {
		$dbtodsn['mysql'] = 'MySQL 3.x';
	}
}
$gBitSmarty->assign( 'include_template', INSTALL_PKG_PATH."templates/install_database".$app.".tpl" );
?>
