<script setup>
import {Head} from '@inertiajs/vue3';
import {onMounted, ref} from 'vue'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Map from "@/Components/chood/Map.vue";
import Multiselect from "vue-multiselect";
import {ControlSchemes} from "@/controlSchemes.js";

const props = defineProps({
    cabins: Array,
    dogs: Object,
    employees: Array,
    statuses: Object,
    photoUri: String,
});
const dogs = ref(props.dogs);
const employees = ref(props.employees);
const statuses = ref(props.statuses);
const statusMessage = ref(null);
const statusClass = ref('text-gray-600');
const homebaseId = ref(null);
const todo = ref(null);
const targets = ref({'dogsToAssign': [], 'cabin_id': 0, 'cabin_short_name': ''});
const step = ref(1);
const localChecksum = ref('');
const frequency = 10000;
let counter = 0;
let refreshInterval;

async function updateData() {
    const response = await axios.get(`/task/data/` + localChecksum.value);

    if (response.data && localChecksum.value !== response.data?.checksum) {
        dogs.value = response.data.dogs;
        employees.value = response.data.employees;
        statuses.value = response.data.statuses;
        localChecksum.value = response.data.checksum;
    }

    if (step.value !== 1 && counter++ > 2) {
        statusMessage.value = null;
        step.value = 1;
        counter = 0;
    }
    clearInterval(refreshInterval);
    refreshInterval = setInterval(updateData, frequency);
}

const prevStep = () => {
    statusMessage.value = null;
    counter = 0;
    if (step.value > 1) step.value--
}

const nextStep = () => {
    statusMessage.value = null;
    counter = 0;
    if (step.value < 4) step.value++
}

const handleEmployeeClick = (employee) => {
    homebaseId.value = employee.homebase_user_id;
    nextStep();
}

const handleTaskClick = (thisTodo) => {
    todo.value = thisTodo;
    nextStep();
}

const handleTargetClick = (cabin) => {
    if (todo.value === 'assignCabin') {
        targets.value = {
            ...targets.value, // Preserve existing properties
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name
        };
        if (targets.value['dogsToAssign'].length > 0) nextStep();
    } else if (todo.value === 'cleanCabin' && statuses.value.hasOwnProperty(cabin.id)) {
        targets.value = {
            homebase_user_id: homebaseId.value,
            cabin_id: cabin.id,
            cabin_short_name: cabin.short_name,
        };
        nextStep();
    }
};

const handleAssignDogUpdate = () => {
    if (targets.value['dogsToAssign'].length > 0 && targets.value['cabin_id'] > 0) nextStep();
};


const handleFinishAction = async (action) => {
    if (action === 'Done' || action === 'More') {
        axios({
            method: 'POST',
            url: `/task/${todo.value}`,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: targets.value,
        }).then((response) => {
            localChecksum.value = '';
            clearInterval(refreshInterval);
            updateData();
            refreshInterval = setInterval(updateData, frequency);

            statusMessage.value = response.data?.message;
            statusClass.value = 'text-green-500';
        }).catch((error) => {
            if (error.response && error.response.status === 419) {
                if (confirm('Your session has expired due to inactivity. Would you like to reload the page?')) {
                    window.location.reload();
                }
            } else {
                statusMessage.value = `Error: ${error.response?.data?.message || 'Unable to complete action'}`;
                statusClass.value = 'text-red-500';
            }
        });

        statusMessage.value = `Processing ${action} action...`;
        statusClass.value = 'text-gray-500';

    }
    targets.value = {'dogsToAssign': [], 'cabin_id': 0, 'cabin_short_name': ''};
    counter = 0;
    step.value = action === 'Done' ? 1 : 3;
}

onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, frequency);
});
</script>

<template>
    <Head title="Task Entry"/>
    <div class="flex flex-col items-center h-screen p-4">
        <template v-if="step === 1">
            <h1 class="text-lg font-semibold mb-4">Hi! Huaryoo?</h1>
            <div class="grid grid-cols-4 gap-4 w-full h-full overflow-y-auto">
                <button
                    v-for="employee in employees"
                    :key="employee.id"
                    class="bg-blue-500 text-white text-3xl py-4 px-6 rounded-2xl flex flex-col items-center justify-center w-full"
                    @click="handleEmployeeClick(employee)">

                    <img
                        :src="`/images/staff/${employee.homebase_user_id}.png`"
                        :alt="employee.first_name"
                        class="w-[80%] h-[80%] rounded-full object-cover mb-4"
                    />
                    <span>{{ employee.first_name }}</span>
                </button>
            </div>
        </template>

        <template v-else-if="step === 2">
            <h1 class="text-lg font-semibold mb-4">So, watchadoin?</h1>
            <div class="grid grid-cols-3 gap-4 w-[75vw] h-[75vh]">
                <button
                    class="bg-blue-500 text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('assignCabin')">
                    <font-awesome-icon :icon="['fas', 'house-circle-check']" class="me-5"/>
                    Assigning a Cabin
                </button>
                <button
                    class="bg-blue-500 text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('cleanCabin')">
                    <font-awesome-icon :icon="['fas', 'broom']" class="me-5"/>
                    Cleaned a Cabin
                </button>
            </div>
            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 3">
            <h1 class="text-lg font-semibold mb-4">Cool! Which one?</h1>

            <template v-if="todo === 'assignCabin'">
                <multiselect
                    class="!w-1/2 dogsToAssign-multiselect mb-5 border-2 border-red-500 bg-red-100 focus:ring focus:ring-red-300 placeholder:text-red-700"
                    v-model="targets.dogsToAssign" multiple
                    :options="dogs['unassigned']"
                    label="firstname"
                    placeholder="Select Dog(s) (Required)"
                    @update:modelValue="handleAssignDogUpdate">
                    <template #option="{ option }">
                        <div class="dog-option-item">
                            <img v-if="option.photoUri" :src="props.photoUri + option.photoUri"
                                 :alt="'Picture of ' + option.firstname" class="dog-photo"/>
                            <span class="ml-10">{{ option.firstname }}</span>
                        </div>
                    </template>
                </multiselect>
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="dogs" :controls="ControlSchemes.SELECT_CABIN"
                         :card-width="46" :card-height="55" :photoUri="photoUri" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <template v-else-if="todo === 'cleanCabin'">
                <div class="choodmap items-center justify-center p-1">
                    <Map :cabins="cabins" :statuses="statuses" :dogs="[]" :controls="ControlSchemes.SELECT_CABIN"
                         :card-width="46" :card-height="57" @cabinClicked="handleTargetClick"/>
                </div>
            </template>
            <button class="px-16 py-6 text-2xl bg-gray-500 text-white mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 4">
            <div class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
                <div class="bg-white p-6 rounded-lg w-2/3">
                    <h3 class="text-2xl font-semibold mb-4 text-center">
                        <template v-if="todo === 'assignCabin'">
                            {{ targets.dogsToAssign.map(dog => dog.firstname).join(', ') }}
                            in Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                        <template v-else-if="todo === 'cleanCabin'">
                            Cabin {{ targets.cabin_short_name }}, right?
                        </template>
                    </h3>
                    <div class="flex justify-between mb-4 text-3xl">
                        <button
                            @click="handleFinishAction('Done')"
                            class="px-16 py-10 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'badge-check']"/>
                            <span>Done</span>
                        </button>
                        <button
                            @click="handleFinishAction('Undo')"
                            class="px-16 py-10 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'rotate-left']"/>
                            <span>Undo</span>
                        </button>
                        <button
                            @click="handleFinishAction('More')"
                            class="px-16 py-10 bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'cowbell-circle-plus']"/>
                            <span>More</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="statusMessage" class="text-3xl mt-4 text-center" :class="statusClass">
            {{ statusMessage }}
        </div>
    </div>
    <i class="cabin cabin-empty"></i>
</template>

<style>
.choodmap {
    display: grid;
    text-align: center;
    grid-template-columns: 1fr repeat(8, 20px 1fr 1fr) 20px 1fr;
    grid-template-rows: repeat(4, 1fr) 20px repeat(5, 1fr);
}

.cabin {
    border-width: 5px;
}

.cabin-empty {
    font-size: 22px;
}
</style>
<style scoped>
.dog-photo {
    width: 250px;
    height: 50px;
    max-width: 250px; /* Prevent image from exceeding 200px width */
    max-height: 50px; /* Prevent image from exceeding 50px height */
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 5px;
    flex-shrink: 0; /* Prevent the image from shrinking */
}

.dog-option-item {
    display: flex;
    align-items: center; /* Vertically align text with image */
}
</style>
