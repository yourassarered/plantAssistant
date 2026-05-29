import { defineStore } from "pinia";
import { toast } from "vue-sonner";

import { apiClient } from "@/shared/api/client";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        token: apiClient.token,
        loading: false,
        error: "",
        initialized: false,
        initPromise: null,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        isAdmin: (state) => state.user?.role?.name === "admin",
    },
    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = "";

            try {
                const payload = await apiClient.post(
                    "/auth/login",
                    credentials,
                );
                this.token = payload.access_token;
                this.user = payload.user?.data || payload.user;
                apiClient.setToken(payload.access_token);
                await this.loadMe();
                return payload;
            } catch (error) {
                this.error = error.message;
                throw error;
            } finally {
                this.loading = false;
            }
        },
        async register(payload) {
            this.loading = true;
            this.error = "";

            try {
                const response = await apiClient.post(
                    "/auth/register",
                    payload,
                );
                this.token = response.access_token;
                this.user = response.user?.data || response.user;
                apiClient.setToken(response.access_token);
                await this.loadMe();
                return response;
            } catch (error) {
                this.error = error.message;
                throw error;
            } finally {
                this.loading = false;
            }
        },
        async loadMe() {
            if (!this.token) {
                this.initialized = true;
                return null;
            }

            try {
                const payload = await apiClient.get("/auth/me");
                this.user = payload.data || payload;
                this.initialized = true;
                return this.user;
            } catch (error) {
                if (error?.status === 403) {
                    this.error = error.message;
                    toast.error(error.message);
                }
                this.clearSession();
                this.initialized = true;
                return null;
            }
        },
        async ensureMeLoaded() {
            if (this.initialized) return this.user;
            if (this.initPromise) return this.initPromise;

            this.initPromise = this.loadMe().finally(() => {
                this.initPromise = null;
            });

            return this.initPromise;
        },
        clearSession() {
            this.user = null;
            this.token = null;
            apiClient.setToken(null);
            this.initialized = true;
        },
        async logout() {
            try {
                if (this.token) {
                    await apiClient.post("/auth/logout");
                }
            } catch (error) {
                if (error?.status === 403) {
                    toast.error(error.message);
                }
            } finally {
                this.clearSession();
            }
        },
        async updateProfile(values) {
            const payload = await apiClient.put("/users/profile", values);
            this.user = payload.data || payload;
            return this.user;
        },
        async updateAvatar(file) {
            const formData = new FormData();
            formData.append("avatar", file);
            const payload = await apiClient.postForm(
                "/users/profile/avatar",
                formData,
            );
            this.user = payload.data || payload;
            return this.user;
        },
        async deleteAvatar() {
            const payload = await apiClient.delete("/users/profile/avatar");
            this.user = payload.data || payload;
            return this.user;
        },
    },
    persist: {
        key: "plant-assistant-auth",
        pick: ["user", "token"],
        afterRestore: (ctx) => {
            apiClient.setToken(ctx.store.token);
        },
    },
});
