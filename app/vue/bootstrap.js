/**
 * Vue Parking Slots bootstrap - mounts when #parking-slots-view appears.
 * Keeps original Vanilla JS files (SlotsPage, Router, etc.) unchanged.
 */

import { mountParkingSlotsView } from './ParkingSlotsView';

function tryMount() {
	const container = document.getElementById('parking-slots-view');
	if (container && !container.__vueMounted) {
		container.__vueMounted = true;
		setTimeout(() => mountParkingSlotsView(), 0);
	}
}

// Watch for container to appear (e.g. when user navigates to /slots)
const appRoot = document.getElementById('app');
if (appRoot) {
	const observer = new MutationObserver(() => tryMount());
	observer.observe(appRoot, { childList: true, subtree: true });
}

// Try immediately in case we're already on /slots
tryMount();
