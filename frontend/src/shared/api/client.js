const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";
const TOKEN_KEY = "plant-assistant-token";

export const apiClient = {
    get token() {
        return localStorage.getItem(TOKEN_KEY);
    },

    setToken(token) {
        if (token) {
            localStorage.setItem(TOKEN_KEY, token);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    },

    async request(path, options = {}) {
        const isFormData = options.body instanceof FormData;
        const response = await fetch(`${API_BASE_URL}${path}`, {
            ...options,
            headers: {
                Accept: "application/json",
                ...(options.body && !isFormData ? { "Content-Type": "application/json" } : {}),
                ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
                ...options.headers,
            },
            body: isFormData ? options.body : options.body ? JSON.stringify(options.body) : undefined,
        });

        const payload = response.status === 204 ? null : await response.json().catch(() => null);

        if (!response.ok) {
            const validationMessage = payload?.errors
                ? Object.values(payload.errors).flat().join(" ")
                : "";
            const error = new Error(
                validationMessage || payload?.message || `API request failed: ${response.status}`,
            );
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload;
    },

    get(path) {
        return this.request(path);
    },

    post(path, body) {
        return this.request(path, { method: "POST", body });
    },

    postForm(path, formData) {
        return this.request(path, { method: "POST", body: formData });
    },

    put(path, body) {
        return this.request(path, { method: "PUT", body });
    },

    delete(path) {
        return this.request(path, { method: "DELETE" });
    },
};
