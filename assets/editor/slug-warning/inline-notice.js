/* global pfcptSlugWarning */
import { PluginPostStatusInfo } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { CheckboxControl, Flex, Notice } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const SlugWarning = () => {
	const [ acknowledged, setAcknowledged ] = useState( false );
	const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );

	const { editedSlug, currentSlug } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		return {
			editedSlug: editor.getEditedPostAttribute( 'slug' ),
			currentSlug: editor.getCurrentPostAttribute( 'slug' ),
		};
	}, [] );

	const { postTypeLabel, postTypeName } =
		typeof pfcptSlugWarning !== 'undefined' ? pfcptSlugWarning : {};
	const lockKey = `pfcpt-slug-ack-${ postTypeName }`;

	const slugChanged =
		typeof editedSlug === 'string' &&
		typeof currentSlug === 'string' &&
		currentSlug !== '' &&
		editedSlug !== currentSlug;

	useEffect( () => {
		if ( ! slugChanged || acknowledged ) {
			unlockPostSaving( lockKey );
			return undefined;
		}
		lockPostSaving( lockKey );
		return () => unlockPostSaving( lockKey );
	}, [ slugChanged, acknowledged, lockKey, lockPostSaving, unlockPostSaving ] );

	useEffect( () => {
		if ( ! slugChanged ) {
			setAcknowledged( false );
		}
	}, [ slugChanged ] );

	if ( ! slugChanged ) {
		return null;
	}

	return (
		<PluginPostStatusInfo>
			<Flex direction="column" gap={ 3 } expanded>
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: plural post type name */
						__(
							'Changing this slug will break URLs for all published %s that use this page as their archive base. Old URLs will not auto-redirect.',
							'pfcpt'
						),
						postTypeLabel
					) }
				</Notice>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ sprintf(
						/* translators: %s: plural post type name */
						__(
							'I understand and want to proceed with this slug change for %s.',
							'pfcpt'
						),
						postTypeLabel
					) }
					checked={ acknowledged }
					onChange={ setAcknowledged }
				/>
			</Flex>
		</PluginPostStatusInfo>
	);
};

export default SlugWarning;
