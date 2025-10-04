<script setup>
import {computed, nextTick, onUnmounted, ref, watch} from "vue";
import {getFittedFontSize} from "@/utils.js";
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
const breakMinutesRemaining = ref(null);
const currentDog = computed(() => {
    const index = currentDogIndex.value;
    const dogs = props.dogs;

    return dogs.length ? dogs[index] : null;
});
const now = ref(Date.now())
const iconRefs = ref({});
const setIconRef = (index, dir) => (el) => {
    if (el) iconRefs.value[`chood${dir}Icon${index}`] = el;
};
const intervals = [null, null]; // [rotationInterval, timerInterval]
const emit = defineEmits(['imageLoaded']);

const getTimeColor = (iconData) => {
    const now = new Date();
    if (iconData.completed) return 'text-meadow';
    else if (new Date(iconData.checkout) - now <= 3600000) return 'text-alerted'; // within 1 hour
    else if (new Date(iconData.start) < now) return 'text-sunshine';
    return 'text-white';
};

const breakTimeLeft = computed(() => {
    const dog = currentDog.value;
    if (!dog?.rest_starts_at || !dog?.rest_duration_minutes) return null;

    if(dog.rest_duration_minutes <= 120) {
        const start = new Date(dog.rest_starts_at);
        const end = new Date(start.getTime() + dog.rest_duration_minutes * 60 * 1000);
        const minutesLeft = Math.max(Math.ceil((end.getTime() - now.value) / (60 * 1000)), 0);
        const percentElapsed = 1 - (minutesLeft / dog.rest_duration_minutes);
        const percentRemaining = minutesLeft / dog.rest_duration_minutes;

        return {
            minutesLeft,
            percentElapsed,
            percentRemaining,
            expired: minutesLeft === 0,
        };
    } else {
        return {
            minutesLeft: '',
            percentElapsed: 0,
            percentRemaining: 1,
            expired: false,
        };
    }
});

const bannerStyle = computed(() => {
    if (breakTimeLeft.value?.expired) {
        return {label: 'Return to Yard', class: 'bg-alerted'};
    }
    if (currentDog.value?.is_boarding) {
        return {label: 'Sleepover', class: 'bg-caregiver'};
    }
    if (currentDog.value?.is_daycare) {
        return {label: 'Daycamper', class: 'bg-meadow'};
    }
    if (currentDog.value?.is_interview) {
        return {label: 'Orientation', class: 'bg-crimson'};
    }
    return {label: 'Grooming/Training Only', class: 'bg-greyhound'};
});


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

watch(() => props.dogs, (newDogs) => {
    intervals.forEach((i, index) => {
        if (i) {
            clearInterval(i);
            intervals[index] = null;
        }
    });

    if (newDogs.length > 0) {
        currentDogIndex.value = 0;

        intervals[1] = setInterval(() => {
            now.value = Date.now();
        }, 8000);

        if (newDogs.length > 1) {
            intervals[0] = setInterval(() => {
                currentDogIndex.value = (currentDogIndex.value + 1) % props.dogs.length;
            }, 8000);
        }
    }
}, {immediate: true});

watch([() => props.cardHeight, currentDog], async ([newHeight]) => {
    await nextTick();

    if (dogBanner.value) dogBanner.value.style.fontSize = `${newHeight * 0.05}px`;

    if (dogName.value) {
        dogName.value.style.fontSize = `${newHeight * 0.18}px`;
        dogName.value.style.height = `${newHeight * 0.25}px`;

        dogName.value.style.fontSize = getFittedFontSize(dogName.value, dogName.value.offsetWidth);
    }

    if (breakMinutesRemaining.value) {
        breakMinutesRemaining.value.style.fontSize = `${newHeight * .4}px`;
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
    intervals.forEach((i) => i && clearInterval(i));
});
</script>

<template>
    <div v-if="currentDog" :key="currentDog.id" :class="['relative', 'flex', 'flex-col', 'h-full', bannerStyle.class]">

        <div ref="dogBanner" class="text-white text-center z-10">
            {{ bannerStyle.label }}
        </div>


        <div ref="photoContainer" class="relative bg-cover bg-center z-0 overflow-hidden" style="flex: 1 1 auto"
             :style="{ backgroundImage: currentDog.photoUri && imageCache.has(currentDog.photoUri) ? `url(${props.photoUri}${currentDog.photoUri})` : 'none' }"
        >
            <svg v-if="breakTimeLeft && !breakTimeLeft.expired" preserveAspectRatio="none"
                 class="absolute top-0 left-0 w-full h-full pointer-events-none z-10" viewBox="0 0 1 1">
                <defs>
                    <mask :id="`revealMask-${currentDog.id}`">
                        <rect x="0" :y="breakTimeLeft.percentElapsed" width="1"
                              :height="1 - breakTimeLeft.percentElapsed" fill="white"/>
                    </mask>
                </defs>

                <rect x="0" y="0" width="1" height="1" fill="rgba(0,0,0,0.75)"
                      :mask="`url(#revealMask-${currentDog.id})`"/>
            </svg>

            <div class="absolute inset-0 flex items-center justify-center z-20 pointer-events-none">
                  <span ref="breakMinutesRemaining" class="text-white drop-shadow-xl leading-none text-center">
                    {{ breakTimeLeft?.minutesLeft }}
                  </span>
            </div>

            <div v-if="currentDog.left_icons" class="absolute inset-y-0 left-1 flex flex-col py-1">
                <div v-for="(iconData, index) in currentDog.left_icons" :key="index"
                     class="relative flex items-center justify-center mt-2"
                     :style="{ height: props.cardHeight * .12 + 'px' }">
                    <font-awesome-icon :icon="['fas', iconData.icon]" class="text-white icon-with-outline"
                                       :style="{ fontSize: props.cardHeight * .12 + 'px' }"/>
                    <span v-if="iconData.text"
                          class="absolute inset-0 flex items-center justify-center font-bold pointer-events-none"
                          :style="{fontSize: props.cardHeight * 0.06 + 'px', lineHeight: 1}">
                      {{ iconData.text }}
                    </span>
                </div>
            </div>

            <div v-if="currentDog.right_icons" class="absolute inset-y-0 right-1 flex flex-col py-1">
                <div v-for="(iconData, index) in currentDog.right_icons" :key="index"
                     class="relative flex items-center justify-center mt-2"
                     :style="{ height: props.cardHeight * .12 + 'px' }">
                    <font-awesome-icon :icon="['fas', iconData.icon]" class="text-white icon-with-outline"
                                       :style="{ fontSize: props.cardHeight * .12 + 'px' }"/>
                    <span v-if="iconData.text"
                          class="absolute inset-0 flex items-center justify-center font-bold pointer-events-none"
                          :style="{fontSize: props.cardHeight * 0.06 + 'px', lineHeight: 1}">
                      {{ iconData.text }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="currentDog.firstname" ref="dogName"
             class="flex items-center justify-center z-20 text-white font-semibold">
            {{ currentDog.firstname.slice(0, props.maxlength) }}
        </div>
    </div>

</template>
