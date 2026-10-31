/**
 * Build configuration: @wordpress/scripts defaults plus the WooCommerce dependency
 * extraction plugin, so @woocommerce/* packages are loaded from WooCommerce instead
 * of being bundled.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		'sinpe-movil-blocks': path.resolve( __dirname, 'assets/src/sinpe-movil-blocks/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin( { outputFormat: 'json' } ),
	],
};
