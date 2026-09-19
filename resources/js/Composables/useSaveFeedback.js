const TAILWIND_RED_CLASSES = Array.from({length: 9}, (_, i) => `bg-red-${(i + 1) * 100}`);

export function useSaveFeedback() {
    const inputRefs = {};

    function setInputRef(key, el) {
        inputRefs[key] = el;
    }

    async function withFeedback(key, fn) {
        const el = inputRefs[key];
        if (el) {
            el.classList.remove(...TAILWIND_RED_CLASSES);
            el.style.backgroundColor = 'gray';
        }
        try {
            await fn();
            if (el) el.style.backgroundColor = 'green';
        } catch (error) {
            console.error(error);
            if (el) el.style.backgroundColor = 'red';
        } finally {
            setTimeout(() => {
                if (el) el.style.backgroundColor = '';
            }, 5000);
        }
    }

    return {setInputRef, withFeedback};
}
