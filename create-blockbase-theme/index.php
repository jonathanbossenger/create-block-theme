<?php

/**
 * Plugin Name: Create Blockbase theme.
 * Plugin URI: https://github.com/Automattic/create-blockbase-theme
 * Description: Generates a Blockbase child theme
 * Version: 0.0.1
 * Author: Automattic
 * Author URI: https://automattic.com/
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: create-blockbase-theme
 */

/**
 * REST endpoint for exporting the contents of the Edit Site Page editor.
 *
 * @package gutenberg
 */

function gutenberg_edit_site_get_theme_json_for_export() {
	$child_theme_json                = json_decode( file_get_contents( get_stylesheet_directory() . '/theme.json' ), true );
	$child_theme_json_class_instance = new WP_Theme_JSON_Gutenberg( $child_theme_json );
	$user_theme_json                 = WP_Theme_JSON_Resolver_Gutenberg::get_user_data();
	// Merge the user theme.json into the child theme.json.
	$child_theme_json_class_instance->merge( $user_theme_json );

	// I feel like there should be a function to do this in Gutenberg but I couldn't find it
	function remove_theme_key( $data ) {
		if ( is_array( $data ) ) {
			if ( array_key_exists( 'theme', $data ) ) {
				if ( array_key_exists( 'user', $data ) ) {
					return $data['user'];
				}

				return $data['theme'];
			}
			foreach ( $data as $node_name => $node_value ) {
				$data[ $node_name ] = remove_theme_key( $node_value );
			}
		}

		return $data;
	}

	return remove_theme_key( $child_theme_json_class_instance->get_raw_data() );
}

function blockbase_get_style_css( $theme ) {
	$slug        = $theme['slug'];
	$name        = $theme['name'];
	$description = $theme['description'];
	$uri         = $theme['uri'];
	$author      = $theme['author'];
	$author_uri  = $theme['author_uri'];

	return "/*
Theme Name: {$name}
Theme URI: {$uri}
Author: {$author}
Author URI: {$author_uri}
Description: {$description}
Requires at least: 5.8
Tested up to: 5.8
Requires PHP: 5.7
Version: 0.0.1
License: GNU General Public License v2 or later
License URI: https://raw.githubusercontent.com/Automattic/themes/trunk/LICENSE
Template: blockbase
Text Domain: {$slug}
Tags: one-column, custom-colors, custom-menu, custom-logo, editor-style, featured-images, full-site-editing, rtl-language-support, theme-options, threaded-comments, translation-ready, wide-blocks
*/";
}

function blockbase_get_functions_php( $theme ) {
	$slug = $theme['slug'];
	return "<?php
/**
 * Add Editor Styles
 */
function {$slug}_editor_styles() {
	// Enqueue editor styles.
	add_editor_style(
		array(
			'/assets/theme.css',
		)
	);
}
add_action( 'after_setup_theme', '{$slug}_editor_styles' );

/**
 *
 * Enqueue scripts and styles.
 */
function {$slug}_scripts() {
	wp_enqueue_style( '{$slug}-styles', get_stylesheet_directory_uri() . '/assets/theme.css', array( 'blockbase-ponyfill' ), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', '{$slug}_scripts' );";
}

function blockbase_get_theme_css( $theme ) {
	if ( file_exists( get_stylesheet_directory() . '/assets/theme.css' ) ) {
		return file_get_contents( get_stylesheet_directory() . '/assets/theme.css' );
	}
}

function blockbase_get_readme_txt( $theme ) {
	$slug        = $theme['slug'];
	$name        = $theme['name'];
	$description = $theme['description'];
	$uri         = $theme['uri'];
	$author      = $theme['author'];
	$author_uri  = $theme['author_uri'];

	return "=== {$name} ===
Contributors: {$author}
Requires at least: 5.8
Tested up to: 5.8
Requires PHP: 5.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

{$description}

== Changelog ==

= 1.0.0 =
* Initial release

== Copyright ==

{$name} WordPress Theme, (C) 2021 {$author}
{$name} is distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
";
}

/**
 * Creates an export of the current templates and
 * template parts from the site editor at the
 * specified path in a ZIP file.
 *
 * @param string $filename path of the ZIP file.
 */
function gutenberg_edit_site_export_theme_create_zip( $filename, $theme ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'Zip Export not supported.' );
	}

	$zip = new ZipArchive();
	$zip->open( $filename, ZipArchive::OVERWRITE );
	$zip->addEmptyDir( $theme['slug'] );
	$zip->addEmptyDir( $theme['slug'] . '/block-templates' );
	$zip->addEmptyDir( $theme['slug'] . '/block-template-parts' );

	// Load templates into the zip file.
	$templates = gutenberg_get_block_templates();
	foreach ( $templates as $template ) {
		$template->content = _remove_theme_attribute_from_content( $template->content );

		$zip->addFromString(
			$theme['slug'] . '/block-templates/' . $template->slug . '.html',
			$template->content
		);
	}

	// Load template parts into the zip file.
	$template_parts = gutenberg_get_block_templates( array(), 'wp_template_part' );
	foreach ( $template_parts as $template_part ) {
		$zip->addFromString(
			$theme['slug'] . '/block-template-parts/' . $template_part->slug . '.html',
			$template_part->content
		);
	}

	// Add theme.json.

	// TODO only get child theme settings not the parent.
	$zip->addFromString(
		$theme['slug'] . '/theme.json',
		wp_json_encode( gutenberg_edit_site_get_theme_json_for_export(), JSON_PRETTY_PRINT )
	);

	// Add style.css.
	$zip->addFromString(
		$theme['slug'] . '/style.css',
		blockbase_get_style_css( $theme )
	);

	// Add theme.css.
	// TODO get any CSS that the theme is already using
	$zip->addFromString(
		$theme['slug'] . '/theme.css',
		blockbase_get_theme_css( $theme )
	);

	// Add functions.php.
	$zip->addFromString(
		$theme['slug'] . '/functions.php',
		blockbase_get_functions_php( $theme )
	);

	// Add functions.php.
	$zip->addFromString(
		$theme['slug'] . '/readme.txt',
		blockbase_get_readme_txt( $theme )
	);

	// Save changes to the zip file.
	$zip->close();
}

/**
 * Output a ZIP file with an export of the current templates
 * and template parts from the site editor, and close the connection.
 */
function gutenberg_edit_site_export_theme( $theme ) {
	$theme['slug'] = str_replace( '-', '_', sanitize_title( $theme['name'] ) ); // Slugs can't contain -.
	// Create ZIP file in the temporary directory.
	$filename = tempnam( get_temp_dir(), $theme['slug'] );
	gutenberg_edit_site_export_theme_create_zip( $filename, $theme );

	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename=' . $theme['slug'] . '.zip' );
	header( 'Content-Length: ' . filesize( $filename ) );
	flush();
	echo readfile( $filename );
	die();
}

// In Gutenberg a simialr route is called from the frontend to export template parts
// I've left this in although we aren't using it at the moment, as I think eventually this will become part of Gutenberg.
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'__experimental/edit-site/v1',
			'/create-theme',
			array(
				'methods'             => 'GET',
				'callback'            => 'gutenberg_edit_site_export_theme',
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' );
				},
			)
		);
	}
);

function create_blockbase_theme_page() {
	?>
		<div class="wrap">
			<h2><?php _e( 'Create Blockbase Theme', 'create-blockbase-theme' ); ?></h2>
			<p><?php _e( 'Save your current block templates and theme.json settings as a new theme.', 'create-blockbase-theme' ); ?></p>
			<form method="get" action="/wp-admin/themes.php">
				<label><?php _e( 'Theme name', 'create-blockbase-theme' ); ?><br /><input placeholder="<?php _e( 'Blockbase', 'create-blockbase-theme' ); ?>" type="text" name="theme[name]" /></label><br /><br />
				<label><?php _e( 'Theme description', 'create-blockbase-theme' ); ?><br /><textarea placeholder="<?php _e( 'Blockbase is a simple theme that supports full-site editing. Use it to build something beautiful.', 'create-blockbase-theme' ); ?>" rows="4" cols="50" name="theme[description]"></textarea></label><br /><br />
				<label><?php _e( 'Theme URI', 'create-blockbase-theme' ); ?><br /><input placeholder="https://github.com/automattic/themes/tree/trunk/blockbase" type="text" name="theme[uri]"/></label><br /><br />
				<label><?php _e( 'Author', 'create-blockbase-theme' ); ?><br /><input placeholder="<?php _e( 'Automattic', 'create-blockbase-theme' ); ?>" type="text" name="theme[author]"/></label><br /><br />
				<label><?php _e( 'Author URI', 'create-blockbase-theme' ); ?><br /><input placeholder="<?php _e( 'https://automattic.com/', 'create-blockbase-theme' ); ?>" type="text" name="theme[author_uri]"/></label><br /><br />
				<input type="hidden" name="page" value="create-blockbase-theme" />
				<input type="submit" value="<?php _e( 'Create Blockbase theme', 'create-blockbase-theme' ); ?>" />
			</form>
		</div>
	<?php
}
function blockbase_create_theme_menu() {
	$page_title = __( 'Create Blockbase Theme', 'create-blockbase-theme' );
	$menu_title = __( 'Create Blockbase Theme', 'create-blockbase-theme' );
	add_theme_page( $page_title, $menu_title, 'edit_theme_options', 'create-blockbase-theme', 'create_blockbase_theme_page' );
}

add_action( 'admin_menu', 'blockbase_create_theme_menu' );

function blockbase_save_theme() {
	// I can't work out how to call the API but this works for now.
	if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'create-blockbase-theme' && ! empty( $_GET['theme'] ) ) {
		gutenberg_edit_site_export_theme( $_GET['theme'] );
	}
}
add_action( 'admin_init', 'blockbase_save_theme' );