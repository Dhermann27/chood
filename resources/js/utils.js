export function getFittedFontSize(el, maxWidth, minFontSize = 10, decrement = 2) {
    const canvas = getFittedFontSize.canvas || (getFittedFontSize.canvas = document.createElement('canvas'));
    const context = canvas.getContext('2d');

    const computed = window.getComputedStyle(el);
    let fontSize = parseFloat(computed.fontSize);

    while (fontSize > minFontSize) {
        context.font = `${fontSize}px ${computed.fontFamily}`;
        if (context.measureText(el.innerText).width <= maxWidth * 0.8) break;
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

export async function fetchMapData(uri, checksum) {
    try {
        const response = await axios.get(uri + checksum);

        if (response.data && checksum !== response.data?.checksum) {
            return {
                dogs: response.data.dogs,
                statuses: response.data.statuses,
                checksum: response.data.checksum,
            }
        }
    } catch (error) {
        console.error('Error fetching data:', error);
    }
    return false;
}

