<?php
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 */

// assign next step in installation process
$gBitSmarty->assign( 'next_step', $step );

$schema = $gBitInstaller->mPackagesSchemas;
ksort( $schema );
$gBitSmarty->assign_by_ref( 'schema', $schema );



// ===================== Post install table check =====================
// $dbTables is the output of BitSystem::verifyInstalledPackages() in
// install_inc.php and contains all tables that are not present in the database
// - even tables of packages that are not installed
$dbIntegrity = install_check_database_integrity( $dbTables );



// ===================== Permissions =====================
// check all permissions, compare them to each other and see if there are old 
// permissions and ones that need to be inserted
$bitPerms = array();
$query = "SELECT * FROM `".BIT_DB_PREFIX."users_permissions` ORDER BY `package` ASC";
$result = $gBitInstaller->mDb->query( $query );
while ($res = $result->fetchRow()) {
	/*
	foreach( $result->fields as $r ) {
		$bitPerms[$result->fields['perm_name']][] = $r;
	}
	 */
	$bitPerms[$res['perm_name']] = $res;
	$bitPerms[$res['perm_name']]['sql'][] = "DELETE FROM `".BIT_DB_PREFIX."users_group_permissions` WHERE `perm_name`='".$res['perm_name']."'";
	$bitPerms[$res['perm_name']]['sql'][] = "DELETE FROM `".BIT_DB_PREFIX."users_permissions` WHERE `perm_name`='".$res['perm_name']."'";
}

// we will make sure all the permission levels are what they should be. we will 
// update these without consulting the user. this is purely backend stuff, has 
// no outcome on the site itself but determines what the default permission 
// level is. the user can never modify these settings.
foreach( $gBitInstaller->mPermissionsSchema as $perm => $permHash ){
	$bindVars = array();
	if( !empty( $bitPerms[$perm] ) && $permHash['level'] != $bitPerms[$perm]['perm_level'] ) {
		$query = "UPDATE `".BIT_DB_PREFIX."users_permissions` SET `perm_level` = ? WHERE `perm_name` = ?";
		$bindVars[] = $permHash['level'];
		$bindVars[] = $perm;
		$gBitInstaller->mDb->query( $query, $bindVars );
	}
}

// compare both perm arrays with each other and work out what permissions need 
// to be added and which ones removed
$insPerms = $delPerms = array();
foreach( $gBitInstaller->mPermissionsSchema as $perm => $permHash ){
	if( !in_array( $perm, array_keys( $bitPerms ))) {
		// require the package or plugin be installed to be processed
		if( $gBitInstaller->isPackageInstalled( $permHash['package'] ) &&											// package check
			( empty( $permHash['plugin_guid'] ) || $gBitInstaller->isPluginInstalled( $permHash['plugin_guid'] ))	// plugin check
		){
			$insPerms[$perm] = $permHash;
		}
	}
}

foreach( array_keys( $bitPerms ) as $perm ) {
	if( !in_array( $perm, array_keys( $gBitInstaller->mPermissionsSchema ) ) ) {
		$delPerms[$perm] = $bitPerms[$perm];
	}
}
$gBitSmarty->assign( 'delPerms', $delPerms );
$gBitSmarty->assign( 'insPerms', $insPerms );



// ===================== Process Form =====================
// create missing tables if possible
if( !empty(  $_REQUEST['create_tables'] ) && !empty( $dbIntegrity )) {
	$gBitInstallDb = &ADONewConnection( $gBitDbType );

	if( $gBitInstallDb->Connect( $gBitDbHost, $gBitDbUser, $gBitDbPassword, $gBitDbName )) {
		$dict = NewDataDictionary( $gBitInstallDb );

		if( !$gBitInstaller->mDb->getCaseSensitivity() ) {
			$dict->connection->nameQuote = '';
		}

		if( !empty( $gDebug ) || !empty( $_REQUEST['debug'] )) {
			$gBitInstaller->debug();
			$gBitInstallDb->debug = 99;
		}

		// If we use MySql check which storage engine to use
		if( isset( $_SESSION['use_innodb'] ) ){
			if( $_SESSION['use_innodb'] == TRUE ) {
				$build = array('NEW', 'MYSQL' => 'ENGINE=INNODB');
			} else {
				$build = array('NEW', 'MYSQL' => 'ENGINE=MYISAM');
			}
		} else {
			$build = 'NEW';
		}

		$tablePrefix = $gBitInstaller->getTablePrefix();
		foreach( $dbIntegrity as $package => $info ) {
			foreach( $info['tables'] as $table ) {
				$completeTableName = $tablePrefix.$table['name'];
				$sql = $dict->CreateTableSQL( $completeTableName, $gBitInstaller->mPackagesSchemas[$package]['tables'][$table['name']], $build );
				// Uncomment this line to see the create sql
				//vd( $sql );
				if( $sql ) {
					$dict->ExecuteSQLArray( $sql );
				}
			}
		}
	}
}

if( !empty(  $_REQUEST['resolve_conflicts'] ) ) {
	if( !empty( $gDebug ) || !empty( $_REQUEST['debug'] ) ) {
		$gBitInstaller->debug();
		$gBitInstallDb->debug = 99;
	}
	// === Permissions
	$fixedPermissions = array();
	$groupMap = array();
	$groupMap['basic'] = ANONYMOUS_GROUP_ID;
	$groupMap['registered'] = 3;
	$groupMap['editors'] = 2;
	$groupMap['admin'] = 1;
	if( !empty( $_REQUEST['perms'] ) ) {
		foreach( $_REQUEST['perms'] as $perm ) {
			if( !empty( $delPerms[$perm] )) {
				foreach( $delPerms[$perm]['sql'] as $sql ) {
					$gBitInstaller->mDb->query( $sql );
				}
				$fixedPermissions[] = $delPerms[$perm];
			}

			if( !empty( $insPerms[$perm] )) {
				$permHash = $insPerms[$perm];
				$gBitInstaller->installPermission( $perm, $permHash['description'], $permHash['level'], $permHash['package'] );
				$fixedPermissions[] = $insPerms[$perm];
				if( !empty( $groupMap[$insPerms[$perm]['level']] )) {
					$gBitUser->assignPermissionToGroup( $perm, $groupMap[$insPerms[$perm]['level']] );
				}
			}
		}
	}
	$gBitSmarty->assign( 'fixedPermissions', $fixedPermissions );

	// === Services
	/*
	$deActivated = array();
	foreach( $serviceList as $service ) {
		foreach( array_keys( $service ) as $package ) {
			$packages = !empty( $_REQUEST['packages'] ) ? $_REQUEST['packages'] : array();
			if( !in_array( $package, $packages )) {
				$gBitSystem->storeConfig( 'package_'.$package, 'n', KERNEL_PKG_NAME );
				$deActivated[] = $package;
			}
		}
	}
	 */

	$gBitSmarty->assign( 'next_step', $step + 1 );
	// $gBitSmarty->assign( 'deActivated', $deActivated );
	// display the confirmation page
	$app = '_done';
} elseif( !empty(  $_REQUEST['skip'] ) ) {
	// if there were no conflicts, we move on to the next page
	header( "Location: ".$_SERVER['PHP_SELF']."?step=".++$step );
}

// make sure everything is up to date after the above changes
$dbTables = $gBitInstaller->verifyInstalledPackages( 'all' );
$dbIntegrity = install_check_database_integrity( $dbTables );
$gBitSmarty->assign( 'dbIntegrity', $dbIntegrity );



/**
 * function - install_check_database_integrity
 */
function install_check_database_integrity( $pDbTables ) {
	global $gBitInstaller;
	$ret = array();
	if( !empty( $pDbTables['missing'] ) && is_array( $pDbTables['missing'] )) {
		foreach( array_keys( $pDbTables['missing'] ) as $package ) {
			// we can't use the 'installed' flag in $gBitInstaller->mPackages[$package] because that is set to 'not installed' as soon as a table is missing
			// at least one table is missing
			$ret[$package] = array(
				'name'     => ucfirst( $gBitInstaller->mPackagesSchemas[$package]['name'] ),
				'required' => $gBitInstaller->mPackagesSchemas[$package]['required'],
			);
			foreach( $pDbTables['missing'][$package] as $table ) {
				$ret[$package]['tables'][$table] = array(
					'name' => $table,
					'sql'  => $gBitInstaller->mPackagesSchemas[$package]['tables'][$table],
				);
			}
		}
	}
	return $ret;
}
?>
