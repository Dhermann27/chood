<script setup>
import {computed} from "vue";

const props = defineProps({
    photoUri: String,
    dog: Object,
    maxlength: Number,
    cardHeight: Number,
});
const bannerSize = computed(() => `${props.cardHeight * 0.05}px`);
const nameSize = computed(() => `${props.cardHeight * 0.25}px`);

const isBoarder = (dog) => {
    if (dog.services && dog.services.length > 0) return dog.services.some(service => service.id === 1000 || service.id === 1001);
    if (dog.service_ids) return dog.service_ids.includes('1000') || dog.service_ids.includes('1001');
    return false;
}

</script>
<template>
    <div :class="isBoarder(props.dog) ? 'dog-boarder' : 'dog-daycamper'" :style="{height: cardHeight}">
        <div class="dog-banner" :style="{fontSize: bannerSize}">
            {{ isBoarder(props.dog) ? 'Boarder' : 'Daycamper' }}
        </div>
        <div class="dog-photo"
             :style="{ backgroundImage: props.dog.photoUri ? `url(${props.photoUri}${props.dog.photoUri})` : 'none'}">
            &nbsp;
        </div>
        <div v-if="props.dog.firstname" class="dog-name"  :style="{fontSize: nameSize}">
            {{ props.dog.firstname.slice(0, props.maxlength) }}
        </div>
    </div>
</template>
