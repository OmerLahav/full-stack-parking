<template>
	<div class="parking-slots-vue">
		<p v-if="error" class="slot-error">{{ error }}</p>
		<div v-else-if="loading" class="slot-loading">Loading...</div>
		<div v-else-if="!selectedDate" class="placeholder-msg">
			<p>Select a date above to view available slots</p>
		</div>
		<div v-else class="slots-grid">
			<p class="grid-date-label">Viewing: {{ formattedDateLabel }}</p>
			<div class="grid-header">
				<div class="grid-cell header-cell">Spot</div>
				<div
					v-for="slot in TIME_SLOTS"
					:key="slot.label"
					class="grid-cell header-cell"
				>
					{{ slot.label }}
				</div>
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
						disabled: slot.reservation || bookingInProgress,
					}"
					@click="handleSlotClick(spot, slot)"
				>
					<span v-if="slot.available && !bookingInProgress">🟢 Book</span>
					<span v-else-if="slot.reservation">🔴 Booked</span>
					<span v-else>—</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { parkingApi } from '../api/parkingApi';
import { TIME_SLOTS, DEFAULT_SPOTS } from './constants';
import { getReservationForSlot, formatDateLabel } from './utils';
import { useWebSocket } from './composables/useWebSocket';

// State
const selectedDate = ref(new Date().toISOString().split('T')[0]);
const spots = ref([]);
const reservations = ref([]);
const loading = ref(false);
const error = ref('');
const bookingInProgress = ref(null);
const baseDateForLabels = ref(null);

// Data loading
async function loadSpots() {
	try {
		spots.value = await parkingApi.getSpots();
		error.value = '';
	} catch (e) {
		error.value = e.message;
		spots.value = [];
	}
}

async function loadReservations() {
	if (!selectedDate.value) return;
	loading.value = true;
	error.value = '';
	try {
		reservations.value = await parkingApi.getReservations(selectedDate.value);
	} catch (e) {
		error.value = e.message;
	} finally {
		loading.value = false;
	}
}

// WebSocket: refresh on reservation changes
useWebSocket(loadReservations);

// Computed
const displaySpots = computed(() =>
	spots.value.length > 0 ? spots.value : DEFAULT_SPOTS
);

const grid = computed(() => {
	if (!selectedDate.value) return [];
	return displaySpots.value.map((spot) => ({
		...spot,
		slots: TIME_SLOTS.map((slot, idx) => {
			const res = getReservationForSlot(
				reservations.value,
				spot.id,
				slot,
				selectedDate.value
			);
			return {
				...slot,
				slotIndex: idx,
				reservation: res,
				available: !res,
			};
		}),
	}));
});

const formattedDateLabel = computed(() =>
	formatDateLabel(selectedDate.value, baseDateForLabels.value)
);

// Actions
function handleSlotClick(spot, slot) {
	if (slot.reservation || bookingInProgress.value) return;
	bookSlot(spot, slot);
}

async function bookSlot(spot, slot) {
	const key = `${spot.id}-${slot.slotIndex}`;
	if (bookingInProgress.value === key) return;

	bookingInProgress.value = key;
	error.value = '';
	try {
		const startTime = `${selectedDate.value}T${slot.start}:00`;
		const endTime = `${selectedDate.value}T${slot.end}:00`;
		await parkingApi.createReservation(spot.id, startTime, endTime);
		await loadReservations();
	} catch (e) {
		error.value = e.message;
	} finally {
		bookingInProgress.value = null;
	}
}

// Date picker sync (Vanilla JS dropdown)
function setupDatePickerSync() {
	const handler = (e) => {
		selectedDate.value = e.detail ?? e.target?.value ?? '';
		loadReservations();
	};
	window.addEventListener('parking-date-change', handler);

	const select = document.getElementById('date-select');
	if (select) {
		select.addEventListener('change', handler);
		return () => {
			window.removeEventListener('parking-date-change', handler);
			select.removeEventListener('change', handler);
		};
	}
	return () => window.removeEventListener('parking-date-change', handler);
}

// Lifecycle
onMounted(async () => {
	const picker = document.getElementById('date-select');
	if (picker) {
		selectedDate.value = picker.value || selectedDate.value;
		baseDateForLabels.value = picker.options[0]?.value ?? null;
	}
	const teardown = setupDatePickerSync();
	onBeforeUnmount(teardown);

	await loadSpots();
	await loadReservations();
});

watch(selectedDate, loadReservations);
</script>
