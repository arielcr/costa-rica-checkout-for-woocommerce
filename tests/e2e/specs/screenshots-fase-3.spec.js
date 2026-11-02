/**
 * Screenshots of phase 3 (docs/screenshots/fase-3/). Only runs when CRKIT_E2E_SCREENSHOTS=3:
 *
 *   npm run seed && CRKIT_E2E_SCREENSHOTS=3 npx playwright test screenshots-fase-3
 */
const path = require( 'path' );
const { execSync } = require( 'child_process' );
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const DIR = path.join( __dirname, '../../../docs/screenshots/fase-3' );
const shot = ( page, name, options = {} ) =>
	page.screenshot( {
		path: path.join( DIR, `${ name }.png` ),
		fullPage: true,
		...options,
	} );
const wp = ( command ) =>
	execSync( `npx wp-env run cli bash -c ${ JSON.stringify( command ) }`, {
		encoding: 'utf8',
	} )
		.split( '\n' )
		.filter( ( line ) => line && ! /^(ℹ|✔)/.test( line ) )
		.join( '\n' );

test.skip(
	'3' !== process.env.CRKIT_E2E_SCREENSHOTS,
	'Set CRKIT_E2E_SCREENSHOTS=3 to take the phase 3 screenshots.'
);

test( 'phase 3 screenshots', async ( { browser } ) => {
	const shopper = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const page = await shopper.newPage();
	await u.addToCart( page, 'CRK-COFFEE' );

	// Classic checkout: the invoice checkbox makes the ID required.
	await u.openClassicCheckout( page, true );
	await u.selectClassicSinpe( page );
	await page.locator( '#crkit_electronic_invoice' ).check();
	await page.locator( '#crkit_id_type' ).selectOption( '01' );
	await page.fill( '#crkit_id_number', '0-1234-0567' );
	await page.click( '#place_order' );
	const classicError = page
		.locator( '.woocommerce-NoticeGroup-checkout' )
		.filter( { hasText: /9 (digits|dígitos)/ } );
	await expect( classicError ).toBeVisible();
	// WooCommerce scrolls to the notices with a jQuery animation: let it finish first.
	await page.waitForFunction( () => ! window.jQuery( ':animated' ).length );
	await classicError.evaluate( ( element ) =>
		element.scrollIntoView( { block: 'start', behavior: 'instant' } )
	);
	await shot( page, '01-checkout-clasico-cedula-invalida', {
		fullPage: false,
	} );
	await page.locator( '#crkit_id_type_field' ).scrollIntoViewIfNeeded();
	await page.fill( '#crkit_id_number', '1-1234-0567' );
	await page.locator( '.woocommerce-billing-fields' ).screenshot( {
		path: path.join( DIR, '02-checkout-clasico-factura.png' ),
	} );
	await Promise.all( [
		page.waitForURL( /order-received/, { timeout: 60000 } ),
		page.click( '#place_order' ),
	] );
	const classicOrder = u.orderFromUrl( page );
	await shot( page, '03-pedido-recibido-cedula' );

	// Block checkout: contact fields, server-side format check.
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.openBlockCheckout( page, true );
	await page.locator( '#contact-crkit-electronic-invoice' ).check();
	await page.locator( '#contact-crkit-id-type' ).selectOption( '03' );
	await page.fill( '#contact-crkit-id-number', '1234567890' );
	await u.selectBlockSinpe( page );
	await page
		.locator( '.wc-block-components-checkout-place-order-button' )
		.click();
	const blockError = page
		.locator( '.wc-block-components-notice-banner.is-error' )
		.filter( { hasText: /11 (or|o) 12/ } )
		.first();
	await expect( blockError ).toBeVisible();
	await expect(
		page.locator( '.wc-block-checkout__sidebar [class*="skeleton"]' )
	).toHaveCount( 0 );
	await blockError.evaluate( ( element ) =>
		element.scrollIntoView( { block: 'start', behavior: 'instant' } )
	);
	await shot( page, '04-checkout-bloques-dimex-invalido', {
		fullPage: false,
	} );
	await page.fill( '#contact-crkit-id-number', '155812345678' );
	await page.waitForLoadState( 'networkidle' );
	await page.locator( '.wc-block-checkout__contact-fields' ).screenshot( {
		path: path.join( DIR, '05-checkout-bloques-cedula.png' ),
	} );

	// Store: order screen, edit form, settings and the new order email.
	const store = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const admin = await store.newPage();
	await u.login( admin, u.ADMIN );
	await admin.goto( u.orderEditUrl( classicOrder.id ) );
	await expect( admin.locator( '#order_data' ) ).toContainText(
		'Cédula física: 112340567'
	);
	await admin
		.locator( '#order_data' )
		.screenshot( { path: path.join( DIR, '06-admin-pedido-cedula.png' ) } );
	await admin.locator( '#order_data .edit_address' ).first().click();
	await admin
		.locator( '#order_data' )
		.screenshot( { path: path.join( DIR, '07-admin-editar-cedula.png' ) } );

	await admin.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=crkit&section=identification'
	);
	await shot( admin, '08-ajustes-identificacion', { fullPage: false } );

	const mail = wp(
		`cd wp-content/uploads/crkit-dev-mail && ls -1t *-${ classicOrder.id }.html | head -1`
	).trim();
	if ( mail ) {
		await admin.goto( `/wp-content/uploads/crkit-dev-mail/${ mail }` );
		await shot( admin, '09-correo-tienda-cedula' );
	}

	// Customer: My Account → Account details.
	const customer = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const account = await customer.newPage();
	await u.login( account, u.CUSTOMER );
	await account.goto( '/my-account/edit-account/' );
	await shot( account, '10-mi-cuenta-detalles-cedula' );

	// Block checkout on a phone.
	const phone = await browser.newContext( {
		viewport: { width: 390, height: 844 },
		deviceScaleFactor: 2,
		isMobile: true,
		hasTouch: true,
	} );
	const mobile = await phone.newPage();
	await u.addToCart( mobile, 'CRK-COFFEE' );
	await mobile.goto( '/checkout/' );
	await mobile.waitForSelector( '.wc-block-checkout__form' );
	await mobile.locator( '#contact-crkit-electronic-invoice' ).check();
	await mobile.locator( '#contact-crkit-id-type' ).selectOption( '01' );
	await mobile.waitForLoadState( 'networkidle' );
	await mobile.locator( '.wc-block-checkout__contact-fields' ).screenshot( {
		path: path.join( DIR, '11-movil-bloques-cedula.png' ),
	} );

	await shopper.close();
	await store.close();
	await customer.close();
	await phone.close();
} );
