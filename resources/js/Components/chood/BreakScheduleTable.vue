<script setup>
import {ref, watch} from 'vue';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';
import {formatTime, datetimeToMinutes} from '@/utils.js';

const props = defineProps({
    employees: {type: Object, default: () => ({})},
    readonly: {type: Boolean, default: false},
    shiftsRefreshing: {type: Boolean, default: false},
});

const emit = defineEmits(['saved', 'refreshShifts']);

const localEmployees = ref({});
const timepickerOpen = ref({});
const inputRefs = ref({});

watch(() => props.employees, (val) => {
    localEmployees.value = val ? {...val} : {};
}, {immediate: true});

const LUNCH_FLOOR_MIN = 8 * 60 + 30;       // 8:30am
const PM_SHIFT_START_MIN = 13 * 60;         // 1:00pm
const AFTERNOON_FLOOR_MIN = 16 * 60 + 30;  // 4:30pm

function parseBreakTimeToMinutes(str) {
    if (!str) return null;
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

function setInputRef(key, el) {
    if (!inputRefs.value) inputRefs.value = {};
    inputRefs.value[key] = el;
}

async function handleBreakChange(eventData, wiw_user_id, shift_start, break_name) {
    const select = inputRefs.value[`timepick-${wiw_user_id}-${break_name}`];
    const redClasses = Array.from({length: 9}, (_, i) => `bg-red-${(i + 1) * 100}`);

    try {
        if (select) {
            select.classList.remove(...redClasses);
            select.style.backgroundColor = 'gray';
        }
        await axios.post('/api/mealmap/break', {
            [break_name]: `${eventData.displayTime}`,
            wiw_user_id: wiw_user_id,
            shift_start: shift_start,
        });
        if (select) select.style.backgroundColor = 'green';
        emit('saved');
    } catch (error) {
        console.error('Error handling Break Change', error);
        if (select) select.style.backgroundColor = 'red';
    }

    setTimeout(() => {
        if (select) select.style.backgroundColor = '';
    }, 5000);
}
</script>

<template>
    <div class="mx-5 m-10 inline-block">
        <div v-if="!readonly" class="flex justify-end mb-1">
            <button @click="emit('refreshShifts')" title="Refresh shift schedule"
                    :disabled="shiftsRefreshing"
                    class="w-10 h-10 bg-crimson text-white rounded text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                <FontAwesomeIcon :icon="['fas', 'rotate-right']" :spin="shiftsRefreshing"/>
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
                    :ref="el => setInputRef(`timepick-${String(employee.wiw_user_id)}-next_first_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_first_break') }"
                    :title="timepickerOpen[`${employee.wiw_user_id}-next_first_break`] ? null : breakFairnessTooltip(employee, 'next_first_break')">
                    <div :class="[!readonly && employee.first_name !== 'Everyone' ? 'hidden' : '', 'print:block']">
                        {{ employee.next_first_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly && employee.first_name !== 'Everyone'"
                        :id="`timepick-${String(employee.wiw_user_id)}-next_first_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_first_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${employee.wiw_user_id}-next_first_break`] = true"
                        @close="delete timepickerOpen[`${employee.wiw_user_id}-next_first_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_first_break')"/>
                </td>

                <!-- Lunch -->
                <td class="border border-DEFAULT px-4 py-2"
                    :ref="el => setInputRef(`timepick-${String(employee.wiw_user_id)}-next_lunch_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_lunch_break') }"
                    :title="timepickerOpen[`${employee.wiw_user_id}-next_lunch_break`] ? null : breakFairnessTooltip(employee, 'next_lunch_break')">
                    <div :class="[!readonly ? 'hidden' : '', 'print:block']">
                        {{ employee.next_lunch_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly"
                        :id="`timepick-${String(employee.wiw_user_id)}-next_lunch_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_lunch_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${employee.wiw_user_id}-next_lunch_break`] = true"
                        @close="delete timepickerOpen[`${employee.wiw_user_id}-next_lunch_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_lunch_break')"/>
                </td>

                <!-- Second Break -->
                <td class="border border-DEFAULT px-4 py-2"
                    :ref="el => setInputRef(`timepick-${String(employee.wiw_user_id)}-next_second_break`, el)"
                    :style="{ backgroundColor: breakFairnessColor(employee, 'next_second_break') }"
                    :title="timepickerOpen[`${employee.wiw_user_id}-next_second_break`] ? null : breakFairnessTooltip(employee, 'next_second_break')">
                    <div :class="[!readonly ? 'hidden' : '', 'print:block']">
                        {{ employee.next_second_break }}
                    </div>
                    <VueTimepicker
                        v-if="!readonly"
                        :id="`timepick-${String(employee.wiw_user_id)}-next_second_break`"
                        class="print-hide" placeholder="None"
                        v-model="employee.next_second_break" format="HH:mma" :minute-interval="5"
                        :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                        @open="timepickerOpen[`${employee.wiw_user_id}-next_second_break`] = true"
                        @close="delete timepickerOpen[`${employee.wiw_user_id}-next_second_break`]"
                        @change="handleBreakChange($event, employee.wiw_user_id, employee.shift_start, 'next_second_break')"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
