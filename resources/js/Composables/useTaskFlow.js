import {computed, ref} from 'vue';
import axios from 'axios';
import {getYardGridStyle} from '@/utils.js';

const EMPTY_TARGETS = {
    dogsToAssign: [],
    yardsToAssign: [],
    cabin_id: 0,
    cabin_short_name: '',
    break_type_id: null,
    rest_minutes: null,
    lunch_notes: '1 Bag',
};

export function useTaskFlow(breakTypes, {onInteraction, onSuccess} = {}) {
    const dogs = ref(null);
    const employees = ref(null);
    const openYards = ref(null);
    const statuses = ref(null);
    const statusMessage = ref(null);
    const statusClass = ref('text-greyhound');
    const wiwId = ref(null);
    const todo = ref(null);
    const restMinutes = ref('');
    const staffImageCache = ref(new Set());
    const targets = ref({...EMPTY_TARGETS});
    const step = ref(1);
    const showNoCabinWarning = ref(false);
    const savedBreakDogs = ref([]);
    const savedNoCabinDog = ref(null);
    const is1pmOrLater = ref(false);

    const empColumns = computed(() => Math.max(1, Math.ceil(Math.sqrt((4 / 3) * (employees.value?.length ?? 0)))));
    const empRows = computed(() => Math.max(1, Math.ceil((employees.value?.length ?? 0) / empColumns.value)));
    const dogsOnBreak = computed(() => {
        if (!dogs.value) return [];
        return dogs.value.filter(dog => dog.rest_starts_at !== null && !dog.checked_out_at);
    });
    const restColumns = computed(() => Math.ceil(Math.sqrt((16 / 9) * (dogsOnBreak.value.length + 1))));
    const restRows = computed(() => Math.ceil((dogsOnBreak.value.length + 1) / restColumns.value));
    const restGridStyle = computed(() => getYardGridStyle(restRows.value, restColumns.value, false));
    const restCardWidth = computed(() => (770 - (restColumns.value - 1) * 10) / restColumns.value);
    const restCardHeight = computed(() => (290 - (restRows.value - 1) * 10) / restRows.value);
    const dogsNotOnBreak = computed(() => {
        if (!dogs.value) return [];
        return dogs.value.filter(dog => dog.rest_starts_at === null && dog.pet_id !== null && !dog.checked_out_at);
    });
    const dogsByCabin = computed(() => {
        const grouped = {};
        if (!dogs.value) return grouped;
        dogs.value.forEach(dog => {
            const key = dog.cabin_id ?? 'unassigned';
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(dog);
        });
        return grouped;
    });
    const moveDogEnabled = computed(() => (openYards.value?.length ?? 0) >= 3);
    const dogsWithCabinMates = computed(() => {
        if (!dogs.value) return [];
        const assignedNames = new Set(dogs.value.filter(d => d.pet_id === null).map(d => d.display_name));
        const eligible = dogs.value.filter(d => d.cabin_id && d.is_boarding && d.pet_id !== null && !d.checked_out_at && !assignedNames.has(d.display_name));
        const counts = {};
        eligible.forEach(d => { counts[d.cabin_id] = (counts[d.cabin_id] || 0) + 1; });
        return eligible.filter(d => counts[d.cabin_id] > 1);
    });
    const feedingCabinEnabled = computed(() => dogsWithCabinMates.value.length > 0);
    const markReturnedIsWalked = computed(() => {
        const dog = targets.value.dogsToAssign;
        if (dog?.break_type?.behavior !== 'walks_only' || !dog?.rest_starts_at) return false;
        const elapsed = (Date.now() - new Date(dog.rest_starts_at).getTime()) / 60000;
        return elapsed >= dog.break_type.duration_minutes;
    });
    const breakStatus = computed(() => {
        const bt = breakTypes?.find(t => t.id === targets.value.break_type_id);
        if (!bt) return 'on break';
        if (bt.behavior === 'lunch') return 'on lunch break';
        if (bt.behavior === 'unlimited') return `in ${bt.label}`;
        if (bt.behavior === 'walks_only') return 'marked as walks only';
        return `resting for ${targets.value.rest_minutes ?? bt.duration_minutes} minutes`;
    });

    function preloadStaffPhoto(employee) {
        const id = employee.wiw_user_id;
        if (staffImageCache.value.has(id)) return;
        const img = new Image();
        img.onload = () => { staffImageCache.value = new Set([...staffImageCache.value, id]); };
        img.src = `/images/staff/${id}.png`;
    }

    function preloadDogPhotos(dogList) {
        dogList?.forEach(dog => {
            if (!dog?.photoUri) return;
            const img = new Image();
            img.src = dog.photoUri;
        });
    }

    function prevStep() {
        onInteraction?.();
        statusMessage.value = null;
        if (step.value > 1) step.value--;
    }

    function nextStep() {
        onInteraction?.();
        statusMessage.value = null;
        if (step.value < 4) step.value++;
    }

    function handleEmployeeClick(employee) {
        wiwId.value = employee.wiw_user_id;
        nextStep();
    }

    function handleTaskClick(thisTodo) {
        is1pmOrLater.value = new Date().getHours() >= 13;
        todo.value = thisTodo;
        nextStep();
    }

    function handleTargetClick(cabin) {
        onInteraction?.();
        if (todo.value === 'assignCabin') {
            targets.value = {...targets.value, cabin_id: cabin.id, cabin_short_name: cabin.short_name};
            if (targets.value.dogsToAssign.length > 0) nextStep();
        } else if (todo.value === 'assignFeedingCabin') {
            const dummy = (dogsByCabin.value[cabin.id] ?? []).find(d => d.pet_id === null);
            if (dummy) {
                todo.value = 'clearFeedingCabin';
                targets.value = {...targets.value, cabin_id: cabin.id, cabin_short_name: cabin.short_name, dummy_display_name: dummy.display_name};
                nextStep();
                return;
            }
            targets.value = {...targets.value, cabin_id: cabin.id, cabin_short_name: cabin.short_name};
            if (targets.value.dogsToAssign?.id) nextStep();
        } else if (todo.value === 'cleanCabin') {
            targets.value = {wiw_user_id: wiwId.value, cabin_id: cabin.id, cabin_short_name: cabin.short_name, is_cleaned: statuses.value.hasOwnProperty(cabin.id)};
            nextStep();
        }
    }

    function handleFeedingDogUpdate() {
        onInteraction?.();
        if (targets.value.dogsToAssign?.id && targets.value.cabin_id > 0) nextStep();
    }

    function handleAssignDogUpdate() {
        onInteraction?.();
        if (targets.value.dogsToAssign.length > 0 && targets.value.cabin_id > 0) nextStep();
    }

    function addAllBoarders() {
        onInteraction?.();
        const existingIds = new Set(targets.value.dogsToAssign.map(d => d.id));
        const boarders = dogsNotOnBreak.value.filter(d => d.is_boarding && d.pet_id !== null && !existingIds.has(d.id));
        targets.value.dogsToAssign = [...targets.value.dogsToAssign, ...boarders];
    }

    function handleBreakDogSelect(dog) {
        onInteraction?.();
        if (!dog.cabin_id) showNoCabinWarning.value = true;
    }

    function handleBreakDogUpdate(breakTypeId) {
        onInteraction?.();
        targets.value.break_type_id = breakTypeId;
        targets.value.rest_minutes = null;
        if (targets.value.dogsToAssign.length > 0) nextStep();
    }

    function handleTimerStart() {
        const mins = parseInt(restMinutes.value);
        if (!mins || mins < 1) return;
        const timerType = breakTypes?.find(bt => bt.behavior === 'countdown' && bt.duration_minutes === null);
        if (!timerType) return;
        restMinutes.value = '';
        onInteraction?.();
        targets.value.break_type_id = timerType.id;
        targets.value.rest_minutes = mins;
        if (targets.value.dogsToAssign.length > 0) nextStep();
    }

    function handleRotateStart() {
        const m = new Date().getMinutes();
        const until = m < 30 ? 30 - m : 90 - m;
        const mins = until < 10 ? until + 60 : until;
        const timerType = breakTypes?.find(bt => bt.behavior === 'countdown' && bt.duration_minutes === null);
        if (!timerType) return;
        onInteraction?.();
        targets.value.break_type_id = timerType.id;
        targets.value.rest_minutes = mins;
        if (targets.value.dogsToAssign.length > 0) nextStep();
    }

    function handleNoCabinAssign() {
        onInteraction?.();
        showNoCabinWarning.value = false;
        savedNoCabinDog.value = targets.value.dogsToAssign.find(d => !d.cabin_id) ?? null;
        savedBreakDogs.value = targets.value.dogsToAssign.filter(d => d.cabin_id);
        targets.value = {
            ...targets.value,
            dogsToAssign: savedNoCabinDog.value ? [savedNoCabinDog.value] : [],
            cabin_id: 0,
            cabin_short_name: '',
            break_type_id: null,
        };
        todo.value = 'assignCabin';
    }

    function handleNoCabinDismiss() {
        onInteraction?.();
        showNoCabinWarning.value = false;
        targets.value.dogsToAssign = targets.value.dogsToAssign.filter(d => d.cabin_id);
    }

    function handleBreakDogDelete(dog) {
        targets.value.dogsToAssign = dog;
        todo.value = `markReturned/${dog.id}`;
        nextStep();
    }

    function handleYardChange(pendingMoves) {
        const payload = Object.entries(pendingMoves).map(([dog_id, yard_id]) => ({
            dog_id: Number(dog_id),
            yard_id: Number(yard_id),
        }));
        if (!payload.length) return;
        targets.value.yardsToAssign = payload;
        nextStep();
    }

    async function handleFinishAction(action) {
        onInteraction?.();
        if (action === 'Done' || action === 'More') {
            const isClearFeeding = todo.value === 'clearFeedingCabin';
            axios({
                method: isClearFeeding ? 'DELETE' : 'POST',
                url: isClearFeeding ? '/task/assignFeedingCabin' : `/task/${todo.value}`,
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                data: targets.value,
            }).then((response) => {
                onSuccess?.();
                statusMessage.value = response.data?.message;
                statusClass.value = 'text-meadow';
            }).catch((error) => {
                if (error.response && error.response.status === 419) {
                    if (confirm('Your session has expired due to inactivity. Would you like to reload the page?')) {
                        window.location.reload();
                    }
                } else {
                    statusMessage.value = `Error: ${error.response?.data?.message || 'Unable to complete action'}`;
                    statusClass.value = 'text-alerted';
                }
            });
            statusMessage.value = `Processing ${action} action...`;
            statusClass.value = 'text-greyhound';
        }
        targets.value = {...EMPTY_TARGETS};
        if (todo.value.includes('markReturned')) todo.value = 'startBreak';
        if (todo.value === 'clearFeedingCabin') todo.value = 'assignFeedingCabin';
        step.value = action === 'Done' ? 1 : 3;
        if (savedBreakDogs.value.length > 0 || savedNoCabinDog.value) {
            targets.value.dogsToAssign = [...savedBreakDogs.value, ...(savedNoCabinDog.value ? [savedNoCabinDog.value] : [])];
            savedBreakDogs.value = [];
            savedNoCabinDog.value = null;
            todo.value = 'startBreak';
            step.value = 3;
        }
    }

    return {
        dogs, employees, openYards, statuses, statusMessage, statusClass,
        wiwId, todo, restMinutes, staffImageCache, targets, step,
        showNoCabinWarning, savedBreakDogs, savedNoCabinDog, is1pmOrLater,
        empColumns, empRows, restColumns, restRows, restGridStyle, restCardWidth, restCardHeight,
        dogsOnBreak, dogsNotOnBreak, dogsByCabin, moveDogEnabled, feedingCabinEnabled,
        dogsWithCabinMates, markReturnedIsWalked, breakStatus,
        preloadStaffPhoto, preloadDogPhotos,
        prevStep, nextStep,
        handleEmployeeClick, handleTaskClick, handleTargetClick,
        handleFeedingDogUpdate, handleAssignDogUpdate, addAllBoarders,
        handleBreakDogSelect, handleBreakDogUpdate, handleTimerStart, handleRotateStart,
        handleNoCabinAssign, handleNoCabinDismiss, handleBreakDogDelete,
        handleYardChange, handleFinishAction,
    };
}
