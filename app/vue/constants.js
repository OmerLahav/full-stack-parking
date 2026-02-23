/**
 * Parking slots view constants.
 */

export const TIME_SLOTS = [
	{ start: '08:00', end: '12:00', label: '08:00–12:00' },
	{ start: '12:00', end: '16:00', label: '12:00–16:00' },
	{ start: '16:00', end: '20:00', label: '16:00–20:00' },
];

export const DEFAULT_SPOTS = [
	{ id: 1, spot_number: 1, floor_number: 1, type: 'Regular' },
	{ id: 2, spot_number: 2, floor_number: 1, type: 'Regular' },
	{ id: 3, spot_number: 3, floor_number: 1, type: 'Regular' },
	{ id: 4, spot_number: 4, floor_number: 1, type: 'Regular' },
	{ id: 5, spot_number: 5, floor_number: 1, type: 'Regular' },
];

export const WS_CHANNEL = 'reservation_change';
