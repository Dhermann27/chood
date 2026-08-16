<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onMounted, ref} from "vue";
import DogCard from "@/Components/chood/DogCard.vue";
import {ControlSchemes} from "@/controlSchemes.js";
import {useMapPolling} from "../Composables/useMapPolling.js";
import YardRotationTable from "@/Components/chood/YardRotationTable.vue";
import BreakScheduleTable from "@/Components/chood/BreakScheduleTable.vue";

const props = defineProps({
    rotations: Array,
    yards: Array,
    yardPresets: Array,
    preset: String,
});

const controls = ref(ControlSchemes.NONE);
const showOverwriteModal = ref(false);
const pendingConfirmAction = ref(null);
const breaks = ref({});
const lunchDogs = ref([]);
const medicatedDogs = ref([]);
const selectedYardPreset = ref(props.preset);
const isUpdatingPreset = ref(false);
const employees = ref([]);
const fohStaff = ref('');
const assignments = ref({});
const headerYardIds = ref([]);
const openYardIdsByRotation = ref({});
const shiftsRefreshing = ref(false);
const overscheduled = ref({});
const sectionCounts = ref({checkin_today: null, checkout_today: null});
const cardHeight = computed(() => Math.min(300, 800 / (lunchDogs.value.length + medicatedDogs.value.length)));
const openYards = computed(() => {
    const ids = (headerYardIds.value ?? []).map(Number);
    if (!ids.length) return [];
    return (props.yards ?? []).filter(e => ids.includes(Number(e.id)));
});

function mergedMedications(medications) {
    const map = new Map();
    for (const med of medications) {
        const key = [med.type, med.quantity, med.unit, med.description].join('|');
        if (map.has(key)) {
            map.get(key).timeslotNames.push(med.timeslot?.name);
        } else {
            map.set(key, {...med, timeslotNames: [med.timeslot?.name]});
        }
    }
    return Array.from(map.values()).map(med => ({
        ...med,
        timeslotLabel: med.timeslotNames.filter(Boolean).join('/'),
    }));
}

const {poll} = useMapPolling('/api/mealmap/', 15000, (data) => {
    assignments.value = {...data.assignments};
    breaks.value = {...data.breaks};
    lunchDogs.value = data.lunchDogs;
    medicatedDogs.value = data.medicatedDogs;
    employees.value = data.employees;
    fohStaff.value = data.fohStaff;
    selectedYardPreset.value = data.preset;
    headerYardIds.value = data.headerYards;
    openYardIdsByRotation.value = data.openYardsByRotation;
    overscheduled.value = data.overscheduled ?? {};
    sectionCounts.value = data.sectionCounts ?? sectionCounts.value;
});

function onYardPresetChange(e) {
    const preset = e.target.value;
    const previous = selectedYardPreset.value;
    e.target.value = previous;
    selectedYardPreset.value = previous;
    pendingConfirmAction.value = async (overwrite) => {
        isUpdatingPreset.value = true;
        try {
            await axios.post('/api/mealmap/markActive', {preset, overwrite});
            selectedYardPreset.value = preset;
            await poll();
        } finally {
            isUpdatingPreset.value = false;
        }
    };
    showOverwriteModal.value = true;
}

function cancelOverwrite() {
    pendingConfirmAction.value = null;
    showOverwriteModal.value = false;
}

async function confirmOverwrite(overwrite) {
    showOverwriteModal.value = false;
    await pendingConfirmAction.value?.(overwrite);
    pendingConfirmAction.value = null;
}

function onRefreshShiftsClick() {
    pendingConfirmAction.value = async (recalculate) => {
        shiftsRefreshing.value = true;
        try {
            await axios.post('/api/mealmap/refreshShifts', {recalculate});
        } finally {
            shiftsRefreshing.value = false;
        }
    };
    showOverwriteModal.value = true;
}

onMounted(() => {
    if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
        controls.value = !navigator.userAgent.includes('Linux') ? ControlSchemes.MODAL : ControlSchemes.NONE;
    }
});
</script>


<template>
    <Head title="Mealmap"/>
    <div class="h-full print:h-auto w-full flex flex-col items-center justify-center">
        <div class="w-full grid grid-cols-2 print:grid-cols-1 gap-4 h-full print:h-auto relative">
            <div class="flex flex-col ps-3 items-center divider pt-5 print:hidden">
                <div class="text-3xl font-header mb-2">Medications</div>
                <div class="grid grid-cols-1 w-full">
                    <div v-for="(dog, index) in medicatedDogs" :key="index" class="flex border-b-2 even:bg-gray-200">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: cardHeight + 'px'}">
                            <DogCard :dogs="[dog]" :maxlength="20" :card-height="cardHeight"/>
                        </div>

                        <div class="flex-grow flex flex-col items-start justify-center p-1 text-xl">
                            <div v-for="medication in mergedMedications(dog.medications)" :key="medication.id"
                                 class="flex-col justify-center">
                                <FontAwesomeIcon v-if="medication.medication_id"
                                                 :icon="['fas', 'prescription-bottle-pill']" class="me-1"/>
                                <FontAwesomeIcon v-else :icon="['fas', 'stethoscope']" class="me-1"/>
                                {{ medication.timeslotLabel }}
                                {{ medication.type?.trim() }}
                                <span v-if="medication.quantity || medication.unit">
                                    — {{ medication.quantity }} {{ medication.unit }}
                                </span>
                                <span v-if="medication.type && medication.description">:&nbsp;</span>
                                {{ medication.description?.trim() }}
                            </div>
                            <div v-for="allergy in dog.allergies" :key="allergy.id"
                                 class="flex-col justify-center text-crimson">
                                <FontAwesomeIcon :icon="['fas', 'hand-dots']" class="me-1"/>
                                ALLERGY: {{ allergy.description?.trim() }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-3xl font-header my-2">Lunches</div>
                <div class="grid grid-cols-1 w-full">
                    <div v-for="(dog, index) in lunchDogs" :key="index" class="flex border-b-2 even:bg-gray-200">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: cardHeight + 'px'}">
                            <DogCard :dogs="[dog]" :maxlength="20" :card-height="cardHeight"/>
                        </div>

                        <div class="flex-grow flex items-center gap-3 p-1 text-xl min-w-0">
                            <FontAwesomeIcon :icon="['fas', 'turkey']" class="flex-shrink-0 me-1"/>
                            {{ dog.lunch_notes }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mealmap-right flex flex-col items-center pt-5 print:flex relative">
                <YardRotationTable
                    :rotations="props.rotations"
                    :yards="props.yards"
                    :header-yard-ids="headerYardIds"
                    :open-yard-ids-by-rotation="openYardIdsByRotation"
                    :assignments="assignments"
                    :overscheduled="overscheduled"
                    :employees="employees"
                    :readonly="controls === ControlSchemes.NONE"
                    :yard-presets="controls !== ControlSchemes.NONE ? props.yardPresets : null"
                    :selected-preset="selectedYardPreset"
                    :is-updating-preset="isUpdatingPreset"
                    :foh-staff="fohStaff"
                    @saved="poll()"
                    @preset-change="onYardPresetChange"/>

                <BreakScheduleTable
                    :employees="breaks"
                    :readonly="controls === ControlSchemes.NONE"
                    :shifts-refreshing="shiftsRefreshing"
                    @saved="poll()"
                    @refresh-shifts="onRefreshShiftsClick"/>
            </div>

            <div v-if="sectionCounts.in_house != null"
                 class="absolute left-1/2 -translate-x-1/2 z-10 bg-crimson text-white font-bold flex items-center justify-center print:hidden"
                 :style="{ width: Math.max(150, cardHeight) + 'px', height: Math.max(150, cardHeight) + 'px' }">
                <span :style="{ fontSize: (Math.max(150, cardHeight) * 0.5) + 'px' }">
                    {{ sectionCounts.in_house }}
                </span>
                <span v-if="sectionCounts.checkin_today !== null"
                      class="absolute left-0 right-0 flex items-center justify-center gap-1 leading-none"
                      :style="{ fontSize: (Math.max(150, cardHeight) * 0.18) + 'px', top: '5px' }">
                    {{ sectionCounts.checkin_today }}
                    <FontAwesomeIcon :icon="['fas', 'left-right']"/>
                    {{ sectionCounts.checkout_today }}
                </span>
            </div>

        </div>
    </div>
    <div v-if="showOverwriteModal" class="fixed inset-0 z-50 flex items-center justify-center print:hidden">
        <!-- backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="cancelOverwrite"></div>

        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700" @click="cancelOverwrite">
                <FontAwesomeIcon :icon="['fas', 'xmark']" class="text-xl"/>
            </button>
            <div class="text-lg font-semibold mb-2">Recalculate?</div>
            <div class="text-sm text-gray-600 mb-6">
                Chood can recalculate yard rotation and breaks if needed. This process will overwrite any changes you
                have made today.
            </div>
            <div class="flex justify-end gap-3">
                <button
                    class="rounded-xl px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-sm leading-tight w-48"
                    @click="confirmOverwrite(false)">
                    Do not recalculate<br>Assign manually
                </button>
                <button class="rounded-xl px-4 py-2 bg-crimson text-white hover:bg-red-700 text-sm leading-tight w-48"
                        @click="confirmOverwrite(true)">
                    <FontAwesomeIcon :icon="['fas', 'triangle-exclamation']"
                                     class="text-yellow-400 float-left text-2xl mr-2"/>
                    Recalculate<br>Lose assignments
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
    @page {
        margin: 0.5cm;
    }

    .print-hide,
    :deep(.print-hide) {
        display: none !important;
    }

    .mealmap-right {
        font-size: 82%;
        padding-top: 0;
        width: 100%;
    }

    .mealmap-right :deep(td),
    .mealmap-right :deep(th) {
        padding: 2px 8px;
    }

    .mealmap-right :deep(.m-10) {
        margin: 4px 0;
    }

    .mealmap-right :deep(.text-3xl) {
        font-size: 1.4em;
        margin-bottom: 2px;
    }

    .mealmap-right :deep(.mb-2) {
        margin-bottom: 2px;
    }

    .mealmap-right :deep(.absolute) {
        top: 0;
        right: 0;
    }
}
</style>
