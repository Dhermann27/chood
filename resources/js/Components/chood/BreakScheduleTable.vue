<script setup>
import {ref, watch} from 'vue';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';
import {datetimeToMinutes, formatTime} from '@/utils.js';
import {useSaveFeedback} from '@/Composables/useSaveFeedback.js';

const props = defineProps({
    employees: {type: Object, default: () => ({})},
    readonly: {type: Boolean, default: false},
});

const emit = defineEmits(['saved']);

const localEmployees = ref({});
const timepickerOpen = ref({});
const isRefreshing = ref(false);
const showModal = ref(false);
const {setInputRef, withFeedback} = useSaveFeedback();

watch(() => props.employees, (val) => {
    localEmployees.value = val ? {...val} : {};
    if (isRefreshing.value) isRefreshing.value = false;
}, {immediate: true});

const LUNCH_FLOOR_MIN = 8 * 60 + 30;       // 8:30am
const PM_SHIFT_START_MIN = 13 * 60;         // 1:00pm
const AFTERNOON_FLOOR_MIN = 16 * 60 + 30;  // 4:30pm

function parseBreakTimeToMinutes(str) {
    if (!str || typeof str !== 'string') return null;
    const match = str.match(/^(\d{1,2}):(\d{2})(am|pm)$/i);
    if (!match) return null;
    let h = parseInt(match[1]);
    const m = parseInt(match[2]);
    if (match[3].toLowerCase() === 'pm' && h !== 12) h += 12;
    if (match[3].toLowerCase() === 'am' && h === 12) h = 0;
    return h * 60 + m;
}

function minutesToTimeStr(min) {
    const h24 = Math.floor(min / 60) % 24;
    const m = Math.round(min % 60);
    const h12 = h24 % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')}${h24 >= 12 ? 'pm' : 'am'}`;
}

function breakIdealMinutes(employee, breakKey) {
    const startMin = datetimeToMinutes(employee.shift_start);
    const endMin = datetimeToMinutes(employee.shift_end);
    if (startMin === null || endMin === null) return null;
    const dur = endMin - startMin;
    if (dur <= 0) return null;
    if (breakKey === 'next_lunch_break') {
        if (dur < 6.5 * 60) return null;
        const target = dur >= 8 * 60 ? startMin + dur / 2 : startMin + dur * 2 / 3;
        return Math.max(target, LUNCH_FLOOR_MIN);
    }
    if (breakKey === 'next_first_break') {
        if (dur < 4 * 60) return null;
        const fraction = dur >= 8 * 60 ? 1 / 4 : dur >= 6.5 * 60 ? 1 / 3 : 1 / 2;
        const target = startMin + dur * fraction;
        return startMin >= PM_SHIFT_START_MIN ? Math.max(target, AFTERNOON_FLOOR_MIN) : target;
    }
    if (breakKey === 'next_second_break') {
        if (dur < 8 * 60) return null;
        return startMin + dur * 3 / 4;
    }
    return null;
}

function breakFairnessColor(employee, breakKey) {
    const actual = parseBreakTimeToMinutes(employee[breakKey]);
    if (actual === null) return null;
    const startMin = datetimeToMinutes(employee.shift_start);
    const endMin = datetimeToMinutes(employee.shift_end);
    if (startMin !== null && actual <= startMin) return '#FF4F4F';
    if (endMin !== null && actual >= endMin) return '#FF4F4F';
    const ideal = breakIdealMinutes(employee, breakKey);
    if (ideal === null) return null;
    const t = Math.min(Math.abs(actual - ideal) / 120, 1);
    const r = Math.round(0x87 + t * (0xFF - 0x87));
    const g = Math.round(0xB3 + t * (0xDE - 0xB3));
    const b = Math.round(0xD1 + t * (0x17 - 0xD1));
    return `rgb(${r},${g},${b})`;
}

function breakFairnessTooltip(employee, breakKey) {
    const ideal = breakIdealMinutes(employee, breakKey);
    return ideal !== null ? `Ideal: ${minutesToTimeStr(ideal)}` : null;
}

function shiftKey(employee) {
    return `${employee.wiw_user_id}-${String(employee.shift_start ?? '').replace(/:/g, '')}`;
}

function onRefreshClick() {
    showModal.value = true;
}

function cancelModal() {
    showModal.value = false;
}

async function confirmRecalc() {
    showModal.value = false;
    isRefreshing.value = true;
    await axios.post('/api/mealmap/refreshShifts', {recalculateBreaks: true, recalculateYards: false});
    emit('saved');
}

async function handleBreakChange(eventData, wiw_user_id, shift_start, break_name) {
    const key = `timepicker-${wiw_user_id}-${String(shift_start ?? '').replace(/:/g, '')}-${break_name}`;
    await withFeedback(key, async () => {
        await axios.post('/api/mealmap/break', {
            [break_name]: `${eventData.displayTime}`,
            wiw_user_id: wiw_user_id,
            shift_start: shift_start,
        });
        emit('saved');
    });
}
</script>

<template>
    <div class="mx-5 m-10 inline-block">
        <div v-if="!readonly" class="flex justify-end mb-1">
            <button @click="onRefreshClick" title="Recalculate break schedule" :disabled="isRefreshing"
                    class="w-10 h-10 bg-crimson text-white rounded text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                <FontAwesomeIcon :icon="['fas', 'rotate-right']" :spin="isRefreshing"/>
            </button>
        </div>
        <table class="bg-caregiver">
            <thead>
            <tr class="font-subheader uppercase">
                <th>&nbsp;</th>
                <th>First Break</th>
                <th>Lunch</th>
                <th>Second Break</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="(employee, key) in localEmployees" :key="key">
                <td class="border border-DEFAULT px-4 py-2">
                    {{ employee.first_name }}
                    <template v-if="employee.shift_start && employee.shift_end">
                        ({{ formatTime(employee.shift_start) }}-{{ formatTime(employee.shift_end) }})
                    </template>
                </td>

                <!-- First Break -->
                <td class="border border-DEFAULT px-4 py-2"
                    :ref="el => setInputRef(`timepicker-${shiftKey(employee)}-next_first_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_first_break') }"
                    :title="timepickerOpen[`${shiftKey(employee)}-next_first_break`] ? null : breakFairnessTooltip(employee, 'next_first_break')">
                    <div :class="[!readonly && employee.first_name !== 'Everyone' ? 'hidden' : '', 'print:block']">
                        {{ employee.next_first_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly && employee.first_name !== 'Everyone'"
                        :id="`timepicker-${shiftKey(employee)}-next_first_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_first_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${shiftKey(employee)}-next_first_break`] = true"
                        @close="delete timepickerOpen[`${shiftKey(employee)}-next_first_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_first_break')"/>
                </td>

                <!-- Lunch -->
                <td class="border border-DEFAULT px-4 py-2"
                    :ref="el => setInputRef(`timepicker-${shiftKey(employee)}-next_lunch_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_lunch_break') }"
                    :title="timepickerOpen[`${shiftKey(employee)}-next_lunch_break`] ? null : breakFairnessTooltip(employee, 'next_lunch_break')">
                    <div :class="[!readonly ? 'hidden' : '', 'print:block']">
                        {{ employee.next_lunch_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly"
                        :id="`timepicker-${shiftKey(employee)}-next_lunch_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_lunch_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${shiftKey(employee)}-next_lunch_break`] = true"
                        @close="delete timepickerOpen[`${shiftKey(employee)}-next_lunch_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_lunch_break')"/>
                </td>

                <!-- Second Break -->
                <td class="border border-DEFAULT px-4 py-2"
                    :ref="el => setInputRef(`timepicker-${shiftKey(employee)}-next_second_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_second_break') }"
                    :title="timepickerOpen[`${shiftKey(employee)}-next_second_break`] ? null : breakFairnessTooltip(employee, 'next_second_break')">
                    <div :class="[!readonly ? 'hidden' : '', 'print:block']">
                        {{ employee.next_second_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly"
                        :id="`timepicker-${shiftKey(employee)}-next_second_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_second_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${shiftKey(employee)}-next_second_break`] = true"
                        @close="delete timepickerOpen[`${shiftKey(employee)}-next_second_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_second_break')"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-greyhound/50" @click="cancelModal"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
            <button class="absolute top-4 right-4 text-greyhound" @click="cancelModal">
                <FontAwesomeIcon :icon="['fas', 'xmark']" class="text-xl"/>
            </button>
            <div class="text-lg font-semibold mb-2">Recalculate Break Schedule</div>
            <div class="text-sm text-greyhound mb-6">Manually set break times will be overwritten.</div>
            <div class="flex justify-end gap-3">
                <button
                    class="rounded-xl px-4 py-2 border border-greyhound bg-white hover:bg-greyhound/10 text-sm leading-tight w-48"
                    @click="cancelModal">Leave existing schedule
                </button>
                <button class="rounded-xl px-4 py-2 bg-crimson text-white hover:bg-alerted text-sm leading-tight w-48"
                        @click="confirmRecalc">
                    <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']"
                                     class="text-yellow-400 float-left text-2xl mr-2"/>
                    Recalculate schedule
                </button>
            </div>
        </div>
    </div>
</template>
