<script setup>
import {Head} from '@inertiajs/vue3';
import {ref, computed, onMounted, onBeforeUnmount} from 'vue';
import DogCard from "@/Components/chood/DogCard.vue";
import {formatTime} from "@/utils.js";

const props = defineProps({
    photoUri: String
});
const dogs = ref([]);
const currentGif = ref('/images/doggifs/dog1.webp');
const randomPosition = ref({top: 0, left: 0});
const localChecksum = ref('');
let refreshInterval = null;
const currentLoadingIndex = ref(0);
const columns = computed(() => {
    const count = displayDogs.value.length;
    return Math.min(4, Math.ceil((count <= 3 ? count : count / 2))) * 2;
});
const rows = computed(() => displayDogs.value.length > 3 ? 2 : 1);
const yardGridStyle = computed(() => getYardGridStyle(rows.value, columns.value));
const cardWidth = computed(() => (1278 - (columns.value - 1) * 10) / columns.value);
const cardHeight = computed(() => (718 - (rows.value - 1) * 10) / rows.value);

const handleImageLoaded = () => {
    while (++currentLoadingIndex.value < dogs.value?.length) {
        if (dogs.value[currentLoadingIndex.value].photoUri) {
            break;
        }
    }
};

const displayDogs = computed(() =>
    dogs.value.length > 8 ? dogs.value.slice(0, 7) : dogs.value
)

function getNewGifAndPosition() {
    return {
        newGif: '/images/doggifs/dog' + (Math.floor(Math.random() * 11) + 1) + '.webp',
        top: Math.random() * (1080 - 480),
        left: Math.random() * (1920 - 480),
    };
}

function getYardGridStyle(rows, columns) {
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gridTemplateRows: `repeat(${rows}, 1fr) 100px`,
        gap: '10px',
    };
}

const getBathServiceSteps = (service) => {
    const steps = [
        {text: 'Shampoo', icon: 'soap'}
    ];
    if (service.service.name.includes('UltiMutt') || service.service.name.includes('Deluxe')) {
        steps.push({text: 'Conditioner', icon: 'pump-soap'});
    }

    steps.push(
        {text: 'Blow Dry', icon: 'wind'},
        {text: 'Brush Out', icon: 'brush'}
    );

    if (service.service.name.includes('UltiMutt') || service.service.name.includes('Deluxe')) {
        steps.push(
            {text: 'Teeth Brushing', icon: 'tooth'},
            {text: 'Ear Clean', icon: 'ear-listen'},
            {text: 'Eye Cleanse', icon: 'eye'}
        );
    }

    if (service.service.name.includes('UltiMutt')) {
        steps.push({text: 'Finishing Scent', icon: 'spray-can-sparkles'});
    }

    return steps;
};

async function updateData() {
    try {
        const response = await axios.get(`/api/groommap/${localChecksum.value}`);

        if (response.data && localChecksum.value !== response.data?.checksum) {
            dogs.value = response.data.dogs;
            localChecksum.value = response.data.checksum;

            currentLoadingIndex.value = 0;
        }

    } catch (error) {
        console.error('Error fetching data:', error);
    }
    if (dogs.value.length === 0) {
        const {newGif: fetchedGif, left: fetchedLeft, top: fetchedTop} = getNewGifAndPosition();
        currentGif.value = fetchedGif;
        randomPosition.value = {top: fetchedTop, left: fetchedLeft};
    }
}

onMounted(() => {
    updateData();
    refreshInterval = setInterval(updateData, 60000);
});

// Clear the interval when the component is unmounted
onBeforeUnmount(() => {
    clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Groommap"/>
    <main class="w-full h-full ">
        <div class="text-3xl font-header my-2 text-center">Today's Grooming Schedule</div>
        <div id="yardmap" class="items-center justify-center p-1" :style="yardGridStyle">
            <template v-for="(dog, index) in displayDogs" :id="dog.id">
                <div class="rounded-tl-2xl rounded-bl-2xl shadow-xl py-4 h-full"
                     :style="{ width: cardWidth + 'px'}">
                    <DogCard :dogs="[dog]" :photoUri="props.photoUri" :card-width="cardWidth" :card-height="cardHeight"
                             :shouldLoad="index === currentLoadingIndex" @imageLoaded="handleImageLoaded"/>
                </div>
                <div
                    class="bg-yellow-100 text-3xl rounded-tr-2xl rounded-br-2xl shadow-inner p-4 h-full">
                    <div>Checkout: {{ formatTime(dog.checkout) }}</div>
                    <div v-for="service in dog.dog_services" :key="service.id" class="my-5 overflow-y-hidden">
                        <div class="font-bold text-caregiver">{{ service.service.name }}</div>
                        <div class="text-gray-600">Start: {{ formatTime(service.scheduled_start) }}</div>

                        <ol v-if="service.service.category === 'Bath'" class="mt-3 list-none text-gray-800">
                            <li v-for="step in getBathServiceSteps(service)" :key="step.text"
                                class="flex items-center gap-2">
                                <font-awesome-icon :icon="['fas', step.icon]" class="text-2xl" fixed-width/>
                                <span>{{ step.text }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </template>
            <template v-if="dogs.length > 8">
                <div
                    class="col-span-2 bg-crimson rounded-2xl shadow-xl flex items-center justify-center text-4xl h-full">
                    Plus {{ dogs.length - 7 }} More
                </div>
            </template>
            <img v-if="dogs.length === 0" :src="currentGif" alt="Dancing Doggo"
                 :style="{ top: randomPosition.top + 'px', left: randomPosition.left + 'px', position: 'absolute' }"
            />
        </div>
    </main>
</template>


