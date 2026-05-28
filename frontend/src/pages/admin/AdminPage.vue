<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { RouterLink } from "vue-router";
import {
    ExternalLink,
    Pencil,
    Save,
    ShieldCheck,
    Trash2,
    X,
} from "lucide-vue-next";
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

const reportStatusLabels = {
    pending: "На рассмотрении",
    accepted: "Принята",
    rejected: "Отклонена",
};

const reportTypeLabels = {
    plant: "Растение",
    tip: "Совет",
};

const roleLabels = {
    user: "Пользователь",
    admin: "Администратор",
};

const reportReasonLabels = {
    spam: "Спам",
    abuse: "Оскорбления",
    other: "Другое",
};

const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();

    if (!query) {
        return adminStore.users;
    }

    return adminStore.users.filter((user) =>
        [user.name, user.email, user.role?.name]
            .filter(Boolean)
            .join(" ")
            .toLowerCase()
            .includes(query),
    );
});

const trafficCards = computed(() => [
    {
        value: Number(adminStore.traffic?.total_requests || 0),
        unit: "запросов",
        title: "Всего API-запросов",
        description: `За ${adminStore.traffic?.window_minutes || 0} минут`,
    },
    {
        value: Number(adminStore.traffic?.average_requests_per_minute || 0),
        unit: "в минуту",
        title: "Средняя нагрузка",
        description: "Сглаженное значение по выбранному окну",
    },
    {
        value: Number(adminStore.traffic?.peak_requests_per_minute || 0),
        unit: "в минуту",
        title: "Пиковая нагрузка",
        description: "Максимум за один минутный слот",
    },
    {
        value: Number(adminStore.traffic?.status_totals?.["2xx"] || 0),
        unit: "ответов",
        title: "Успешные ответы",
        description: "Запросы, завершившиеся без ошибок",
    },
    {
        value: Number(adminStore.traffic?.status_totals?.["4xx"] || 0),
        unit: "ответов",
        title: "Клиентские ошибки",
        description: "Например 401, 403, 404 или 422",
    },
    {
        value: `${Number(adminStore.traffic?.error_rate_percent || 0)} %`,
        unit: "ошибок",
        title: "Доля ошибок",
        description: "Суммарно 4xx и 5xx от всех запросов",
    },
]);

const isSelf = (user) => Number(user.id) === Number(authStore.user?.id);

const formatReportStatus = (status) =>
    reportStatusLabels[status] || status || "Неизвестно";

const formatTargetType = (type) =>
    reportTypeLabels[type] || type || "Объект";

const formatRole = (roleName) => roleLabels[roleName] || roleName || "Без роли";

const formatReason = (reason) =>
    reportReasonLabels[reason] || reason || "Без причины";

const reportPlantId = (report) =>
    report.target?.plant?.id ||
    (report.target_type === "plant" ? report.target_id : null);

const reportHasPlantLink = (report) => Boolean(reportPlantId(report));

const reportPlantRoute = (report) => ({
    name: "plant-details",
    params: { id: reportPlantId(report) },
});

const reportPlantHref = (report) => `/plants/${reportPlantId(report)}`;

const reportTargetTitle = (report) => {
    if (report.target_type === "plant") {
        return report.target?.plant?.name || `Растение #${report.target_id}`;
    }

    if (report.target_type === "tip") {
        return `Совет #${report.target?.tip?.id || report.target_id}`;
    }

    return `Объект #${report.target_id}`;
};

const reportTargetMeta = (report) => {
    if (report.target_type === "plant") {
        const ownerName = report.target?.plant?.owner_name;
        return ownerName
            ? `Владелец: ${ownerName}`
            : "Владелец не определён";
    }

    if (report.target_type === "tip") {
        const authorName = report.target?.tip?.author_name || "Неизвестный автор";
        const plantName = report.target?.plant?.name || "растение без названия";
        return `${authorName} · растение «${plantName}»`;
    }

    return "Контекст объекта недоступен";
};

const reportTargetStatus = (report) => {
    if (report.target_type !== "tip") return "";

    return formatReportStatus(report.target?.tip?.status);
};

const reportReviewMeta = (report) => {
    if (!report.reviewed_at) return "";

    const reviewerName = report.reviewer?.name || "Администратор";
    return `${reviewerName} · ${formatIsoDateTime(report.reviewed_at)}`;
};

const moderationSummary = (report) =>
    report.moderation_effect?.summary || "Автоматические последствия не указаны.";

const reportCommentValue = (report) => reportComments.value[report.id] || "";

const setReportComment = (reportId, value) => {
    reportComments.value = {
        ...reportComments.value,
        [reportId]: value,
    };
};

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
    const comment = reportCommentValue(report).trim();

    if (status === "rejected" && !comment) {
        toast.error("Для отклонения жалобы добавьте комментарий модератора.");
        return;
    }

    try {
        const updated = await adminStore.reviewReport(report.id, status, comment);
        setReportComment(updated.id, updated.admin_comment || comment);
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
            `Изменить роль пользователя ${user.name} на ${formatRole(roleName)}?`,
        )
    ) {
        return;
    }

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
        toast.success("Пользователь удалён");
    } catch (error) {
        toast.error(error.message);
    }
};

const formatAuditTitle = (action) => {
    switch (action.action) {
        case "report.review":
            return action.payload?.status === "accepted"
                ? "Жалоба принята"
                : "Жалоба отклонена";
        case "user.delete":
            return "Удалён пользователь";
        case "user.update":
            return "Обновлён профиль пользователя";
        case "user.role_update":
            return "Изменена роль пользователя";
        case "user.avatar_delete":
            return "Удалён аватар пользователя";
        default:
            return action.action || "Действие администратора";
    }
};

const formatAuditSummary = (action) => {
    switch (action.action) {
        case "report.review": {
            const targetType = formatTargetType(
                action.payload?.report_target_type,
            ).toLowerCase();
            const targetId = action.payload?.report_target_id
                ? ` #${action.payload.report_target_id}`
                : "";
            return `Жалоба на ${targetType}${targetId}.`;
        }
        case "user.delete":
            return action.payload?.name
                ? `${action.payload.name}${action.payload.email ? ` · ${action.payload.email}` : ""}`
                : `Пользователь #${action.target_id}`;
        case "user.update": {
            const chunks = [
                action.payload?.name || `Пользователь #${action.target_id}`,
                action.payload?.email || "",
                Number.isFinite(Number(action.payload?.rank))
                    ? `ранг ${action.payload.rank}`
                    : "",
                action.payload?.role_name
                    ? formatRole(action.payload.role_name).toLowerCase()
                    : "",
            ].filter(Boolean);

            return chunks.join(" · ");
        }
        case "user.role_update":
            return `${action.payload?.name || `Пользователь #${action.target_id}`} · новая роль: ${formatRole(action.payload?.role_name).toLowerCase()}`;
        case "user.avatar_delete":
            return `Пользователь #${action.target_id}`;
        default:
            return "Подробности действия недоступны.";
    }
};

watch(
    () => adminStore.reports,
    (reports) => {
        const nextComments = {};

        for (const report of reports) {
            nextComments[report.id] =
                reportComments.value[report.id] ?? report.admin_comment ?? "";
        }

        reportComments.value = nextComments;
    },
    { immediate: true },
);

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
                <UiButton variant="ghost" @click="adminStore.loadAll">
                    Повторить загрузку
                </UiButton>
            </div>
            <div v-else-if="adminStore.loading" class="panel admin-state">
                Загрузка данных админки...
            </div>

            <section v-else-if="activeTab === 'reports'" class="admin-list">
                <div class="panel admin-filters admin-filters--reports">
                    <UiField label="Статус жалобы">
                        <select
                            v-model="reportStatusFilter"
                            @change="refreshReports"
                        >
                            <option value="">Все</option>
                            <option value="pending">На рассмотрении</option>
                            <option value="accepted">Принятые</option>
                            <option value="rejected">Отклонённые</option>
                        </select>
                    </UiField>
                    <UiField label="Тип объекта">
                        <select
                            v-model="reportTargetFilter"
                            @change="refreshReports"
                        >
                            <option value="">Все</option>
                            <option value="plant">Растение</option>
                            <option value="tip">Совет</option>
                        </select>
                    </UiField>
                    <UiButton variant="ghost" @click="refreshReports">
                        Применить
                    </UiButton>
                </div>

                <article
                    v-for="report in adminStore.reports"
                    :key="report.id"
                    class="panel report-item"
                >
                    <header class="report-item__header">
                        <div class="report-badges">
                            <span
                                class="report-badge"
                                :data-tone="report.status"
                            >
                                {{ formatReportStatus(report.status) }}
                            </span>
                            <span class="report-badge report-badge--soft">
                                {{ formatTargetType(report.target_type) }}
                            </span>
                            <span class="report-id">Жалоба #{{ report.id }}</span>
                        </div>
                        <span class="report-date">
                            {{ formatIsoDateTime(report.created_at) }}
                        </span>
                    </header>

                    <div class="report-item__summary">
                        <strong>{{ reportTargetTitle(report) }}</strong>
                        <span>
                            {{ report.reporter?.name || "Неизвестный пользователь" }}
                            · {{ formatReason(report.reason) }}
                        </span>
                    </div>

                    <section class="report-context">
                        <div class="report-context__body">
                            <div class="report-context__label">
                                Контекст объекта
                            </div>
                            <p>{{ reportTargetMeta(report) }}</p>
                            <p
                                v-if="reportTargetStatus(report)"
                                class="report-context__status"
                            >
                                Статус совета: {{ reportTargetStatus(report) }}
                            </p>
                        </div>

                        <blockquote
                            v-if="report.target?.tip?.content"
                            class="report-quote"
                        >
                            {{ report.target.tip.content }}
                        </blockquote>

                        <div
                            v-if="reportHasPlantLink(report)"
                            class="report-links"
                        >
                            <RouterLink
                                class="report-link"
                                :to="reportPlantRoute(report)"
                            >
                                Открыть растение
                            </RouterLink>
                            <a
                                class="report-link report-link--icon"
                                :href="reportPlantHref(report)"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Открыть растение в новой вкладке"
                            >
                                <ExternalLink :size="16" />
                            </a>
                        </div>
                    </section>

                    <section class="report-note">
                        <div class="report-note__label">
                            Комментарий пользователя
                        </div>
                        <p>
                            {{
                                report.details ||
                                "Пользователь не добавил подробности к жалобе."
                            }}
                        </p>
                    </section>

                    <section class="report-note report-note--effect">
                        <div class="report-note__label">Последствия решения</div>
                        <p>{{ moderationSummary(report) }}</p>
                    </section>

                    <div
                        v-if="report.reviewed_at || report.admin_comment"
                        class="report-review"
                    >
                        <strong>Решение модератора</strong>
                        <span v-if="reportReviewMeta(report)">
                            {{ reportReviewMeta(report) }}
                        </span>
                        <p v-if="report.admin_comment">
                            {{ report.admin_comment }}
                        </p>
                    </div>

                    <textarea
                        :value="reportCommentValue(report)"
                        rows="3"
                        placeholder="Комментарий модератора"
                        @input="
                            setReportComment(report.id, $event.target.value)
                        "
                    />

                    <div class="admin-actions">
                        <UiButton
                            variant="ghost"
                            @click="review(report, 'rejected')"
                        >
                            Отклонить
                        </UiButton>
                        <UiButton @click="review(report, 'accepted')">
                            Принять
                        </UiButton>
                    </div>
                </article>

                <div v-if="!adminStore.reports.length" class="panel admin-state">
                    Жалоб по текущему фильтру нет.
                </div>
            </section>

            <section v-else-if="activeTab === 'users'" class="admin-list">
                <div class="panel admin-filters admin-filters--users">
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
                            <option value="user">Пользователь</option>
                            <option value="admin">Администратор</option>
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
                    <UiButton variant="ghost" @click="refreshUsers">
                        Найти
                    </UiButton>
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
                            <span>{{ user.email || "Email скрыт" }}</span>
                        </div>
                        <div class="user-meta">
                            <span class="role-pill">
                                {{ formatRole(user.role?.name) }}
                            </span>
                            <span>Ранг {{ user.rank }}</span>
                        </div>
                        <div class="user-actions">
                            <button
                                class="icon-button"
                                type="button"
                                aria-label="Редактировать пользователя"
                                @click="startEditUser(user)"
                            >
                                <Pencil :size="18" />
                            </button>
                            <select
                                :value="user.role?.name"
                                :disabled="isSelf(user)"
                                @change="updateRole(user, $event.target.value)"
                            >
                                <option value="user">Пользователь</option>
                                <option value="admin">Администратор</option>
                            </select>
                            <button
                                class="icon-danger"
                                type="button"
                                aria-label="Удалить пользователя"
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
                                <option value="user">Пользователь</option>
                                <option value="admin">Администратор</option>
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
                    Пользователи по текущему фильтру не найдены.
                </div>
            </section>

            <section v-else class="admin-traffic">
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
                    <UiButton variant="ghost" @click="refreshTraffic">
                        Обновить метрики
                    </UiButton>
                </div>

                <div class="admin-metrics">
                    <article
                        v-for="metric in trafficCards"
                        :key="metric.title"
                        class="panel metric-card"
                    >
                        <div class="metric-card__headline">
                            <strong>{{ metric.value }}</strong>
                            <span>{{ metric.unit }}</span>
                        </div>
                        <p class="metric-card__title">{{ metric.title }}</p>
                        <small>{{ metric.description }}</small>
                    </article>
                </div>

                <section class="panel audit-panel">
                    <h2 class="panel__title">Последние действия модераторов</h2>

                    <article
                        v-for="action in adminStore.traffic
                            ?.recent_moderator_actions || []"
                        :key="action.id"
                        class="audit-row"
                    >
                        <div class="audit-row__body">
                            <strong>{{ formatAuditTitle(action) }}</strong>
                            <span>{{ formatAuditSummary(action) }}</span>
                        </div>
                        <div class="audit-row__meta">
                            {{ action.actor_name || "Система" }} ·
                            {{ formatIsoDateTime(action.created_at) }}
                        </div>
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
                        В выбранном окне пока нет действий модераторов.
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
.admin-traffic,
.admin-metrics,
.audit-panel,
.report-item,
.user-item,
.user-main,
.user-meta,
.metric-card,
.audit-row__body {
    display: grid;
    gap: 12px;
}

.admin-filters {
    display: grid;
    align-items: end;
    gap: 12px;
}

.admin-filters--reports {
    grid-template-columns: repeat(2, minmax(0, 220px)) auto;
}

.admin-filters--users {
    grid-template-columns: minmax(0, 1.6fr) minmax(180px, 220px) auto auto;
}

.admin-filters--wide {
    grid-template-columns: minmax(180px, 240px) auto;
    justify-content: start;
}

.admin-filters input,
.admin-filters select,
.user-item select,
.user-edit input,
.user-edit select,
.report-item textarea {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.admin-state,
.report-item__summary span,
.report-date,
.report-context__body p,
.report-note p,
.report-review span,
.report-review p,
.user-main span,
.user-meta span,
.metric-card small,
.audit-row span,
.audit-row__meta {
    color: var(--color-muted);
}

.rank-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    color: var(--color-muted);
    font-weight: 800;
}

.report-item {
    gap: 14px;
}

.report-item__header,
.report-item__summary,
.report-links,
.user-edit__actions,
.admin-actions,
.audit-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.report-item__summary {
    align-items: start;
    flex-direction: column;
    gap: 4px;
}

.report-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.report-badge,
.report-id,
.report-link,
.role-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
}

.report-badge {
    color: #fff;
    background: var(--color-green);
}

.report-badge[data-tone="pending"] {
    color: #815b00;
    background: #fff0b8;
}

.report-badge[data-tone="accepted"] {
    background: var(--color-green);
}

.report-badge[data-tone="rejected"] {
    background: var(--color-red);
}

.report-badge--soft,
.report-id {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.report-context,
.report-note,
.report-review {
    display: grid;
    gap: 10px;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.55);
}

.report-note--effect {
    background: #f6fbf2;
}

.report-context__label,
.report-note__label {
    color: var(--color-green-dark);
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.report-context__status {
    color: var(--color-green-dark) !important;
    font-weight: 700;
}

.report-quote {
    margin: 0;
    padding-left: 12px;
    border-left: 3px solid var(--color-border);
    color: var(--color-text);
    line-height: 1.5;
}

.report-links {
    justify-content: start;
}

.report-link {
    border: 1px solid var(--color-border);
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    text-decoration: none;
}

.report-link--icon {
    width: 28px;
    padding: 0;
}

.user-item {
    gap: 14px;
}

.user-item__summary {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) minmax(150px, auto) minmax(
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

.role-pill {
    width: fit-content;
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    text-transform: uppercase;
}

.user-main strong,
.user-main span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-actions {
    display: grid;
    grid-template-columns: 38px minmax(120px, 150px) 38px;
    align-items: center;
    justify-content: end;
    gap: 8px;
}

.user-edit {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
}

.user-edit__actions {
    grid-column: 1 / -1;
    justify-content: flex-end;
}

.icon-button,
.icon-danger {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    cursor: pointer;
}

.icon-button {
    color: var(--color-green-dark);
    background: var(--color-green-soft);
}

.icon-danger {
    color: var(--color-red);
    background: #ffe0dc;
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

.metric-card {
    align-content: start;
    gap: 8px;
}

.metric-card__headline {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
}

.metric-card__headline strong {
    font-size: 32px;
    line-height: 1;
}

.metric-card__headline span {
    color: var(--color-green-dark);
    font-weight: 800;
}

.metric-card__title {
    margin: 0;
    font-weight: 800;
}

.audit-panel {
    gap: 4px;
}

.audit-row {
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
}

.audit-row:last-child {
    border-bottom: 0;
}

.audit-row__meta {
    white-space: nowrap;
}

@media (max-width: 980px) {
    .admin-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .user-item__summary {
        grid-template-columns: 44px minmax(0, 1fr);
    }

    .user-meta,
    .user-actions {
        grid-column: 2;
    }

    .user-actions {
        justify-content: start;
    }
}

@media (max-width: 760px) {
    .admin-metrics,
    .admin-filters--reports,
    .admin-filters--users,
    .admin-filters--wide,
    .user-edit,
    .user-actions {
        grid-template-columns: 1fr;
    }

    .report-item__header,
    .admin-actions,
    .user-edit__actions,
    .audit-row {
        align-items: start;
        flex-direction: column;
    }

    .audit-row__meta {
        white-space: normal;
    }
}
</style>
