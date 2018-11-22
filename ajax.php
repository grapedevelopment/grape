<?php
header('Cache-Control: no-cache, no-store');
define('GRAPE', TRUE);
define('OUTPUT', "AJAX");
/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}
if ( file_exists( ABSPATH . 'grape-config.php') ) {
	/** The config file resides in ABSPATH */
	require_once( ABSPATH . 'grape-config.php' );
	require_once( ABSPATH . 'grape/kernel.php' );
} else {
  echo "no config file";
  exit;
}
?>
