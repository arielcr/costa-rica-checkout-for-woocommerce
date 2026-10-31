/**
 * Playwright: end-to-end checks of the SPEC §8 matrix and screenshots for the phase reports.
 * Needs the wp-env site running and seeded (npm run seed).
 */
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	outputDir: './tests/e2e/.results/artifacts',
	timeout: 120000,
	expect: { timeout: 20000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	use: {
		...devices[ 'Desktop Chrome' ],
		baseURL: process.env.CRKIT_E2E_BASE_URL || 'http://localhost:8888',
		viewport: { width: 1280, height: 900 },
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
} );
