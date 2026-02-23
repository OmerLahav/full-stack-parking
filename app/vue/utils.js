/**
 * Pure utility functions for parking slots view.
 */

/**
 * Normalize API datetime (MySQL "Y-m-d H:i:s") to ISO format for comparison.
 */
export function normalizeDateTime(dt) {
	return dt ? String(dt).replace(' ', 'T') : '';
}

/**
 * Check if a reservation overlaps with a time slot.
 */
export function reservationOverlapsSlot(reservation, slot, date) {
	const slotStart = `${date}T${slot.start}:00`;
	const slotEnd = `${date}T${slot.end}:00`;
	const start = normalizeDateTime(reservation.start_time);
	const end = normalizeDateTime(reservation.end_time);
	return start < slotEnd && end > slotStart;
}

/**
 * Find reservation for a spot/slot combination.
 */
export function getReservationForSlot(reservations, spotId, slot, date) {
	return reservations.find(
		(r) =>
			parseInt(r.spot_id) === parseInt(spotId) &&
			r.status === 'Booked' &&
			reservationOverlapsSlot(r, slot, date)
	);
}

/**
 * Format date for display (e.g. "Tomorrow (25/02/2026)").
 * Uses baseDate (dropdown's "today") for consistent labels.
 */
export function formatDateLabel(isoDate, baseDate = null) {
	if (!isoDate) return '';
	const labels = ['Today', 'Tomorrow', 'Day after tomorrow'];
	const d = new Date(isoDate + 'T12:00:00');
	const base = baseDate || new Date().toISOString().split('T')[0];
	const today = new Date(base + 'T12:00:00');
	const diff = Math.floor((d - today) / (1000 * 60 * 60 * 24));
	const label = diff >= 0 && diff < labels.length ? labels[diff] : d.toLocaleDateString('en-GB');
	return `${label} (${d.toLocaleDateString('en-GB')})`;
}
