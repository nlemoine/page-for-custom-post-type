import { registerPlugin } from '@wordpress/plugins';
import SlugWarning from './inline-notice';

registerPlugin( 'pfcpt-slug-warning', { render: SlugWarning } );
