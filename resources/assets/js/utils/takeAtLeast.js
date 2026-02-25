export function takeAtLeast(promise, ms) {
    const delay = new Promise(resolve => setTimeout(resolve, ms));
    return Promise.all([promise, delay]).then(([result]) => result);
}
