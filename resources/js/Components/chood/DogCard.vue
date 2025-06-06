<script setup>
import {computed, nextTick, onUnmounted, ref, watch} from "vue";
import {getTextWidth} from "@/utils.js";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";

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
const setIconRef = (index, dir) => (el) => {
    if (el) {
        iconRefs.value[`chood${dir}Icon${index}`] = el;
    }
};
const getTimeColor = (iconData) => {
    const now = new Date();
    if (iconData.completed) return 'text-green-400';
    else if (new Date(iconData.checkout) - now <= 3600000) return 'text-red-400'; // within 1 hour
    else if (new Date(iconData.start) < now) return 'text-yellow-400';
    return 'text-white';
};

let rotationInterval = null;

const emit = defineEmits(['imageLoaded']);

const isBoarder = (dog) => {
    if (dog.dog_services && dog.dog_services.length > 0) return dog.dog_services.some(service => service.service.name.includes('Boarding'));
    return false;
}


const preloadImage = (dog) => {
    return new Promise((resolve) => {
        if (dog.photoUri && !imageCache.has(dog.photoUri)) {
            const img = new Image();
            img.src = `${props.photoUri}${dog.photoUri}`;

            const timer = setTimeout(() => {
                console.warn('Timeout loading image:', dog.photoUri);
                resolve(); // still resolve even if it times out
            }, 10000);

            img.onload = () => {
                clearTimeout(timer);
                imageCache.set(dog.photoUri, img);
                resolve();
            };

            img.onerror = () => {
                clearTimeout(timer);
                console.warn('Failed to load image:', dog.photoUri);
                resolve();
            };
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


watch([() => props.cardHeight, currentDog], async ([newHeight]) => {
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
            const faIcon = iconRefs.value[`choodLIcon${index}`]?.querySelector('.icon-with-outline');
            if (faIcon) {
                faIcon.classList.remove('text-2xl');
                faIcon.style.fontSize = `${size}px`;
            }

            const iconSpan = iconRefs.value[`choodLIcon${index}`]?.querySelector('span');
            if (iconSpan) {
                iconSpan.style.fontSize = `${Math.floor(size * 0.6)}px`;
            }
        });
    }

    if (currentDog.value?.right_icons) {
        currentDog.value.right_icons.forEach((icon, index) => {
            const faIcon = iconRefs.value[`choodRIcon${index}`]?.querySelector('.icon-with-outline');
            if (faIcon) {
                faIcon.classList.remove('text-2xl');
                faIcon.style.fontSize = `${size}px`;
            }

            const iconSpan = iconRefs.value[`choodRIcon${index}`]?.querySelector('span');
            if (iconSpan) {
                iconSpan.style.fontSize = `${Math.floor(size * 0.6)}px`;
            }
        });
    }
}, {immediate: true});

watch(() => props.shouldLoad, async (newVal) => {
    if (newVal) {
        const imagePromises = props.dogs.map(dog => preloadImage(dog));
        await Promise.all(imagePromises);

        setTimeout(() => {
            emit('imageLoaded');
        }, 500);
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
                <div v-if="currentDog.left_icons" class="absolute inset-y-0 left-1 flex flex-col py-1">
                    <div v-for="(iconData, index) in currentDog.left_icons" :key="index" :ref="setIconRef(index, 'L')"
                         class="relative flex items-center justify-center mt-2">

                        <font-awesome-icon :icon="['fas', iconData.icon]"
                                           class="text-white text-2xl icon-with-outline"/>

                        <span v-if="iconData.text"
                              class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-black font-bold pointer-events-none">
                            {{ iconData.text }}
                        </span>
                    </div>

                </div>
                <div v-if="currentDog.right_icons" class="absolute inset-y-0 right-1 flex flex-col py-1">
                    <div v-for="(iconData, index) in currentDog.right_icons" :key="index" :ref="setIconRef(index, 'R')"
                         class="relative flex items-center justify-center mt-2">

                        <font-awesome-icon :icon="['fas', iconData.icon]" :transform="iconData.transform"
                                           :class="['text-2xl icon-with-outline', getTimeColor(iconData)]"/>

                        <span v-if="iconData.text"
                              class="absolute inset-0 flex items-center justify-center text-black font-bold text-sm pointer-events-none">
                              {{ iconData.text }}
                        </span>

                    </div>

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
    filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.7)) drop-shadow(0 0 8px rgba(0, 0, 0, 0.7));
    transform: translateY(-2px);
}
</style>
