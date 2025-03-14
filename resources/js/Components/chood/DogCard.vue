<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from "vue";

const props = defineProps({
    photoUri: String,
    dogs: Array,
    shortName: String,
    maxlength: Number,
    cardHeight: Number,
});
const currentDogIndex = ref(0);
const bannerSize = computed(() => `${props.cardHeight * 0.05}px`);
const nameSize = computed(() => `${props.cardHeight * 0.18}px`);
const nameHeight = computed(() => `${props.cardHeight * 0.25}px`);
const imageCache = new Map();

const isBoarder = (dog) => {
    if (dog.services && dog.services.length > 0) return dog.services.some(service => service.id === 1000 || service.id === 1001);
    if (dog.service_ids) return dog.service_ids.includes('1000') || dog.service_ids.includes('1001');
    return false;
}

const preloadImage = (dog) => {
    if (dog.photoUri && !imageCache.has(dog.photoUri)) {
        const img = new Image();
        img.src = `${props.photoUri}${dog.photoUri}`;
        imageCache.set(dog.photoUri, img);
    }
};


const currentDog = computed(() => {
    return props.dogs.length ? props.dogs[currentDogIndex.value] : null;
});
let rotationInterval;

const startRotation = () => {
    rotationInterval = setInterval(() => {
        currentDogIndex.value = (currentDogIndex.value + 1) % props.dogs.length;
        preloadImage(props.dogs[currentDogIndex.value]);
    }, 8000);
};

watch(() => props.dogs, () => {
    if (rotationInterval) clearInterval(rotationInterval);
    if (props.dogs.length > 0) {
        currentDogIndex.value = 0;
        startRotation();
    }
});

// Start rotation when component is mounted
onMounted(() => {
    if (props.dogs.length > 0) startRotation();
});

// Clean up interval when component is unmounted
onUnmounted(() => {
    clearInterval(rotationInterval);
});
</script>

<template>
    <div v-if="currentDog" :key="currentDog.firstname"
         :class="isBoarder(currentDog) ? 'dog-boarder' : 'dog-daycamper'"
         :style="{height: cardHeight}">
        <div class="dog-banner" :style="{fontSize: bannerSize}">
            {{ isBoarder(currentDog) ? 'Boarder' : 'Daycamper' }}
        </div>
        <div class="dog-photo"
             :style="{ backgroundImage: currentDog.photoUri ? `url(${props.photoUri}${currentDog.photoUri})` : 'none'}">

            <div class="relative">
                <div v-if="props.shortName || currentDog.cabin" class="absolute inset-y-0 left-1 flex flex-col py-1 chood-icon">
                    <font-awesome-icon :icon="['fas', 'house-chimney-blank']" class="text-white text-2xl icon-with-outline"/>
                    <span class="absolute inset-0 top-1 flex justify-center text-black font-bold">
                        {{ props.shortName || currentDog.cabin?.short_name }}
                    </span>
                </div>
                <div class="absolute inset-y-0 right-1 flex flex-col py-1 chood-icon">
                    <font-awesome-icon :icon="['fas', 'weight-hanging']" class="text-white text-2xl icon-with-outline"/>
                    <span class="absolute inset-0 top-1 flex justify-center text-black font-bold">
                        {{ currentDog.size_letter }}
                    </span>
                </div>
            </div>
        </div>
        <div v-if="currentDog.firstname" class="dog-name flex items-center justify-center"
             :style="{height: nameHeight, fontSize: nameSize}">
            {{ currentDog.firstname.slice(0, props.maxlength) }}
        </div>
    </div>
</template>

<style scoped>
.icon-with-outline {
    color: white;
    filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.7)) drop-shadow(0 0 8px rgba(0, 0, 0, 0.7));
    transform: translateY(-2px);
}
</style>
