<script setup>
import {computed, nextTick, onUnmounted, ref, watch} from "vue";
import {getBannerStyle, getFittedFontSize} from "@/utils.js";
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";

const props = defineProps({
    dogs: Array,
    maxlength: Number,
    cardHeight: Number,
});
const currentDogIndex = ref(0);
const imageCache = ref(new Set());
const dogBanner = ref(null);
const dogName = ref(null);
const breakMinutesRemaining = ref(null);
const currentDog = computed(() => {
    const index = currentDogIndex.value;
    const dogs = props.dogs;

    return dogs.length ? dogs[index] : null;
});
const iconRefs = ref({});
function setIconRef(index, dir) {
    return (el) => {
        if (el) iconRefs.value[`chood${dir}Icon${index}`] = el;
    };
}
const intervals = [null]; // [rotationInterval]

const bannerStyle = computed(() =>
    getBannerStyle(currentDog.value, breakTimeLeft.value)
);

function getTimeColor(iconData) {
    const now = new Date();
    if (iconData.completed) return 'text-meadow';
    else if (new Date(iconData.checkout) - now <= 3600000) return 'text-alerted'; // within 1 hour
    else if (new Date(iconData.start) < now) return 'text-sunshine';
    return 'text-white';
}

const maskColor = computed(() => {
    const dog = currentDog.value;
    if (dog?.is_boarding) return 'rgba(135,179,209,0.75)';   // caregiver blue
    if (dog?.is_daycare) return 'rgba(136,201,153,0.75)';   // meadow green
    if (dog?.is_interview) return 'rgba(158,27,50,0.75)';    // crimson red
    return 'rgba(0,0,0,0.75)';                               // black for GRM/TRN
});

const breakTimeLeft = computed(() => {
    const dog = currentDog.value;
    if (!dog?.rest_starts_at || !dog?.break_type) return null;

    const bt = dog.break_type;
    const start = new Date(dog.rest_starts_at);

    if (bt.behavior === 'countdown') {
        const end = new Date(start.getTime() + bt.duration_minutes * 60 * 1000);
        const minutesLeft = Math.max(Math.ceil((end.getTime() - Date.now()) / (60 * 1000)), 0);
        return {
            minutesLeft,
            percentElapsed: 1 - minutesLeft / bt.duration_minutes,
            percentRemaining: minutesLeft / bt.duration_minutes,
            expired: minutesLeft === 0,
        };
    }

    if (bt.behavior === 'lunch') {
        const onePm = new Date(start);
        onePm.setHours(13, 0, 0, 0);
        const totalMinutes = Math.max(Math.ceil((onePm.getTime() - start.getTime()) / (60 * 1000)), 1);
        const minutesLeft = Math.max(Math.ceil((onePm.getTime() - Date.now()) / (60 * 1000)), 0);
        return {
            minutesLeft,
            percentElapsed: 1 - minutesLeft / totalMinutes,
            percentRemaining: minutesLeft / totalMinutes,
            expired: minutesLeft === 0,
        };
    }

    if (bt.behavior === 'walks_only') {
        const elapsed = Math.floor((Date.now() - start.getTime()) / (60 * 1000));
        const timeForWalk = elapsed >= bt.duration_minutes;
        return {
            minutesLeft: timeForWalk ? 'Walk!' : 'EOD',
            percentElapsed: 0,
            percentRemaining: 1,
            expired: timeForWalk
        };
    }

    // unlimited (GRM, TRN, etc.)
    return {minutesLeft: bt.short_label, percentElapsed: 0, percentRemaining: 1, expired: false};
});

function preloadImage(dog) {
    if (dog.photoUri && !imageCache.value.has(dog.photoUri)) {
        const img = new Image();
        img.onload = () => imageCache.value.add(dog.photoUri);
        img.onerror = () => console.warn('Failed to load image:', dog.photoUri);
        img.src = dog.photoUri;
    }
}

watch(() => props.dogs, (newDogs) => {
    newDogs.forEach(dog => preloadImage(dog));
    intervals.forEach((i, index) => {
        if (i) {
            clearInterval(i);
            intervals[index] = null;
        }
    });

    if (newDogs.length > 0) {
        currentDogIndex.value = 0;

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
             :style="{ backgroundImage: currentDog.photoUri && imageCache.has(currentDog.photoUri) ? `url(${currentDog.photoUri})` : 'none' }">

            <svg v-if="breakTimeLeft && !breakTimeLeft.expired" preserveAspectRatio="none"
                 class="absolute top-0 left-0 w-full h-full pointer-events-none z-10" viewBox="0 0 1 1">
                <defs>
                    <mask :id="`revealMask-${currentDog.id}`">
                        <rect x="0" :y="breakTimeLeft.percentElapsed" width="1"
                              :height="1 - breakTimeLeft.percentElapsed" fill="white"/>
                    </mask>
                </defs>

                <rect x="0" y="0" width="1" height="1" :fill="maskColor" :mask="`url(#revealMask-${currentDog.id})`"/>
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
                    <FontAwesomeIcon :icon="['fas', iconData.icon]" class="text-white icon-with-outline"
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
                    <FontAwesomeIcon :icon="['fas', iconData.icon]" class="text-white icon-with-outline"
                                     :style="{ fontSize: props.cardHeight * .12 + 'px' }"/>
                    <span v-if="iconData.text"
                          class="absolute inset-0 flex items-center justify-center font-bold pointer-events-none"
                          :style="{fontSize: props.cardHeight * 0.06 + 'px', lineHeight: 1}">
                      {{ iconData.text }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="currentDog.display_name" ref="dogName"
             class="flex items-center justify-center z-20 text-white font-semibold">
            {{ currentDog.display_name.slice(0, props.maxlength) }}
        </div>
    </div>

</template>
