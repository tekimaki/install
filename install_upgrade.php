<?php
/**
 * @version $Header$
 * @package install
 * @subpackage functions
 */

$gBitSmarty->assign( 'next_step', $step );

$errors = $success = $plugin_errors = $plugin_success =  array();

// load up all available package upgrades that we have
$gBitInstaller->loadAllUpgradeFiles();

if( !empty( $_REQUEST['upgrade_packages'] )) {
	if( !empty( $gDebug ) || !empty( $_REQUEST['debug'] ) ) {
		$gBitInstaller->debug();
		$gBitInstallDb->debug = 99;
	}

	if( !empty( $_REQUEST['packages'] ) && is_array( $_REQUEST['packages'] )) {
		// ensure all packages are in the right order before we start applying upgrades
		uasort( $_REQUEST['packages'], 'upgrade_package_sort' );

		foreach( $_REQUEST['packages'] as $package ) {
			if( $error = $gBitInstaller->upgradePackageVersions( $package )) {
				$errors[$package] = $error;
			} elseif( !empty( $gBitInstaller->mPackageUpgrades[$package] )) {
				// copy the upgrade hash to success. next round this isn't available anymore from mPackageUpgrades since the package is up to date and the upgrade files aren't loaded anymore.
				$success[$package] = $gBitInstaller->mPackageUpgrades[$package];
			}
		}
	}
	
	if( !empty( $_REQUEST['plugins'] ) && is_array( $_REQUEST['plugins'] )) {
		// ensure all plugins are in the right order before we start applying upgrades
		//@TODO: Not sure if sorting plugins is necessary
		//uasort( $_REQUEST['plugins'], 'upgrade_plugin_sort' );

		foreach( $_REQUEST['plugins'] as $plugin ) {
			$plugin_errors[$plugin] = 'foo';
			/*
			if( $plugin_error = $gBitInstaller->upgradePackageVersions( $plugin )) {
				$plugin_errors[$plugin] = $plugin_error;
			} elseif( !empty( $gBitInstaller->mPackageUpgrades[$plugin] )) {
				// copy the upgrade hash to success. next round this isn't available anymore from mPackageUpgrades since the plugin is up to date and the upgrade files aren't loaded anymore.
				$plugin_success[$plugin] = $gBitInstaller->mPackageUpgrades[$plugin];
			}*/
		}
	}

	if( empty( $errors ) && empty( $plugin_errors )) {
		// display success page when done
		$app = '_done';
		$gBitSmarty->assign( 'next_step', $step + 1 );
	}
}

$gBitSmarty->assign( 'requirements', $gBitInstaller->calculateRequirements() );
$gBitSmarty->assign( 'requirementsMap', $gBitInstaller->drawRequirementsGraph( FALSE, 'cmapx', ( !empty( $_REQUEST['command'] ) ? $_REQUEST['command'] : 'dot' )));
$gBitSmarty->assign( 'packageUpgrades', $gBitInstaller->mPackageUpgrades );
$gBitSmarty->assign( 'pluginUpgrades', $gBitInstaller->mPluginUpgrades );
$gBitSmarty->assign( 'schema', $gBitInstaller->mPackagesSchemas );
$gBitSmarty->assign( 'success', $success );
$gBitSmarty->assign( 'errors', $errors );
$gBitSmarty->assign( 'plugin_success', $plugin_success );
$gBitSmarty->assign( 'plugin_errors', $plugin_errors );
?>
