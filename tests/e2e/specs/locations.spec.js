/**
 * Locations module: dependent selects in the classic checkout, one district field per province in the
 * block checkout, and My Account → Addresses. Needs `npm run seed` (Locations module on).
 */
const { test, expect } = require( '@playwright/test' );
const u = require( '../utils' );

test( 'classic checkout: province → canton → district fills city and postcode', async ( {
	page,
} ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout-clasico/' );
	await page.waitForSelector( 'form.checkout' );

	await expect( page.locator( '#billing_city_field' ) ).toBeHidden();
	await expect( page.locator( '#billing_postcode_field' ) ).toBeHidden();

	await page.locator( '#billing_state' ).selectOption( 'CR-H' );
	await expect(
		page.locator( '#billing_crkit_canton option[value="402"]' )
	).toHaveText( 'Barva' );
	await expect(
		page.locator( '#billing_crkit_canton option[value="101"]' )
	).toHaveCount( 0 );

	await page.locator( '#billing_crkit_canton' ).selectOption( '402' );
	await expect(
		page.locator( '#billing_crkit_district option[value="40206"]' )
	).toHaveText( 'San José de la Montaña' );

	await page.locator( '#billing_crkit_district' ).selectOption( '40206' );
	await expect( page.locator( '#billing_city' ) ).toHaveValue( 'Barva' );
	await expect( page.locator( '#billing_postcode' ) ).toHaveValue( '40206' );

	// Changing the province clears the district.
	await page.locator( '#billing_state' ).selectOption( 'CR-L' );
	await expect( page.locator( '#billing_crkit_district' ) ).toHaveValue( '' );
	await expect( page.locator( '#billing_postcode' ) ).toHaveValue( '' );
} );

test( 'block checkout: the district field follows the province', async ( {
	page,
} ) => {
	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );

	await expect( page.locator( '#shipping-city' ) ).toBeHidden();
	await expect( page.locator( '#shipping-postcode' ) ).toBeHidden();

	await page.locator( '#shipping-state' ).selectOption( 'CR-SJ' );
	await expect( page.locator( '#shipping-crkit-district-sj' ) ).toBeVisible();

	await page.locator( '#shipping-state' ).selectOption( 'CR-L' );
	await expect( page.locator( '#shipping-crkit-district-l' ) ).toBeVisible();
	await expect( page.locator( '#shipping-crkit-district-sj' ) ).toHaveCount(
		0
	);
	await expect(
		page.locator( '#shipping-crkit-district-l option[value="70102"]' )
	).toHaveText( 'Valle La Estrella (Limón)' );
} );

test( 'My Account: the saved district shows in the address and prefills the block checkout', async ( {
	page,
} ) => {
	await u.login( page, u.CUSTOMER );

	const setDistrict = async ( state, canton, district ) => {
		await page.goto( '/my-account/edit-address/billing/' );
		await page.locator( '#billing_state' ).selectOption( state );
		await page.locator( '#billing_crkit_canton' ).selectOption( canton );
		await page
			.locator( '#billing_crkit_district' )
			.selectOption( district );
		await Promise.all( [
			page.waitForURL( /my-account\/edit-address\/?$/ ),
			page.click( 'button[name="save_address"]' ),
		] );
	};

	await setDistrict( 'CR-H', '402', '40206' );
	await expect( page.locator( '.woocommerce-Addresses' ) ).toContainText(
		'San José de la Montaña, Barva, Heredia'
	);

	await u.addToCart( page, 'CRK-COFFEE' );
	await page.goto( '/checkout/' );
	await page.waitForSelector( '.wc-block-checkout__form' );
	// The saved address shows as a summary; open the billing form if it is collapsed.
	const edit = page.locator(
		'.wc-block-checkout__billing-fields .wc-block-components-address-card__edit'
	);
	if ( await edit.isVisible() ) {
		await edit.click();
	}
	await expect( page.locator( '#billing-crkit-district-h' ) ).toHaveValue(
		'40206'
	);

	// Restore the seeded address (Escazú) for the other specs.
	await setDistrict( 'CR-SJ', '102', '10201' );
	await expect( page.locator( '.woocommerce-Addresses' ) ).toContainText(
		'Escazú, Escazú, San José'
	);
} );
