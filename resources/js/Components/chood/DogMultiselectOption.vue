<script setup>
defineProps({
    option: Object,
});
</script>

<template>
    <div style="display:flex; align-items:center;">
        <div v-if="option.photoUri"
             style="width:75px; height:75px; flex-shrink:0; border-radius:8px; overflow:hidden;">
            <img :src="option.photoUri" :alt="option.display_name"
                 style="width:100%; height:100%; object-fit:cover;"
                 @error="e => e.target.parentElement.style.display = 'none'"/>
        </div>
        <span class="text-3xl ml-10">{{ option.display_name }}</span>
    </div>
</template>
