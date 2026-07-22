export function getFittedFontSize(el, maxWidth, minFontSize = 10, maxFontSize = null, decrement = 2) {
    const canvas = getFittedFontSize.canvas || (getFittedFontSize.canvas = document.createElement('canvas'));
    const context = canvas.getContext('2d');

    const computed = window.getComputedStyle(el);
    let fontSize = maxFontSize ?? parseFloat(computed.fontSize);

    while (fontSize > minFontSize) {
        context.font = `${fontSize}px ${computed.fontFamily}`;
        if (context.measureText(el.innerText).width <= maxWidth * 0.85) break;
        fontSize -= decrement;
    }
    return `${fontSize}px`;
}

export function formatTime(time) {
    if (time && typeof time === 'string') {
        // Extract time part if the string contains a date
        const timeString = time.includes(' ') ? time.split(' ')[1] : time;

        let [hours, minutes] = timeString.split(":");
        hours = parseInt(hours, 10);
        const suffix = hours >= 12 ? "pm" : "am";
        hours = hours % 12 || 12; // Convert to 12-hour format (0 becomes 12)
        return `${hours}:${minutes}${suffix}`.replace(/:00/i, '');
    }
    return null;
}

export function getYardGridStyle(rows, columns, includeFooter = true) {
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gridTemplateRows: includeFooter ? `repeat(${rows}, 1fr) 100px` : `repeat(${rows}, 1fr)`,
        gap: '10px',
    };
}

export function checkoutReservationColor(dog) {
    if (dog?.is_boarding) return '#87b3d1';
    if (dog?.is_daycare) return '#88c999';
    if (dog?.is_interview) return '#9e1b32';
    return '#000000';
}

export function getBannerStyle(currentDog, breakTimeLeft) {
    if (breakTimeLeft?.expired) {
        return {label: 'Return to Yard', class: 'bg-alerted'};
    }
    if (currentDog?.is_boarding) {
        return {label: 'Sleepover', class: 'bg-caregiver'};
    }
    if (currentDog?.is_daycare) {
        return {label: 'Daycamper', class: 'bg-meadow'};
    }
    if (currentDog?.is_interview) {
        return {label: 'Orientation', class: 'bg-crimson'};
    }
    return {label: 'Grooming/Training Only', class: 'bg-greyhound'};
}
