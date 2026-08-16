<script setup>
import {computed, ref, watch} from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

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
    isUpdatingPreset: {type: Boolean, default: false},
    fohStaff: {type: String, default: ''},
});

const emit = defineEmits(['saved', 'presetChange']);

const uiAssignments = ref({});
const inputRefs = ref({});

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
    },
    {deep: true, immediate: true}
);

function setInputRef(key, el) {
    if (!inputRefs.value) inputRefs.value = {};
    inputRefs.value[key] = el;
}

async function handleYardChange(rotationId, yardId) {
    const r = String(rotationId);
    const y = String(yardId);
    const selected = uiAssignments.value?.[r]?.[y] ?? null;
    const td = inputRefs.value[`multiselect-${r}-${y}`];

    try {
        if (td) td.style.backgroundColor = 'gray';
        await axios.post('/api/mealmap/yard', {
            rotation_id: Number(rotationId),
            yard_id: Number(yardId),
            wiw_user_id: selected ? selected.wiw_user_id : null,
        });
        if (td) td.style.backgroundColor = 'green';
        emit('saved');
    } catch (error) {
        console.error('Error handling Yard Change', error);
        if (td) td.style.backgroundColor = 'red';
    }

    setTimeout(() => {
        if (td) td.style.backgroundColor = '';
    }, 5000);
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
                class="print:hidden text-sm rounded-md border border-gray-300 bg-white px-2 py-1 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="emit('presetChange', $event)">
                <option v-for="preset in yardPresets" :key="preset.value" :value="preset.value">
                    {{ preset.label }}
                </option>
            </select>
        </div>

        <div class="text-3xl font-header mb-2">Daily Rotation</div>
        <div v-if="fohStaff" class="text-base mb-2">{{ fohStaff }}</div>

        <table class="mx-5 bg-amber-100">
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
                    :class="{ 'bg-orange-300': `${rotation.id}-${yardId}` in (overscheduled ?? {}) }"
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
</template>
