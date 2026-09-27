<?php
/**
 * Loads theme includes. PHP 7.3 compatible only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/class-nav-walker.php';
require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/qualification-helpers.php';
require_once __DIR__ . '/contact-helpers.php';
require_once __DIR__ . '/presentation-helpers.php';
require_once __DIR__ . '/menu-fallback.php';
require_once __DIR__ . '/page-layouts.php';
require_once __DIR__ . '/form-shells.php';
require_once __DIR__ . '/catalog-helpers.php';
require_once __DIR__ . '/content-helpers.php';
