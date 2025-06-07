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

    const start = new Date(dog.rest_starts_at);
    const end = new Date(start.getTime() + dog.rest_duration_minutes * 60 * 1000);
    const msLeft = end.getTime() - now.value;
    const minutesLeft = Math.max(Math.ceil(msLeft / (60 * 1000)), 0);
    const totalMinutes = dog.rest_duration_minutes;
    const percentElapsed = 1 - (minutesLeft / totalMinutes);

    return {
        minutesLeft,
        percentElapsed,
        expired: minutesLeft === 0,
    };
});

// Utility to calculate SVG pie arcs
function polarToCartesian(cx, cy, r, angleInDegrees) {
    const angleInRadians = (angleInDegrees - 90) * Math.PI / 180.0;
    return {
        x: cx + r * Math.cos(angleInRadians),
        y: cy + r * Math.sin(angleInRadians),
    };
}

function describeArc(x, y, radius, startAngle, endAngle) {
    const start = polarToCartesian(x, y, radius, endAngle);
    const end = polarToCartesian(x, y, radius, startAngle);
    const largeArcFlag = endAngle - startAngle <= 180 ? '0' : '1';

    return [
        'M', x, y,
        'L', start.x, start.y,
        'A', radius, radius, 0, largeArcFlag, 0, end.x, end.y,
        'Z',
    ].join(' ');
}

const bannerStyle = computed(() => {
    if (breakTimeLeft.value?.expired) {
        return {label: 'Return to Yard', class: 'bg-alerted'};
    }
    const services = currentDog.value?.dog_services || [];
    const categories = services.map(s => s.service?.category || '');
    if (categories.includes('Boarding')) {
        return {label: 'Sleepover', class: 'bg-caregiver'};
    }
    if (categories.includes('Day Care') || categories.includes('Daycare')) {
        return {label: 'Daycamper', class: 'bg-meadow'};
    }
    if (categories.includes('Interview')) {
        return {label: 'Orientation', class: 'bg-greyhound'};
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
        breakMinutesRemaining.value.style.fontSize = `${newHeight * .6}px`;
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
    <div v-if="currentDog" :key="currentDog.firstname"
         :class="['relative', 'flex', 'flex-col', 'h-full', bannerStyle.class]">


        <!-- Rest break curtain effect at card level -->
        <div v-if="breakTimeLeft" class="absolute inset-0 z-10 pointer-events-none">
            <svg v-if="breakTimeLeft && !breakTimeLeft.expired" class="absolute inset-0 pointer-events-none z-0"
                 viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <mask :id="`revealMask-${currentDog.id}`">
                        <rect x="0" y="0" width="100" height="100" fill="white"/>
                        <path
                            :d="describeArc(50, 50, 70, 0, breakTimeLeft.percentElapsed * 360)"
                            :class="'fill-[--color-DEFAULT]'"
                        />
                    </mask>
                </defs>
                <rect x="0" y="0" width="100" height="100" fill="rgba(0,0,0,0.75)" :mask="`url(#revealMask-${currentDog.id})`"/>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center minutes-remaining">
                <span ref="breakMinutesRemaining"
                      class="text-white font-subheader drop-shadow-xl leading-none text-center">
                  {{ breakTimeLeft?.minutesLeft }}
                </span>
            </div>
        </div>

        <div ref="dogBanner" class="text-white text-center">{{ bannerStyle.label }}</div>
        <div class="dog-photo flex-1 relative bg-cover bg-center z-0"
            :style="{backgroundImage: currentDog.photoUri && imageCache.has(currentDog.photoUri) ? `url(${props.photoUri}${currentDog.photoUri})` : 'none'}">
        <div class="relative">
                <div v-if="currentDog.left_icons" class="absolute inset-y-0 left-1 flex flex-col py-1 ">
                    <div v-for="(iconData, index) in currentDog.left_icons" :key="index" :ref="setIconRef(index, 'L')"
                         class="relative flex items-center justify-center mt-2">

                        <font-awesome-icon :icon="['fas', iconData.icon]"
                                           class="text-white text-2xl icon-with-outline"/>

                        <span v-if="iconData.text"
                              class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-DEFAULT font-bold pointer-events-none">
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
                              class="absolute inset-0 flex items-center justify-center text-DEFAULT font-bold text-sm pointer-events-none">
                              {{ iconData.text }}
                        </span>

                    </div>

                </div>
            </div>
        </div>
        <div v-if="currentDog.firstname" ref="dogName" class="flex items-center justify-center z-20 text-white font-semibold">
            {{ currentDog.firstname.slice(0, props.maxlength) }}
        </div>
    </div>
</template>

<style scoped>
.icon-with-outline, .minutes-remaining {
    filter: drop-shadow(0 0 8px rgba(0, 0, 0, 0.7)) drop-shadow(0 0 8px rgba(0, 0, 0, 0.7));
    transform: translateY(-2px);
}
</style>
