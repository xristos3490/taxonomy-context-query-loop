<?php
/**
 * Plugin Name:       Query Loop with Contextual Taxonomy Filter
 * Description:       A variation of the Query block with a contextual taxonomy filter.
 * Requires at least: 6.4
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            Chris Lilitsas
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tax-context-query-loop
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// I know...it is a bit of a hack.
const CURRENT_TERM_ID = 999999999999999;

/*---------------------------------------------------*/
/* Post Template Block Enchancements.                */
/*---------------------------------------------------*/

/**
 * Enchance the core/post-template block context.
 * 
 * @param array $metadata The block metadata.
 * 
 * @return array
 */
function tql_enchance_core_post_template_context( $metadata ) {
	if ( 'core/post-template' === $metadata['name'] ) {

		$context = $metadata['usesContext'] ?? [];
		if ( ! in_array( 'postId', $context ) ) {
			$metadata['usesContext'][] = 'postId';
		}

		if ( ! in_array( 'postType', $context ) ) {
			$metadata['usesContext'][] = 'postType';
		}
	}

    return $metadata;
}
add_filter( 'block_type_metadata', 'tql_enchance_core_post_template_context' );

/*---------------------------------------------------*/
/* Handle Frontend Query.                            */
/*---------------------------------------------------*/

/**
 * Handle the query args.
 *
 * @param array $query The query args.
 * @param WP_Block $block The block instance.
 * @return array
 */
function tql_handle_query_args( $query, $block ) {

	$tax_query = $query['tax_query'] ?? [];
	if ( empty( $tax_query ) ) {
		return $query;
	}

	// Get context.
	$post_id = $block->context['postId'] ?? null;
	if ( empty( $post_id ) ) {
		return $query;
	}

	// Available taxonomies.
	$taxonomies = get_object_taxonomies( 'post' );

	// Exclude currently viewed post.
	$query['post__not_in'] = empty( $query['post__not_in'] ) ? [ $post_id ] : array_merge( $query['post__not_in'], [ $post_id ] );


	// Modify the tax query.
	//
	// The goal is to enchance query to show related posts based on the current post's terms.
	// Current terms will be intersected with existing terms in the query.
	// If the intersection is empty, the taxonomy will be removed from the query.
	foreach ( $query['tax_query'] as $key => $tax ) {
		if ( ! isset( $tax['taxonomy'] ) ) {
			continue;
		}

		$taxonomy = $tax['taxonomy'];
		if ( ! in_array( $taxonomy, $taxonomies ) ) {
			continue;
		}

		// Has contextual features? Is our flag included in the terms?
		if ( ! in_array( CURRENT_TERM_ID, $tax['terms'], true ) ) {
			continue;
		}

		// Find the current terms, if any.
		$currentTerms = wp_get_post_terms( $post_id, $taxonomy );
		$currentTerms = $currentTerms ?? [];
		$currentTerms = $currentTerms 
			? array_map( function( $term ) {
				return $term->term_id;
			}, $currentTerms ) 
			: [];

		// Remove the current flag and intersect with the current terms.
		$tax['terms']                      = array_diff( $tax['terms'], [CURRENT_TERM_ID] );
		$query['tax_query'][$key]['terms'] = ! empty( $tax['terms'] ) ? array_intersect( $currentTerms, $tax['terms'] ) : $currentTerms;

		// Clean up.
		if ( empty( $query['tax_query'][$key]['terms'] ) ) {
			unset( $query['tax_query'][$key] );
		}
	}

	return $query;
}

/**
 * Filter query args.
 * 
 * @param string $block_content The block content.
 * @param array $parsed_block The parsed block.
 * @param WP_Block $block The block instance.
 * 
 * @return string|null
 */
add_filter( 'pre_render_block', function($pre, $parsed_block ) {

	// Sanity checks.
	if ( $parsed_block['blockName'] !== 'core/query' ) {
		return $pre;
	}
	
	if ( ! isset( $parsed_block['attrs']['namespace'] ) || $parsed_block['attrs']['namespace'] !== 'tax-context-query-loop/query-loop' ) {
		return $pre;
	}

	// Filter the queries.
	add_filter(
		'query_loop_block_query_vars',
		'tql_handle_query_args',
		10,
		2
	);

	return $pre;

}, 10, 2 );

/**
 * Remove the filter after the block is rendered.
 * 
 * @param string $block_content The block content.
 * @param array $parsed_block The parsed block.
 * @param WP_Block $block The block instance.
 * 
 * @return string
 */
add_filter( 'render_block_core/query', function($block_content, $parsed_block, $block) {

	// Sanity namespace check.
	if ( ! isset( $parsed_block['attrs']['namespace'] ) || 'tax-context-query-loop/query-loop' !== $parsed_block['attrs']['namespace'] ) {
		return $block_content;
	}

	// Clean up.
	remove_filter(
		'query_loop_block_query_vars',
		'tql_handle_query_args',
		10,
		2
	);

	return $block_content;

}, 100, 3 );

/*---------------------------------------------------*/
/* Editor's Preview Query Handling.                  */
/*---------------------------------------------------*/

/**
 * Handle the preview mode of the block.
 * 
 * The goal is to remove the "current" flag from the tax query, so the block can be previewed.
 * 
 * @param array $query The query args.
 * @param WP_REST_Request $request The request object.
 * 
 * @return array
 */
function tax_context_query_loop_handle_query_args_for_editor( $query, $request ) {

	// Always remove the "current" value from the terms.
	if ( empty( $query['tax_query'] ) ) {
		return $query;
	}

	foreach ( $query['tax_query'] as $key => $taxonomy_data ) {

		// Remove flag.
		$query['tax_query'][$key]['terms'] = array_diff( $taxonomy_data['terms'], [CURRENT_TERM_ID] );

		// Remove if empty.
		if ( empty( $query['tax_query'][$key]['terms'] ) ) {
			unset( $query['tax_query'][$key] );
		}
	}

	// Remove if empty.
	if ( empty( $query['tax_query'] ) ) {
		unset( $query['tax_query'] );
	}

	return $query;
}
add_filter( 'rest_post_query' , 'tax_context_query_loop_handle_query_args_for_editor', 100, 2 );

/*---------------------------------------------------*/
/* Enqueue the block's assets                        */
/*---------------------------------------------------*/

/**
 * Enqueue the block's assets for the block editor.
 */
function tax_context_query_loop_block_editor_assets() {

	$script_path       = plugin_dir_path( __FILE__ ) . 'build/edit.js';
	$script_asset_path = plugin_dir_path( __FILE__ ) . 'build/edit.asset.php';
	$script_asset      = file_exists( $script_asset_path )
		? require( $script_asset_path )
		: array(
			'dependencies' => array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor'),
			'version'      => filemtime($script_path ),
		);

	wp_enqueue_script(
		'tax-context-query-loop-block-editor',
		plugins_url( 'build/edit.js', __FILE__ ),
		$script_asset[ 'dependencies' ],
		$script_asset[ 'version' ],
	);
}
add_action( 'enqueue_block_editor_assets', 'tax_context_query_loop_block_editor_assets' );

/**
 * Enqueue the block's assets.
 */
function tax_context_query_loop_block_assets() {

	$script_path       = plugin_dir_path( __FILE__ ) . 'build/index.js';
	$script_asset_path = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';
	$script_asset      = file_exists( $script_asset_path )
		? require( $script_asset_path )
		: array(
			'dependencies' => array('wp-blocks', 'wp-i18n', 'wp-element'),
			'version'      => filemtime($script_path),
		);

	wp_enqueue_script(
		'tax-context-query-loop-block',
		plugins_url( 'build/index.js', __FILE__ ),
		$script_asset[ 'dependencies' ],
		$script_asset[ 'version' ],
	);
}
add_action( 'enqueue_block_assets', 'tax_context_query_loop_block_assets' );
