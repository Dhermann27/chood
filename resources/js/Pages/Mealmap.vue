<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, ref} from "vue";
import DogCard from "@/Components/chood/DogCard.vue";
import {ControlSchemes} from "@/controlSchemes.js";
import {useMapPolling} from "@/Composables/useMapPolling.js";
import {useControlScheme} from "@/Composables/useControlScheme.js";
import YardRotationTable from "@/Components/chood/YardRotationTable.vue";
import BreakScheduleTable from "@/Components/chood/BreakScheduleTable.vue";

const props = defineProps({
    rotations: Array,
    yards: Array,
    yardPresets: Array,
    preset: String,
});

const {controls} = useControlScheme();
const breaks = ref({});
const lunchDogs = ref([]);
const medicatedDogs = ref([]);
const selectedYardPreset = ref(props.preset);
const employees = ref([]);
const fohStaff = ref('');
const assignments = ref({});
const headerYardIds = ref([]);
const openYardIdsByRotation = ref({});
const overscheduled = ref({});
const sectionCounts = ref({checkin_today: null, checkout_today: null});
const cardHeight = computed(() => Math.min(300, 800 / (lunchDogs.value.length + medicatedDogs.value.length)));

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


</script>


<template>
    <Head title="Mealmap"/>
    <div class="h-full print:h-auto w-full flex flex-col items-center justify-center">
        <div class="w-full grid grid-cols-2 print:grid-cols-1 gap-4 h-full print:h-auto relative">
            <div class="flex flex-col ps-3 items-center divider pt-5 print:hidden">
                <div class="text-3xl font-header mb-2">Medications</div>
                <div class="grid grid-cols-1 w-full">
                    <div v-for="(dog, index) in medicatedDogs" :key="index"
                         class="flex border-b-2 even:bg-greyhound/20">
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
                    <div v-for="(dog, index) in lunchDogs" :key="index" class="flex border-b-2 even:bg-greyhound/20">
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
                <YardRotationTable :rotations="props.rotations" :yards="props.yards" :header-yard-ids="headerYardIds"
                                   :open-yard-ids-by-rotation="openYardIdsByRotation" :assignments="assignments"
                                   :overscheduled="overscheduled" :employees="employees"
                                   :readonly="controls === ControlSchemes.NONE"
                                   :yard-presets="controls !== ControlSchemes.NONE ? props.yardPresets : null"
                                   :selected-preset="selectedYardPreset" :foh-staff="fohStaff" @saved="poll()"/>

                <BreakScheduleTable :employees="breaks" :readonly="controls === ControlSchemes.NONE" @saved="poll()"/>
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
