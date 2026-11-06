/**
 * The exchange rate module is opt-in (ADR-0053): no external request until the merchant enables it.
 * Needs `npm run seed`, which is restored at the end (USD store, every module on).
 */
const { execSync } = require( 'child_process' );
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const SLUG = 'costa-rica-checkout-for-woocommerce';
const wp = ( command ) =>
	execSync( `npx wp-env run cli bash -c ${ JSON.stringify( command ) }`, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} )
		.split( '\n' )
		.filter( ( line ) => line && ! /^(ℹ|✔)/.test( line ) )
		.join( '\n' )
		.trim();
const scheduled = () =>
	wp(
		`wp eval "echo (int) as_has_scheduled_action('crkit_refresh_exchange_rate');"`
	);

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	wp(
		`wp option delete crkit_modules; wp plugin deactivate ${ SLUG } --quiet; wp plugin activate ${ SLUG } --quiet`
	);
} );

test.afterAll( () => {
	wp(
		`wp option update crkit_modules '{"exchange_rate":"yes","sinpe_movil":"yes","locations":"yes","identification":"yes"}' --format=json --quiet`
	);
} );

test( 'a fresh install schedules nothing and a dollar store does not offer SINPE Móvil', async ( {
	page,
} ) => {
	expect( scheduled() ).toBe( '0' );

	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );
	await expect(
		page
			.locator( 'input[name="radio-control-wc-payment-method-options"]' )
			.first()
	).toBeAttached();
	await expect( u.blockSinpeRadio( page ) ).toHaveCount( 0 );
} );

test( 'the merchant is told why, and enabling the module schedules the first request', async ( {
	page,
} ) => {
	await u.login( page, u.ADMIN );
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=crkit_sinpe_movil'
	);
	await expect( page.locator( '.notice-warning.inline' ) ).toContainText(
		/(Exchange rate module is disabled|módulo Tipo de cambio está desactivado)/
	);

	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=crkit' );
	const toggle = page.locator( '[name="crkit_modules[exchange_rate]"]' );
	await expect( toggle ).not.toBeChecked();
	await expect( page.locator( '.forminp', { has: toggle } ) ).toContainText(
		/(every 12 hours|cada 12 horas)/
	);

	await toggle.check();
	await page.click( '.woocommerce-save-button' );
	await expect( toggle ).toBeChecked();
	expect( scheduled() ).toBe( '1' );
} );
