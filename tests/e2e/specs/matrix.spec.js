/**
 * SPEC §8 matrix, one scenario per run (currency, order storage and PHP are set by bin/e2e-matrix.sh):
 * classic and block checkout × guest and logged-in customer. Each test places an order with SINPE Móvil,
 * checks the frozen amount in colones, uploads the receipt and confirms the payment as the store.
 */
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

const { currency } = u.scenario();
const texts = u.texts();

for ( const checkout of [ 'classic', 'blocks' ] ) {
	for ( const user of [ 'guest', 'customer' ] ) {
		test( `${ checkout } checkout, ${ user }: pay, upload receipt, confirm`, async ( {
			browser,
		} ) => {
			const shopper = await browser.newContext();
			const page = await shopper.newPage();
			const errors = [];
			page.on( 'pageerror', ( error ) => errors.push( error.message ) );

			if ( 'customer' === user ) {
				await u.login( page, u.CUSTOMER );
			}

			await u.addToCart( page, 'CRK-COFFEE' );

			if ( 'classic' === checkout ) {
				await u.openClassicCheckout( page, 'guest' === user );
				await u.placeClassicOrder( page );
			} else {
				await u.openBlockCheckout( page, 'guest' === user );
				if ( 'usd' === currency ) {
					await u.selectBlockSinpe( page );
					const estimate = page.locator(
						'.crkit-sinpe-movil-blocks__estimate'
					);
					await expect( estimate ).toContainText( '₡' );
					if ( texts ) {
						// Comes from the JSON translation of the block script.
						await expect( estimate ).toContainText(
							texts.estimate
						);
					}
				}
				await u.placeBlockOrder( page );
			}

			// Order received: payment steps with the amount in colones and the receipt form.
			const order = u.orderFromUrl( page );
			const instructions = page.locator( '.crkit-sinpe-instructions' );
			await expect( instructions ).toBeVisible();
			await expect( instructions ).toContainText( '8888-7777' );
			await expect( instructions ).toContainText( '₡' );
			await expect(
				page.locator( '.crkit-sinpe-instructions__rate' )
			).toHaveCount( 'usd' === currency ? 1 : 0 );
			if ( texts ) {
				await expect(
					page.locator( '.crkit-sinpe-instructions__title' )
				).toHaveText( texts.instructionsTitle );
			}

			if ( '0' !== process.env.CRKIT_E2E_LOCATIONS ) {
				// Locations module: the order address shows "District, Canton, Province".
				await expect( page.locator( 'body' ) ).toContainText(
					u.DISTRICT_LINE[ user ]
				);
			}

			await u.uploadReceipt( page );
			const thanks = page.locator(
				'#crkit-sinpe-receipt .woocommerce-message'
			);
			await expect( thanks ).toBeVisible();
			if ( texts ) {
				await expect( thanks ).toContainText( texts.receiptThanks );
			}

			// Store: the meta box shows the receipt; confirming moves the order to processing.
			const store = await browser.newContext();
			const admin = await store.newPage();
			await u.login( admin, u.ADMIN );
			await admin.goto( u.orderEditUrl( order.id ) );
			const box = admin.locator( '#crkit-sinpe-movil' );
			await expect( box ).toBeVisible();

			const view = await admin.request.get(
				await box
					.locator( '.crkit-sinpe-receipt a' )
					.first()
					.getAttribute( 'href' )
			);
			expect( view.status() ).toBe( 200 );
			expect( view.headers()[ 'content-type' ] ).toContain( 'image/png' );

			await Promise.all( [
				admin.waitForURL( /crkit_sinpe=confirmed/ ),
				box.locator( '.crkit-sinpe-actions .button-primary' ).click(),
			] );
			await expect( admin.locator( '#order_status' ) ).toHaveValue(
				'wc-processing'
			);

			// The receipt URL (with the store manager's nonce) does not work for the shopper's session:
			// guests have no handler at all (400) and customers fail the nonce check (403).
			const receiptUrl = await box
				.locator( '.crkit-sinpe-receipt a' )
				.first()
				.getAttribute( 'href' );
			const denied = await shopper.request.get( receiptUrl, {
				maxRedirects: 0,
			} );
			expect( denied.status() ).not.toBe( 200 );
			expect( denied.headers()[ 'content-type' ] || '' ).not.toContain(
				'image/'
			);

			expect( errors ).toEqual( [] );
			await shopper.close();
			await store.close();
		} );
	}
}

test( 'SINPE Móvil is not offered above the maximum amount', async ( {
	page,
} ) => {
	await u.addToCart( page, 'CRK-SURF' );

	await u.openBlockCheckout( page, true );
	await expect( u.blockSinpeRadio( page ) ).toHaveCount( 0 );

	await u.openClassicCheckout( page, true );
	await expect(
		page.locator( '#payment_method_crkit_sinpe_movil' )
	).toHaveCount( 0 );
} );
