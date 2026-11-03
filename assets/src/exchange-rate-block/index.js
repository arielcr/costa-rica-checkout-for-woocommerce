/**
 * Editor side of the "Exchange rate (Costa Rica)" block. The block is rendered on the server
 * (same output as the [crkit_exchange_rate] shortcode), so the editor shows a live preview.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

const Edit = ( { attributes, setAttributes } ) => {
	const { type, showDate, showSource, hideOutdated } = attributes;

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Settings',
						'costa-rica-checkout-for-woocommerce'
					) }
				>
					<SelectControl
						label={ __(
							'Rate',
							'costa-rica-checkout-for-woocommerce'
						) }
						value={ type }
						options={ [
							{
								label: __(
									'Sell and buy',
									'costa-rica-checkout-for-woocommerce'
								),
								value: 'both',
							},
							{
								label: __(
									'Sell',
									'costa-rica-checkout-for-woocommerce'
								),
								value: 'sell',
							},
							{
								label: __(
									'Buy',
									'costa-rica-checkout-for-woocommerce'
								),
								value: 'buy',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { type: value } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ __(
							'Show the date',
							'costa-rica-checkout-for-woocommerce'
						) }
						checked={ showDate }
						onChange={ ( value ) =>
							setAttributes( { showDate: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Hide when outdated',
							'costa-rica-checkout-for-woocommerce'
						) }
						help={ __(
							'When off, an outdated rate is shown with its date.',
							'costa-rica-checkout-for-woocommerce'
						) }
						checked={ hideOutdated }
						onChange={ ( value ) =>
							setAttributes( { hideOutdated: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Show the source',
							'costa-rica-checkout-for-woocommerce'
						) }
						checked={ showSource }
						onChange={ ( value ) =>
							setAttributes( { showSource: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="crkit/exchange-rate"
				attributes={ attributes }
			/>
		</div>
	);
};

registerBlockType( 'crkit/exchange-rate', {
	edit: Edit,
	save: () => null,
} );
