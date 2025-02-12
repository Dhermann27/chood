import puppeteer from 'puppeteer';

const baseuri = process.argv[2];
const username = process.argv[3];
const password = process.argv[4];
const franchiseIdRegexp = /index.php\/api\/v02\/franchises\/(\d+)/i;

async function waitForOneOfTwoElements(page, selector1, selector2) {
    try {
        return await Promise.race([
            page.waitForSelector(selector1, {timeout: 10000}),
            page.waitForSelector(selector2, {timeout: 10000})
        ]);
    } catch (error) {
        console.error('Timeout waiting for elements:', error);
        return null;
    }
}

async function waitForFranchiseId(page) {
    return new Promise((resolve) => {
        page.on('request', (request) => {
            const url = request.url();
            const match = franchiseIdRegexp.exec(url);
            if (match) {
                const franchiseId = match[1];
                resolve(franchiseId); // Resolve the promise once we have the franchiseId
            }
            request.continue(); // Always continue the request
        });
    });
}

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    let franchiseId = null;

    try {
        await page.setRequestInterception(true);
        // Start listening for franchiseId before submitting the login form
        const franchiseIdPromise = waitForFranchiseId(page);

        // Go to the page and submit the login form
        await page.goto(baseuri, {waitUntil: 'networkidle0'});
        await page.type('input[name="username"]', username);
        await page.type('input[name="password"]', password);
        await page.click('button[type="submit"]');

        // Wait for the page to load and for one of the elements to appear
        const element = await waitForOneOfTwoElements(page, '.cbw-messaging-error', 'div#in-house');

        if (element) {
            const errorElement = await page.$('.cbw-messaging-error');
            if (errorElement) {
                const errorMessage = await errorElement.evaluate(el => el.textContent.trim());
                console.log('Error Message:', errorMessage);
            } else {
                const idValue = await element.getProperty('id');
                const actualId = await idValue.jsonValue();
                if (actualId === 'in-house') {
                    // Wait for the franchiseId to be captured
                    franchiseId = await franchiseIdPromise;

                    const cookies = await page.cookies();
                    const cookieData = cookies.map(cookie => ({
                        name: cookie.name,
                        value: cookie.value,
                        expires: cookie.expires,
                        domain: cookie.domain,
                    }));
                    cookieData.push({
                        name: 'franchiseId',
                        value: franchiseId,
                        expires: -1,
                        domain: '.cbwsoft.com',
                    });

                    console.log(JSON.stringify(cookieData));
                }
            }
        }
    } catch (error) {
        console.error('Error:', error);
        await page.screenshot({path: 'scraper_error.png'});
    } finally {
        await browser.close();
    }
})();
