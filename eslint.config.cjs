/**
 * ESLint: the @wordpress/scripts defaults, plus the packages that WordPress and
 * WooCommerce provide at runtime (they are externals, not npm dependencies).
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

const runtimePackages = [
	'@woocommerce/blocks-registry',
	'@woocommerce/settings',
	'@wordpress/html-entities',
	'@wordpress/i18n',
];

module.exports = [
	...defaultConfig,
	{
		settings: {
			'import/core-modules': runtimePackages,
		},
	},
];
