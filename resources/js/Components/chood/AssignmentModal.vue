<script setup>
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    modalType: String,
    cabins: Array,
    dogs: Array,
    isNewDog: Boolean,
    assignment: Object,
    errorMessages: Array,
});

const emit = defineEmits(['closeModal', 'submitForm']);

function submitForm() {
    emit('submitForm', props.assignment);
}

function closeModal() {
    emit('closeModal');
}
</script>

<template>
    <div class="fixed inset-0 bg-DEFAULT bg-opacity-50 flex justify-center items-center z-40">
        <div class="bg-white p-6 rounded-lg w-96">
            <h3 class="text-lg font-semibold mb-4">
                {{ (modalType === 'add' ? 'Add ' : 'Edit ') }} Cabin Assignment
            </h3>

            <form @submit.prevent="submitForm">
                <div class="mb-4">
                    <label for="cabin-select">Select Cabin</label>
                    <select id="cabin-select" v-model="props.assignment.cabin_id" required
                            class="mt-1 block w-full text-sm border border-greyhound rounded-md p-2">
                        <option disabled value="">Please select a cabin</option>
                        <option v-for="cabin in cabins" :key="cabin.id" :value="cabin.id">
                            {{ cabin.short_name }}
                        </option>
                    </select>
                </div>

                <template v-if="!props.isNewDog">
                    <!-- Dog Selection -->
                    <div class="mb-4">
                        <multiselect
                            v-model="assignment.dogs" multiple :options="dogs" label="display_name"
                            :searchable="true" :clearable="true" placeholder="Select Dog(s)">
                            <template #option="props">
                                <div v-if="props.option.id === 'new'" class="text-sm text-caregiver">
                                    Enter a new dog
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <div v-if="props.option.photoUri" class="dog-photo-wrap">
                                        <img :src="props.option.photoUri" :alt="props.option.display_name"
                                             @error="e => e.target.parentElement.style.display = 'none'"/>
                                    </div>
                                    <span class="text-base font-medium">{{ props.option.display_name }}</span>
                                </div>
                            </template>
                        </multiselect>
                    </div>
                </template>
                <template v-else>
                    <div class="mb-4">
                        <label for="name" class="block text-xs font-medium text-greyhound">Dog Name</label>
                        <input
                            v-model="assignment.firstname"
                            id="firstname"
                            type="text"
                            class="mt-1 block w-full text-sm border border-greyhound rounded-md p-2"
                        />
                    </div>

                    <div class="mb-4">
                        <label for="name" class="block text-xs font-medium text-greyhound">Family Name</label>
                        <input
                            v-model="assignment.lastname"
                            id="lastname"
                            type="text"
                            class="mt-1 block w-full text-sm border border-greyhound rounded-md p-2"
                        />
                    </div>
                </template>

                <div v-if="errorMessages.length > 0"
                     class="p-4 mb-4 bg-alerted border rounded">
                    <div v-for="message in errorMessages" :key="message" class="font-semibold">
                        {{ message }}
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" @click="closeModal"
                            class="px-4 py-2 bg-greyhound text-white rounded-md text-xs">Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-caregiver text-white rounded-md text-xs hover:bg-caregiver">
                        {{ modalType === 'add' ? 'Add Assignment' : 'Update Assignment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
:deep(.dog-photo-wrap) {
    width: 75px;
    height: 75px;
    flex-shrink: 0;
    border-radius: 10%;
    overflow: hidden;
}

:deep(.dog-photo-wrap) img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

