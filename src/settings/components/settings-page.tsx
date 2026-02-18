/**
 * Settings page component.
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';

import { useSettings } from '../hooks';
import { Notices } from './notices';

import type { Field, Form } from '@wordpress/dataviews';

interface SettingsData {
	alt_text_generation: boolean;
	alt_text_prompt: string;
}

const fields: Field< SettingsData >[] = [
	{
		id: 'alt_text_generation',
		label: __( 'Enable Alt Text Generation', 'travelopia-wordpress-ai' ),
		type: 'text',
		Edit: ( { data, onChange } ) => (
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Automatically generate alt text for images using AI',
					'travelopia-wordpress-ai'
				) }
				checked={ !! data.alt_text_generation }
				onChange={ ( value ) =>
					onChange( { alt_text_generation: value } )
				}
			/>
		),
	},
	{
		id: 'alt_text_prompt',
		label: __( 'Custom Prompt', 'travelopia-wordpress-ai' ),
		type: 'text',
		Edit: ( { data, onChange } ) => (
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Custom Prompt', 'travelopia-wordpress-ai' ) }
				value={ data.alt_text_prompt }
				onChange={ ( value ) => onChange( { alt_text_prompt: value } ) }
				rows={ 4 }
				help={ __(
					'Customize how AI describes your images. Leave blank to use the default prompt.',
					'travelopia-wordpress-ai'
				) }
			/>
		),
	},
];

const form: Form = {
	type: 'regular',
	fields: [ 'alt_text_generation', 'alt_text_prompt' ],
};

export function SettingsPage() {
	const { data, setData, save, isSaving, isDirty } = useSettings();

	return (
		<>
			<Notices />
			<Card>
				<CardHeader>
					<h2>
						{ __(
							'Alt Text Generation',
							'travelopia-wordpress-ai'
						) }
					</h2>
				</CardHeader>
				<CardBody>
					<DataForm< SettingsData >
						data={ data }
						fields={ fields }
						form={ form }
						onChange={ setData }
					/>
				</CardBody>
			</Card>

			<div style={ { marginTop: '16px' } }>
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ isSaving }
					disabled={ isSaving || ! isDirty }
				>
					{ isSaving
						? __( 'Saving…', 'travelopia-wordpress-ai' )
						: __( 'Save Settings', 'travelopia-wordpress-ai' ) }
				</Button>
			</div>
		</>
	);
}
