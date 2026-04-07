import puppeteer from 'puppeteer';

const loginUrl = process.argv[2];
const username = process.argv[3];
const password = process.argv[4];

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    try {
        await page.goto(loginUrl, {waitUntil: 'networkidle2'});
        await page.type('input[name="identity"]', username);
        await page.type('input[name="password"]', password);

        await Promise.all([
            page.waitForNavigation({waitUntil: 'networkidle2', timeout: 15000}),
            page.click('button.btn-primary'),
        ]);

        const cookies = await page.cookies();
        console.log(JSON.stringify(cookies.map(c => ({
            name: c.name,
            value: c.value,
            domain: c.domain,
            expires: c.expires,
        }))));
    } catch (error) {
        console.error('Error:', error);
        await page.screenshot({path: 'gingr_scraper_error.png'});
        process.exit(1);
    } finally {
        await browser.close();
    }
})();
