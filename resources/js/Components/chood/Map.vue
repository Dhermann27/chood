<script setup>
import {ref} from "vue";
import axios from 'axios';

import DogCard from "@/Components/chood/DogCard.vue";
import AssignmentModal from "@/Components/chood/AssignmentModal.vue";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    statuses: Object,
    dogs: Object,
    services: Array,
    outhouseDogs: Array,
    admin: Number,
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
const cabins = ref(props.cabins); // Why?
const errorMessages = ref([]);
const showModal = ref(false);
const modalType = ref('add'); // 'add' or 'edit'
const isNewDog = ref(false);
const hoveredCabinId = ref(null);


const emit = defineEmits(['cabinClicked']);

const updateIsNewDog = (value) => {
    isNewDog.value = value;
};

function openModal(type, cabin) {
    modalType.value = type;
    assignment.value.cabin_id = cabin.id;
    if (type === 'edit') { // No need to nullcheck, there has to be at least one dog
        if (props.dogs[cabin.id][0].pet_id == null) {
            isNewDog.value = true;
            assignment.value.id = props.dogs[cabin.id][0].id;
            assignment.value.firstname = props.dogs[cabin.id][0].firstname;
            assignment.value.lastname = props.dogs[cabin.id][0].lastname;
            assignment.value.services = props.dogs[cabin.id][0].services;
        } else {
            isNewDog.value = false;
            assignment.value.dogs = props.dogs[cabin.id];
        }
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
        const method = modalType.value === 'edit' ? 'PUT' : 'POST';

        await axios({
            method: method,
            url: '/api/dog',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            data: {
                id: assignment.value.id,
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
                    id: assignment.value.id,
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

const cabinStyle = (cabin) => {
    const isHovered = hoveredCabinId.value === cabin.id && props.statuses[cabin.id];
    const borderColor = props.statuses?.[cabin.id]
        ? props.statuses[cabin.id] === 'deep'
            ? '#dd454f' // Red for deep cleaning
            : '#f4df7a' // Yellow for normal cleaning
        : '#373a36';   // Default gray if no status

    return {
        gridRow: `${cabin.rho} / span ${cabin.rowspan}`,
        gridColumn: cabin.kappa,
        borderColor: borderColor,
        color: isHovered ? '#fff' : '#373a36',
        backgroundColor: isHovered ? borderColor : '#fff',
        width: props.cardWidth + 'px',
        height: (props.cardHeight * cabin.rowspan) + 'px',
        transition: 'background-color 0.3s ease',
        cursor: props.admin > 0 && props.statuses?.[cabin.id] ? 'pointer' : 'auto',
    };
};

const handleHover = (cabinId) => {
    if (props.statuses?.[cabinId]) {
        hoveredCabinId.value = cabinId;
    }
};

const handleHoverLeave = () => {
    hoveredCabinId.value = null;
};

const handleClick = (cabin) => {
    emit('cabinClicked', cabin);
};

</script>
<template>
    <div
        v-for="cabin in cabins"
        :key="cabin.id"
        :class="['cabin', { 'cabin-empty': !props.dogs[cabin.id]}]"
        :style="cabinStyle(cabin)"
        @mouseover="handleHover(cabin.id)"
        @mouseleave="handleHoverLeave"
        @click="handleClick(cabin)"
    >
        <div v-if="props.dogs[cabin.id] && props.dogs[cabin.id].length > 0" class="h-full w-full relative">
            <DogCard :dogs="props.dogs[cabin.id]" :photoUri="photoUri" :maxlength="maxlength"
                     :short-name="cabin.short_name" :card-height="cardHeight"/>
            <div v-if="admin > 1 && props.dogs[cabin.id][0].is_inhouse === 0"
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
            <div v-if="admin > 1" @click="openModal( 'add', cabin) " class="cabin-icon">
                <button class="bg-blue-100 text-blue-500 hover:text-blue-700 p-1 rounded-r-md">
                    <font-awesome-icon :icon="['fas', 'add']"/>
                </button>
            </div>
        </div>
    </div>

    <AssignmentModal v-if="admin > 1 && showModal"
                     :modalType="modalType" :cabins="cabins" :outhouseDogs="outhouseDogs" :services="services"
                     :assignment="assignment" :errorMessages="errorMessages" :photoUri="photoUri" :is-new-dog="isNewDog"
                     @closeModal="showModal = false" @submitForm="submitForm" @updateIsNewDog="updateIsNewDog"/>
</template>
