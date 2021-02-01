/* eslint-disable react/react-in-jsx-scope */
/**
 * External dependencies
 */
import { h } from 'preact';
import { useEffect } from 'preact/hooks';

/**
 * Internal dependencies
 */
import style from './style.scss';

import {callOnEscapeKey} from '../../utils';

const Overlay = props => {
	const { children, closeOverlay, colorTheme, isVisible, opacity } = props;
	const closeWithEscape = callOnEscapeKey(closeOverlay);
	useEffect( () => {
		window.addEventListener( 'keydown', closeWithEscape );
		return () => {
			// Cleanup after event
			window.removeEventListener('keydown', closeWithEscape );
		};
	}, [] );

	return (
		<div
			aria-labelledby="search-dev-tools__overlay-title"
			className={[
				'search-dev-tools__overlay',
				`search-dev-tools__overlay--${ colorTheme }`,
				isVisible ? '' : 'is-hidden',
			].join( ' ' )}
			role="dialog"
			style={{ opacity: isVisible ? opacity / 100 : 0 }}
		>
			{ children }
		</div>
	);
};

export default Overlay;