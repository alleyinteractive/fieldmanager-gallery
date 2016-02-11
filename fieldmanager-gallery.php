<?php

/*
	Plugin Name: Fieldmanager Gallery
	Plugin URI: https://github.com/alleyinteractive/fieldmanager-gallery
	Description: A temporary Fieldmanager Field extension for image galleries. This will eventually be merged into Fm Core. Forked from https://github.com/fusioneng/fieldmanager-gallery.
	Version: 0.1
	Author: Fusion
	Author URI: http://fusion.net/section/tech-product/
*/

/*  This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Version number.
 *
 * @var string
 */
define( 'FM_GALLERY_VERSION', '0.0.1' );

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function() {
	if ( defined( 'FM_VERSION' ) ) {
		require_once( __DIR__ . '/php/class-fieldmanager-gallery.php' );
	}
} );
