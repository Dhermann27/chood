<script setup>
import {ref, onMounted} from "vue";
import axios from 'axios';

import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import DogCard from "@/Components/chood/DogCard.vue";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    statuses: Object,
    dogs: Object,
    services: Array,
    outhouseDogs: Array,
    maxlength: Number,
    cardWidth: Number,
    cardHeight: Number,
});

const assignment = ref({
    id: null,
    firstname: '',
    lastname: '',
    dogs: [],
    cabin_id: null,
    services: []
});
const cabins = ref(props.cabins);
const errorMessages = ref([]);
const showModal = ref(false);
const modalType = ref('add'); // 'add' or 'edit'
const isBrowser = ref(false);

function openModal(type, cabin) {
    modalType.value = type;
    assignment.value.cabin_id = cabin.id;
    if (type === 'edit') {
        assignment.value.id = props.dogs[cabin.id][0].id;
        assignment.value.firstname = props.dogs[cabin.id][0].firstname;
        assignment.value.lastname = props.dogs[cabin.id][0].lastname;
        assignment.value.dogs = props.dogs[cabin.id];
        assignment.value.services = props.dogs[cabin.id][0].services;
    }
    errorMessages.value = [];
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    assignment.value = {id: null, firstname: '', lastname: '', dogs: [], cabin_id: null, services: []};
}

async function submitForm() {
    try {
        const url = modalType.value === 'edit' ? `/api/dog/${assignment.value.id}` : '/api/dog';
        const method = modalType.value === 'edit' ? 'PUT' : 'POST';

        await axios({
            method: method,
            url: url,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: {
                firstname: assignment.value.firstname,
                lastname: assignment.value.lastname,
                dogs: assignment.value.dogs,
                cabin_id: assignment.value.cabin_id,
                service_ids: assignment.value.services,
            },
        });

        assignment.value = {id: null, firstname: '', lastname: '', dogs: [], cabin_id: null, services: []};
        closeModal();
    } catch (error) {
        if (error.response && error.response.status === 419) {
            if (confirm('Your session has expired due to inactivity. Would you like to reload the page?')) {
                window.location.reload();
            }
        } else if (error.response && error.response.data.errors) {
            errorMessages.value = Object.values(error.response.data.errors).flat();
        } else {
            console.error('Error saving assignment:', error);
            errorMessages.value = ['Error saving the assignment.'];
        }
    }
}

async function handleDelete(dogs) {
    try {
        if (confirm('Are you sure you want to delete this assignment?')) {
            await axios({
                method: 'DELETE',
                url: '/api/dog',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                data: {
                    dogs: dogs,
                },
            });

        }
    } catch (error) {
        if (error.response && error.response.data.errors) {
            errorMessages.value = Object.values(error.response.data.errors).flat();
        } else {
            console.error('Error saving assignment:', error);
            errorMessages.value = ['Error saving the assignment.'];
        }
    }
}

onMounted(() => {
    if (typeof window !== 'undefined' && typeof navigator !== 'undefined') {
        isBrowser.value = !navigator.userAgent.includes('Linux');
    }
});

const cabinStyle = (cabin) => {
    return {
        gridRow: `${cabin.rho} / span ${cabin.rowspan}`,
        gridColumn: cabin.kappa,
        borderColor: props.statuses?.[cabin.id]
            ? props.statuses[cabin.id] === 'deep'
                ? '#dd454f'
                : '#f4df7a'
            : '#373a36',
        width: props.cardWidth + 'px',
        height: (props.cardHeight * cabin.rowspan) + 'px'
    };
};
</script>
<template>
    <div
        v-for="cabin in cabins"
        :key="cabin.id"
        :class="['cabin', { 'cabin-empty': !props.dogs[cabin.id]}]"
        :style="cabinStyle(cabin)"
    >
        <div v-if="props.dogs[cabin.id] && props.dogs[cabin.id].length > 0" class="h-full w-full relative">
            <DogCard :dogs="props.dogs[cabin.id]" :photoUri="photoUri" :maxlength="maxlength"
                     :card-height="cardHeight"/>
            <div v-if="isBrowser && props.dogs[cabin.id][0].is_inhouse === 0"
                 class="absolute inset-y-0 left-0 flex flex-col justify-center py-1">
                <button
                    @click="openModal( 'edit', cabin)"
                    class="bg-blue-100 text-blue-500 hover:bg-blue-200 hover:text-blue-700 p-1 rounded-r-md"
                >
                    <font-awesome-icon :icon="['fas', 'edit']"/>
                </button>
                <button
                    @click="handleDelete(props.dogs[cabin.id])"
                    class="bg-blue-100 text-red-500 hover:text-red-700 p-1 rounded-r-md"
                >
                    <font-awesome-icon :icon="['fas', 'trash']"/>
                </button>
            </div>
        </div>
        <div v-else>
            {{ cabin.cabinName }}
            <div v-if="isBrowser" @click="openModal( 'add', cabin) " class="cabin-icon">
                <button class="bg-blue-100 text-blue-500 hover:text-blue-700 p-1 rounded-r-md">
                    <font-awesome-icon :icon="['fas', 'add']"/>
                </button>
            </div>
        </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h3 class="text-lg font-semibold mb-4">
                {{ (modalType === 'add' ? 'Add ' : 'Edit ') }} Cabin Assignment
            </h3>

            <form @submit.prevent="submitForm">
                <div class="mb-4">
                    <label for="cabin-select">Select Cabin</label>
                    <select id="cabin-select" v-model="assignment.cabin_id" required
                            class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2">
                        <option disabled value="">Please select a cabin</option>
                        <option v-for="cabin in cabins" :key="cabin.id" :value="cabin.id">
                            {{ cabin.cabinName }}
                        </option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="name" class="block text-xs font-medium text-gray-700">Dog Name (ignored if dog
                        selected)</label>
                    <input
                        v-model="assignment.firstname"
                        id="firstname"
                        type="text"
                        class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2"
                    />
                </div>

                <div class="mb-4">
                    <label for="name" class="block text-xs font-medium text-gray-700">Family Name (ignored if dog
                        selected)</label>
                    <input
                        v-model="assignment.lastname"
                        id="lastname"
                        type="text"
                        class="mt-1 block w-full text-sm border border-gray-300 rounded-md p-2"
                    />
                </div>

                <!-- Dog Selection -->
                <div class="mb-4">
                    <multiselect
                        v-model="assignment.dogs"
                        :options="props.outhouseDogs"
                        multiple
                        label="firstname"
                        :searchable="true"
                        :clearable="true"
                        placeholder="Select Dog(s)"
                    >
                        <template #option="props">
                            <img v-if="props.option.photoUri" :src="photoUri + props.option.photoUri"
                                 :alt="'Picture of' + props.option.firstname" class="dog-photo"/>
                            {{ props.option.firstname }}
                        </template>
                    </multiselect>
                </div>

                <!-- Services (Multiselect) -->
                <div class="mb-4">
                    <label for="service_ids" class="block text-xs font-medium text-gray-700">Services</label>
                    <Multiselect
                        v-model="assignment.services"
                        :options="props.services"
                        multiple
                        track-by="id"
                        label="name"
                        placeholder="Select services"
                        class="w-full text-sm"
                    />
                </div>

                <div v-if="errorMessages.length > 0"
                     class="p-4 mb-4 bg-red-100 border border-red-500 text-red-700 rounded">
                    <div v-for="message in errorMessages" :key="message" class="font-semibold">
                        {{ message }}
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" @click="closeModal"
                            class="px-4 py-2 bg-gray-400 text-white rounded-md text-xs hover:bg-gray-500">Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700">
                        {{ modalType === 'add' ? 'Add Assignment' : 'Update Assignment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.dog-photo {
    width: 30px;
    height: 30px;
    margin: 0 5px 5px 0;
    border-radius: 10%;
}
</style>
