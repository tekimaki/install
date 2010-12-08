<?php
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 *
 * @copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.
 */

/**
 * we set this variable since || admin >> kernel >> packages || uses this file as well
 */
if( !isset( $step ) ) {
	$step = NULL;
}

// set the maximum execution time to very high
ini_set( "max_execution_time", "86400" );

// assign next step in installation process
$gBitSmarty->assign( 'next_step', $step );

// pass all package data to template
$schema = $gBitInstaller->mPackagesSchemas;
ksort( $schema );
$gBitSmarty->assign_by_ref( 'schema', $schema );

// confirm that we have all the admin data in the session before proceeding
if( !empty( $_REQUEST['packages'] ) && in_array( 'users', $_REQUEST['packages'] ) && ( empty( $_SESSION['login'] ) || empty( $_SESSION['password'] ) || empty( $_SESSION['email'] ) ) ) {
	// we have lost our session password and we are not installed
	header( 'Location: '.INSTALL_PKG_URL.'install.php?step=1' );
	die;
}

// cancel
if( !empty( $_REQUEST['cancel'] ) ) {
	header( 'Location: '.INSTALL_PKG_URL.'install.php?step='.( $step + 1 ) );
// process packages 
} elseif( 
	!empty( $_REQUEST['submit_packages'] ) && 
	!empty( $_REQUEST['method'] ) && 
	( !empty( $_REQUEST['packages'] ) && is_array( $_REQUEST['packages'] ) ) || 
	( !empty( $_REQUEST['package_plugins'] ) && is_array( $_REQUEST['package_plugins'] ) )  
	) {

	if( empty( $_REQUEST['packages'] ) ){ $_REQUEST['packages'] = array(); }
	if( empty( $_REQUEST['package_plugins'] ) ){ $_REQUEST['package_plugins'] = array(); }
	
	// DEL $failedcommands = array();

	// shorthand for the actions we are supposed to perform during an unistall or re-install
	$removeActions = !empty( $_REQUEST['remove_actions'] ) ? $_REQUEST['remove_actions'] : array();

	// make sure that required pkgs are only present when we are installing
	if(( $method = ( $_REQUEST['method'] )) == 'install' && !$_SESSION['first_install'] ) {
		// make sure no required packages are included in this list
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
			if( in_array( $package, $_REQUEST['packages'] ) && !empty( $packageHash['required'] ) && $gBitSystem->isPackageInstalled($package) ) {
				$gBitSmarty->assign( 'warning', "Something unexpected has happened: One of the required packages which is already installed has appeared in the list of selected packages. This generally only happens if the installation is missing a core database table. Please contact the bitweaver developers team on how to proceed." );
				$method = FALSE;
			}
		}
	} elseif( $method != 'install' && empty( $removeActions ) ) {
		// we are un / reinstalling stuff but no actions have been selected
		$gBitSmarty->assign( 'warning', "You have selected to un / reinstall packages but have not selected any options. Please select at least one." );
		return FALSE;
	}

	if( $gBitDbType == 'sybase' ) {
		// avoid database change messages
		ini_set('sybct.min_server_severity', '11');
	}

	global $gBitInstallDb;
	$gBitInstallDb = &ADONewConnection( $gBitDbType );

	if( !empty( $gDebug ) || !empty( $_REQUEST['debug'] ) ) {
		$gBitInstaller->debug();
		$gBitInstallDb->debug = 99;
	}

	// by now $method should be populated with something
	if( $gBitInstallDb->Connect( $gBitDbHost, $gBitDbUser, $gBitDbPassword, $gBitDbName ) && !empty( $method ) ) {
		if ( $_SESSION['first_install'] && $gBitDbType == 'firebird' ) {
// Leave commented for present, new installations on Firebird should use FB2.1.x and above which have an internal function library
//			$result = $gBitInstallDb->Execute( "DECLARE EXTERNAL FUNCTION LOWER CSTRING(80) RETURNS CSTRING(80) FREE_IT ENTRY_POINT 'IB_UDF_lower' MODULE_NAME 'ib_udf'" );
//			$result = $gBitInstallDb->Execute( "DECLARE EXTERNAL FUNCTION RAND RETURNS DOUBLE PRECISION BY VALUE ENTRY_POINT 'IB_UDF_rand' MODULE_NAME 'ib_udf'" );
		}

		$packageList = array();

		// initialize the datadictionary
		$gBitInstaller->initDataDict( $gBitInstallDb );

		if( !$gBitInstaller->mDb->getCaseSensitivity() ) {
			// set nameQuote to blank
			$gBitInstaller->mDataDict->connection->nameQuote = '';
		}

		// When using MySql and installing further packages after first install
		// check to see what storage engine in use, InnoDb or MyIsam,
		// so we don't end up with mixed table types.
		if( $gBitInstaller->isPackageInstalled('kernel') ) {
			global $gBitDbType;
			if( preg_match( '/mysql/', $gBitDbType )) {
				$_SESSION['use_innodb'] = FALSE;
				$rs = $gBitDb->Execute("SHOW TABLE STATUS LIKE '%kernel_config'");
				while ( !$rs->EOF) {
					$row = $rs->GetRowAssoc(false);
					switch( isset( $row['Engine'] ) ? strtoupper( $row['Engine'] ) : strtoupper( $row['Type'] )) {
						case 'INNODB':
						case 'INNOBASE':
							$_SESSION['use_innodb'] = TRUE;
							break 2;
					}

					$rs->MoveNext();
				}
				$rs->Close();
			}
		}

		$sqlArray = array();

		//error_reporting( E_ALL );
		// packages are sorted alphabetically. but we really need a /etc/rc.d/rc.3 style loading precidence!
		// We perform several loops through mPackagesSchemas due to foreign keys, and some packages may insert
		// value into other packages tables - typically users_permissions, bit_preferences, etc...
		sort( $_REQUEST['packages'] );


		// Need to unquote constraints. but this need replacing with a datadict function
		require_once('../kernel/BitDbBase.php');
		global $gBitKernelDb;
		$gBitKernelDb = new BitDb();
		$gBitKernelDb->mType = $gBitDbType;


		// ******************************* Install Packages *********************************//
		// -------------------- Tables -------------------
		// Do all tables first so that we avoid chicken egg problems
		// with constraints between packages, particularly liberty &
		// users packages.
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
			if( in_array( $package, $_REQUEST['packages'] )) {
				// if dropping tables (handled in installPackageTables - insane!) we need to drop content and settings first
				// expunge packages content
				if( in_array( 'content', $removeActions ) ) {
					$gBitInstaller->expungePackageContent( $packageHash, $method, $removeActions );
				}
				// expunge packages settings
				if( in_array( 'settings', $removeActions ) ) {
					$gBitInstaller->expungePackageSettings( $packageHash, $method, $removeActions );
				}
				// generate all the tables's
				$gBitInstaller->installPackageTables( $packageHash, $method, $removeActions );
				// generate all the indexes
				$gBitInstaller->installIndexes( $packageHash, $method, $removeActions );
				// generate all the sequences
				$gBitInstaller->installSequences( $packageHash, $method, $removeActions );
			}
		}
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
			if( in_array( $package, $_REQUEST['packages'] )) {
				// install additional constraints
				$gBitInstaller->installConstraints( $packageHash, $method, $removeActions );
			}
		}

		// -------------------- Settings -------------------
		// manipulate the data in kernel_config and package content
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
			if( in_array( $package, $_REQUEST['packages'] ) ) {
				// set installed packages active
				if( $method == 'install' || $method == 'reinstall' ) {
					$gBitInstaller->setPackageActive( $packageHash );
				}
			}
		}

		// ------- Defaults Preferences Permissions -------
		// run the defaults through afterwards so we can be sure 
		// all tables needed have been created
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
			if( in_array( $package, $_REQUEST['packages'] ) &&
			// @TODO these install qualifiers are a mess - clean this up to simplify this stuff
			( $method == 'install' || ( $method == 'reinstall' && in_array( 'settings', $removeActions )))) {
				// install defaults (this is just raw sql)
				$gBitInstaller->installDefaults( $packageHash, $method, $removeActions );
				// install preferences
				$gBitInstaller->installPreferences( $packageHash, $method, $removeActions );
				// install permissions
				$gBitInstaller->installPermissions( $packageHash, $method, $removeActions, $packageHash['guid'] );
				// install plugin apis
				$gBitInstaller->installPluginAPIs(  $packageHash, $method, $removeActions );

				// this is to list any processed packages
				$packageList[$method][] = $package;
			}
		}

		// ------------- Register Content Types -----------
		// register all content types for installed packages
		$gBitInstaller->registerContentTypes();

		// ***************************** End Install Packages *******************************//




		// ***************************** Install Package Plugins ****************************//
		// -------------------- Tables -------------------
		// @TODO plugins should be sorted by dependencies and installed in that order
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ){
			if( !empty( $packageHash['plugins'] ) && !empty( $_REQUEST['package_plugins'][$package] ) ){
				foreach( $packageHash['plugins'] as $pluginGuid=>$pluginHash ){
					if( in_array( $pluginGuid, $_REQUEST['package_plugins'][$package] ) ){
						// expunge plugins content
						if( in_array( 'content', $removeActions ) ) {
							$gBitInstaller->expungePluginContent( $pluginHash, $method, $removeActions );
						}
						// expunge plugin settings
						if( in_array( 'settings', $removeActions ) ) {
							$gBitInstaller->expungePluginSettings( $pluginHash, $method, $removeActions );
						}

						// @TODO debug these calls - make sure pluginHash can be submitted
						// generate all the tables's
						$gBitInstaller->installPluginTables( $pluginHash, $method, $removeActions );
						// generate all the indexes
						$gBitInstaller->installIndexes( $pluginHash, $method, $removeActions );
						// generate all the  sequences
						$gBitInstaller->installSequences( $pluginHash, $method, $removeActions );
					}
				}
			}
		}
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ){
			if( !empty( $packageHash['plugins'] ) && !empty( $_REQUEST['package_plugins'][$package] ) ){
				foreach( $packageHash['plugins'] as $pluginGuid=>$pluginHash ){
					if( in_array( $pluginGuid, $_REQUEST['package_plugins'][$package] ) ){
						// install additional constraints
						$gBitInstaller->installConstraints( $pluginHash, $method, $removeActions );
					}
				}
			}
		}

		// @TODO install plugin settings
		// Register plugins and set active.
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ){
			if( !empty( $packageHash['plugins'] ) && !empty( $_REQUEST['package_plugins'][$package] ) ){
				foreach( $packageHash['plugins'] as $pluginGuid=>$pluginHash ){
					if( in_array( $pluginGuid, $_REQUEST['package_plugins'][$package] ) ) {
						// modify the data hash with required params
						$pluginHash['guid'] = $pluginGuid;
						$pluginHash['package_guid'] = $package;

						// set installed packages active
						if( $method == 'install' || $method == 'reinstall' ) {
							$gBitInstaller->setPluginActive( $pluginHash );
						}
						// install api handlers - must be installed after plugin is inserting into package_plugins table due to constraints
						$gBitInstaller->installPluginAPIHandlers(  $pluginHash, $method, $removeActions );
					}
				}
			}
		}

		// @TODO install plugin content

		// ------- Defaults Preferences Permissions -------
		// run the defaults through afterwards so we can be sure 
		// all tables needed have been created
		foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ){
			if( !empty( $packageHash['plugins'] ) && !empty( $_REQUEST['package_plugins'][$package] ) ){
				foreach( $packageHash['plugins'] as $pluginGuid=>$pluginHash ){
					if( in_array( $pluginGuid, $_REQUEST['package_plugins'][$package] ) &&
					// @TODO these install qualifiers are a mess - clean this up to simplify this stuff
					( $method == 'install' || ( $method == 'reinstall' && in_array( 'settings', $removeActions )))) {
						// install defaults (this is just raw sql)
						$gBitInstaller->installDefaults( $pluginHash, $method, $removeActions );
						// install preferences
						$gBitInstaller->installPreferences( $pluginHash, $method, $removeActions );
						// install permissions
						$gBitInstaller->installPermissions( $pluginHash, $method, $removeActions, $packageHash['guid'] );

						// this is to list any processed packages
						$packageList[$method][] = $package.' plugin: '.$pluginGuid;
					}
				}
			}
		}

		// ---------- Content Type Preferences -----------
		// set lcconfig service configurations for all content types of installed packages
		// @TODO dont limit this to plugins - apply to all services
		// @TODO need to check if a service has been installed before - 
		// fix is to overhaul how services are registered not hack in 
		// any table checking non-sense
		if( !empty( $_REQUEST['package_plugins'] ) && $method == 'install'){
			require_once( LCCONFIG_PKG_PATH.'LCConfig.php' );
			require_once( LIBERTY_PKG_PATH.'LibertySystem.php' );
			$LCConfig = LCConfig::getInstance();
			$LSys = new LibertySystem();
			$LSys->loadContentTypes();
			// loop over each package installed 
			foreach( $_REQUEST['package_plugins'] as $pkg=>$plugins ){
				// loop over each package plugin installed
				foreach( $plugins as $plugin_guid ){
					$schema = $gBitSystem->getPackageSchema( $pkg );
					if( !empty( $schema['plugins'][$plugin_guid] ) ){
						$plugin = $schema['plugins'][$plugin_guid];
						foreach( array_keys( $LSys->mContentTypes ) as $ctype ) {
							// currently LCConfig prefers to store a negation - tho it can store a possitive association as well
							if( empty( $plugin['content_types'] ) || !in_array( $ctype, $plugin['content_types'] ) ){
								$LCConfig->storeConfig( 'service_'.$plugin_guid, $ctype, 'n');
							}else{
								$LCConfig->storeConfig( 'service_'.$plugin_guid, $ctype, 'y');
							}
						}
					}
				}
			}
		}
		// ************************* End Install Package Plugins ****************************//


		// ---------------------- 6. ----------------------
		// Do stuff that only applies during the first install
		if( isset( $_SESSION['first_install'] ) && $_SESSION['first_install'] == TRUE ) {
			// set the version of bitweaver in the database
			$gBitSystem->storeVersion( NULL, $gBitSystem->getBitVersion() );

			// install layout modules
			foreach( $gBitInstaller->mPackagesSchemas as $package=>$packageHash ) {
				if( in_array( $package, $_REQUEST['packages'] ) ) {
					if( !empty( $packageHash['modules'] ) ){
						foreach( $packageHash['modules'] as $moduleHash ){
							$gBitThemes->storeModule( $moduleHash );
						}
					}
				}
			}

			// Set the default format to get quicktags and content storing working
			$plugin_file = LIBERTY_PKG_PATH.'plugins/format.tikiwiki.php';
			if( is_readable( $plugin_file ) ) {
				require_once( $plugin_file );
				// manually set the config settings to avoid problems
				$gBitSystem->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."kernel_config` ( `config_name`, `package`, `config_value` ) VALUES ( 'liberty_plugin_file_".PLUGIN_GUID_TIKIWIKI."', '$plugin_file', '".LIBERTY_PKG_NAME."' )" );
				$gBitSystem->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."kernel_config` ( `config_name`, `package`, `config_value` ) VALUES ( 'liberty_plugin_status_".PLUGIN_GUID_TIKIWIKI."', 'y', '".LIBERTY_PKG_NAME."' )" );
				// it appear default_format is already set.
				$gBitSystem->storeConfig( 'default_format', PLUGIN_GUID_TIKIWIKI, LIBERTY_PKG_NAME );
			}

			// Installing users has some special things to take care of here and needs a separate check.
			if( in_array( 'users', $_REQUEST['packages'] ) ) {
				// Creating 'root' user has id=1. phpBB starts with user_id=2, so this is a hack to keep things in sync
				$rootUser = new BitPermUser();
				$storeHash = array(
					'real_name' => 'Root',
					'login'     => 'root',
					'password'  => $_SESSION['password'],
					'email'     => 'root@localhost',
					'pass_due'  => FALSE,
					'user_id'   => ROOT_USER_ID
				);
				if( $rootUser->store( $storeHash ) ) {
					$gBitUser->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."users_groups` (`user_id`, `group_id`, `group_name`,`group_desc`) VALUES ( ".ROOT_USER_ID.", 1, 'Administrators','Site operators')" );
					$rootUser->addUserToGroup( ROOT_USER_ID, 1 );
				} else {
					vd( 'Errors in root user store:'.PHP_EOL );
					vd( $rootUser->mErrors );
				}

				// now let's set up some default data. Group_id's are hardcoded in users/schema_inc defaults
				$gBitUser->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."users_groups` (`user_id`, `group_id`, `group_name`,`group_desc`) VALUES ( ".ROOT_USER_ID.", ".ANONYMOUS_GROUP_ID.", 'Anonymous','Public users not logged')" );
				$gBitUser->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."users_groups` (`user_id`, `group_id`, `group_name`,`group_desc`) VALUES ( ".ROOT_USER_ID.", 2, 'Editors','Site  Editors')" );
				$gBitUser->mDb->query( "INSERT INTO `".BIT_DB_PREFIX."users_groups` (`user_id`, `group_id`, `group_name`,`group_desc`,`is_default`) VALUES ( ".ROOT_USER_ID.", 3, 'Registered', 'Users logged into the system', 'y')" );

				$gBitUser->assignLevelPermissions( ANONYMOUS_GROUP_ID, 'basic' );
				$gBitUser->assignLevelPermissions( 3, 'registered' );
				$gBitUser->assignLevelPermissions( 2, 'editors' );
				$gBitUser->assignLevelPermissions( 1, 'admin' );

				// Create 'Anonymous' user has id= -1 just like phpBB
				$anonUser = new BitPermUser();
				$storeHash = array(
					'real_name'        => 'Guest',
					'login'            => 'guest',
					'password'         => $_SESSION['password'],
					'email'            => 'guest@localhost',
					'pass_due'         => FALSE,
					'user_id'          => ANONYMOUS_USER_ID,
					'default_group_id' => ANONYMOUS_GROUP_ID
				);
				if( $anonUser->store( $storeHash ) ) {
					// Remove anonymous from registered group
					$regGroupId = $anonUser->groupExists( 'Registered', ROOT_USER_ID );
					$anonUser->removeUserFromGroup( ANONYMOUS_USER_ID, $regGroupId );
					$anonUser->addUserToGroup( ANONYMOUS_USER_ID, ANONYMOUS_GROUP_ID);
				}

				$adminUser = new BitPermUser();
				$storeHash = array(
					'real_name' => $_SESSION['real_name'],
					'login'     => $_SESSION['login'],
					'password'  => $_SESSION['password'],
					'email'     => $_SESSION['email'],
					'pass_due'  => FALSE
				);
				if( $adminUser->store( $storeHash ) ) {
					// add user to admin group
					$adminUser->addUserToGroup( $adminUser->mUserId, 1 );
					// set admin group as default
					$adminUser->storeUserDefaultGroup( $adminUser->mUserId, 1 );
				}

				// kill admin info in $_SESSION
				unset( $_SESSION['real_name'] );
				unset( $_SESSION['login'] );
				unset( $_SESSION['password'] );
				unset( $_SESSION['email'] );
			}
		}



		// ---------------------- 7. ----------------------
		// woo! we're done with the installation bit - below here is some generic installer stuff
		$gBitSmarty->assign( 'next_step', $step + 1 );

		// display list of installed packages
		asort( $packageList );
		$gBitSmarty->assign( 'packageList', $packageList );

		// enter some log information to say we've initialised the system
		if( empty( $gBitInstaller->mFailedCommands ) && empty( $gBitInstaller->mErrors ) ) {
			$logHash['action_log'] = array(
				'user_id' => ROOT_USER_ID,
				'title' => 'System Installation',
				'log_message' => 'Packages were successfully installed and bitweaver is ready for use.',
			);

			if( empty( $_SESSION['first_install'] ) ) {
				$list = '';
				foreach( $packageList as $pkg ) {
					$list .= implode( ", ", $pkg );
				}
				$logHash['action_log']['title'] = "Package {$method}";
				$logHash['action_log']['log_message'] = "The following package(s) were {$method}ed: $list";
			} else {
				$gBitSystem->setConfig( 'liberty_action_log', 'y' );
			}

			LibertyContent::storeActionLog( $logHash );
		} else {
			$gBitSmarty->assign( 'errors', $gBitInstaller->mErrors);
			$gBitSmarty->assign( 'failedcommands', $gBitInstaller->mFailedCommands);
		}

		// display the confirmation page
		$app = '_done';
	} else {
		// if we can't connect to the db, move back 2 steps
		header( "Location: ".$_SERVER['PHP_SELF']."?step=".$step - 2 );
	}
} elseif( !empty( $_REQUEST['submit_packages'] ) ) {
	// No packages to install so just move to the next step.
	$gBitSmarty->assign( 'next_step', $step + 1 );
	$app = '_done';
}
