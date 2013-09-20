<?php

/**
 * facileManager Upgrader
 *
 * @package facileManager
 * @subpackage Administration
 *
 */

/** Define ABSPATH as this files directory */
define('ABSPATH', dirname(__FILE__) . '/');

/** Set installation variable */
define('UPGRADE', true);

/** Enforce authentication */
require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');

require_once('fm-init.php');

if (!$fm_login->isLoggedIn() || !$super_admin) header('Location: ' . dirname($_SERVER['PHP_SELF']));

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-includes/init.php');
require_once(ABSPATH . 'fm-includes/version.php');
$app_compat = checkAppVersions(false);

if ($app_compat) {
	bailOut($app_compat);
}

$step = isset($_GET['step']) ? $_GET['step'] : 0;

printHeader('Upgrade', 'install');

switch ($step) {
	case 0:
	case 1:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
		}
		echo <<<HTML
	<center>
	<p>I have detected you recently upgraded $fm_name, but have not upgraded the database.<br />Click 'Upgrade' to start the upgrade process.</p>
	<p class="step"><a href="?step=2" class="button">Upgrade</a></p>
	</center>

HTML;
		break;
	case 2:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: /fm-install.php');
		}
		require_once(ABSPATH . 'fm-modules/facileManager/upgrade.php');

		include(ABSPATH . 'config.inc.php');
		include_once(ABSPATH . 'fm-includes/fm-db.php');

		fmUpgrade($__FM_CONFIG['db']['name']);
		break;
}

printFooter();


/**
 * Processes installation.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function processSetup() {
	extract($_POST);
	
	$link = @mysql_connect($dbhost, $dbuser, $dbpass);
	if (!$link) {
		displaySetup('Could not connect to MySQL.  Please check your credentials.');
		exit;
	} else {
		$db_selected = @mysql_select_db($dbname, $link);
		if ($db_selected) {
			@mysql_close($link);
			displaySetup('Database already exists.  Please choose a different name.');
			exit;
		}
	}
	
	require_once(ABSPATH . 'fm-modules/facileManager/install.php');
	createConfig();
}

?>