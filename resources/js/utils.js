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

export function datetimeToMinutes(str) {
    if (!str || typeof str !== 'string') return null;
    const time = str.includes(' ') ? str.split(' ')[1] : str;
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
}

export function formatTime(time) {
    const totalMin = datetimeToMinutes(time);
    if (totalMin === null) return null;
    const hours = Math.floor(totalMin / 60);
    const minutes = String(totalMin % 60).padStart(2, '0');
    const suffix = hours >= 12 ? 'pm' : 'am';
    const h12 = hours % 12 || 12;
    return `${h12}:${minutes}${suffix}`.replace(/:00/i, '');
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
