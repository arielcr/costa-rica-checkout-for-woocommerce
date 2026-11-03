/**
 * WordPress.org screenshots (.wordpress-org/screenshot-N.png, in the order of the readme captions),
 * with a copy in docs/screenshots/fase-4/. Only runs when CRKIT_E2E_SCREENSHOTS=wporg:
 *
 *   npm run seed && CRKIT_E2E_SCREENSHOTS=wporg npx playwright test screenshots-wporg
 *
 * The seed leaves the site in es_CR, selling in US dollars, with every module on.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const ROOT = path.join( __dirname, '../../..' );
const ASSETS = path.join( ROOT, '.wordpress-org' );
const DOCS = path.join( ROOT, 'docs/screenshots/fase-4' );

const NAMES = {
	1: 'ajustes-modulos',
	2: 'checkout-bloques-sinpe-distrito',
	3: 'pedido-recibido-instrucciones',
	4: 'admin-pedido-sinpe',
	5: 'ajustes-tipo-de-cambio',
	6: 'checkout-bloques-cedula',
	7: 'checkout-clasico-canton-distrito',
	8: 'pagina-tipo-de-cambio',
};

/**
 * Saves screenshot N for WordPress.org and its copy for the phase docs.
 *
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} target  Page or element.
 * @param {number}                                                             n       Screenshot number.
 * @param {Object}                                                             options Screenshot options.
 */
async function capture( target, n, options = {} ) {
	const file = path.join( ASSETS, `screenshot-${ n }.png` );
	await target.screenshot( { path: file, ...options } );
	fs.mkdirSync( DOCS, { recursive: true } );
	fs.copyFileSync(
		file,
		path.join(
			DOCS,
			`${ String( n ).padStart( 2, '0' ) }-${ NAMES[ n ] }.png`
		)
	);
}

/**
 * Shows the queued "added to your cart" notices on the cart page, so they do not appear in the
 * checkout screenshots.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function consumeNotices( page ) {
	await page.goto( '/cart/' );
	await page.waitForLoadState( 'networkidle' );
}

const noSkeletons = ( page ) =>
	expect(
		page.locator( '.wc-block-checkout__sidebar [class*="skeleton"]' )
	).toHaveCount( 0 );

test.skip(
	'wporg' !== process.env.CRKIT_E2E_SCREENSHOTS,
	'Set CRKIT_E2E_SCREENSHOTS=wporg to take the WordPress.org screenshots.'
);

test( 'WordPress.org screenshots', async ( { browser } ) => {
	test.setTimeout( 240000 );
	const viewport = { width: 1280, height: 900 };
	const store = await browser.newContext( { viewport } );
	const admin = await store.newPage();
	await u.login( admin, u.ADMIN );

	// 1. Modules.
	await admin.goto( '/wp-admin/admin.php?page=wc-settings&tab=crkit' );
	await capture( admin, 1 );

	// 5. Exchange rate settings.
	await admin.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=crkit&section=exchange_rate'
	);
	await capture( admin, 5 );

	// 2. Block checkout: SINPE Móvil with the amount in colones and the district.
	const shopper = await browser.newContext( { viewport } );
	const page = await shopper.newPage();
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.addToCart( page, 'CRK-SHIRT-M' );
	await consumeNotices( page );
	await u.openBlockCheckout( page, true );
	await u.selectBlockSinpe( page );
	await noSkeletons( page );
	await page
		.locator( '.wc-block-components-checkout-step' )
		.first()
		.evaluate( ( el ) =>
			el.scrollIntoView( { block: 'start', behavior: 'instant' } )
		);
	await capture( page, 2, { fullPage: true } );

	// 3. Order received: payment steps and receipt upload.
	await u.placeBlockOrder( page );
	await expect( page.locator( '.crkit-sinpe-instructions' ) ).toBeVisible();
	const order = u.orderFromUrl( page );
	await capture( page, 3, { fullPage: true } );

	// 4. Order screen with the receipt and the confirm and reject buttons.
	await u.uploadReceipt( page );
	await admin.goto( u.orderEditUrl( order.id ) );
	const box = admin.locator( '#crkit-sinpe-movil' );
	await expect(
		box.locator( '.crkit-sinpe-receipt a' ).first()
	).toBeVisible();
	await box.evaluate( ( el ) =>
		el.scrollIntoView( { block: 'center', behavior: 'instant' } )
	);
	await capture( admin, 4 );

	// 6. ID number in the block checkout.
	// The checkout remembers the guest's address from the previous order (shown as a card).
	await u.addToCart( page, 'CRK-COFFEE' );
	await consumeNotices( page );
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );
	await page.locator( '#contact-crkit-electronic-invoice' ).check();
	await page.locator( '#contact-crkit-id-type' ).selectOption( '01' );
	await page.fill( '#contact-crkit-id-number', '1-1234-0567' );
	await page.waitForLoadState( 'networkidle' );
	await noSkeletons( page );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await capture( page, 6 );

	// 7. Classic checkout: province → canton → district.
	await consumeNotices( page );
	await page.goto( '/checkout-clasico/' );
	await page.waitForSelector( 'form.checkout' );
	await page.locator( '#billing_state' ).selectOption( 'CR-H' );
	await page.locator( '#billing_crkit_canton' ).selectOption( '402' );
	await page
		.locator( '#billing_crkit_district_field .select2-selection' )
		.click();
	await page
		.locator( '#billing_crkit_district_field' )
		.evaluate( ( el ) =>
			el.scrollIntoView( { block: 'center', behavior: 'instant' } )
		);
	await capture( page, 7 );
	await page.keyboard.press( 'Escape' );

	// 8. Exchange rate shortcode and block on a page.
	await page.goto( '/tipo-de-cambio/' );
	await expect( page.locator( '.crkit-exchange-rate' ) ).toHaveCount( 2 );
	await capture( page, 8, { fullPage: true } );

	await store.close();
	await shopper.close();
} );
