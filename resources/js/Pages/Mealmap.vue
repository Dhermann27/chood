<script setup>
import {Head} from '@inertiajs/vue3';
import {computed, onBeforeUnmount, onMounted, ref, watchEffect} from "vue";
import {formatTime} from "@/utils.js";
import DogCard from "@/Components/chood/DogCard.vue";
import {ControlSchemes} from "@/controlSchemes.js";
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';

const props = defineProps({
    dogsPerPage: Number,
    photoUri: String,
    employees: Array,
    rotations: Array,
});

const controls = ref(ControlSchemes.NONE);
const inputRefs = ref({});
const breaks = ref([]);
const dogs = ref([]);
const fohStaff = ref('');
const assignments = ref({});
const yards = ref([]);
const localChecksum = ref('');
let refreshInterval;
const currentViewIndex = ref(0);
const currentLoadingIndex = ref(0);
const cardHeight = computed(() => 800 / Math.min(Math.max(dogs.value.length, 3), props.dogsPerPage));

function setInputRef(key, el) {
    if (!inputRefs.value) inputRefs.value = {};
    inputRefs.value[key] = el;
}


const handleImageLoaded = () => {
    currentLoadingIndex.value++;
    for (; currentLoadingIndex.value < dogs.value.length; currentLoadingIndex.value++) {
        if (dogs.value[currentLoadingIndex.value].photoUri) {
            break;
        }
    }
};

async function updateData() {
    try {
        const response = await axios.get(`/api/mealmap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data.checksum) {
            breaks.value = {...response.data.breaks};
            dogs.value = response.data.dogs;
            fohStaff.value = response.data.fohStaff;
            assignments.value = {...response.data.assignments};
            yards.value = response.data.yards;
            localChecksum.value = response.data.checksum;
        } else if (dogs.value.length > props.dogsPerPage) {
            const maxChunks = Math.ceil(dogs.value.length / props.dogsPerPage);
            currentViewIndex.value = (currentViewIndex.value + 1) % maxChunks;
        }

    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

const isVisible = (index) => {
    const start = currentViewIndex.value * props.dogsPerPage;
    const end = start + props.dogsPerPage;
    return index >= start && index < end;
};

const progressBarStyle = computed(() => ({
    left: ((currentViewIndex.value * props.dogsPerPage) / dogs.value.length) * 100 + '%',
    width: (Math.min(props.dogsPerPage, dogs.value.length - currentViewIndex.value * props.dogsPerPage)
        / dogs.value.length) * 100 + '%',
    color: 'white',
}));

// Next three methods are all so Vue3 detects changes inside the nested objects
function matchEmployeeInGroups(employee) {
    for (const group of props.employees) {
        const match = group.employees.find(e => e.homebase_user_id === employee.homebase_user_id);
        if (match) return match;
    }
    return null;
}

function matchByHour() {
    for (const rotationId in assignments.value) {
        for (const yardId in assignments.value[rotationId]) {
            assignments.value[rotationId][yardId] = (assignments.value[rotationId][yardId] || []).map(employee => {
                if (!employee || !employee.homebase_user_id) return employee;
                return matchEmployeeInGroups(employee) ?? employee;
            });
        }
    }
}


watchEffect(() => {
    if (props.employees.length && Object.keys(assignments.value).length) matchByHour();
});

const handleYardChange = async (rotationId, yardId) => {
    const select = inputRefs.value[`multiselect-${rotationId}-${yardId}`];

    if (!Array.isArray(assignments.value[rotationId]?.[yardId])) {
        assignments.value[rotationId][yardId] = assignments.value[rotationId][yardId]
            ? [assignments.value[rotationId][yardId]] : [];
    }

    try {
        select.style.backgroundColor = 'gray';

        await axios.post('/api/mealmap/yard', {
            rotation_id: rotationId,
            yard_id: yardId,
            homebase_user_id: assignments.value[rotationId][yardId].map(e => e.homebase_user_id),
        });

        select.style.backgroundColor = 'green';
    } catch (error) {
        console.log('Error handling Yard Change', error);
        select.style.backgroundColor = 'red';
    }

    setTimeout(() => {
        select.style.backgroundColor = '';
    }, 5000);
};

const handleBreakChange = async (eventData, homebase_user_id, break_name) => {
    const select = inputRefs.value[`timepick-${homebase_user_id}-${break_name}`];
    try {
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
            <div class="flex flex-col ps-3 items-center divider pt-10 print:hidden">
                <div class="text-3xl mb-2">Dog Feeding Instructions</div>

                <div v-if="dogs && dogs?.length > props.dogsPerPage" class="flex justify-center gap-2 mb-4 w-full">
                    <div class="h-6 bg-gray-200 rounded-full w-3/4">
                        <div class="relative h-6 bg-blue-600 rounded-full text-center" :style="progressBarStyle">
                            {{ currentViewIndex * props.dogsPerPage + 1 }} - {{
                                Math.min((currentViewIndex + 1) * props.dogsPerPage, dogs?.length)
                            }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 w-full">
                    <div v-for="(dog, index) in dogs" :key="index" class="flex pb-2 border-b-2"
                         v-show="isVisible(index)">
                        <div class="flex-shrink-0" :style="{height: cardHeight + 'px', width: '150px'}">
                            <DogCard :dogs="[dog]" :photoUri="props.photoUri" :maxlength="20" :card-height="cardHeight"
                                     :shouldLoad="index === currentLoadingIndex"
                                     @imageLoaded="handleImageLoaded"/>
                        </div>

                        <div class="flex-grow flex flex-col items-start justify-center p-4 text-2xl">
                            <div v-for="feeding in dog.feedings" :key="feeding.id" class="flex-col justify-center">
                                <font-awesome-icon :icon="['fas', 'bowl-food']" class="me-2"/>
                                {{ feeding.type.trim() }}
                                <span v-if="feeding.type && feeding.description">: </span>
                                {{ feeding.description.trim() }}
                            </div>
                            <div v-for="medication in dog.medications" :key="medication.id"
                                 class="flex-col justify-center">
                                <font-awesome-icon v-if="medication.type_id === 18"
                                                   :icon="['fas', 'prescription-bottle-pill']" class="me-2"/>
                                <font-awesome-icon v-if="medication.type_id === 15" :icon="['fas', 'note-medical']"
                                                   class="me-2"/>
                                {{ medication.type.trim() }}
                                <span v-if="medication.type && medication.description">:&nbsp;</span>
                                {{ medication.description.trim() }}
                            </div>
                            <div v-for="allergy in dog.allergies" :key="allergy.id"
                                 class="flex-col justify-center text-red-700">
                                <font-awesome-icon :icon="['fas', 'hand-dots']" class="me-2"/>
                                ALLERGY: {{ allergy.description.trim() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="flex flex-col items-center pt-10 print:flex">
                <div class="text-3xl mb-2">Daily Rotation</div>
                <div v-if="fohStaff" class="text-base mb-2">{{ fohStaff }}</div>

                <table class="mx-5 bg-amber-100">
                    <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th v-for="yard in yards" :key="yard.id">{{ yard.name }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="rotation in props.rotations" :key="rotation.id">
                        <td class="border border-black px-4 py-2">{{ rotation.label }}</td>
                        <td v-for="yard in yards" :key="yard.id" class="border border-black px-4 py-2"
                            :ref="el => setInputRef(`multiselect-${rotation.id}-${yard.id}`, el)">
                            <div :class="[controls !== ControlSchemes.NONE ? 'hidden' : '', 'print:block']">
                                {{ (assignments[rotation.id]?.[yard.id] || []).map(e => e.first_name).join(', ') }}
                            </div>
                            <multiselect v-if="controls !== ControlSchemes.NONE" class="print-hide"
                                         :key="`multiselect-${rotation.id}-${yard.id}`"
                                         :id="`multiselect-${rotation.id}-${yard.id}`"
                                         v-model="assignments[rotation.id][yard.id]" :options="employees"
                                         group-label="status" group-values="employees" :group-select="true"
                                         label="first_name" track-by="homebase_user_id" :searchable="true"
                                         :clearable="true" placeholder="Unassigned" :multiple="yard.id === 999"
                                         @select="() => handleYardChange(rotation.id, yard.id)"
                                         @remove="() => handleYardChange(rotation.id, yard.id)">
                            </multiselect>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <table class="mx-5 bg-blue-200 m-10">
                    <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <th>First Break</th>
                        <th>Lunch</th>
                        <th>Second Break</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="employee in breaks">
                        <td class="border border-black px-4 py-2">{{ employee.first_name }}
                            <template v-if="employee.shift_start && employee.shift_end">
                                ({{ formatTime(employee.shift_start) }}-{{ formatTime(employee.shift_end) }})
                            </template>
                        </td>
                        <td class="border border-black px-4 py-2"
                            :ref="el => setInputRef(`timepick-${String(employee.homebase_user_id)}-next_first_break`, el)">
                            <div
                                :class="[controls !== ControlSchemes.NONE  && employee.first_name !== 'Everyone' ? 'hidden' : '', 'print:block']">
                                {{ employee.next_first_break }}
                            </div>
                            <VueTimepicker v-if="controls !== ControlSchemes.NONE && employee.first_name !== 'Everyone'"
                                           :id="`timepick-${String(employee.homebase_user_id)}-next_first_break`"
                                           v-model="employee.next_first_break" format="HH:mma" :minute-interval="5"
                                           :hour-range="[[1, 12]]" hide-disabled-items lazy manual-input
                                           placeholder="None" class="print-hide"
                                           @change="handleBreakChange($event, employee.homebase_user_id, 'next_first_break')"/>
                        </td>
                        <td class="border border-black px-4 py-2"
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
                        <td class="border border-black px-4 py-2"
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
