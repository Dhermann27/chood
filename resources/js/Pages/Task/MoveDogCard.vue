<script setup>
import {computed, nextTick, ref, watch} from 'vue';
import {getBannerStyle, getFittedFontSize} from '@/utils';

const props = defineProps({
    dog: {type: Object, required: true},
    photoUri: {type: String, required: true},
    cardWidth: {type: Number, required: true},
    cardHeight: {type: Number, required: true},
});

const dogBanner = ref(null);
const dogName = ref(null);

const bannerStyle = computed(() => getBannerStyle(props.dog, null));

watch(() => [props.cardHeight, props.cardWidth, props.dog], async ([newHeight]) => {
    await nextTick();

    if (dogBanner.value) dogBanner.value.style.fontSize = `${newHeight * 0.05}px`;

    if (dogName.value) {
        dogName.value.style.fontSize = `${newHeight * 0.18}px`;
        dogName.value.style.height = `${newHeight * 0.25}px`;
        dogName.value.style.fontSize = getFittedFontSize(dogName.value, dogName.value.offsetWidth);
    }
}, {immediate: true});
</script>

<template>
    <div :class="['relative', 'flex', 'flex-col', 'h-full', bannerStyle.class]">
        <div ref="dogBanner" class="text-white text-center z-10">
            {{ bannerStyle.label }}
        </div>

        <div class="relative z-0 overflow-hidden flex-1">
            <img v-if="dog.photoUri" :src="`${photoUri}${dog.photoUri}`" draggable="false"
                 class="absolute inset-0 w-full h-full object-cover object-center" loading="eager" decoding="async"
                 :alt="`Picture of ${dog.firstname ?? 'dog'}`" @error="e => { e.target.style.display = 'none'; }"/>
        </div>

        <div v-if="dog.firstname" ref="dogName"
            class="flex items-center justify-center z-20 text-white font-semibold">
            {{ dog.firstname.slice(0, 12) }}
        </div>
    </div>
</template>
