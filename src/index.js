/**
 * External dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';

/**
 * Setup constants for the block variation.
 */
const PARENT_BLOCK = 'core/query';
const MY_VARIATION_NAME = 'tax-context-query-loop/query-loop';

/**
 * Register the block variation.
 */
registerBlockVariation( PARENT_BLOCK, {
	name: MY_VARIATION_NAME,
	title: 'Query Loop with Contextual Taxonomy Filter',
	description: 'Displays posts based on the currently viewed taxonomy terms.',
	attributes: {
		namespace: MY_VARIATION_NAME,
		query: {
			perPage: 6,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[ 
				[ 
					'core/post-title', 
					{
						isLink: true,
					},
				], 
				[ 'core/post-excerpt' ] 
			],
		],
	],
	scope: [ 'inserter' ],
	allowedControls: [ 'order', 'search' ],
	isActive: ( { namespace, query } ) => {
		return namespace === MY_VARIATION_NAME && query.postType === 'post';
	},
} );
