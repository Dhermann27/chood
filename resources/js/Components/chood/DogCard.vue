<script setup>

const props = defineProps({
    photoUri: String,
    dog: Object,
    maxlength: Number
});
const isBoarder = (dog) => {
    if (dog.services && dog.services.length > 0) return dog.services.some(service => service.id === 1000 || service.id === 1001);
    if (dog.service_ids) return dog.service_ids.includes('1000') || dog.service_ids.includes('1001');
    return false;
}

</script>
<template>
    <div :class="isBoarder(props.dog) ? 'dog-boarder' : 'dog-daycamper'">
        <div>{{ isBoarder(props.dog) ? 'Boarder' : 'Daycamper' }}</div>
        <div class="dog-photo"
             :style="{ backgroundImage: props.dog.photoUri ? `url(${props.photoUri}${props.dog.photoUri})` : 'none'}">
            &nbsp;
        </div>
        <div v-if="props.dog.name" class="dog-name">{{ props.dog.name.slice(0, maxlength) }}</div>
    </div>
</template>
