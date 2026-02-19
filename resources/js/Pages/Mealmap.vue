<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref, watchEffect} from "vue";
import DogCard from "@/Components/chood/DogCard.vue";
import {ControlSchemes} from "@/controlSchemes.js";
import {formatTime} from "@/utils.js";
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';

const props = defineProps({
    dogsPerPage: Number,
    photoUri: String,
    rotations: Array,
    yards: Array,
    yardPresets: Array,
    preset: String,
});

const controls = ref(ControlSchemes.NONE);
const inputRefs = ref({});
const breaks = ref([]);
const lunchDogs = ref([]);
const medicatedDogs = ref([]);
const selectedYardPreset = ref(props.preset);
const employees = ref([]);
const fohStaff = ref('');
const assignments = ref({});
const headerYardIds = ref([]);
const openYardIdsByRotation = ref({});
const uiAssignments = ref({});
const localChecksum = ref('');
let refreshInterval;
// const currentViewIndex = ref(0);
const currentLoadingIndex = ref(0);

const cardHeight = computed(() => Math.min(300, 800 / (lunchDogs.value.length + medicatedDogs.value.length)));
const allDogs = computed(() => [...lunchDogs.value, ...medicatedDogs.value]);
const employeesById = computed(() => {
    const map = new Map();
    for (const group of employees.value ?? []) {
        for (const e of (group.employees ?? [])) map.set(String(e.homebase_user_id), e);
    }
    return map;
});


function setInputRef(key, el) {
    if (!inputRefs.value) inputRefs.value = {};
    inputRefs.value[key] = el;
}

const handleImageLoaded = () => {
    const list = allDogs.value;
    while (++currentLoadingIndex.value < list.value?.length) {
        if (list.value[currentLoadingIndex.value].photoUri) {
            break;
        }
    }
};

async function updateData() {
    try {
        const response = await axios.get(`/api/mealmap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data.checksum) {
            assignments.value = {...response.data.assignments};
            breaks.value = {...response.data.breaks};
            lunchDogs.value = response.data.lunchDogs;
            medicatedDogs.value = response.data.medicatedDogs;
            employees.value = response.data.employees;
            fohStaff.value = response.data.fohStaff;
            selectedYardPreset.value = response.data.preset;
            headerYardIds.value = response.data.headerYards;
            openYardIdsByRotation.value = response.data.openYardsByRotation;
            localChecksum.value = response.data.checksum;
            currentLoadingIndex.value = 0;
            // } else if (dogs.value.length > props.dogsPerPage) {
            //     const maxChunks = Math.ceil(dogs.value.length / props.dogsPerPage);
            //     currentViewIndex.value = (currentViewIndex.value + 1) % maxChunks;

            hydrateUiAssignments();
        }

    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

const showOverwriteModal = ref(false);
const pendingYardPreset = ref(null);
const isSavingPreset = ref(false);

function onYardPresetChange(e) {
    pendingYardPreset.value = e.target.value;
    showOverwriteModal.value = true;
    e.target.value = selectedYardPreset.value;
}

function cancelOverwrite() {
    pendingYardPreset.value = null;
    showOverwriteModal.value = false;
}

async function applyPreset(overwrite) {
    if (!pendingYardPreset.value || isSavingPreset.value) return;

    isSavingPreset.value = true;
    try {
        const preset = pendingYardPreset.value;
        await axios.post('/api/mealmap/markActive', {preset, overwrite});

        selectedYardPreset.value = preset;
        pendingYardPreset.value = null;
        showOverwriteModal.value = false;
    } finally {
        isSavingPreset.value = false;
    }
}

function hydrateUiAssignments() {
    const out = {};

    for (const rotation of props.rotations ?? []) {
        const r = String(rotation.id);
        out[r] = {};

        for (const yardId of headerYardIds.value ?? []) {
            const y = String(yardId);

            if (!isYardOpen(rotation.id, yardId)) {
                out[r][y] = null;
                continue;
            }

            const s = assignments.value?.[r]?.[y] ?? null;
            const userId = s?.homebase_user_id ? String(s.homebase_user_id) : null;

            out[r][y] = userId ? (employeesById.value.get(userId) ?? null) : null;
        }
    }

    uiAssignments.value = out;
}

function isYardOpen(rotationId, yardId) {
    const ids = openYardIdsByRotation.value?.[String(rotationId)] ?? [];
    return ids.includes(Number(yardId));
}

function slot(rotationId, yardId) {
    return assignments.value?.[String(rotationId)]?.[String(yardId)] ?? null;
}

// const isVisible = (index) => {
//     const start = currentViewIndex.value * props.dogsPerPage;
//     const end = start + props.dogsPerPage;
//     return index >= start && index < end;
// };

// const progressBarStyle = computed(() => ({
//     left: ((currentViewIndex.value * props.dogsPerPage) / dogs.value.length) * 100 + '%',
//     width: (Math.min(props.dogsPerPage, dogs.value.length - currentViewIndex.value * props.dogsPerPage)
//         / dogs.value.length) * 100 + '%',
//     color: 'white',
// }));

// const getFairnessColor = (score) => {
//     if (!score || score <= 0) return '';
//     const intensity = Math.min(Math.ceil(score) * 100, 800);
//     return `bg-red-${intensity}`;
// };


// Next three methods are all so Vue3 detects changes inside the nested objects
function matchEmployeeInGroups(employee) {
    for (const group of employees.value) {
        const match = group.employees.find(e => e.homebase_user_id === employee.homebase_user_id);
        if (match) return match;
    }
    return null;
}

function matchByHour() {
    for (const rotationId in assignments.value) {
        for (const yardId in assignments.value[rotationId]) {
            const slot = assignments.value?.[rotationId]?.[yardId] ?? null;
            if (!slot || !slot.homebase_user_id) continue;

            assignments.value[rotationId][yardId] = matchEmployeeInGroups(slot) ?? slot;
        }
    }
}

watchEffect(() => {
    if (employees.value && Object.keys(assignments.value).length) matchByHour();
});

const handleYardChange = async (rotationId, yardId) => {
    const select = inputRefs.value[`multiselect-${rotationId}-${yardId}`];
    const r = String(rotationId);
    const y = String(yardId);
    const selected = uiAssignments.value?.[r]?.[y] ?? null;

    if (!assignments.value[r]) assignments.value[r] = {};
    assignments.value[r][y] = selected ? {
        homebase_user_id: selected.homebase_user_id,
        first_name: selected.first_name
    } : null;

    try {
        if (select) select.style.backgroundColor = 'gray';

        await axios.post('/api/mealmap/yard', {
            rotation_id: Number(rotationId),
            yard_id: Number(yardId),
            homebase_user_id: selected ? selected.homebase_user_id : null,
        });

        if (select) select.style.backgroundColor = 'green';
    } catch (error) {
        console.log('Error handling Yard Change', error);
        if (select) select.style.backgroundColor = 'red';
    }

    setTimeout(() => {
        if (select) select.style.backgroundColor = '';
    }, 5000);
};

const handleBreakChange = async (eventData, homebase_user_id, break_name) => {
    const select = inputRefs.value[`timepick-${homebase_user_id}-${break_name}`];
    const redClasses = Array.from({length: 9}, (_, i) => `bg-red-${(i + 1) * 100}`);

    try {
        select.classList.remove(...redClasses); // Remove any Tailwind red class
        select.style.backgroundColor = 'gray';
        await axios.post('/api/mealmap/break', {
            [break_name]: `${eventData.displayTime}`,
            homebase_user_id: homebase_user_id,
        });
        select.style.backgroundColor = 'green';
    } catch (error) {
        console.log('Error handling Break Change ', error);
        select.style.backgroundColor = 'red';
    }
    setTimeout(() => {
        select.style.backgroundColor = '';
    }, 5000);
};


onMounted(() => {
    if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
        controls.value = !navigator.userAgent.includes('Linux') ? ControlSchemes.MODAL : ControlSchemes.NONE;
    }

    updateData();
    refreshInterval = setInterval(updateData, 15000);
});

onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>


<template>
    <Head title="Mealmap"/>
    <div class="h-full w-full flex flex-col items-center justify-center">
        <div class="w-full grid grid-cols-2 print:grid-cols-1 gap-4 h-full">
            <div class="flex flex-col ps-3 items-center divider pt-5 print:hidden">
                <div class="text-3xl font-header mb-2">Medications</div>
                <div class="grid grid-cols-1 w-full">
                    <div v-for="(dog, index) in medicatedDogs" :key="index" class="flex border-b-2 even:bg-gray-200">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: cardHeight + 'px'}">
                            <DogCard :dogs="[dog]" :photoUri="props.photoUri" :maxlength="20" :card-height="cardHeight"
                                     :shouldLoad="index === currentLoadingIndex" @imageLoaded="handleImageLoaded"/>
                        </div>

                        <div class="flex-grow flex flex-col items-start justify-center p-1 text-xl">
                            <div v-for="medication in dog.medications" :key="medication.id"
                                 class="flex-col justify-center">
                                <FontAwesomeIcon v-if="medication.type_id !== 15"
                                                 :icon="$fa.fas['prescription-bottle-pill']" class="me-1"/>
                                <FontAwesomeIcon v-else :icon="$fa.fas['note-medical']" class="me-1"/>
                                {{ medication.type.trim() }}
                                <span v-if="medication.type && medication.description">:&nbsp;</span>
                                {{ medication.description.trim() }}
                            </div>
                            <div v-for="allergy in dog.allergies" :key="allergy.id"
                                 class="flex-col justify-center text-crimson">
                                <FontAwesomeIcon :icon="$fa.fas['hand-dots']" class="me-1"/>
                                ALLERGY: {{ allergy.description.trim() }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-3xl font-header my-2">Lunches</div>
                <div class="grid grid-cols-1 w-full">
                    <div v-for="(dog, index) in lunchDogs" :key="index" class="flex border-b-2 even:bg-gray-200">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: cardHeight + 'px'}">
                            <DogCard :dogs="[dog]" :photoUri="props.photoUri" :maxlength="20" :card-height="cardHeight"
                                     :shouldLoad="index + medicatedDogs.length === currentLoadingIndex"
                                     @imageLoaded="handleImageLoaded"/>
                        </div>

                        <div class="flex-grow flex items-center gap-3 p-1 text-xl min-w-0">
                            <FontAwesomeIcon :icon="$fa.fas['turkey']" class="flex-shrink-0 me-1"/>
                            {{ dog.lunch_notes }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-center pt-5 print:flex relative">
                <div class="absolute top-5 right-10">
                    <div :class="[controls !== ControlSchemes.NONE ? 'hidden' : '', 'print:block']">
                        {{ yards.filter(e => e.id >= 1000).map(e => e.name).join(', ') }}
                    </div>
                    <select
                        v-if="controls !== ControlSchemes.NONE"
                        class="print:hidden text-sm rounded-md border border-gray-300 bg-white px-2 py-1 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        v-model="selectedYardPreset" @change="onYardPresetChange($event)">
                        <option v-for="preset in props.yardPresets" :key="preset.value" :value="preset.value">
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
                            {{ (props.yards ?? []).find(y => y.id === Number(yardId))?.name ?? yardId }}
                        </th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="rotation in props.rotations" :key="rotation.id">
                        <td class="border border-DEFAULT px-4 py-2">{{ rotation.label }}</td>

                        <td v-for="yardId in headerYardIds" :key="yardId" class="border border-DEFAULT px-4 py-2">

                            <div :class="[controls !== ControlSchemes.NONE ? 'hidden' : '', 'print:block']">
                              <span v-if="slot(rotation.id, yardId)">
                                {{ slot(rotation.id, yardId).first_name }}
                              </span>
                            </div>

                            <!-- editor -->
                            <multiselect
                                v-if="controls !== ControlSchemes.NONE"
                                class="print-hide"
                                :key="`multiselect-${rotation.id}-${yardId}`"
                                :id="`multiselect-${rotation.id}-${yardId}`"
                                v-model="uiAssignments[rotation.id][yardId]" :options="employees"
                                group-label="status" group-values="employees" :group-select="true"
                                label="first_name" track-by="homebase_user_id" :searchable="true"
                                :clearable="true" placeholder="Unassigned"
                                @select="() => handleYardChange(rotation.id, yardId)"
                                @remove="() => handleYardChange(rotation.id, yardId)"/>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <table class="mx-5 bg-caregiver m-10">
                    <thead>
                    <tr class="font-subheader uppercase">
                        <th>&nbsp;</th>
                        <th>First Break</th>
                        <th>Lunch</th>
                        <th>Second Break</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="employee in breaks">
                        <td class="border border-DEFAULT px-4 py-2">{{ employee.first_name }}
                            <template v-if="employee.shift_start && employee.shift_end">
                                ({{ formatTime(employee.shift_start) }}-{{ formatTime(employee.shift_end) }})
                            </template>
                        </td>
                        <td class="border border-DEFAULT px-4 py-2"
                            :ref="el => setInputRef(`timepick-${String(employee.homebase_user_id)}-next_first_break`, el)">
                            <div
                                :class="[controls !== ControlSchemes.NONE  && employee.first_name !== 'Everyone' ? 'hidden' : '', 'print:block']">
                                {{ employee.next_first_break }}
                            </div>
                            <VueTimepicker
                                v-if="controls !== ControlSchemes.NONE && employee.first_name !== 'Everyone'"
                                :id="`timepick-${String(employee.homebase_user_id)}-next_first_break`"
                                class="print-hide" placeholder="None"
                                v-model="employee.next_first_break" format="HH:mma" :minute-interval="5"
                                :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                                @change="handleBreakChange($event, employee.homebase_user_id, 'next_first_break')"
                            />
                            <!--                                :class="getFairnessColor(employee.fairness_score)"-->
                        </td>
                        <td class="border border-DEFAULT px-4 py-2"
                            :ref="el => setInputRef(`timepick-${String(employee.homebase_user_id)}-next_lunch_break`, el)">
                            <div :class="[controls !== ControlSchemes.NONE ? 'hidden' : '', 'print:block']">
                                {{ employee.next_lunch_break }}
                            </div>
                            <VueTimepicker v-if="controls !== ControlSchemes.NONE" class="print-hide"
                                           :id="`timepick-${String(employee.homebase_user_id)}-next_lunch_break`"
                                           v-model="employee.next_lunch_break" format="HH:mma" :minute-interval="5"
                                           :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                                           placeholder="None"
                                           @change="handleBreakChange($event, employee.homebase_user_id, 'next_lunch_break')"/>
                        </td>
                        <td class="border border-DEFAULT px-4 py-2"
                            :ref="el => setInputRef(`timepick-${String(employee.homebase_user_id)}-next_second_break`, el)">
                            <div :class="[controls !== ControlSchemes.NONE ? 'hidden' : '', 'print:block']">
                                {{ employee.next_second_break }}
                            </div>
                            <VueTimepicker v-if="controls !== ControlSchemes.NONE" class="print-hide"
                                           :id="`timepick-${String(employee.homebase_user_id)}-next_second_break`"
                                           v-model="employee.next_second_break" format="HH:mma" :minute-interval="5"
                                           :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                                           placeholder="None"
                                           @change="handleBreakChange($event, employee.homebase_user_id, 'next_second_break')"/>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div v-if="showOverwriteModal" class="fixed inset-0 z-50 flex items-center justify-center print:hidden">
        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="cancelOverwrite"></div>

        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700" @click="cancelOverwrite">
                <FontAwesomeIcon :icon="$fa.fas['xmark']" class="text-xl"/>
            </button>
            <div class="text-lg font-semibold mb-2">Recalculate?</div>
            <div class="text-sm text-gray-600 mb-6">
                Do you want to recalculate all assignments, overwriting any changes you have made, or leave new slots
                unassigned?
            </div>
            <div class="flex justify-end gap-3">
                <button class="rounded-xl px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50"
                        @click="applyPreset(true)">
                    Recalculate, lose changes
                </button>
                <button class="rounded-xl px-4 py-2 bg-crimson text-white hover:bg-red-700"
                        @click="applyPreset(false)">
                    Keep existing assignments, add manually
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.divider {
    border-right: 10px solid #9e1b32;
}

@media print {
    .print-hide {
        display: none !important;
    }
}
</style>
