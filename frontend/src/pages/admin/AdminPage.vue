<script setup>
import { computed, onMounted, ref } from "vue";
import { Pencil, Save, ShieldCheck, Trash2, X } from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAdminStore } from "@/entities/admin/model/admin.store";
import { useAuthStore } from "@/entities/auth/model/auth.store";
import { formatIsoDateTime } from "@/shared/lib/date/calendarGrid";
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
const editingUserId = ref(null);
const userForm = ref({
    name: "",
    email: "",
    rank: 0,
    role_name: "user",
    password: "",
    password_confirmation: "",
});

const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();
    if (!query) return adminStore.users;

    return adminStore.users.filter((user) =>
        [user.name, user.email, user.role?.name]
            .filter(Boolean)
            .join(" ")
            .toLowerCase()
            .includes(query),
    );
});

const trafficTotal = computed(() =>
    Number(adminStore.traffic?.total_requests || 0),
);
const trafficPeak = computed(() =>
    Number(adminStore.traffic?.peak_requests_per_minute || 0),
);
const trafficAverage = computed(() =>
    Number(adminStore.traffic?.average_requests_per_minute || 0),
);
const trafficErrorRate = computed(() =>
    Number(adminStore.traffic?.error_rate_percent || 0),
);
const isSelf = (user) => Number(user.id) === Number(authStore.user?.id);

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
        toast.success(
            status === "accepted" ? "Жалоба принята" : "Жалоба отклонена",
        );
    } catch (error) {
        toast.error(error.message);
    }
};

const updateRole = async (user, roleName) => {
    if (
        !window.confirm(
            `Изменить роль пользователя ${user.name} на ${roleName}?`,
        )
    )
        return;

    try {
        await adminStore.updateUserRole(user.id, roleName);
        toast.success("Роль обновлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const startEditUser = (user) => {
    editingUserId.value = user.id;
    userForm.value = {
        name: user.name || "",
        email: user.email || "",
        rank: Number(user.rank || 0),
        role_name: user.role?.name || "user",
        password: "",
        password_confirmation: "",
    };
};

const cancelEditUser = () => {
    editingUserId.value = null;
    userForm.value.password = "";
    userForm.value.password_confirmation = "";
};

const saveUser = async (user) => {
    if (!userForm.value.name.trim()) {
        toast.error("Укажите имя пользователя.");
        return;
    }

    if (!userForm.value.email.trim()) {
        toast.error("Укажите email пользователя.");
        return;
    }

    if (userForm.value.password && userForm.value.password.length < 8) {
        toast.error("Пароль должен быть не короче 8 символов.");
        return;
    }

    if (
        userForm.value.password &&
        userForm.value.password !== userForm.value.password_confirmation
    ) {
        toast.error("Пароль и подтверждение не совпадают.");
        return;
    }

    try {
        const payload = {
            name: userForm.value.name.trim(),
            email: userForm.value.email.trim(),
            rank: Number(userForm.value.rank || 0),
            role_name: userForm.value.role_name,
        };

        if (userForm.value.password) {
            payload.password = userForm.value.password;
            payload.password_confirmation =
                userForm.value.password_confirmation;
        }

        await adminStore.updateUser(user.id, payload);
        toast.success("Пользователь обновлён");
        cancelEditUser();
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
                <p class="page-subtitle">
                    Модерация жалоб, пользователи, роли и технические метрики.
                </p>
            </div>
            <UiButton
                v-if="authStore.isAdmin"
                variant="ghost"
                @click="adminStore.loadAll"
            >
                <ShieldCheck :size="17" />
                Обновить
            </UiButton>
        </header>

        <div v-if="!authStore.isAdmin" class="panel admin-state">
            Доступ только для администратора.
        </div>

        <template v-else>
            <div class="admin-tabs">
                <button
                    :class="{ active: activeTab === 'reports' }"
                    @click="activeTab = 'reports'"
                >
                    Жалобы
                    <span>{{ adminStore.pendingReports.length }}</span>
                </button>
                <button
                    :class="{ active: activeTab === 'users' }"
                    @click="activeTab = 'users'"
                >
                    Пользователи
                    <span>{{ adminStore.users.length }}</span>
                </button>
                <button
                    :class="{ active: activeTab === 'traffic' }"
                    @click="activeTab = 'traffic'"
                >
                    Метрики
                </button>
            </div>

            <div v-if="adminStore.error" class="panel admin-state">
                <p>{{ adminStore.error }}</p>
                <UiButton variant="ghost" @click="adminStore.loadAll"
                    >Повторить загрузку</UiButton
                >
            </div>
            <div v-else-if="adminStore.loading" class="panel admin-state">
                Загрузка админских данных...
            </div>

            <section v-else-if="activeTab === 'reports'" class="admin-list">
                <div class="panel admin-filters">
                    <UiField label="Статус">
                        <select
                            v-model="reportStatusFilter"
                            @change="refreshReports"
                        >
                            <option value="">Все</option>
                            <option value="pending">pending</option>
                            <option value="accepted">accepted</option>
                            <option value="rejected">rejected</option>
                        </select>
                    </UiField>
                    <UiField label="Тип цели">
                        <select
                            v-model="reportTargetFilter"
                            @change="refreshReports"
                        >
                            <option value="">Все</option>
                            <option value="plant">plant</option>
                            <option value="tip">tip</option>
                        </select>
                    </UiField>
                    <UiButton variant="ghost" @click="refreshReports"
                        >Применить фильтры</UiButton
                    >
                </div>

                <article
                    v-for="report in adminStore.reports"
                    :key="report.id"
                    class="panel report-item"
                >
                    <div>
                        <strong
                            >#{{ report.id }} · {{ report.target_type }}
                            {{ report.target_id }}</strong
                        >
                        <span>{{ report.status }} · {{ report.reason }}</span>
                    </div>
                    <p>{{ report.details || "Без деталей" }}</p>
                    <textarea
                        v-model="reportComments[report.id]"
                        rows="2"
                        placeholder="Комментарий модератора"
                    />
                    <div class="admin-actions">
                        <UiButton
                            variant="ghost"
                            @click="review(report, 'rejected')"
                            >Отклонить</UiButton
                        >
                        <UiButton @click="review(report, 'accepted')"
                            >Принять</UiButton
                        >
                    </div>
                </article>
                <div
                    v-if="!adminStore.reports.length"
                    class="panel admin-state"
                >
                    Жалоб нет.
                </div>
            </section>

            <section v-else-if="activeTab === 'users'" class="admin-list">
                <div class="panel admin-filters">
                    <UiField label="Поиск пользователя">
                        <input
                            v-model="userSearch"
                            placeholder="Имя или email"
                            @keyup.enter="refreshUsers"
                        />
                    </UiField>
                    <UiField label="Роль">
                        <select v-model="userRoleFilter" @change="refreshUsers">
                            <option value="">Все</option>
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </UiField>
                    <label class="rank-toggle">
                        <input
                            v-model="sortUsersByRank"
                            type="checkbox"
                            @change="refreshUsers"
                        />
                        Сначала высокий ранг
                    </label>
                    <UiButton variant="ghost" @click="refreshUsers"
                        >Найти</UiButton
                    >
                </div>

                <article
                    v-for="user in filteredUsers"
                    :key="user.id"
                    class="panel user-item"
                >
                    <div class="user-item__summary">
                        <div class="user-avatar">
                            {{ (user.name || user.email || "?").slice(0, 1) }}
                        </div>
                        <div class="user-main">
                            <strong>{{ user.name }}</strong>
                            <span>{{ user.email || "email скрыт" }}</span>
                        </div>
                        <div class="user-meta">
                            <span class="role-pill">{{ user.role?.name }}</span>
                            <span>Ранг {{ user.rank }}</span>
                        </div>
                        <div class="user-actions">
                            <button
                                class="icon-button"
                                type="button"
                                aria-label="Редактировать"
                                @click="startEditUser(user)"
                            >
                                <Pencil :size="18" />
                            </button>
                            <select
                                :value="user.role?.name"
                                :disabled="isSelf(user)"
                                @change="updateRole(user, $event.target.value)"
                            >
                                <option value="user">user</option>
                                <option value="admin">admin</option>
                            </select>
                            <button
                                class="icon-danger"
                                type="button"
                                aria-label="Удалить"
                                :disabled="isSelf(user)"
                                @click="deleteUser(user)"
                            >
                                <Trash2 :size="18" />
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="editingUserId === user.id"
                        class="user-edit"
                        @submit.prevent="saveUser(user)"
                    >
                        <UiField label="Имя">
                            <input v-model="userForm.name" />
                        </UiField>
                        <UiField label="Email">
                            <input v-model="userForm.email" type="email" />
                        </UiField>
                        <UiField label="Ранг">
                            <input
                                v-model.number="userForm.rank"
                                min="0"
                                type="number"
                            />
                        </UiField>
                        <UiField label="Роль">
                            <select
                                v-model="userForm.role_name"
                                :disabled="isSelf(user)"
                            >
                                <option value="user">user</option>
                                <option value="admin">admin</option>
                            </select>
                        </UiField>
                        <UiField label="Новый пароль">
                            <input
                                v-model="userForm.password"
                                autocomplete="new-password"
                                placeholder="Оставьте пустым без изменений"
                                type="password"
                            />
                        </UiField>
                        <UiField label="Повтор пароля">
                            <input
                                v-model="userForm.password_confirmation"
                                autocomplete="new-password"
                                type="password"
                            />
                        </UiField>
                        <div class="user-edit__actions">
                            <UiButton
                                variant="ghost"
                                type="button"
                                @click="cancelEditUser"
                            >
                                <X :size="16" />
                                Отмена
                            </UiButton>
                            <UiButton type="submit">
                                <Save :size="16" />
                                Сохранить
                            </UiButton>
                        </div>
                    </form>
                </article>
                <div v-if="!filteredUsers.length" class="panel admin-state">
                    Пользователи не найдены по текущему фильтру.
                </div>
            </section>

            <section v-else class="admin-metrics">
                <div class="panel admin-filters admin-filters--wide">
                    <UiField label="Окно метрик, минут">
                        <select
                            v-model="trafficMinutes"
                            @change="refreshTraffic"
                        >
                            <option :value="15">15</option>
                            <option :value="60">60</option>
                            <option :value="180">180</option>
                            <option :value="720">720</option>
                        </select>
                    </UiField>
                    <UiButton variant="ghost" @click="refreshTraffic"
                        >Обновить метрики</UiButton
                    >
                </div>

                <article class="panel metric-card">
                    <strong>{{ trafficTotal }}</strong>
                    <span>Всего API-запросов</span>
                    <small
                        >За
                        {{ adminStore.traffic?.window_minutes || 0 }}
                        минут</small
                    >
                </article>
                <article class="panel metric-card">
                    <strong>{{ trafficAverage }}</strong>
                    <span>Среднее в минуту</span>
                    <small>Нагрузка, сглаженная по выбранному окну</small>
                </article>
                <article class="panel metric-card">
                    <strong>{{ trafficPeak }}</strong>
                    <span>Пик запросов в минуту</span>
                    <small>Максимум за один минутный слот</small>
                </article>
                <article class="panel metric-card">
                    <strong>{{
                        adminStore.traffic?.status_totals?.["2xx"] || 0
                    }}</strong>
                    <span>Успешные ответы (2xx)</span>
                    <small>Запросы, завершившиеся успешно</small>
                </article>
                <article class="panel metric-card">
                    <strong>{{
                        adminStore.traffic?.status_totals?.["4xx"] || 0
                    }}</strong>
                    <span>Ошибки клиента (4xx)</span>
                    <small>Например 401, 403, 404, 422</small>
                </article>
                <article class="panel metric-card">
                    <strong>{{ trafficErrorRate }}%</strong>
                    <span>Доля ошибок</span>
                    <small>4xx и 5xx от всех запросов</small>
                </article>

                <section class="panel audit-panel">
                    <h2 class="panel__title">Последние действия модераторов</h2>
                    <article
                        v-for="action in adminStore.traffic
                            ?.recent_moderator_actions || []"
                        :key="action.id"
                        class="audit-row"
                    >
                        <strong>{{ action.action }}</strong>
                        <span
                            >{{ action.actor_name || "system" }} ·
                            {{ formatIsoDateTime(action.created_at) }}</span
                        >
                    </article>
                    <p
                        v-if="
                            !(
                                adminStore.traffic?.recent_moderator_actions ||
                                []
                            ).length
                        "
                        class="admin-state"
                    >
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
.user-item select,
.user-edit input,
.user-edit select {
    width: 100%;
    min-height: 42px;
    padding: 0 10px;
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
    gap: 12px;
}

.user-item__summary {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) minmax(130px, auto) minmax(
            260px,
            auto
        );
    align-items: center;
    gap: 12px;
}

.user-avatar {
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: var(--color-green);
    font-weight: 900;
    text-transform: uppercase;
}

.user-main,
.user-meta {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.user-main strong,
.user-main span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-meta {
    justify-items: start;
}

.role-pill {
    display: inline-grid;
    min-height: 24px;
    padding: 0 9px;
    place-items: center;
    border-radius: 999px;
    color: var(--color-green-dark) !important;
    background: #e8f4df;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.user-actions {
    display: grid;
    grid-template-columns: 38px minmax(110px, 140px) 38px;
    align-items: center;
    justify-content: end;
    gap: 8px;
}

.user-edit {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
}

.user-edit__actions {
    display: flex;
    grid-column: 1 / -1;
    justify-content: flex-end;
    gap: 8px;
}

.icon-button,
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

.icon-button {
    color: var(--color-green-dark);
    background: #e8f4df;
}

.icon-danger:disabled,
.user-actions select:disabled,
.user-edit select:disabled {
    cursor: not-allowed;
    opacity: 0.45;
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

.metric-card {
    align-content: start;
    gap: 4px;
}

.metric-card small {
    color: var(--color-muted);
    line-height: 1.35;
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
    .user-item__summary,
    .user-actions,
    .user-edit,
    .admin-filters,
    .admin-filters--wide {
        grid-template-columns: 1fr;
    }

    .user-actions {
        justify-content: stretch;
    }
}
</style>
