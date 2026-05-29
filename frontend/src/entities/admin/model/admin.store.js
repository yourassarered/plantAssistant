import { defineStore } from "pinia";

import { apiClient } from "@/shared/api/client";
import { unwrapApiCollection } from "@/shared/api/mappers";

export const useAdminStore = defineStore("admin", {
    state: () => ({
        users: [],
        reports: [],
        traffic: null,
        loading: false,
        error: "",
    }),
    getters: {
        pendingReports: (state) =>
            state.reports.filter((report) => report.status === "pending"),
    },
    actions: {
        async loadAll() {
            this.loading = true;
            this.error = "";

            try {
                const [usersPayload, reportsPayload, trafficPayload] =
                    await Promise.all([
                        apiClient.get("/users?per_page=100"),
                        apiClient.get("/admin/reports?per_page=100&status=pending"),
                        apiClient.get("/admin/metrics/traffic?minutes=60"),
                    ]);

                this.users = unwrapApiCollection(usersPayload);
                this.reports = unwrapApiCollection(reportsPayload);
                this.traffic = trafficPayload;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async loadReports(filters = {}) {
            this.loading = true;
            this.error = "";

            const params = new URLSearchParams({ per_page: "100" });
            if (filters.status) params.set("status", filters.status);
            if (filters.targetType)
                params.set("target_type", filters.targetType);

            try {
                const payload = await apiClient.get(
                    `/admin/reports?${params.toString()}`,
                );
                this.reports = unwrapApiCollection(payload);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async loadReport(reportId) {
            const payload = await apiClient.get(`/admin/reports/${reportId}`);
            const report = payload.data || payload;
            if (this.reports.some((item) => item.id === report.id)) {
                this.reports = this.reports.map((item) =>
                    item.id === report.id ? report : item,
                );
            }
            return report;
        },
        async loadUsers(filters = {}) {
            this.loading = true;
            this.error = "";

            const params = new URLSearchParams({ per_page: "100" });
            if (filters.search) params.set("search", filters.search);
            if (filters.role) params.set("role", filters.role);
            if (filters.sortByRank) params.set("sort_by_rank", "1");

            try {
                const payload = await apiClient.get(
                    `/users?${params.toString()}`,
                );
                this.users = unwrapApiCollection(payload);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async loadTraffic(minutes = 60) {
            this.loading = true;
            this.error = "";

            try {
                this.traffic = await apiClient.get(
                    `/admin/metrics/traffic?minutes=${minutes}`,
                );
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        async reviewReport(
            reportId,
            status,
            adminComment = "",
            resolutionAction = null,
        ) {
            const payload = await apiClient.put(
                `/admin/reports/${reportId}/review`,
                {
                    status,
                    admin_comment: adminComment,
                    resolution_action: resolutionAction,
                },
            );
            const updated = payload.data || payload;
            this.reports = this.reports
                .map((report) => (report.id === updated.id ? updated : report))
                .filter((report) => report.id !== updated.id);
            return updated;
        },
        async updateUserRole(userId, roleName) {
            const payload = await apiClient.put(`/users/${userId}/role`, {
                role_name: roleName,
            });
            const updated = payload.data || payload;
            this.users = this.users.map((user) =>
                user.id === updated.id ? updated : user,
            );
            return updated;
        },
        async updateUser(userId, values) {
            const payload = await apiClient.put(`/users/${userId}`, values);
            const updated = payload.data || payload;
            this.users = this.users.map((user) =>
                user.id === updated.id ? updated : user,
            );
            return updated;
        },
        async deleteUser(userId) {
            await apiClient.delete(`/users/${userId}`);
            this.users = this.users.filter((user) => user.id !== userId);
        },
        async blockUser(userId, reason = "") {
            const payload = await apiClient.post(`/users/${userId}/block`, {
                reason,
            });
            const updated = payload.data || payload;
            this.users = this.users.map((user) =>
                user.id === updated.id ? updated : user,
            );
            return updated;
        },
    },
});
