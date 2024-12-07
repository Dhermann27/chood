import puppeteer from 'puppeteer';

const url = process.argv[2];
const cookies = process.argv[3];

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    await page.setRequestInterception(true);
    // Intercept requests and modify the headers
    page.on('request', (request) => {
        // Modify the headers to accept JSON
        const headers = Object.assign({}, request.headers(), {
            'Accept': 'application/json',  // Set the Accept header for JSON
        });
        request.continue({ headers });  // Continue the request with the modified headers
    });


    try {
        const jsonCookies = JSON.parse(cookies);
        await page.setCookie(...jsonCookies);
        const response = await page.goto(url); // Navigate to the URL

        const contentType = response.headers()['content-type'];
        if (contentType && contentType.includes('application/json')) {
            const jsonData = await response.json();
            console.log(JSON.stringify(jsonData));
        } else {
            console.log('Response is not JSON');
        }

    } catch (error) {
        console.error('Error:', error);
    } finally {
        await browser.close();
    }
})();
