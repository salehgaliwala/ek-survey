const DB_NAME = 'ek_survey_db';
const DB_VERSION = 1;
const STORE_NAME = 'submissions';

const dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
            db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        }
    };

    request.onsuccess = (event) => {
        resolve(event.target.result);
    };

    request.onerror = (event) => {
        reject('IndexedDB error: ' + event.target.errorCode);
    };
});

async function saveSubmission(formData) {
    const db = await dbPromise;
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);

    // Convert FormData to a plain object for storage (FormData is not directly serializable in all browsers)
    // However, we need to handle Files carefully.
    // A better approach for FormData is to store it as is if supported, or convert.
    // IndexedDB supports Blobs.

    const data = {};
    for (const [key, value] of formData.entries()) {
        // If it's a file, we can store it directly.
        // If dependencies/multiple values exist, we might need array handling.
        if (data[key]) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    }

    // Add timestamp
    data._timestamp = new Date().toISOString();

    return new Promise((resolve, reject) => {
        const request = store.add(data);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getSubmissions() {
    const db = await dbPromise;
    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);

    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function deleteSubmission(id) {
    const db = await dbPromise;
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);

    return new Promise((resolve, reject) => {
        const request = store.delete(id);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// Export functions globally or via module if using modules (but this is simple WP plugin)
window.ekOffline = {
    saveSubmission,
    getSubmissions,
    deleteSubmission
};
