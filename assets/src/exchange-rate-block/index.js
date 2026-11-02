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
	const { type, showDate, showSource } = attributes;

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Settings', 'costa-rica-kit-for-woocommerce' ) }
				>
					<SelectControl
						label={ __( 'Rate', 'costa-rica-kit-for-woocommerce' ) }
						value={ type }
						options={ [
							{
								label: __(
									'Sell and buy',
									'costa-rica-kit-for-woocommerce'
								),
								value: 'both',
							},
							{
								label: __(
									'Sell',
									'costa-rica-kit-for-woocommerce'
								),
								value: 'sell',
							},
							{
								label: __(
									'Buy',
									'costa-rica-kit-for-woocommerce'
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
							'costa-rica-kit-for-woocommerce'
						) }
						checked={ showDate }
						onChange={ ( value ) =>
							setAttributes( { showDate: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Show the source',
							'costa-rica-kit-for-woocommerce'
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
