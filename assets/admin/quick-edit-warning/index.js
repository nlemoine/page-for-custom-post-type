/* global pfcptQuickEdit */
import { __, sprintf } from '@wordpress/i18n';

( function () {
	if (
		typeof pfcptQuickEdit === 'undefined' ||
		! pfcptQuickEdit.protectedPages ||
		typeof window.inlineEditPost === 'undefined'
	) {
		return;
	}

	const originalSave = window.inlineEditPost.save;

	window.inlineEditPost.save = function ( id ) {
		// WP passes either a numeric post ID or a DOM element (the Update
		// button). Mirror inlineEditPost.save's own normalization.
		let resolvedId = id;
		if (
			typeof resolvedId === 'object' &&
			resolvedId !== null &&
			typeof window.inlineEditPost.getId === 'function'
		) {
			resolvedId = window.inlineEditPost.getId( resolvedId );
		}
		const postId = String( resolvedId );
		const label = pfcptQuickEdit.protectedPages[ postId ];

		if ( label ) {
			const editor = document.getElementById( 'edit-' + postId );
			const inlineData = document.getElementById( 'inline_' + postId );
			const slugInput =
				editor && editor.querySelector( 'input[name="post_name"]' );
			const originalSlugEl =
				inlineData && inlineData.querySelector( '.post_name' );
			const originalSlug = originalSlugEl
				? originalSlugEl.textContent.trim()
				: null;

			if (
				slugInput &&
				originalSlug !== null &&
				slugInput.value !== originalSlug
			) {
				const message = sprintf(
					/* translators: %s: plural post type name */
					__(
						'Changing this slug will break URLs for all published %s that use this page as their archive base. Old URLs will not auto-redirect.\n\nContinue?',
						'pfcpt'
					),
					label
				);

				if ( ! window.confirm( message ) ) {
					return false;
				}
			}
		}

		return originalSave.apply( this, arguments );
	};
} )();
