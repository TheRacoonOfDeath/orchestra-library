<?php

/**
 * Plugin Name:      Orchestra Library
 * Plugin URI:       https://github.com/TheRacoonOfDeath/orchestra-library
 * Description:      Pieces, parts and sheet music for the Orchestra plugin suite.
 * Version:          0.4.4
 * Author:           Julian Nickerl
 * License:          MIT
 * Text Domain:      orchestra-library
 * Domain Path:      /languages
 * Requires at least: 6.5
 * Requires PHP:     8.3
 * Requires Plugins: orchestra-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

Orchestra\Library\Plugin::boot(__FILE__);
