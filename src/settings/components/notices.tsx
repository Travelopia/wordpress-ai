/**
 * Notices component.
 */
import { NoticeList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

export function Notices() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( notice ) => notice.type === 'snackbar' ),
		[]
	);

	const { removeNotice } = useDispatch( noticesStore );

	if ( ! notices.length ) {
		return null;
	}

	return (
		<NoticeList
			notices={
				notices as React.ComponentProps<
					typeof NoticeList
				>[ 'notices' ]
			}
			onRemove={ removeNotice }
			className="components-editor-notices__snackbar"
		/>
	);
}
