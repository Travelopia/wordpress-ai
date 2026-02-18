/**
 * Hook for loading and saving settings via the REST API.
 */
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

interface SettingsData {
	alt_text_generation: boolean;
	alt_text_prompt: string;
}

declare global {
	interface Window {
		travelopiaWpAiSettings?: SettingsData;
	}
}

const OPTION_KEY = 'travelopia_wp_ai_settings';

const initialSettings: SettingsData = window.travelopiaWpAiSettings ?? {
	alt_text_generation: false,
	alt_text_prompt: '',
};

export function useSettings() {
	const [ data, setDataState ] = useState< SettingsData >( initialSettings );
	const [ savedData, setSavedData ] =
		useState< SettingsData >( initialSettings );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const setData = useCallback( ( value: Record< string, unknown > ) => {
		setDataState( ( prev ) => ( { ...prev, ...value } ) );
	}, [] );

	const isDirty = JSON.stringify( data ) !== JSON.stringify( savedData );

	const save = useCallback( async () => {
		setIsSaving( true );

		try {
			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: {
					[ OPTION_KEY ]: data,
				},
			} );

			setSavedData( data );

			createSuccessNotice(
				__( 'Settings saved successfully.', 'travelopia-wordpress-ai' ),
				{ type: 'snackbar' }
			);
		} catch {
			createErrorNotice(
				__( 'Failed to save settings.', 'travelopia-wordpress-ai' ),
				{ type: 'snackbar' }
			);
		} finally {
			setIsSaving( false );
		}
	}, [ data, createSuccessNotice, createErrorNotice ] );

	return { data, setData, save, isSaving, isDirty };
}
