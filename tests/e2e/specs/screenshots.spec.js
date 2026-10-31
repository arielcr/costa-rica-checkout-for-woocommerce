/**
 * Screenshots of the key screens for the phase report (docs/screenshots/fase-1/).
 * Only runs when CRKIT_E2E_SCREENSHOTS=1:
 *
 *   npm run seed -- usd && CRKIT_E2E_SCREENSHOTS=1 CRKIT_E2E_CURRENCY=usd npx playwright test screenshots
 *   npm run seed -- crc && CRKIT_E2E_SCREENSHOTS=1 CRKIT_E2E_CURRENCY=crc npx playwright test screenshots
 */
const path = require( 'path' );
const { execSync } = require( 'child_process' );
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const DIR = path.join( __dirname, '../../../docs/screenshots/fase-1' );
const { currency } = u.scenario();
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
	! process.env.CRKIT_E2E_SCREENSHOTS,
	'Set CRKIT_E2E_SCREENSHOTS=1 to take screenshots.'
);

test.describe.configure( { mode: 'serial' } );

test( `screenshots (${ currency })`, async ( { browser } ) => {
	const store = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const admin = await store.newPage();
	await u.login( admin, u.ADMIN );

	if ( 'usd' === currency ) {
		await admin.goto( '/wp-admin/admin.php?page=wc-settings&tab=crkit' );
		await shot( admin, '01-ajustes-modulos' );
		await admin.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=crkit&section=exchange_rate'
		);
		await shot( admin, '02-ajustes-tipo-de-cambio' );
		await admin.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=crkit_sinpe_movil'
		);
		await expect(
			admin.locator( '#woocommerce_crkit_sinpe_movil_phone' )
		).toBeVisible();
		await shot( admin, '03-ajustes-sinpe-movil' );
	}

	// Customer: checkouts.
	const shopper = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const page = await shopper.newPage();
	await u.login( page, u.CUSTOMER );
	await u.addToCart( page, 'CRK-COFFEE' );
	await u.addToCart( page, 'CRK-SHIRT-M' );

	if ( 'usd' === currency ) {
		await u.openClassicCheckout( page, false );
		await u.selectClassicSinpe( page );
		await shot( page, '05-checkout-clasico-usd' );
	}

	await u.openBlockCheckout( page, false );
	await u.selectBlockSinpe( page );
	await shot( page, `04-checkout-bloques-${ currency }` );

	await u.placeBlockOrder( page );
	await expect( page.locator( '.crkit-sinpe-instructions' ) ).toBeVisible();
	await shot( page, `06-pedido-recibido-${ currency }` );

	if ( 'crc' === currency ) {
		await store.close();
		await shopper.close();
		return;
	}

	const order = u.orderFromUrl( page );
	await u.uploadReceipt( page );
	await page.locator( '#crkit-sinpe-receipt' ).scrollIntoViewIfNeeded();
	await shot( page, '07-comprobante-recibido' );

	await page.goto( `/my-account/view-order/${ order.id }/` );
	await shot( page, '08-mi-cuenta-ver-pedido' );

	// Store: order screen, confirm.
	await admin.goto( u.orderEditUrl( order.id ) );
	await expect( admin.locator( '#crkit-sinpe-movil' ) ).toBeVisible();
	await shot( admin, '09-admin-pedido-con-comprobante' );
	await admin
		.locator( '#crkit-sinpe-movil' )
		.screenshot( { path: path.join( DIR, '09b-admin-metabox.png' ) } );

	const receipt = await admin.context().newPage();
	await receipt.goto(
		await admin
			.locator( '#crkit-sinpe-movil .crkit-sinpe-receipt a' )
			.first()
			.getAttribute( 'href' )
	);
	await shot( receipt, '10-admin-ver-comprobante', { fullPage: false } );
	await receipt.close();

	await Promise.all( [
		admin.waitForURL( /crkit_sinpe=confirmed/ ),
		admin.locator( '#crkit-sinpe-movil .button-primary' ).click(),
	] );
	await shot( admin, '11-admin-pago-confirmado' );

	// Emails saved by the development mail catcher.
	const mails = wp(
		'ls -1t wp-content/uploads/crkit-dev-mail/ | head -20'
	).split( '\n' );
	for ( const [ needle, name ] of [
		[ 'recibido', '12-correo-cliente-pedido-en-espera' ],
		[ 'comprobante-de-sinpe', '13-correo-tienda-comprobante' ],
	] ) {
		const file = mails.find( ( mail ) => mail.includes( needle ) );
		if ( file ) {
			await admin.goto( `/wp-content/uploads/crkit-dev-mail/${ file }` );
			await shot( admin, name );
		}
	}

	// SINPE Móvil hidden above the maximum amount.
	const surf = await browser.newContext( {
		viewport: { width: 1280, height: 900 },
	} );
	const surfPage = await surf.newPage();
	await u.addToCart( surfPage, 'CRK-SURF' );
	await u.openBlockCheckout( surfPage, true );
	await expect( u.blockSinpeRadio( surfPage ) ).toHaveCount( 0 );
	await shot( surfPage, '14-checkout-sin-sinpe-sobre-el-tope' );
	await surf.close();

	// Outdated exchange rate: notice in the admin.
	wp(
		`wp eval '$r = get_option( "crkit_exchange_rate" ); $r["fetched_at"] = time() - 72 * HOUR_IN_SECONDS; update_option( "crkit_exchange_rate", $r, false );'`
	);
	await admin.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=crkit&section=exchange_rate'
	);
	await shot( admin, '15-aviso-tipo-de-cambio-vencido' );
	wp( `wp eval 'crkit_exchange_rate_service()->refresh();'` );

	await store.close();
	await shopper.close();
} );
