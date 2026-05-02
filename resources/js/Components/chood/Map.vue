<script setup>
import {onBeforeUnmount, onMounted, ref} from "vue";
import axios from 'axios';

import DogCard from "@/Components/chood/DogCard.vue";
import AssignmentModal from "@/Components/chood/AssignmentModal.vue";
import {ControlSchemes} from "@/controlSchemes.js";
import {checkoutReservationColor} from '@/utils.js';

const props = defineProps({
    cabins: Array,
    statuses: Object,
    dogs: Object,
    controls: ControlSchemes,
    maxlength: Number,
    cardWidth: Number,
    cardHeight: Number,
    displayCabinId: {type: Number, default: null},
    sectionCounts: {type: Object, default: null},
});

const emit = defineEmits(['cabinClicked']);

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
const now = ref(Date.now());
let clockInterval;
onMounted(() => {
    clockInterval = setInterval(() => {
        now.value = Date.now();
    }, 5000);
});
onBeforeUnmount(() => clearInterval(clockInterval));

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

function isCheckingOutTodayOrEarlier(dogs) {
    if (!dogs) return false;
    const today = new Date().toLocaleDateString('en-CA'); // "YYYY-MM-DD"
    return dogs.some(dog => {
        const checkoutDate = dog.checkout?.slice(0, 10);
        return checkoutDate && checkoutDate <= today && dog.is_boarding;
    });
}

function recentCheckoutDog(dogs) {
    return dogs?.find(d => d.checked_out_at &&
        (now.value - new Date(d.checked_out_at).getTime()) < 120000) ?? null;
}

function hasCheckedOut(dogs) {
    return dogs?.some(d => d.checked_out_at) ?? false;
}

function cabinStyle(cabin) {
    const isHovered = hoveredCabinId.value === cabin.id && props.statuses[cabin.id];
    const dogsInCabin = props.dogs?.[cabin.id] || [];
    const recentDog = recentCheckoutDog(dogsInCabin);

    let borderColor = props.statuses?.[cabin.id]
        ? props.statuses[cabin.id] === 'deep' ? '#dd454f' : '#f4df7a'
        : '#373a36';
    let borderStyle = isCheckingOutTodayOrEarlier(dogsInCabin) ? 'dashed' : 'solid';

    if (recentDog) {
        borderColor = checkoutReservationColor(recentDog);
        borderStyle = 'dashed';
    }

    return {
        gridRow: `${cabin.rho} / span ${cabin.rowspan}`,
        gridColumn: cabin.kappa,
        borderColor,
        borderStyle,
        color: isHovered ? '#fff' : '#373a36',
        backgroundColor: isHovered ? borderColor : '#fff',
        width: props.cardWidth + 'px',
        height: (props.cardHeight * cabin.rowspan) + 'px',
        transition: 'background-color 0.3s ease',
        cursor: props.controls !== ControlSchemes.NONE ? 'pointer' : 'auto',
    };
}

function handleHover(cabinId) {
    if (props.statuses?.[cabinId]) {
        hoveredCabinId.value = cabinId;
    }
}

function handleHoverLeave() {
    hoveredCabinId.value = null;
}

function handleClick(cabin) {
    emit('cabinClicked', cabin);
}

function displayCabinStyle(cabin) {
    return {
        gridRow: `${cabin.rho} / span ${cabin.rowspan}`,
        gridColumn: cabin.kappa,
        width: props.cardWidth + 'px',
        height: (props.cardHeight * cabin.rowspan) + 'px',
        borderWidth: '10px',
        borderStyle: 'solid',
        borderColor: '#373a36',
    };
}

</script>
<template>
    <template v-for="cabin in cabins" :key="cabin.id">
        <div v-if="displayCabinId && cabin.id === displayCabinId"
             class="bg-crimson text-white font-bold flex flex-col items-center justify-center"
             :style="displayCabinStyle(cabin)">
            <span v-if="sectionCounts?.checkin_today !== null"
                  class="flex items-center justify-center gap-1 leading-none"
                  :style="{ fontSize: (cardHeight * 0.18) + 'px' }">
                {{ sectionCounts.checkin_today }}
                <FontAwesomeIcon :icon="['fas', 'arrows-left-right']" style="transform: translateY(-0.1em)"/>
                {{ sectionCounts.checkout_today }}
            </span>
            <span :style="{ fontSize: (cardHeight * 0.5) + 'px', lineHeight: 1 }">
                {{ sectionCounts?.in_house }}
            </span>
        </div>
        <div v-else
             :class="['cabin', { 'cabin-empty': !props.dogs[cabin.id]}]"
             :style="cabinStyle(cabin)"
             @mouseover="handleHover(cabin.id)" @mouseleave="handleHoverLeave" @click="handleClick(cabin)"
        >
            <div v-if="props.dogs[cabin.id] && props.dogs[cabin.id].length > 0" class="h-full w-full relative">
                <DogCard :dogs="props.dogs[cabin.id]" :maxlength="maxlength" :card-height="cardHeight"/>
                <div v-if="hasCheckedOut(props.dogs[cabin.id]) && !recentCheckoutDog(props.dogs[cabin.id])"
                     class="absolute inset-0 flex items-center justify-center bg-black/60 text-white pointer-events-none"
                     :style="{ fontSize: (cardHeight * 0.4) + 'px' }">
                    CO
                </div>
                <div v-if="controls === ControlSchemes.MODAL && props.dogs[cabin.id].every(d => !d.is_boarding)"
                     class="absolute inset-y-0 left-0 flex flex-col justify-center">
                    <button
                        @click="openModal( 'edit', cabin)"
                        class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md"
                    >
                        <FontAwesomeIcon :icon="['fas', 'edit']"/>
                    </button>
                    <button @click="handleDelete(props.dogs[cabin.id])"
                            class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md">
                        <FontAwesomeIcon :icon="['fas', 'trash']"/>
                    </button>
                </div>
            </div>
            <div v-else>
                {{ cabin.short_name }}
                <div v-if="controls === ControlSchemes.MODAL" @click="openModal( 'add', cabin) " class="cabin-icon">
                    <button class="bg-caregiver text-crimson hover:text-alerted p-1 rounded-r-md">
                        <FontAwesomeIcon :icon="['fas', 'add']"/>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <AssignmentModal v-if="controls === ControlSchemes.MODAL && showModal"
                     :modalType="modalType" :cabins="cabins"
                     :dogs="(props.dogs['unassigned'] ?? []).filter(d => !d.is_boarding)" :assignment="assignment"
                     :errorMessages="errorMessages" :is-new-dog="isNewDog"
                     @closeModal="showModal = false" @submitForm="submitForm"/>
</template>
