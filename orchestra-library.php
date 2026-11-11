<?php

/**
 * Plugin Name:      Orchestra Library
 * Plugin URI:       https://github.com/TheRacoonOfDeath/orchestra-library
 * Description:      Pieces, parts and sheet music for the Orchestra plugin suite.
 * Version:          0.10.0
 * Author:           Julian Nickerl (contact@julian-nickerl.de)
 * Author URI:       mailto:contact@julian-nickerl.de
 * License:          GPL-2.0-or-later
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      orchestra-library
 * Domain Path:      /languages
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP:     8.3
 * Requires Plugins: orchestra-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

Orchestra\Library\Plugin::boot(__FILE__);
