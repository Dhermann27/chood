<script setup>
import {onMounted, ref} from 'vue'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";
import Map from "@/Components/chood/Map.vue";

const props = defineProps({
    cabins: Array,
    statuses: Object,
    employees: Array,
});
const employees = ref(props.employees);
const statuses = ref(props.statuses);
const statusMessage = ref(null);
const statusClass = ref('text-gray-600');
const homebaseId = ref(null);
const targetId = ref(null);
const step = ref(1);
const localChecksum = ref('');
const frequency = 10000;
let counter = 0;
let refreshInterval;

async function updateData() {
    const response = await axios.get(`/task/data/` + localChecksum.value);

    if (response.data && localChecksum.value !== response.data?.checksum) {
        statuses.value = response.data.statuses;
        employees.value = response.data.employees;
        localChecksum.value = response.data.checksum;
    }

    if(step.value !== 1 && counter++ > 2) {
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
    homebaseId.value = employee.homebase_id;
    nextStep();
}

const handleTaskClick = (task) => {
    // console.log(`Selected task: ${task}`);
    nextStep();
}

const handleCabinClick = (cabin) => {
    if(statuses.value.hasOwnProperty(cabin.id)) {
        targetId.value = cabin.id;
        nextStep();
    }
};

const handleFinishAction = async (action) => {
    if (action === 'Done' || action === 'More') {
        axios({
            method: 'POST',
            url: `/task/cleaned`,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: {
                'homebase_id': homebaseId.value,
                'cabin_id': targetId.value
            }
        }).then((response) => {
            localChecksum.value = '';
            updateData();

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
    targetId.value = null;
    counter = 0;
    step.value = action === 'Done' ? 1 : 3;
}

onMounted(() => {
    refreshInterval = setInterval(updateData, frequency);
});

</script>

<template>
    <div class="flex flex-col items-center h-screen p-4">
        <template v-if="step === 1">
            <h1 class="text-lg font-semibold mb-4">Hi! Huaryoo?</h1>
            <div class="grid grid-cols-4 gap-4 w-[75vw] h-[75vh]">
                <button
                    v-for="employee in employees"
                    :key="employee.id"
                    class="bg-blue-500 text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleEmployeeClick(employee)">
                    {{ employee.first_name }}
                </button>
            </div>
        </template>

        <template v-else-if="step === 2">
            <h1 class="text-lg font-semibold mb-4">So, watchadoin?</h1>
            <div class="grid grid-cols-3 gap-4 w-[75vw] h-[75vh]">
                <button
                    class="bg-blue-500 text-white text-3xl py-4 px-6 rounded-2xl flex items-center justify-center"
                    @click="handleTaskClick('cabin')">
                    <font-awesome-icon :icon="['fas', 'broom']"/>
                    Cleaned a Cabin
                </button>
            </div>
            <button class="bg-gray-500 text-white py-2 px-4 mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 3">
            <h1 class="text-lg font-semibold mb-4">Cool! Which one?</h1>
            <div class="choodmap items-center justify-center p-1">
                <Map :cabins="cabins" :statuses="statuses" :dogs="[]" :admin="1" :card-width="46" :card-height="57"
                     @cabinClicked="handleCabinClick"/>
            </div>
            <button class="bg-gray-500 text-white py-2 px-4 mt-4" @click="prevStep">Back</button>
        </template>

        <template v-else-if="step === 4">
            <div class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
                <div class="bg-white p-6 rounded-lg w-96">
                    <h3 class="text-lg font-semibold mb-4">Nice job! What next?</h3>
                    <div class="flex justify-between mb-4">
                        <button
                            @click="handleFinishAction('Done')"
                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'badge-check']"/>
                            <span>Done</span>
                        </button>
                        <button
                            @click="handleFinishAction('Undo')"
                            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'rotate-left']"/>
                            <span>Undo</span>
                        </button>
                        <button
                            @click="handleFinishAction('More')"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center space-x-2"
                        >
                            <font-awesome-icon :icon="['fas', 'cowbell-circle-plus']"/>
                            <span>More</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="statusMessage" class="mt-4 text-center" :class="statusClass">
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
