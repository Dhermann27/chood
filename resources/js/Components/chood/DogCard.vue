<script setup>

const props = defineProps({
    photoUri: String,
    dog: Object,
    maxlength: Number
});

const isBoarder = (dog) => {
    if (dog.services.length > 0) return dog.services.some(service => service.id === 1003 || service.id === 1004);
    if (dog.service_ids) return dog.service_ids.includes('1003') || dog.service_ids.includes('1004');
    return false;
}

</script>
<template>
    <div :class="isBoarder(dog) ? 'dog-boarder' : 'dog-daycamper'">
        <div>{{ isBoarder(dog) ? 'Boarder' : 'Daycamper' }}</div>
        <div class="dog-photo"
             :style="{ backgroundImage: dog.photoUri ? `url(${props.photoUri}${dog.photoUri})` : 'none' }">&nbsp;
        </div>
        <div class="dog-name">{{ dog.name.slice(0, maxlength) }}</div>
    </div>
</template>
