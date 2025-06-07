<script setup>
import {computed, ref, watch} from "vue";
import axios from 'axios';

import DogCard from "@/Components/chood/DogCard.vue";
import AssignmentModal from "@/Components/chood/AssignmentModal.vue";
import {ControlSchemes} from "@/controlSchemes.js";

const props = defineProps({
    photoUri: String,
    cabins: Array,
    statuses: Object,
    dogs: Object,
    controls: ControlSchemes,
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
});
const cabins = ref(props.cabins);
const errorMessages = ref([]);
const showModal = ref(false);
const modalType = ref('add'); // 'add' or 'edit'
const isNewDog = ref(false);
const hoveredCabinId = ref(null);
const currentLoadingIndex = ref(0);

const emit = defineEmits(['cabinClicked']);

const cabinKeys = computed(() => {
    return Object.keys(props.dogs).filter(cabinId => cabinId !== 'unassigned' && props.dogs[cabinId].length > 0);
});
const getCurrentCabinKey = () => {
    if (cabinKeys.value && currentLoadingIndex.value <= cabinKeys.value.length) {
        return parseInt(cabinKeys.value[currentLoadingIndex.value]);
    } else {
        if (!cabinKeys.value) {
            console.warn('cabinKeys.value false');
        } else {
            console.warn('Index out of range', cabinKeys.value.length, currentLoadingIndex.value);
        }
        return null;
    }
};

const handleImageLoaded = () => {
    currentLoadingIndex.value++;
    for (; currentLoadingIndex.value < cabinKeys.value.length; currentLoadingIndex.value++) {
        if (props.dogs[cabinKeys.value[currentLoadingIndex.value]]?.some(dog => dog.photoUri)) {
            break;
        }
    }
}

watch(() => props.dogs, () => {
    currentLoadingIndex.value = 0;
});

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
    assignment.value = {id: null, firstname: '', lastname: '', dogs: [], cabin_id: null};
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
            },
        });

        assignment.value = {id: null, firstname: '', lastname: '', dogs: [], cabin_id: null};
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
        cursor: props.controls !== ControlSchemes.NONE && props.statuses?.[cabin.id] ? 'pointer' : 'auto',
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
        v-for="cabin in cabins" :key="cabin.id" :class="['cabin', { 'cabin-empty': !props.dogs[cabin.id]}]"
        :style="cabinStyle(cabin)"
        @mouseover="handleHover(cabin.id)" @mouseleave="handleHoverLeave" @click="handleClick(cabin)"
    >
        <div v-if="props.dogs[cabin.id] && props.dogs[cabin.id].length > 0" class="h-full w-full relative">
            <DogCard :dogs="props.dogs[cabin.id]" :photoUri="photoUri" :maxlength="maxlength" :card-height="cardHeight"
                     :shouldLoad="cabin.id === getCurrentCabinKey()"
                     @imageLoaded="handleImageLoaded"/>
            <div v-if="controls === ControlSchemes.MODAL && props.dogs[cabin.id][0].is_inhouse === 0"
                 class="absolute inset-y-0 left-0 flex flex-col justify-center">
                <button
                    @click="openModal( 'edit', cabin)"
                    class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md"
                >
                    <font-awesome-icon :icon="['fas', 'edit']"/>
                </button>
                <button @click="handleDelete(props.dogs[cabin.id])"
                    class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md">
                    <font-awesome-icon :icon="['fas', 'trash']"/>
                </button>
            </div>
        </div>
        <div v-else>
            {{ cabin.short_name }}
            <div v-if="controls === ControlSchemes.MODAL" @click="openModal( 'add', cabin) " class="cabin-icon">
                <button class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md">
                    <font-awesome-icon :icon="['fas', 'add']"/>
                </button>
            </div>
        </div>
    </div>

    <AssignmentModal v-if="controls === ControlSchemes.MODAL && showModal"
                     :modalType="modalType" :cabins="cabins" :dogs="props.dogs['unassigned']" :assignment="assignment"
                     :errorMessages="errorMessages" :photoUri="photoUri" :is-new-dog="isNewDog"
                     @closeModal="showModal = false" @submitForm="submitForm" @updateIsNewDog="updateIsNewDog"/>
</template>
