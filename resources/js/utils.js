export function getTextWidth(text, font = '16px Arial') {
    const canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    const context = canvas.getContext("2d");
    context.font = font;
    return context.measureText(text).width;
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

