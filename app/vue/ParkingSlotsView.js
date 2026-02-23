/**
 * Vue Parking Slots View - mounts inside #parking-slots-view
 * Implements: 5 spots × 3 time slots, real-time WebSocket updates, race-condition handling
 */

import { createApp } from 'vue';
import AuthService from '../services/AuthService';

const TIME_SLOTS = [
	{ start: '08:00', end: '12:00', label: '08:00–12:00' },
	{ start: '12:00', end: '16:00', label: '12:00–16:00' },
	{ start: '16:00', end: '20:00', label: '16:00–20:00' },
];

// Fallback: 5 spots per assignment (show grid even if API fails)
const DEFAULT_SPOTS = [
	{ id: 1, spot_number: 1, floor_number: 1, type: 'Regular' },
	{ id: 2, spot_number: 2, floor_number: 1, type: 'Regular' },
	{ id: 3, spot_number: 3, floor_number: 1, type: 'Regular' },
	{ id: 4, spot_number: 4, floor_number: 1, type: 'Regular' },
	{ id: 5, spot_number: 5, floor_number: 1, type: 'Regular' },
];

function getApiBase() {
	return '';
}

export function mountParkingSlotsView() {
	const container = document.getElementById('parking-slots-view');
	if (!container) {
		console.warn('[ParkingSlotsView] Container #parking-slots-view not found');
		return;
	}

	try {
		createApp({
		data() {
			return {
				TIME_SLOTS,
				selectedDate: new Date().toISOString().split('T')[0],
				spots: [],
				reservations: [],
				loading: false,
				error: '',
				bookingInProgress: null, // prevents double-click race
				ws: null,
				dateChangeHandler: null,
				selectChangeHandler: null,
			};
		},
		computed: {
			displaySpots() {
				return this.spots.length > 0 ? this.spots : DEFAULT_SPOTS;
			},
			grid() {
				if (!this.selectedDate) return [];
				return this.displaySpots.map((spot) => ({
					...spot,
					slots: TIME_SLOTS.map((slot, idx) => {
						const res = this.getReservationForSlot(spot.id, slot);
						return {
							...slot,
							slotIndex: idx,
							reservation: res,
							available: !res,
							isMine: res && this.isMyReservation(res),
						};
					}),
				}));
			},
		},
		async mounted() {
			// Sync with Vanilla JS date picker
			const picker = document.getElementById('date-select');
			this.selectedDate = picker?.value || new Date().toISOString().split('T')[0];
			this.listenForDateChange();
			this.connectWebSocket();
			await this.loadSpots();
			await this.loadReservations();
		},
		beforeUnmount() {
			this.disconnectWebSocket();
			this.removeDateListener();
		},
		methods: {
			async loadSpots() {
				try {
					const res = await fetch(`${getApiBase()}/spots`, {
						headers: { Authorization: `Bearer ${AuthService.getToken()}` },
					});
					if (!res.ok) throw new Error('Failed to load spots');
					const data = await res.json();
					this.spots = data.spots || [];
					this.error = '';
				} catch (e) {
					this.error = e.message;
					this.spots = []; // Use DEFAULT_SPOTS fallback
				}
			},
			async loadReservations() {
				if (!this.selectedDate) return;
				this.loading = true;
				this.error = '';
				try {
					const res = await fetch(
						`${getApiBase()}/reservations?date=${this.selectedDate}`,
						{ headers: { Authorization: `Bearer ${AuthService.getToken()}` } }
					);
					if (!res.ok) throw new Error('Failed to load reservations');
					const data = await res.json();
					this.reservations = data.reservations || [];
				} catch (e) {
					this.error = e.message;
				} finally {
					this.loading = false;
				}
			},
			getReservationForSlot(spotId, slot) {
				const date = this.selectedDate;
				const slotStart = `${date}T${slot.start}:00`;
				const slotEnd = `${date}T${slot.end}:00`;
				// Normalize API datetimes (MySQL "Y-m-d H:i:s") to ISO format for comparison
				const norm = (dt) => (dt ? String(dt).replace(' ', 'T') : '');
				return this.reservations.find(
					(r) =>
						parseInt(r.spot_id) === parseInt(spotId) &&
						r.status === 'Booked' &&
						norm(r.start_time) < slotEnd &&
						norm(r.end_time) > slotStart
				);
			},
			isMyReservation(reservation) {
				// We don't have current user id in AuthService; assume all booked = others for now
				// Could extend AuthService to expose user id if needed
				return false;
			},
			async bookSlot(spot, slot) {
				if (slot.reservation || this.bookingInProgress) return;
				const key = `${spot.id}-${slot.slotIndex}`;
				if (this.bookingInProgress === key) return;

				this.bookingInProgress = key;
				this.error = '';
				try {
					const date = this.selectedDate;
					const startTime = `${date}T${slot.start}:00`;
					const endTime = `${date}T${slot.end}:00`;

					const res = await fetch(`${getApiBase()}/reservations`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							Authorization: `Bearer ${AuthService.getToken()}`,
						},
						body: JSON.stringify({
							spot_id: spot.id,
							start_time: startTime,
							end_time: endTime,
						}),
					});

					const data = await res.json().catch(() => ({}));
					if (!res.ok) {
						throw new Error(data.error || 'Booking failed');
					}
					await this.loadReservations();
				} catch (e) {
					this.error = e.message;
				} finally {
					this.bookingInProgress = null;
				}
			},
			async releaseSlot(reservation) {
				// Optional: release own reservation
				try {
					const res = await fetch(
						`${getApiBase()}/reservations/${reservation.id}/complete`,
						{
							method: 'PUT',
							headers: { Authorization: `Bearer ${AuthService.getToken()}` },
						}
					);
					if (!res.ok) throw new Error('Failed to release');
					await this.loadReservations();
				} catch (e) {
					this.error = e.message;
				}
			},
			listenForDateChange() {
				this.dateChangeHandler = (e) => {
					this.selectedDate = e.detail || '';
					this.loadReservations();
				};
				window.addEventListener('parking-date-change', this.dateChangeHandler);
				// Backup: listen directly to the select
				const select = document.getElementById('date-select');
				if (select) {
					this.selectChangeHandler = (e) => {
						this.selectedDate = e.target.value || '';
						this.loadReservations();
					};
					select.addEventListener('change', this.selectChangeHandler);
				}
			},
			connectWebSocket() {
				const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
				const wsHost = import.meta.env.DEV
					? 'localhost:8081'
					: `${window.location.hostname}:8081`;
				const wsUrl = `${wsProtocol}//${wsHost}`;
				try {
					this.ws = new WebSocket(wsUrl);
					this.ws.onmessage = (event) => {
						const msg = JSON.parse(event.data);
						if (msg.channel === 'reservation_change' && msg.data) {
							this.loadReservations();
						}
					};
				} catch (e) {
					console.warn('WebSocket connect failed:', e);
				}
			},
			disconnectWebSocket() {
				if (this.ws) {
					this.ws.close();
					this.ws = null;
				}
			},
			removeDateListener() {
				if (this.dateChangeHandler) {
					window.removeEventListener('parking-date-change', this.dateChangeHandler);
				}
				const select = document.getElementById('date-select');
				if (select && this.selectChangeHandler) {
					select.removeEventListener('change', this.selectChangeHandler);
				}
			},
			formatDateLabel(isoDate) {
				if (!isoDate) return '';
				const d = new Date(isoDate + 'T12:00:00');
				const today = new Date();
				const diff = Math.floor((d - today) / (1000 * 60 * 60 * 24));
				const labels = ['Today', 'Tomorrow', 'Day after tomorrow'];
				const label = diff >= 0 && diff < labels.length ? labels[diff] : d.toLocaleDateString('en-GB');
				return `${label} (${d.toLocaleDateString('en-GB')})`;
			},
		},
		watch: {
			selectedDate() {
				this.loadReservations();
			},
		},
		template: `
			<div class="parking-slots-vue">
				<p v-if="error" class="slot-error">{{ error }}</p>
				<div v-if="loading" class="slot-loading">Loading...</div>
				<div v-else-if="!selectedDate" class="placeholder-msg">
					<p>Select a date above to view available slots</p>
				</div>
				<div v-else class="slots-grid">
					<p class="grid-date-label">Viewing: {{ formatDateLabel(selectedDate) }}</p>
					<div class="grid-header">
						<div class="grid-cell header-cell">Spot</div>
						<div v-for="slot in TIME_SLOTS" :key="slot.label" class="grid-cell header-cell">{{ slot.label }}</div>
					</div>
					<div v-for="spot in grid" :key="spot.id" class="grid-row">
						<div class="grid-cell spot-label">Spot #{{ spot.spot_number }}</div>
						<div
							v-for="slot in spot.slots"
							:key="slot.slotIndex"
							class="grid-cell slot-cell"
							:class="{
								available: slot.available && !bookingInProgress,
								booked: slot.reservation,
								disabled: slot.reservation || bookingInProgress
							}"
							@click="slot.available && !bookingInProgress && bookSlot(spot, slot)"
						>
							<span v-if="slot.available && !bookingInProgress">🟢 Book</span>
							<span v-else-if="slot.reservation">🔴 Booked</span>
							<span v-else>—</span>
						</div>
					</div>
				</div>
			</div>
		`,
		}).mount('#parking-slots-view');
	} catch (err) {
		console.error('[ParkingSlotsView] Mount failed:', err);
		container.innerHTML = `<div class="slot-error">Failed to load parking slots: ${err.message}</div>`;
	}
}
