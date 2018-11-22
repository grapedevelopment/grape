<?php

/** The name of the database */
define('DB_NAME', '');

/** MySQL database username */
define('DB_USER', '');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', '127.0.0.1:3306');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/** SAML **/
/** Path */
// something like /var/........./simplesaml/lib/_autoload.php
define('SAML_PATH', '');

/** The name of the database */
define('AUTH_DB_NAME', '');

/** MySQL database username */
define('AUTH_DB_USER', '');

/** MySQL database password */
define('AUTH_DB_PASSWORD', '');

/** MySQL hostname */
define('AUTH_DB_HOST', '127.0.0.1:3306');

/** Database Charset to use in creating database tables. */
define('AUTH_DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('AUTH_DB_COLLATE', '');

/** END SAML **/

define('URL', "https://".$_SERVER["SERVER_NAME"]."/");

define('SALUTATION', 'Du');

// move these to db
$system_email = "";
date_default_timezone_set("Europe/Berlin");
$cache_lifetime = 360;


$debug = false;

?>