<?php
/**
 * @version $Header$
 * @package install
 */

/**
 * @package install
 */
class BitInstaller extends BitSystem {

	/**
	 * mPackageUpgrades 
	 * 
	 * @var array
	 * @access public
	 */
	var $mPackageUpgrades = array();

	/**
	 * mRequirements 
	 * 
	 * @var array
	 * @access public
	 */
	var $mRequirements = array();

	/**
	 * mServices
	 *
	 * @var array
	 * @access public
	 * format mServices[service_name][content_type_guid, content_type_guid, ...]
	 */
	var $mServices = array();

	/**
	 * mPackagesSchemas
	 */
	var $mPackagesSchemas = array();

	/**
	 * mPermissionsSchema
	 */
	var $mPermissionsSchema = array();

	/**
	 * Initiolize BitInstaller 
	 * @access public
	 */
	function BitInstaller() {
		BitSystem::BitSystem();
		$this->getWebServerUid();
	}

	/**
	 * loadAllUpgradeFiles load upgrade files from all packages that are installed
	 * 
	 * @access public
	 * @return void
	 */
	function loadAllUpgradeFiles() {
		foreach( array_keys( $this->mPackages ) as $pkg ) {
			$this->loadUpgradeFiles( $pkg );
		}
	}

	/**
	 * loadUpgradeFiles This will load all files in the dir <pckage>/admin/upgrades/<version>.php with a version greater than the one installed
	 * 
	 * @param array $pPackage 
	 * @access public
	 * @return void
	 */
	function loadUpgradeFiles( $pPackage ) {
		if( !empty( $pPackage )) {
			$dir = constant( strtoupper( $pPackage )."_PKG_PATH" )."admin/upgrades/";
			if( $this->isPackageActive( $pPackage ) && is_dir( $dir ) && $upDir = opendir( $dir )) {
				while( FALSE !== ( $file = readdir( $upDir ))) {
					if( is_file( $dir.$file )) {
						$upVersion = str_replace( ".php", "", $file );
						// we only want to load files of versions that are greater than is installed
						if( $this->validateVersion( $upVersion ) && version_compare( $this->getVersion( $pPackage ), $upVersion, '<' )) {
							include_once( $dir.$file );
						}
					}
				}
			}
		}
	}

	/**
	 * registerPackageUpgrade 
	 * 
	 * @param array $pParams Hash of information about upgrade
	 * @param string $pParams[package] Name of package that is upgrading
	 * @param string $pParams[version] Version of this upgrade
	 * @param string $pParams[description] Description of what the upgrade does
	 * @param string $pParams[post_upgrade] Textual note of stuff that needs to be observed after the upgrade
	 * @param array $pUpgradeHash Hash of update rules. See existing upgrades on how this works.
	 * @access public
	 * @return void
	 */
	function registerPackageUpgrade( $pParams, $pUpgradeHash = array() ) {
		if( $this->verifyPackageUpgrade( $pParams )) {
			$this->registerPackageVersion( $pParams['package'], $pParams['version'] );
			$this->mPackageUpgrades[$pParams['package']][$pParams['version']]            = $pParams;
			$this->mPackageUpgrades[$pParams['package']][$pParams['version']]['upgrade'] = $pUpgradeHash;

			// sort everything for a nice display
			ksort( $this->mPackageUpgrades );
			uksort( $this->mPackageUpgrades[$pParams['package']], 'version_compare' );
		}
	}

	/**
	 * verifyPackageUpgrade 
	 * 
	 * @param array $pParams Hash of information about upgrade
	 * @param string $pParams[package] Name of package that is upgrading
	 * @param string $pParams[version] Version of this upgrade
	 * @param string $pParams[description] Description of what the upgrade does
	 * @param string $pParams[post_upgrade] Textual note of stuff that needs to be observed after the upgrade
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function verifyPackageUpgrade( &$pParams ) {
		if( empty( $pParams['package'] )) {
			$this->mErrors['package'] = "Please provide a valid package name.";
		} else {
			$pParams['package'] = strtolower( $pParams['package'] );
		}

		if( empty( $pParams['version'] ) || !$this->validateVersion( $pParams['version'] )) {
			$this->mErrors['version'] = "Please provide a valid version number.";
		} elseif( empty( $this->mErrors ) && !empty( $this->mPackageUpgrades[$pParams['package']][$pParams['version']] )) {
			$this->mErrors['version'] = "Please make sure you use a unique version number to register your new database changes.";
		}

		if( empty( $pParams['description'] )) {
			$this->mErrors['description'] = "Please add a brief description of what this upgrade is all about.";
		}

		// since this should only show up when devs are working, we'll simply display the output:
		if( !empty( $this->mErrors )) {
			vd( $this->mErrors );
			bt();
		}

		return( count( $this->mErrors ) == 0 );
	}

	/**
	 * registerUpgrade 
	 * 
	 * @param array $pPackage 
	 * @param array $pUpgradeHash 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function registerUpgrade( $pPackage, $pUpgradeHash ) {
		$pPackage = strtolower( $pPackage ); // lower case for uniformity
		if( !empty( $pUpgradeHash ) ) {
			$this->mUpgrades[$pPackage] = $pUpgradeHash;
		}
	}

	/**
	 * display 
	 * 
	 * @param string $pTemplate 
	 * @param string $pBrowserTitle 
	 * @access public
	 * @return void
	 */
	function display( $pTemplate, $pBrowserTitle=NULL ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		if( ini_get( 'safe_mode' ) && ini_get( 'safe_mode_gid' )) {
			umask( 0007 );
		}
		// force the session to close *before* displaying. Why? Note this very important comment from http://us4.php.net/exec
		session_write_close();

		if( !empty( $pBrowserTitle ) ) {
			$this->setBrowserTitle( $pBrowserTitle );
		}
		global $gBitSmarty;
		$gBitSmarty->verifyCompileDir();
		$gBitSmarty->display( $pTemplate );
	}

	/**
	 * isInstalled 
	 * 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function isInstalled( $pPackage = 'kernel' ) {
		return( !empty( $this->mPackages[$pPackage]['installed'] ));
	}

	/**
	 * getWebServerUid set global wwwuser and wwwgroup
	 * 
	 * @access public
	 * @return void
	 */
	function getWebServerUid() {
		global $wwwuser, $wwwgroup;
		$wwwuser = $wwwgroup = '';

		if( is_windows() ) {
			$wwwuser = 'SYSTEM';
			$wwwgroup = 'SYSTEM';
		}

		if( function_exists( 'posix_getuid' )) {
			$user = @posix_getpwuid( @posix_getuid() );
			$group = @posix_getpwuid( @posix_getgid() );
			$wwwuser = $user ? $user['name'] : false;
			$wwwgroup = $group ? $group['name'] : false;
		}

		if( !$wwwuser ) {
			$wwwuser = 'nobody (or the user account the web server is running under)';
		}

		if( !$wwwgroup ) {
			$wwwgroup = 'nobody (or the group account the web server is running under)';
		}
	}

	/**
	 * getTablePrefix 
	 * 
	 * @access public
	 * @return database adjusted table prefix
	 */
	function getTablePrefix() {
		global $gBitDbType;
		$ret = BIT_DB_PREFIX;
		// avoid errors in ADONewConnection() (wrong database driver etc...)
		// strip out some schema stuff
		switch( $gBitDbType ) {
			case "sybase":
				// avoid database change messages
				ini_set('sybct.min_server_severity', '11');
				break;
			case "oci8":
			case "postgres":
				// Do a little prep work for postgres, no break, cause we want default case too
				if( preg_match( '/\./', $ret ) ) {
					// Assume we want to dump in a schema, so set the search path and nuke the prefix here.
					$schema = preg_replace( '/`/', '"', substr( $ret, 0, strpos( $ret, '.' )) );
					$quote = strpos( $schema, '"' );
					if( $quote !== 0 ) {
						$schema = '"'.$schema;
					}
					// set scope to current schema
					$result = $this->mDb->query( "SET search_path TO $schema" );
					// return everything after the prefix
					$ret = substr( BIT_DB_PREFIX, strrpos( BIT_DB_PREFIX, '`' ) + 1 );
				}
				break;
		}
		return $ret;
	}

	/**
	 * upgradePackage 
	 * 
	 * @param array $pPackage 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function upgradePackage( $pPackage ) {
		if( !empty( $pPackage ) && !empty( $this->mUpgrades[$pPackage] )) {
			return( $this->applyUpgrade( $pPackage, $this->mUpgrades[$pPackage] ));
		}
	}

	/**
	 * upgradePackageVersion 
	 * 
	 * @param array $pPackage 
	 * @param array $pVersion 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function upgradePackageVersions( $pPackage ) {
		if( !empty( $pPackage ) && !empty( $this->mPackageUpgrades[$pPackage] )) {
			// make sure everything is in the right order
			uksort( $this->mPackageUpgrades[$pPackage], 'upgrade_version_sort' );

			foreach( array_keys( $this->mPackageUpgrades[$pPackage] ) as $version ) {
				// version we are upgrading from
				$this->mPackageUpgrades[$pPackage][$version]['from_version'] = $this->getVersion( $pPackage );

				// apply upgrade
				$errors[$version] = $this->applyUpgrade( $pPackage, $this->mPackageUpgrades[$pPackage][$version]['upgrade'] );
				if( !empty( $errors[$version] )) {
					return $errors;
				} else {
					// if the upgrade ended without incidence, we store the package version.
					// this way any successfully applied upgrade can only be applied once.
					$this->storeVersion( $pPackage, $version );
				}
			}
		}

		return NULL;
	}

	/**
	 * applyUpgrade 
	 * 
	 * @param array $pPackage 
	 * @param array $pUpgradeHash 
	 * @access public
	 * @return empty array on success, array with errors on failure
	 */
	function applyUpgrade( $pPackage, $pUpgradeHash ) {
		global $gBitDb, $gBitDbType;
		$ret = array();

		if( !empty( $pUpgradeHash ) && is_array( $pUpgradeHash )) {
			// set table prefixes and handle special case of sequence prefixes
			$schemaQuote = strrpos( BIT_DB_PREFIX, '`' );
			$sequencePrefix = ( $schemaQuote ? substr( BIT_DB_PREFIX,  $schemaQuote + 1 ) : BIT_DB_PREFIX );
			$tablePrefix = $this->getTablePrefix();
			$dict = NewDataDictionary( $gBitDb->mDb );
			$failedcommands = array();

			for( $i = 0; $i < count( $pUpgradeHash ); $i++ ) {
				if( !is_array( $pUpgradeHash[$i] ) ) {
					vd( "[$pPackage][$i] is NOT an array" );
					vd( $pUpgradeHash[$i] );
					bt();
					die;
				}

				$type = key( $pUpgradeHash[$i] );
				$step = &$pUpgradeHash[$i][$type];

				switch( $type ) {
					case 'DATADICT':
						for( $j = 0; $j < count( $step ); $j++ ) {
							$dd = &$step[$j];
							switch( key( $dd ) ) {
								case 'CREATE':
									foreach( $dd as $create ) {
										foreach( array_keys( $create ) as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											$sql = $dict->CreateTableSQL( $completeTableName, $create[$tableName], 'REPLACE' );
											if( $sql && ( $dict->ExecuteSQLArray( $sql, FALSE ) > 0 ) ) {
											} else {
												$errors[] = 'Failed to create '.$completeTableName;
												$failedcommands[] = implode( " ", $sql );
											}
										}
									}
									break;
								case 'ALTER':
									foreach( $dd as $alter ) {
										foreach( array_keys( $alter ) as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											$this->mDb->convertQuery( $completeTableName );
											foreach( $alter[$tableName] as $from => $flds ) {
												if( is_string( $flds )) {
													$sql = $dict->ChangeTableSQL( $completeTableName, $flds );
												} else {
													$sql = $dict->ChangeTableSQL( $completeTableName, array( $flds ));
												}

												if( $sql ) {
													for( $sqlIdx = 0; $sqlIdx < count( $sql ); $sqlIdx++ ) {
														$this->mDb->convertQuery( $sqlFoo );
													}
												}

												if( $sql && $dict->ExecuteSQLArray( $sql, FALSE ) > 0 ) {
												} else {
													$errors[] = 'Failed to alter '.$completeTableName.' -> '.$alter[$tableName];
													$failedcommands[] = implode( " ", $sql );
												}
											}
										}
									}
									break;
								case 'RENAMETABLE':
									foreach( $dd as $rename ) {
										foreach( array_keys( $rename ) as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											if( $sql = @$dict->RenameTableSQL( $completeTableName, $tablePrefix.$rename[$tableName] ) ) {
												foreach( $sql AS $query ) {
													$this->mDb->query( $query );
												}
											} else {
												$errors[] = 'Failed to rename table '.$completeTableName.'.'.$rename[$tableName][0].' to '.$rename[$tableName][1];
												$failedcommands[] = implode( " ", $sql );
											}
										}
									}
									break;
								case 'RENAMECOLUMN':
									foreach( $dd as $rename ) {
										foreach( array_keys( $rename ) as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											foreach( $rename[$tableName] as $from => $flds ) {
												// MySQL needs the fields string, others do not.
												// see http://phplens.com/lens/adodb/docs-datadict.htm
												$to = substr( $flds, 0, strpos( $flds, ' ') );
												if( $sql = @$dict->RenameColumnSQL( $completeTableName, $from, $to, $flds ) ) {
													foreach( $sql AS $query ) {
														$this->mDb->query( $query );
													}
												} else {
													$errors[] = 'Failed to rename column '.$completeTableName.'.'.$rename[$tableName][0].' to '.$rename[$tableName][1];
													$failedcommands[] = implode( " ", $sql );
												}
											}
										}
									}
									break;
								case 'CREATESEQUENCE':
									foreach( $dd as $create ) {
										foreach( $create as $sequence ) {
											$this->mDb->CreateSequence( $sequencePrefix.$sequence );
										}
									}
									break;
								case 'RENAMESEQUENCE':
									foreach( $dd as $rename ) {
										foreach( $rename as $from => $to ) {
											if( $gBitDbType != 'mysql' || $this->mDb->tableExists( $tablePrefix.$from ) ) {
												if( $id = $this->mDb->GenID( $from ) ) {
													$this->mDb->DropSequence( $sequencePrefix.$from );
													$this->mDb->CreateSequence( $sequencePrefix.$to, $id );
												} else {
													$errors[] = 'Failed to rename sequence '.$sequencePrefix.$from.' to '.$sequencePrefix.$to;
													$failedcommands[] = implode( " ", $sql );
												}
											} else {
												$this->mDb->CreateSequence( $sequencePrefix.$to, $pUpgradeHash['sequences'][$to]['start'] );
											}
										}
									}
									break;
								case 'DROPSEQUENCE':
									foreach( $dd as $drop ) {
										foreach( $drop as $sequence ) {
											$this->mDb->DropSequence( $sequencePrefix.$sequence );
										}
									}
									break;
								case 'DROPCOLUMN':
									foreach( $dd as $drop ) {
										foreach( array_keys( $drop ) as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											foreach( $drop[$tableName] as $col ) {
												if( $sql = $dict->DropColumnSQL( $completeTableName, $col ) ) {
													foreach( $sql AS $query ) {
														$this->mDb->query( $query );
													}
												} else {
													$errors[] = 'Failed to drop column '.$completeTableName;
													$failedcommands[] = implode( " ", $sql );
												}
											}
										}
									}
									break;
								case 'DROPTABLE':
									foreach( $dd as $drop ) {
										foreach( $drop as $tableName ) {
											$completeTableName = $tablePrefix.$tableName;
											$sql = $dict->DropTableSQL( $completeTableName );
											if( $sql && $dict->ExecuteSQLArray( $sql ) > 0 ) {
											} else {
												$errors[] = 'Failed to drop table '.$completeTableName;
												$failedcommands[] = implode( " ", $sql );
											}
										}
									}
									break;
								case 'CREATEINDEX':
									foreach( $dd as $indices ) {
										foreach( array_keys( $indices ) as $index ) {
											$completeTableName = $tablePrefix.$indices[$index][0];
											if( $sql = $dict->CreateIndexSQL( $index, $completeTableName, $indices[$index][1], $indices[$index][2] ) ) {
												foreach( $sql AS $query ) {
													$this->mDb->query( $query );
												}
											} else {
												$errors[] = 'Failed to create index '.$index;
												$failedcommands[] = implode( " ", $sql );
											}
										}
									}
									break;
							}
						}
						if( !empty( $sql ) ) $sql = null;
						break;
					case 'QUERY':
						uksort( $step, 'upgrade_query_sort' );
						foreach( array_keys( $step ) as $dbType ) {
							if( $dbType == 'MYSQL' && preg_match( '/mysql/', $gBitDbType )) {
								$sql = $step[$dbType];
								unset( $step['SQL92'] );
							} elseif( $dbType == 'PGSQL' && preg_match( '/postgres/', $gBitDbType )) {
								$sql = $step[$dbType];
								unset( $step['SQL92'] );
							} elseif( $dbType == 'SQL92' && !empty( $step['SQL92'] )) {
								$sql = $step[$dbType];
							}

							if( !empty( $sql ) ) {
								foreach( $sql as $query ) {
									if( !$result = $this->mDb->query( $query )) {
										$errors[] = 'Failed to execute SQL query';
										$failedcommands[] = implode( " ", $sql );
									}
								}
								$sql = NULL;
							}
						}
						break;
					case 'PHP':
						eval( $step );
						break;
					case 'POST':
						$postSql[] = $step;
						break;
				}
			}

			// turn on features that are turned on
			// legacy stuff
			if( $this->isFeatureActive( 'feature_'.$pPackage )) {
				$this->storeConfig( 'package_'.$pPackage, 'y', KERNEL_PKG_NAME );
			}

			if( !empty( $failedcommands )) {
				$ret['errors'] = $errors;
				$ret['failedcommands'] = $failedcommands;
			}
		}

		return $ret;
	}

	/**
	 * identifyBlobs 
	 * 
	 * @param array $result 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function identifyBlobs( $result ) {
		$blobs = array();
		//echo "FieldCount: ".$result->FieldCount()."\n";
		for( $i = 0; $i < $result->FieldCount(); $i++ ) {
			$field = $result->FetchField($i);
			//echo $i."-".$field->name."-".$result->MetaType($field->type)."-".$field->max_length."\n";
			// check for blobs
			if(( $result->MetaType( $field->type ) == 'B' ) || ( $result->MetaType( $field->type )=='X' && $field->max_length >= 16777215 ))
				$blobs[] = $field->name;
		}
		return $blobs;
	}

	/**
	 * convertBlobs enumerate blob fields and encoded
	 * 
	 * @param string $gDb 
	 * @param array $res 
	 * @param array $blobs 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function convertBlobs( $gDb, &$res, $blobs ) {
		foreach( $blobs as $blob ) {
			$res[$blob] = $gDb->dbByteEncode( $res[$blob] );
		}
	}

	/**
	 * hasAdminBlock 
	 * 
	 * @access public
	 * @return TRUE on success, FALSE on failure
	 * @deprecated i think this isn't used any more
	 */
	function hasAdminBlock() {
		deprecated( "i think this isn't used anymore." );
		global $gBitUser;
		// Let's find out if we are have admin perm or a root user
		$ret = TRUE;
		if( empty( $gBitUser ) || $gBitUser->isAdmin() ) {
			$ret = FALSE;
		} else {
			// let's try to load up user_id - if successful, we know we have one.
			$rootUser = new BitPermUser( 1 );
			$rootUser->load();
			if( !$rootUser->isValid() ) {
				$ret = FALSE;
			}
		}
		return $ret;
	}


	// {{{============== new methods to replace package scanning and installation ========
	
	/**
	 * loadPackagesSchemas
	 *
	 * scans all packages and loads their schema.yaml file
	 */
	function loadPackagesSchemas(){
		$this->mPackagesSchemas = $this->getPackagesSchemas();

		foreach( $this->mPackagesSchemas as $package=>$pkgHash ){
			if( !empty( $pkgHash['permissions'] ) ){
				foreach( $pkgHash['permissions'] as $perm => &$permHash ){
					$permHash['package'] = $package;
					$permHash['name'] = $perm;
					$this->mPermissionsSchema[$perm] = $permHash;
				}
			}
		}
	}

	/** 
	 * installPackageTable
	 */
	function installPackageTables( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		global $gBitSystem, $gBitKernelDb;
		$package = $pPackageHash['guid'];
		$tablePrefix = $this->getTablePrefix();

		// work out what we're going to do with this package
		if ( $pMethod == 'install' && $_SESSION['first_install'] ) {
			$build = array( 'NEW' );
		} elseif( $pMethod == "install" && !$gBitSystem->isPackageInstalled($package) ) {
			$build = array( 'NEW' );
		} elseif( $pMethod == "reinstall" && $gBitSystem->isPackageInstalled($package) && in_array( 'tables', $removeActions )) {
			// only set $build if we want to reset the tables - this allows us to reset a package to it's starting values without deleting any content
			$build = array( 'REPLACE' );
		} elseif( $pMethod == "uninstall" && $gBitSystem->isPackageInstalled($package) && in_array( 'tables', $removeActions )) {
			$build = array( 'DROP' );
		}
		// If we use MySql and not DROP anything
		// set correct storage engine to use
		if( isset( $_SESSION['use_innodb'] ) && isset( $build ) &&  $build['0'] != 'DROP' ){
			if( $_SESSION['use_innodb'] == TRUE) {
				$build = array_merge($build, array('MYSQL' => 'ENGINE=INNODB'));
			} else {
				$build = array_merge($build, array('MYSQL' => 'ENGINE=MYISAM'));
			}
		}
		// Install tables - $build is empty when we don't pick tables, when un / reinstalling packages
		if( !empty( $pPackageHash['tables'] ) && is_array( $pPackageHash['tables'] ) && !empty( $build )) {
			foreach( $pPackageHash['tables'] as $tableName=>$tableHash ) {
				$completeTableName = $tablePrefix.$tableName;
				// in case prefix has backticks for schema
				$sql = $dict->CreateTableSQL( $completeTableName, $tableHash, $build );
				// Uncomment this line to see the create sql
				for( $sqlIdx = 0; $sqlIdx < count( $sql ); $sqlIdx++ ) {
					$gBitKernelDb->convertQuery( $sql[$sqlIdx] );
				}
				if( $sql && $dict->ExecuteSQLArray( $sql ) <= 1) {
					$errors[] = 'Failed to create table '.$completeTableName;
					$failedcommands[] = implode(" ", $sql);
				}
			}
		}
	}

	function installPackageConstraints( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		global $gBitKernelDb, $gBitInstallDb;
		$tablePrefix = $this->getTablePrefix();

		if( ($pMethod == 'install' || $pMethod == 'reinstall' )
			&& !empty( $pPackageHash['constraints'] ) && is_array( $pPackageHash['constraints'] ) 
		) {
			foreach( $pPackageHash['constraints'] as $tableName=>$tableHash ) {
				$completeTableName = $tablePrefix.$tableName;
				foreach( $tableHash as $constraintName=>$constraint ) {
					$sql = 'ALTER TABLE `'.$completeTableName.'` ADD CONSTRAINT `'.$constraintName.'` '.$constraint;
					$gBitKernelDb->convertQuery($sql);
					$ret = $gBitInstallDb->Execute( $sql );
					if ( $ret === false ) {
						$errors[] = 'Failed to add constraint '.$constraintName.' to table '.$completeTableName;
						$failedcommands[] = $sql;
					}
				}
			}
		}
	}

	function installPackageIndexes( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		global $gBitInstallDb;
		// set prefix
		$schemaQuote = strrpos( BIT_DB_PREFIX, '`' );
		$sequencePrefix = ( $schemaQuote ? substr( BIT_DB_PREFIX,  $schemaQuote + 1 ) : BIT_DB_PREFIX );

		if( $pMethod == 'install' || ( $pMethod == 'reinstall' && in_array( 'tables', $pRemoveActions ))) {
			// Install Indexes
			if( isset( $pPackageHash['indexes'] ) && is_array( $pPackageHash['indexes'] ) ) {
				foreach( array_keys( $pPackageHash['indexes'] ) as $tableIdx ) {
					$completeTableName = $sequencePrefix.$pPackageHash['indexes'][$tableIdx]['table'];

					$sql = $dict->CreateIndexSQL( $tableIdx, $completeTableName, $pPackageHash['indexes'][$tableIdx]['cols'], $pPackageHash['indexes'][$tableIdx]['opts'] );
					if( $sql && $dict->ExecuteSQLArray( $sql ) <= 1) {
						$errors[] = 'Failed to create index '.$tableIdx." on ".$completeTableName;
						$failedcommands[] = implode(" ", $sql);
					}
				}
			}

			if( $pMethod == 'reinstall' && in_array( 'tables', $pRemoveActions )) {
				if( isset( $pPackageHash['sequences'] ) && is_array( $pPackageHash['sequences'] ) ) {
					foreach( array_keys( $pPackageHash['sequences'] ) as $sequenceIdx ) {
						$sql = $gBitInstallDb->DropSequence( $sequencePrefix.$sequenceIdx );
						if (!$sql) {
							$errors[] = 'Failed to drop sequence '.$sequencePrefix.$sequenceIdx;
							$failedcommands[] = "DROP SEQUENCE ".$sequencePrefix.$sequenceIdx;
						}
					}
				}
			}

			if( isset( $pPackageHash['sequences'] ) && is_array( $pPackageHash['sequences'] ) ) {
				// If we use InnoDB for MySql we need this to get sequence tables created correctly.
				if( isset( $_SESSION['use_innodb'] ) ) {
					if( $_SESSION['use_innodb'] == TRUE ) {
						$gBitInstallDb->_genSeqSQL = "create table %s (id int not null) ENGINE=INNODB";
					} else {
						$gBitInstallDb->_genSeqSQL = "create table %s (id int not null) ENGINE=MYISAM";
					}
				}
				foreach( array_keys( $pPackageHash['sequences'] ) as $sequenceIdx ) {
					$sql = $gBitInstallDb->CreateSequence( $sequencePrefix.$sequenceIdx, $pPackageHash['sequences'][$sequenceIdx]['start'] );
					if (!$sql) {
						$errors[] = 'Failed to create sequence '.$sequencePrefix.$sequenceIdx;
						$failedcommands[] = "CREATE SEQUENCE ".$sequencePrefix.$sequenceIdx." START ".$pPackageHash['sequences'][$sequenceIdx]['start'];
					}
				}
			}
		} elseif( $pMethod == 'uninstall' && in_array( 'tables', $pRemoveActions )) {
			if( isset( $pPackageHash['sequences'] ) && is_array( $pPackageHash['sequences'] ) ) {
				foreach( array_keys( $pPackageHash['sequences'] ) as $sequenceIdx ) {
					$sql = $gBitInstallDb->DropSequence( $sequencePrefix.$sequenceIdx );
					if (!$sql) {
						$errors[] = 'Failed to drop sequence '.$sequencePrefix.$sequenceIdx;
						$failedcommands[] = "DROP SEQUENCE ".$sequencePrefix.$sequenceIdx;
					}
				}
			}
		}
	}

	function expungePackageSettings( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		$package = $pPackageHash['guid'];
		$tablePrefix = $this->getTablePrefix();

		// remove all the requested settings - this is a bit tricky and might require some more testing
		// Remove settings if requested
		if( in_array( 'settings', $pRemoveActions ) ) {
			// get a list of permissions used by this package
			$query = "SELECT `perm_name` FROM `".$tablePrefix."users_permissions` WHERE `package`=?";
			$perms = $this->mDb->getCol( $query, array( $package ));
			// we deal with liberty_content_permissions below
			$tables = array( 'users_group_permissions', 'users_permissions' );
			foreach( $tables as $table ) {
				foreach( $perms as $perm ) {
					$delete = "
						DELETE FROM `".$tablePrefix.$table."`
						WHERE `perm_name`=?";
					$ret = $this->mDb->query( $delete, array( $perm ) );
					if (!$ret) {
						$errors[] = "Error deleting permission ". $perm;
						$failedcommands[] = $delete." ".$perm;
					}
				}
			}

			// list of tables where we store package specific settings
			$tables = array( 'kernel_config' );
			foreach( $tables as $table ) {
				$delete = "
					DELETE FROM `".$tablePrefix.$table."`
					WHERE `package`=? OR `config_name` LIKE ?";
				$ret = $this->mDb->query( $delete, array( $package, $package."%" ));
				if (!$ret) {
					$errors[] = "Error deleting confgis for package ". $package;
					$failedcommands[] = $delete." ".$package;
				}
			}

			// delete from the master package table
			$delete2 = "DELETE FROM `".$tablePrefix."packages` WHERE `guid`=?";
			$ret2 = $this->mDb->query( $delete, array( $package, $package."%" ));
			if (!$ret2) {
				$errors[] = "Error deleting registration of package ". $package;
				$failedcommands[] = $delete." ".$package;
			}
		}
	}

	function expungePackageContent( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		$package = $pPackageHash['guid'];
		$tablePrefix = $this->getTablePrefix();

		// now we can start removing content if requested
		// lots of foreach loops in here
		if( in_array( 'content', $pRemoveActions ) ) {
			// first we need to work out the package specific content details
			foreach( $gLibertySystem->mContentTypes as $contentType ) {
				if( $contentType['handler_package'] == $package ) {
					// first we get a list of content_ids which we can use to scan various tables without content_type_guid column for data
					$query = "SELECT `content_id` FROM `".$tablePrefix."liberty_content` WHERE `content_type_guid`=?";
					$rmContentIds = $this->mDb->getCol( $query, array( $contentType['content_type_guid'] ));

					// list of core tables where bitweaver might store relevant data
					// firstly, we delete using the content ids
					// order is important due to the constraints set in the schema
					$tables = array(
						'liberty_aliases'             => 'content_id',
						'liberty_structures'          => 'content_id',
						'liberty_content_hits'        => 'content_id',
						'liberty_content_history'     => 'content_id',
						'liberty_content_prefs'       => 'content_id',
						'liberty_content_links'       => 'to_content_id',
						'liberty_content_links'       => 'from_content_id',
						'liberty_process_queue'       => 'content_id',
						'liberty_content_permissions' => 'content_id',
						'users_favorites_map'         => 'favorite_content_id'
						// This table needs to be fixed to use content_id instead of page_id
						//'liberty_copyrights'          => 'content_id',

						// liberty comments are tricky. should we remove comments linked to the content being deleted?
						// makes sense to me but only if boards are not installed - xing
						//'liberty_comments'            => 'root_id',
					);
					foreach( $rmContentIds as $contentId ) {
						foreach( $tables as $table => $column ) {
							$delete = "
								DELETE FROM `".$tablePrefix.$table."`
								WHERE `$column`=?";
							$ret = $this->mDb->query( $delete, array( $contentId ));
							if (!$ret) {
								$errors[] = "Error deleting from ". $tablePrefxi.$table;
								$failedcommands[] = $delete." ".$contentId;
							}
						}
					}
					// TODO: get a list of tables that have a liberty_content.content_id constraint and delete those entries that we can
					// remove the entries from liberty_content in the next step
					// one such example is stars and stars_history - we need to automagically recognise tables with such constraints.

					// TODO: we need an option to physically remove files from the server when we uninstall stuff like fisheye and treasury
					// i think we'll need to call the appropriate expunge function but i'm too tired to work out how or where to get that info from

					// secondly, we delete using the content type guid
					// order is important due to the constraints set in the schema
					$tables = array(
						'liberty_content',
						'liberty_content_types'
					);
					foreach( $tables as $table ) {
						$delete = "
							DELETE FROM `".$tablePrefix.$table."`
							WHERE `content_type_guid`=?";
						$ret = $this->mDb->query( $delete, array( $contentType['content_type_guid'] ));
						if (!$ret) {
							$errors[] = "Error deleting content type";
							$failedcommands[] = $delete." ".$contentType['content_type_guid'];
						}
					}
				}
			}
		}
	}

	function setPackageActive( $pPackageHash ){
		global $gBitSystem;
		
		$pPackageHash['active'] = 'y';
		$gBitSystem->storePackage( $pPackageHash );

		// @TODO Depreacate this - only accept defaul home from form
		// we'll default wiki to the home page
		if( defined( 'WIKI_PKG_NAME' ) && $pPackageHash['guid'] == WIKI_PKG_NAME && !$gBitSystem->isFeatureActive( 'bit_index' )) {
			$gBitSystem->storeConfig( "bit_index", WIKI_PKG_NAME, WIKI_PKG_NAME );
		}
	}

	function installPackageDefaults( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){ 
		// this list of installed packages is used to show newly installed packages
		if( !empty( $pPackageHash['defaults'] ) ) {
			foreach( $pPackageHash['defaults'] as $def ) {
				if( $this->mDb->mType == 'firebird' ) {
					$def = preg_replace( "/\\\'/", "''", $def );
				}
				$ret = $this->mDb->query( $def );
				if (!$ret) {
					$errors[] = "Error setting defaults";
					$failedcommands[] = $def;
				}
			}
		}
	}

	function installPackagePreferences( $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		global $gBitSystem;
		if( !empty( $pPackageHash['preferences'] ) ) {
			foreach( $pPackageHash['preferences'] as $name=>$value ){
				$gBitSystem->storeConfig( $name, $value, $pPackageHash['guid'] );
			}
		}
	}

	function installPackagePermissions(  $pPackageHash, $pMethod, $pRemoveActions, &$dict, &$errors, &$failedcommands ){
		if( !empty( $pPackageHash['permissions'] ) ){
			// @TODO add validation, take this loop out of here
			$tablePrefix = $this->getTablePrefix();

			foreach( $pPackageHash['permissions'] as $perm => $permHash ){
				$storeHash = array(
					'perm_name' => $perm,
					'perm_desc' => $permHash['description'],
					'perm_level' => $permHash['level'],
					'package' => $pPackageHash['guid'],
				);
				$table = "users_permissions";
				if( (!$return = $this->mDb->associateInsert( $table, $storeHash )) ){
					$errors[] = "Error storing permission: ".$perm;
					$failedcommands[] = 'associateInsert '.$perm;
				}
			}
		}
	}

	function registerContentTypes(){
		foreach( $this->mPackagesSchemas as $package=>$packageHash ) {
			if ( $this->isPackageInstalled( $package ) && !empty( $packageHash['contenttypes'] ) ){
				foreach ( $packageHash['contenttypes'] as $objectClass=>$classFile ){
					require_once( $packageHash['path'].$classFile );
					$tempObject = new $objectClass();
				}
			}
		}
	}

	// }}}


	/**
	 * loadPackagePluginsSchemas
	 */
	function loadPackagePluginSchemas( $pPackageName ){
		if( $paths = LibertySystem::getPackagePluginPaths( $pPackageName ) ){
			foreach( $paths as $path ){
				$this->loadPluginSchemasAtPath( $path );
			}
		}
	}

	/**
	 * loadPluginSchemasAtPath
	 * @see BitInstaller::loadPackagePluginSchemas
	 */
	function loadPluginSchemasAtPath( $pPluginsPath ){
        if( is_dir( $pPluginsPath ) && $plugins = opendir( $pPluginsPath )) {
            while( FALSE !== ( $pluginDirName = readdir( $plugins ) ) ) {
				$pluginDirPath = $pPluginsPath.'/'.$pluginDirName;
				if( is_dir( $pluginDirPath ) && is_file( $pluginDirPath.'/schema_inc.php' ) ) {
                    include_once( $pluginDirPath.'/schema_inc.php' );
                }
            }
        }
	}

	/**
	 * registerServicePreferences
	 * @param pServiceGuid service type guids
	 * @param pContentTypes mixed content type guids
	 */
	function registerServicePreferences( $pPkg, $pServiceGuid, $pContentTypes ){
		if( empty( $this->mServices[$pPkg][$pServiceGuid] ) ){
			$this->mServices[$pPkg][$pServiceGuid] = array();
		}
		$this->mServices[$pPkg][$pServiceGuid] = array_merge( $this->mServices[$pPkg][$pServiceGuid], $pContentTypes );
	}
}

/**
 * check_session_save_path 
 * 
 * @access public
 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
 */
function check_session_save_path() {
	global $errors;
	if( ini_get( 'session.save_handler' ) == 'files' ) {
		$save_path = ini_get( 'session.save_path' );

		if( !is_dir( $save_path )) {
			$errors .= "The directory '$save_path' does not exist or PHP is not allowed to access it (check session.save_path or open_basedir entries in php.ini).\n";
		} elseif( !bw_is_writeable( $save_path )) {
			$errors .= "The directory '$save_path' is not writeable.\n";
		}

		if( $errors ) {
			$save_path = BitSystem::tempdir();

			if (is_dir($save_path) && bw_is_writeable($save_path)) {
				ini_set('session.save_path', $save_path);

				$errors = '';
			}
		}
	}
}

/**
 * makeConnection 
 * 
 * @param string $gBitDbType 
 * @param string $gBitDbHost 
 * @param string $gBitDbUser 
 * @param string $gBitDbPassword 
 * @param string $gBitDbName 
 * @access public
 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
 */
function makeConnection( $gBitDbType, $gBitDbHost, $gBitDbUser, $gBitDbPassword, $gBitDbName ) {
	$gDb = &ADONewConnection( $gBitDbType );
	if( !$gDb->Connect( $gBitDbHost, $gBitDbUser, $gBitDbPassword, $gBitDbName )) {
		echo $gDb->ErrorMsg()."\n";
		die;
	}
	global $gBitDbCaseSensitivity;
	$gDb->mCaseSensitive = $gBitDbCaseSensitivity;
	$gDb->SetFetchMode( ADODB_FETCH_ASSOC );
	return $gDb;
}

/**
 * upgrade_package_sort sort packages before they are upgraded
 * 
 * @param string $a 
 * @param string $b 
 * @access public
 * @return numeric sort direction
 */
function upgrade_package_sort( $a, $b ) {
	global $gBitInstaller;
	$aa = $gBitInstaller->mPackages[$a];
	$bb = $gBitInstaller->mPackages[$b];
	if(( $aa['required'] && $bb['required'] ) || ( !$aa['required'] && !$bb['required'] )) {
		return 0;
	} elseif( $aa['required'] && !$bb['required'] ) {
		return -1;
	} elseif( !$aa['required'] && $bb['required'] ) {
		return 1;
	}
}

/**
 * upgrade_version_sort sort upgrades based on version number
 * 
 * @param string $a 
 * @param string $b 
 * @access public
 * @return numeric sort direction
 */
function upgrade_version_sort( $a, $b ) {
	return version_compare( $a, $b, '>' );
}

/**
 * upgrade_query_sort sort queries that SQL92 queries are called last
 * 
 * @param string $a 
 * @param string $b 
 * @access public
 * @return numeric sort direction
 */
function upgrade_query_sort( $a, $b ) {
	if( $a == 'SQL92' ) {
		return 1;
	} elseif( $b == 'SQL92' ) {
		return -1;
	} else {
		return 0;
	}
}

/* vim: :set fdm=marker : */
