const restApiUrl = window.scm.rest_api_url;
const nonce = window.scm.nonce;

/**
 * Validate the entered api-key
 *
 * @param apiKey
 * @returns {Promise<Boolean>}
 */
export async function connectionCheck(apiKey) {
    const response = await fetch(restApiUrl + 'storychief/migrate/connection_check', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            api_key: apiKey,
        }),
    });

    const json = await response.json();

    return json.data ? json.data.success : false;
}

export async function saveApiKey(apiKey) {
    const response = await fetch(restApiUrl + 'storychief/migrate/save_api_key', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            api_key: apiKey,
        }),
    });

    return await response.json();
}

export async function getApiKey() {
    const response = await fetch(restApiUrl + 'storychief/migrate/get_api_key', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
    });

    return await response.json();
}

export async function fetchDestinations(apiKey) {
    const response = await fetch(restApiUrl + 'storychief/migrate/destinations', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            api_key: apiKey,
        }),
    });

    return await response.json();
}

export function prepareFiltersToSearch(filters) {
    return {
        destination_id: filters.destination.id,
        post_type: filters.postType.name,
        post_status: filters.postStatus,
        category: filters.category?.name,
        tag: filters.tag?.name,
        filter_taxonomy: filters.filterTaxonomy ? filters.filterTaxonomy.name : null,
        filter_terms: filters.filterTaxonomy ? filters.filterTaxonomy.items.filter(tax => tax.checked).map(tax => tax.value) : []
    };
}

export async function fetchPreview(apiKey, searchParams) {
    const response = await fetch(restApiUrl + 'storychief/migrate/preview', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify(searchParams),
    });

    return await response.json();
}

export async function fetchErrors() {
    const response = await fetch(restApiUrl + 'storychief/migrate/errors', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
    });

    return await response.json();
}


export async function retry(apiKey, postId) {
    const response = await fetch(restApiUrl + 'storychief/migrate/retry', {
        method: 'post',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Cache': 'no-cache',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            post_id: postId,
        }),
    });

    return await response.json();
}