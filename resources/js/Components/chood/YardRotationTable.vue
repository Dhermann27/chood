<script setup>
import {computed, ref, watch} from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import {useSaveFeedback} from '@/Composables/useSaveFeedback.js';

const props = defineProps({
    rotations: Array,
    yards: Array,
    headerYardIds: Array,
    openYardIdsByRotation: Object,
    assignments: Object,
    overscheduled: Object,
    employees: Array,
    readonly: {type: Boolean, default: false},
    yardPresets: {type: Array, default: null},
    selectedPreset: {type: String, default: null},
    fohStaff: {type: String, default: ''},
});

const emit = defineEmits(['saved']);

const uiAssignments = ref({});
const isUpdatingPreset = ref(false);
const showRotationModal = ref(false);
const pendingPreset = ref(null);
const selectedTime = ref('');
const {setInputRef, withFeedback} = useSaveFeedback();

const timeOptions = Array.from({length: 10}, (_, i) => {
    const h = i + 8;
    const label = h > 12 ? `${h - 12}pm` : h === 12 ? '12pm' : `${h}am`;
    return {value: `${String(h).padStart(2, '0')}:00`, label};
});

function defaultNextHour() {
    const h = Math.min(Math.max(new Date().getHours() + 1, 8), 17);
    return `${String(h).padStart(2, '0')}:00`;
}

const employeesById = computed(() => {
    const map = new Map();
    for (const group of props.employees ?? []) {
        for (const e of (group.employees ?? [])) map.set(String(e.wiw_user_id), e);
    }
    return map;
});

const openYardNames = computed(() =>
    (props.yards ?? [])
        .filter(y => (props.headerYardIds ?? []).map(Number).includes(Number(y.id)) && Number(y.id) >= 1000)
        .map(y => y.name)
        .join(', ')
);

function isYardOpen(rotationId, yardId) {
    const ids = props.openYardIdsByRotation?.[String(rotationId)] ?? [];
    return ids.includes(Number(yardId));
}

function slot(rotationId, yardId) {
    return props.assignments?.[String(rotationId)]?.[String(yardId)] ?? null;
}

function matchEmployeeInGroups(employee) {
    for (const group of props.employees ?? []) {
        const match = (group.employees ?? []).find(e => e.wiw_user_id === employee.wiw_user_id);
        if (match) return match;
    }
    return null;
}

function hydrateUiAssignments() {
    const out = {};
    for (const rotation of props.rotations ?? []) {
        const r = String(rotation.id);
        out[r] = {};
        for (const yardId of props.headerYardIds ?? []) {
            const y = String(yardId);
            if (!isYardOpen(rotation.id, yardId)) {
                out[r][y] = null;
                continue;
            }
            const s = props.assignments?.[r]?.[y] ?? null;
            const userId = s?.wiw_user_id ? String(s.wiw_user_id) : null;
            out[r][y] = userId ? (employeesById.value.get(userId) ?? null) : null;
        }
    }
    uiAssignments.value = out;
}

watch(
    [() => props.employees, () => props.assignments],
    () => {
        // Re-sync existing entries to updated employee objects, then re-hydrate
        for (const r in uiAssignments.value) {
            for (const y in uiAssignments.value[r]) {
                const current = uiAssignments.value[r][y];
                if (!current?.wiw_user_id) continue;
                const updated = matchEmployeeInGroups(current);
                if (updated) uiAssignments.value[r][y] = updated;
            }
        }
        hydrateUiAssignments();
        if (isUpdatingPreset.value) isUpdatingPreset.value = false;
    },
    {deep: true, immediate: true}
);

function onPresetChange(e) {
    pendingPreset.value = e.target.value;
    e.target.value = props.selectedPreset;
    selectedTime.value = defaultNextHour();
    showRotationModal.value = true;
}

function onRecalculateClick() {
    pendingPreset.value = null;
    selectedTime.value = defaultNextHour();
    showRotationModal.value = true;
}

async function cancelRotationModal() {
    showRotationModal.value = false;
    if (pendingPreset.value) {
        await axios.post('/api/mealmap/markActive', {preset: pendingPreset.value});
        emit('saved');
    }
    pendingPreset.value = null;
}

async function confirmRotation() {
    showRotationModal.value = false;
    isUpdatingPreset.value = true;
    try {
        if (pendingPreset.value) {
            await axios.post('/api/mealmap/markActive', {preset: pendingPreset.value, from_time: selectedTime.value});
        } else {
            await axios.post('/api/mealmap/recalculateRotation', {from_time: selectedTime.value});
        }
        emit('saved');
    } finally {
        pendingPreset.value = null;
    }
}

async function handleYardChange(rotationId, yardId) {
    const r = String(rotationId);
    const y = String(yardId);
    const selected = uiAssignments.value?.[r]?.[y] ?? null;
    await withFeedback(`multiselect-${r}-${y}`, async () => {
        await axios.post('/api/mealmap/yard', {
            rotation_id: Number(rotationId),
            yard_id: Number(yardId),
            wiw_user_id: selected ? selected.wiw_user_id : null,
        });
        emit('saved');
    });
}
</script>

<template>
    <div class="relative w-full">
        <div class="absolute top-0 right-0 print:block">
            <div v-if="readonly">
                Yards: {{ openYardNames }}
            </div>
            <select
                v-if="yardPresets?.length && !readonly"
                :disabled="isUpdatingPreset"
                :value="selectedPreset"
                class="print:hidden text-sm rounded-md border border-greyhound bg-white px-2 py-1 shadow-sm focus:outline-none focus:ring-2 focus:ring-caregiver"
                @change="onPresetChange">
                <option v-for="preset in yardPresets" :key="preset.value" :value="preset.value">
                    {{ preset.label }}
                </option>
            </select>
        </div>

        <div class="text-3xl font-header mb-2 text-center">Daily Rotation</div>
        <div v-if="fohStaff" class="text-base mb-2 text-center">{{ fohStaff }}</div>

        <div class="w-fit mx-auto">
            <div v-if="!readonly" class="flex justify-end mb-1">
                <button @click="onRecalculateClick" title="Recalculate yard rotation"
                        :disabled="isUpdatingPreset"
                        class="w-10 h-10 bg-crimson text-white rounded text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    <FontAwesomeIcon :icon="['fas', 'rotate-right']" :spin="isUpdatingPreset"/>
                </button>
            </div>
            <table class="bg-sunshine/20">
                <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th class="font-subheader uppercase" v-for="yardId in headerYardIds" :key="yardId">
                        {{ (yards ?? []).find(y => y.id === Number(yardId))?.name ?? yardId }}
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="rotation in rotations" :key="rotation.id">
                    <td class="border border-DEFAULT px-4 py-2">{{ rotation.label }}</td>
                    <td v-for="yardId in headerYardIds" :key="yardId"
                        class="border border-DEFAULT px-4 py-2"
                        :class="{ 'bg-alerted/40': `${rotation.id}-${yardId}` in (overscheduled ?? {}) }"
                        :title="(overscheduled ?? {})[`${rotation.id}-${yardId}`] ?? null"
                        :ref="el => setInputRef(`multiselect-${String(rotation.id)}-${String(yardId)}`, el)">

                        <div :class="[!readonly ? 'hidden' : '', 'print:block']">
                        <span v-if="slot(rotation.id, yardId)">
                            {{ slot(rotation.id, yardId).first_name }}
                            <FontAwesomeIcon v-if="`${rotation.id}-${yardId}` in (overscheduled ?? {})"
                                             :icon="['fas', 'clock']" class="me-1"/>
                        </span>
                        </div>

                        <multiselect
                            v-if="!readonly && uiAssignments[String(rotation.id)]"
                            class="print-hide"
                            :key="`multiselect-${rotation.id}-${yardId}`"
                            :id="`multiselect-${rotation.id}-${yardId}`"
                            v-model="uiAssignments[String(rotation.id)][String(yardId)]"
                            :options="employees ?? []"
                            group-label="status" group-values="employees" :group-select="true"
                            label="first_name" track-by="wiw_user_id" :searchable="true"
                            :clearable="true" placeholder="Unassigned"
                            @select="() => handleYardChange(rotation.id, yardId)"
                            @remove="() => handleYardChange(rotation.id, yardId)"/>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showRotationModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-greyhound/50" @click="cancelRotationModal"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <button class="absolute top-4 right-4 text-greyhound" @click="cancelRotationModal">
                    <FontAwesomeIcon :icon="['fas', 'xmark']" class="text-xl"/>
                </button>
                <div class="text-lg font-semibold mb-2">Recalculate Yard Rotation</div>
                <p class="text-sm text-greyhound mb-4">Assignments will be recalculated from the set time forward.</p>
                <label class="block text-sm text-greyhound mb-1">Recalculate after:</label>
                <select v-model="selectedTime"
                        class="w-full rounded-md border border-greyhound bg-white px-3 py-2 text-sm mb-6 focus:outline-none focus:ring-2 focus:ring-caregiver">
                    <option v-for="opt in timeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <div class="flex justify-end gap-3">
                    <button
                        class="rounded-xl px-4 py-2 border border-greyhound bg-white hover:bg-greyhound/10 text-sm leading-tight w-48"
                        @click="cancelRotationModal">
                        Leave existing assignments
                    </button>
                    <button
                        class="rounded-xl px-4 py-2 bg-crimson text-white hover:bg-alerted text-sm leading-tight w-48"
                        @click="confirmRotation">
                        <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']"
                                         class="text-yellow-400 float-left text-2xl mr-2"/>
                        Recalculate assignments
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
