<script setup>
import {Head, Link} from '@inertiajs/vue3';

const props = defineProps({
    photoUri: {
        type: String,
    },
    dog: {
        type: Object,
    }
});

const isBoarder = (services) => {
    if (services) return services.some(service => service.id === 1003 || service.id === 1004);
    return false;
}

</script>
<template>
    <div :class="isBoarder(dog.services) ? 'dog-boarder' : 'dog-daycamper'">
        <div>{{ isBoarder(dog.services) ? 'Boarder' : 'Daycamper' }}</div>
        <div class="dog-photo"
             :style="{ backgroundImage: dog.photoUri ? `url(${props.photoUri}${dog.photoUri})` : 'none' }">&nbsp;
        </div>
        <div class="dog-name">{{ dog.shortname }}</div>
    </div>
</template>
