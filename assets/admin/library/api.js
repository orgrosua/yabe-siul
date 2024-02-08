import axios from 'axios';

export function useApi(config = {}) {
    return axios.create(Object.assign({
        baseURL: window.siul.rest_api.url,
        headers: {
            'content-type': 'application/json',
            'accept': 'application/json',
            'X-WP-Nonce': window.siul.rest_api.nonce,
        },
    }, config));
}