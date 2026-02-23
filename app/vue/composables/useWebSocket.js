/**
 * Composable: WebSocket connection for real-time reservation updates.
 * Broadcasts trigger the onUpdate callback (e.g. refresh reservations).
 */

import { onMounted, onUnmounted, ref } from 'vue';
import { WS_CHANNEL } from '../constants';

function getWsUrl() {
	const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
	const host = import.meta.env.DEV ? 'localhost:8081' : `${window.location.hostname}:8081`;
	return `${protocol}//${host}`;
}

export function useWebSocket(onUpdate) {
	const ws = ref(null);

	function connect() {
		try {
			ws.value = new WebSocket(getWsUrl());
			ws.value.onmessage = (event) => {
				const msg = JSON.parse(event.data);
				if (msg.channel === WS_CHANNEL && msg.data) {
					onUpdate();
				}
			};
		} catch (e) {
			console.warn('WebSocket connect failed:', e);
		}
	}

	function disconnect() {
		if (ws.value) {
			ws.value.close();
			ws.value = null;
		}
	}

	onMounted(connect);
	onUnmounted(disconnect);

	return { ws, connect, disconnect };
}
