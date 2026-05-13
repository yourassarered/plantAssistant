<script setup>
import { computed, onMounted, ref } from "vue";
import { ShieldCheck, Trash2 } from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAdminStore } from "@/entities/admin/model/admin.store";
import { useAuthStore } from "@/entities/auth/model/auth.store";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";

const authStore = useAuthStore();
const adminStore = useAdminStore();
const activeTab = ref("reports");
const reportComments = ref({});
const userSearch = ref("");
const reportStatusFilter = ref("");
const reportTargetFilter = ref("");
const userRoleFilter = ref("");
const sortUsersByRank = ref(false);
const trafficMinutes = ref(60);

const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();
    if (!query) return adminStore.users;

    return adminStore.users.filter((user) =>
        [user.name, user.email, user.role?.name].filter(Boolean).join(" ").toLowerCase().includes(query),
    );
});

const trafficTotal = computed(() =>
    (adminStore.traffic?.traffic_series || []).reduce(
        (sum, point) => sum + Number(point.requests_per_minute || 0),
        0,
    ),
);

const refreshReports = () =>
    adminStore.loadReports({
        status: reportStatusFilter.value,
        targetType: reportTargetFilter.value,
    });

const refreshUsers = () =>
    adminStore.loadUsers({
        search: userSearch.value.trim(),
        role: userRoleFilter.value,
        sortByRank: sortUsersByRank.value,
    });

const refreshTraffic = () => adminStore.loadTraffic(trafficMinutes.value);

const review = async (report, status) => {
    const comment = (reportComments.value[report.id] || "").trim();
    if (status === "rejected" && !comment) {
        toast.error("Для отклонения добавьте комментарий модератора.");
        return;
    }

    try {
        await adminStore.reviewReport(report.id, status, comment);
        toast.success(status === "accepted" ? "Жалоба принята" : "Жалоба отклонена");
    } catch (error) {
        toast.error(error.message);
    }
};

const updateRole = async (user, roleName) => {
    if (!window.confirm(`Изменить роль пользователя ${user.name} на ${roleName}?`)) return;

    try {
        await adminStore.updateUserRole(user.id, roleName);
        toast.success("Роль обновлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const deleteUser = async (user) => {
    if (!window.confirm(`Удалить пользователя ${user.name}?`)) return;

    try {
        await adminStore.deleteUser(user.id);
        toast.success("Пользователь удален");
    } catch (error) {
        toast.error(error.message);
    }
};

onMounted(() => {
    if (authStore.isAdmin) {
        adminStore.loadAll();
    }
});
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Админка</h1>
                <p class="page-subtitle">Модерация жалоб, пользователи, роли и технические метрики.</p>
            </div>
            <UiButton v-if="authStore.isAdmin" variant="ghost" @click="adminStore.loadAll">
                <ShieldCheck :size="17" />
                Обновить
            </UiButton>
        </header>

        <div v-if="!authStore.isAdmin" class="panel admin-state">
            Доступ только для администратора.
        </div>

        <template v-else>
            <div class="admin-tabs">
                <button :class="{ active: activeTab === 'reports' }" @click="activeTab = 'reports'">
                    Жалобы
                    <span>{{ adminStore.pendingReports.length }}</span>
                </button>
                <button :class="{ active: activeTab === 'users' }" @click="activeTab = 'users'">
                    Пользователи
                    <span>{{ adminStore.users.length }}</span>
                </button>
                <button :class="{ active: activeTab === 'traffic' }" @click="activeTab = 'traffic'">
                    Метрики
                </button>
            </div>

            <div v-if="adminStore.error" class="panel admin-state">
                <p>{{ adminStore.error }}</p>
                <UiButton variant="ghost" @click="adminStore.loadAll">Повторить загрузку</UiButton>
            </div>
            <div v-else-if="adminStore.loading" class="panel admin-state">Загрузка админских данных...</div>

            <section v-else-if="activeTab === 'reports'" class="admin-list">
                <div class="panel admin-filters">
                    <UiField label="Статус">
                        <select v-model="reportStatusFilter" @change="refreshReports">
                            <option value="">Все</option>
                            <option value="pending">pending</option>
                            <option value="accepted">accepted</option>
                            <option value="rejected">rejected</option>
                        </select>
                    </UiField>
                    <UiField label="Тип цели">
                        <select v-model="reportTargetFilter" @change="refreshReports">
                            <option value="">Все</option>
                            <option value="plant">plant</option>
                            <option value="tip">tip</option>
                        </select>
                    </UiField>
                    <UiButton variant="ghost" @click="refreshReports">Применить фильтры</UiButton>
                </div>

                <article v-for="report in adminStore.reports" :key="report.id" class="panel report-item">
                    <div>
                        <strong>#{{ report.id }} · {{ report.target_type }} {{ report.target_id }}</strong>
                        <span>{{ report.status }} · {{ report.reason }}</span>
                    </div>
                    <p>{{ report.details || "Без деталей" }}</p>
                    <textarea
                        v-model="reportComments[report.id]"
                        rows="2"
                        placeholder="Комментарий модератора"
                    />
                    <div class="admin-actions">
                        <UiButton variant="ghost" @click="review(report, 'rejected')">Отклонить</UiButton>
                        <UiButton @click="review(report, 'accepted')">Принять</UiButton>
                    </div>
                </article>
                <div v-if="!adminStore.reports.length" class="panel admin-state">Жалоб нет.</div>
            </section>

            <section v-else-if="activeTab === 'users'" class="admin-list">
                <div class="panel admin-filters">
                    <UiField label="Поиск пользователя">
                        <input v-model="userSearch" placeholder="Имя или email" @keyup.enter="refreshUsers" />
                    </UiField>
                    <UiField label="Роль">
                        <select v-model="userRoleFilter" @change="refreshUsers">
                            <option value="">Все</option>
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </UiField>
                    <label class="rank-toggle">
                        <input v-model="sortUsersByRank" type="checkbox" @change="refreshUsers" />
                        Сначала высокий rank
                    </label>
                    <UiButton variant="ghost" @click="refreshUsers">Найти</UiButton>
                </div>

                <article v-for="user in filteredUsers" :key="user.id" class="panel user-item">
                    <div>
                        <strong>{{ user.name }}</strong>
                        <span>{{ user.email || "email скрыт" }} · rank {{ user.rank }}</span>
                    </div>
                    <select :value="user.role?.name" @change="updateRole(user, $event.target.value)">
                        <option value="user">user</option>
                        <option value="admin">admin</option>
                    </select>
                    <button class="icon-danger" type="button" aria-label="Удалить" @click="deleteUser(user)">
                        <Trash2 :size="18" />
                    </button>
                </article>
                <div v-if="!filteredUsers.length" class="panel admin-state">
                    Пользователи не найдены по текущему фильтру.
                </div>
            </section>

            <section v-else class="admin-metrics">
                <div class="panel admin-filters admin-filters--wide">
                    <UiField label="Окно метрик, минут">
                        <select v-model="trafficMinutes" @change="refreshTraffic">
                            <option :value="15">15</option>
                            <option :value="60">60</option>
                            <option :value="180">180</option>
                            <option :value="720">720</option>
                        </select>
                    </UiField>
                    <UiButton variant="ghost" @click="refreshTraffic">Обновить метрики</UiButton>
                </div>

                <article class="panel metric-card">
                    <strong>{{ adminStore.traffic?.window_minutes || 0 }}</strong>
                    <span>минут окна</span>
                </article>
                <article class="panel metric-card">
                    <strong>{{ trafficTotal }}</strong>
                    <span>запросов</span>
                </article>
                <article class="panel metric-card">
                    <strong>{{ adminStore.traffic?.status_totals?.["4xx"] || 0 }}</strong>
                    <span>4xx</span>
                </article>

                <section class="panel audit-panel">
                    <h2 class="panel__title">Последние действия модераторов</h2>
                    <article
                        v-for="action in adminStore.traffic?.recent_moderator_actions || []"
                        :key="action.id"
                        class="audit-row"
                    >
                        <strong>{{ action.action }}</strong>
                        <span>{{ action.actor_name || "system" }} · {{ action.created_at }}</span>
                    </article>
                    <p v-if="!(adminStore.traffic?.recent_moderator_actions || []).length" class="admin-state">
                        Действий модераторов в этом окне пока нет.
                    </p>
                </section>
            </section>
        </template>
    </section>
</template>

<style scoped>
.admin-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
}

.admin-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: pointer;
    font-weight: 800;
}

.admin-tabs button.active {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}

.admin-tabs span {
    display: grid;
    min-width: 22px;
    height: 22px;
    place-items: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.22);
}

.admin-list,
.report-item,
.user-item,
.admin-metrics,
.audit-panel {
    display: grid;
    gap: 10px;
}

.admin-filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: end;
    gap: 12px;
}

.admin-filters--wide {
    grid-column: 1 / -1;
    grid-template-columns: minmax(180px, 260px) auto;
    justify-content: start;
}

.admin-filters select,
.user-item select {
    width: 100%;
    min-height: 42px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.rank-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    color: var(--color-muted);
    font-weight: 800;
}

.report-item span,
.user-item span,
.audit-row span,
.admin-state,
.metric-card span {
    color: var(--color-muted);
}

.report-item textarea {
    width: 100%;
    resize: vertical;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
}

.admin-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.user-item {
    grid-template-columns: minmax(0, 1fr) 140px 38px;
    align-items: center;
}

.icon-danger {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-red);
    background: #ffe0dc;
    cursor: pointer;
}

.admin-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.audit-panel {
    grid-column: 1 / -1;
}

.metric-card strong {
    font-size: 32px;
}

.audit-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-border);
}

@media (max-width: 760px) {
    .admin-metrics,
    .user-item,
    .admin-filters,
    .admin-filters--wide {
        grid-template-columns: 1fr;
    }
}
</style>
