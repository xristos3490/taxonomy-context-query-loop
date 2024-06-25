const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		// Different entry to only load in the backend.
		edit: './src/edit.js',
	},
};
