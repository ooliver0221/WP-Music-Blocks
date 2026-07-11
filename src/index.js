/**
 * WP-Music-Blocks - Block Registration
 */
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from '../block.json';
import './style.scss';
import './editor.scss';

registerBlockType(metadata, {
    edit: Edit,
    save: () => null, // Server-side rendering
});
