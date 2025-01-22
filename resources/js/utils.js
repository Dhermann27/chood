export function getTextWidth(text) {
    const canvas = getTextWidth.canvas || (getTextWidth.canvas = document.createElement("canvas"));
    const context = canvas.getContext("2d");
    const computedStyle = window.getComputedStyle(text);
    context.font = `${computedStyle.fontWeight} ${computedStyle.fontSize} ${computedStyle.fontFamily}`;
    return context.measureText(text.textContent).width;
}

export async function fetchData(uri, checksum) {
    try {
        const response = await fetch(uri + checksum);
        const newData = await response.json();
        let dogs, outhouseDogs = [];
        if (newData) {
            dogs = newData.dogs;
            outhouseDogs = newData.outhouseDogs;
            checksum = newData.checksum;
        }
        // TODO: Return cabin data with cleaning_status
        return {
            dogs: dogs,
            outhouseDogs: outhouseDogs,
            checksum: checksum,
        };
    } catch (error) {
        console.error('Error fetching data:', error);
    }
    return false;
}

export function scaleObjects() {
    const nameFields = document.querySelectorAll('.dog-name');
    nameFields.forEach((name) => {
        const pct = name.offsetWidth / getTextWidth(name);
        if (pct < 1.05) name.style.fontSize = (parseFloat(name.style.fontSize) * (pct - .02)) + 'px';
    });
    const dogCards = document.querySelectorAll('.dog-boarder, .dog-daycamper');
    dogCards.forEach((card) => {
        const size = Math.min(Math.floor(card.offsetWidth / 10), 100);
        const icons = card.querySelectorAll('.chood-icon');
        icons.forEach((icon) => {
            const faIcon = icon.querySelector('.icon-with-outline');
            if (faIcon) {
                faIcon.classList.remove('text-2xl');
                faIcon.style.fontSize = size + 'px';
            }
            icon.querySelector('span').style.fontSize = (Math.floor(size * .75)) + 'px';
        })
    });
}


export function getYardGridStyle(rows, columns) {
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        gridTemplateRows: `repeat(${rows}, 1fr)`,
        gap: '10px',
    };
}

export function getNewGifAndPosition() {
    return {
        newGif: '/images/doggifs/dog' + (Math.floor(Math.random() * 11) + 1) + '.webp',
        top: Math.random() * (1080 - 480),
        left: Math.random() * (1920 - 480),
    };
}
