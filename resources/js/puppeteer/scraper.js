import puppeteer from 'puppeteer';
import path from 'path';

const SUBMIT_BUTTON_CSS = 'button[type=submit]'; // Login

const baseuri = process.argv[2];
const username = process.argv[3];
const password = process.argv[4];
const posturi = process.argv[5];

async function waitForOneOfTwoElements(page, selector1, selector2) {
    try {
        await page.waitForNavigation({waitUntil: 'networkidle0'});

        return await Promise.race([
            page.waitForSelector(selector1, {timeout: 10000}), // 10 seconds timeout
            page.waitForSelector(selector2, {timeout: 10000})
        ]);

    } catch (error) {
        console.error('Timeout waiting for elements:', error);
        return null; // Return null if neither element appears
    }
}

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    await page.goto(baseuri);

    await page.$eval(SUBMIT_BUTTON_CSS, el => el.textContent);
    await page.type('input[name="username"]', username);
    await page.type('input[name="password"]', password);
    await page.click('button[type="submit"]');

    try {
        const element = await waitForOneOfTwoElements(page, '.cbw-messaging-error', 'div#in-house');
        if (element) {
            const idValue = await element.getProperty('id');
            const actualId = await idValue.jsonValue();
            if (actualId === 'in-house') {
                const cookies = await page.cookies();
                const cookieData = cookies.map(cookie => ({
                    name: cookie.name,
                    value: cookie.value,
                    expires: cookie.expires,
                    domain: cookie.domain,
                }));

                console.log(JSON.stringify(cookieData));
            }
        }
    } catch (error) {
        console.error('Error: ' + error);
    } finally {
        await browser.close();
    }

})();
