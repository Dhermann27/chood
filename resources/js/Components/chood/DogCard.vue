<script setup>
import {computed, onMounted, onUnmounted, ref, watch} from "vue";

const props = defineProps({
    photoUri: String,
    dogs: Array,
    maxlength: Number,
    cardHeight: Number,
});
const currentDogIndex = ref(0);
const bannerSize = computed(() => `${props.cardHeight * 0.05}px`);
const nameSize = computed(() => `${props.cardHeight * 0.18}px`);
const nameHeight = computed(() => `${props.cardHeight * 0.25}px`);

const isBoarder = (dog) => {
    if (dog.services && dog.services.length > 0) return dog.services.some(service => service.id === 1000 || service.id === 1001);
    if (dog.service_ids) return dog.service_ids.includes('1000') || dog.service_ids.includes('1001');
    return false;
}

const currentDog = computed(() => {
    return props.dogs.length ? props.dogs[currentDogIndex.value] : null;
});
let rotationInterval;

const startRotation = () => {
    rotationInterval = setInterval(() => {
        currentDogIndex.value = (currentDogIndex.value + 1) % props.dogs.length;
    }, 5000);
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
            &nbsp;
        </div>
        <div v-if="currentDog.firstname" class="dog-name flex items-center justify-center"
             :style="{height: nameHeight, fontSize: nameSize}">
            {{ currentDog.firstname.slice(0, props.maxlength) }}
        </div>
    </div>
</template>
