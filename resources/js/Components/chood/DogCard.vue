<script setup>
import {computed, nextTick, onUnmounted, ref, watch} from "vue";
import {getTextWidth} from "@/utils.js";

const props = defineProps({
    photoUri: String,
    dogs: Array,
    maxlength: Number,
    cardHeight: Number,
    shouldLoad: Boolean,
});
const currentDogIndex = ref(0);
const imageCache = new Map();
const dogBanner = ref(null);
const dogName = ref(null);
const currentDog = computed(() => {
    return props.dogs.length ? props.dogs[currentDogIndex.value] : null;
});
const iconRefs = ref({});
const choodRightIcon = ref(null);
const setIconRef = (index) => (el) => {
    if (el) {
        iconRefs.value[`choodLeftIcon${index}`] = el;
    }
};
let rotationInterval = null;

const emit = defineEmits(['imageLoaded']);

const isBoarder = (dog) => {
    if (dog.services && dog.services.length > 0) return dog.services.some(service => service.id === 1000 || service.id === 1001);
    if (dog.service_ids) return dog.service_ids.includes('1000') || dog.service_ids.includes('1001');
    return false;
}

const preloadImage = (dog) => {
    return new Promise((resolve) => {
        if (dog.photoUri && !imageCache.has(dog.photoUri)) {
            const img = new Image();
            img.src = `${props.photoUri}${dog.photoUri}`;
            img.onload = () => {
                resolve();
            };
            img.onerror = () => {
                resolve();
            };
            imageCache.set(dog.photoUri, img);
        } else {
            resolve();
        }
    });
};

const startRotation = () => {
    rotationInterval = setInterval(() => {
        currentDogIndex.value = (currentDogIndex.value + 1) % props.dogs.length;
    }, 8000);
};

watch(() => props.dogs, (newDogs) => {
    if (rotationInterval) clearInterval(rotationInterval);
    if (newDogs.length > 0) {
        currentDogIndex.value = 0;
        if (newDogs.length > 1) startRotation();
    }
}, {immediate: true});


watch(() => props.cardHeight, async (newHeight) => {
    await nextTick();

    if (dogBanner.value) dogBanner.value.style.fontSize = `${newHeight * 0.05}px`;

    if (dogName.value) {
        dogName.value.style.fontSize = `${newHeight * 0.18}px`;
        dogName.value.style.height = `${newHeight * 0.25}px`;

        const computedFont = window.getComputedStyle(dogName.value).font;
        const pct = dogName.value.offsetWidth / getTextWidth(dogName.value.innerText, computedFont);
        if (pct < 1.05) {
            dogName.value.style.fontSize = (parseFloat(dogName.value.style.fontSize) * (pct - 0.02)) + 'px';
        }
    }

    const size = Math.min(Math.floor(newHeight / 7), 100);
    if (currentDog.value?.left_icons) {

        currentDog.value.left_icons.forEach((icon, index) => {
            const faIcon = iconRefs.value[`choodLeftIcon${index}`]?.querySelector('.icon-with-outline');
            if (faIcon) {
                faIcon.classList.remove('text-2xl');
                faIcon.style.fontSize = `${size}px`;
            }

            const iconSpan = iconRefs.value[`choodLeftIcon${index}`]?.querySelector('span');
            if (iconSpan) {
                iconSpan.style.fontSize = `${Math.floor(size * 0.75)}px`;
            }
        });
    }

    // TODO: chood Right icons
    if (choodRightIcon.value) {
        const faIcon = choodRightIcon.value.querySelector('.icon-with-outline');
        if (faIcon) {
            faIcon.classList.remove('text-2xl');
            faIcon.style.fontSize = `${size}px`;
        }

        const iconSpan = choodRightIcon.value.querySelector('span');
        if (iconSpan) {
            iconSpan.style.fontSize = `${Math.floor(size * 0.75)}px`;
        }
    }
}, {immediate: true});

watch(() => props.shouldLoad, (newVal) => {
    if (newVal && props.dogs.length > 0) {
        const imagePromises = props.dogs.map(dog => preloadImage(dog));
        // Wait for all the images to finish loading or error
        Promise.all(imagePromises).then(() => {
            setTimeout(() => {
                emit('imageLoaded');
            }, 1000);

        });
    }
}, {immediate: true});

onUnmounted(() => {
    clearInterval(rotationInterval);
});
</script>

<template>
    <div v-if="currentDog" :key="currentDog.firstname"
         :class="isBoarder(currentDog) ? 'dog-boarder' : 'dog-daycamper'"
         :style="{height: cardHeight}">
        <div ref="dogBanner">{{ isBoarder(currentDog) ? 'Boarder' : 'Daycamper' }}</div>
        <div class="dog-photo"
             :style="{ backgroundImage: currentDog.photoUri && imageCache.has(currentDog.photoUri) ? `url(${props.photoUri}${currentDog.photoUri})` : 'none'}">

            <div class="relative">
                <div v-if="currentDog.left_icons" class="absolute inset-y-0 left-1 flex flex-col py-1 chood-icon">
                    <div v-for="(iconData, index) in currentDog.left_icons" :key="index" :ref="setIconRef(index)"
                         class="flex items-center justify-center mt-1">
                        <font-awesome-icon :icon="['fas', iconData.icon]"
                                           class="text-white text-2xl icon-with-outline"/>
                        <span v-if="iconData.text"
                              class="absolute inset-0 top-2 flex justify-center text-black font-bold">
                            {{ iconData.text }}
                        </span>
                    </div>
                </div>
                <div ref="choodRightIcon" class="absolute inset-y-0 right-1 flex flex-col py-1">
                    <font-awesome-icon :icon="['fas', 'weight-hanging']" class="text-white text-2xl icon-with-outline"/>
                    <span class="absolute inset-0 top-1 flex justify-center text-black font-bold">
                        {{ currentDog.size_letter }}
                    </span>
                </div>
            </div>
        </div>
        <div v-if="currentDog.firstname" ref="dogName" class="flex items-center justify-center">
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
