/**
 * Parking API service - handles all REST calls for spots and reservations.
 * Centralizes fetch logic, auth headers, and error handling.
 */

import AuthService from '../services/AuthService';

const API_BASE = '';

function authHeaders() {
	const token = AuthService.getToken();
	return {
		'Content-Type': 'application/json',
		...(token && { Authorization: `Bearer ${token}` }),
	};
}

async function handleResponse(res, fallbackError = 'Request failed') {
	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		throw new Error(data.error || fallbackError);
	}
	return data;
}

export const parkingApi = {
	async getSpots() {
		const data = await handleResponse(
			await fetch(`${API_BASE}/spots`, { headers: authHeaders() }),
			'Failed to load spots'
		);
		return data.spots || [];
	},

	async getReservations(date) {
		const data = await handleResponse(
			await fetch(`${API_BASE}/reservations?date=${date}`, {
				headers: authHeaders(),
			}),
			'Failed to load reservations'
		);
		return data.reservations || [];
	},

	async createReservation(spotId, startTime, endTime) {
		return handleResponse(
			await fetch(`${API_BASE}/reservations`, {
				method: 'POST',
				headers: authHeaders(),
				body: JSON.stringify({ spot_id: spotId, start_time: startTime, end_time: endTime }),
			}),
			'Booking failed'
		);
	},

	async completeReservation(reservationId) {
		return handleResponse(
			await fetch(`${API_BASE}/reservations/${reservationId}/complete`, {
				method: 'PUT',
				headers: authHeaders(),
			}),
			'Failed to release'
		);
	},
};
