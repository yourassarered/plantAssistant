<script setup>
import { computed, onMounted, ref, watch } from "vue";
import {
    ExternalLink,
    Pencil,
    RefreshCw,
    Save,
    Search,
    Trash2,
    X,
} from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAdminStore } from "@/entities/admin/model/admin.store";
import { useAuthStore } from "@/entities/auth/model/auth.store";
import { apiClient } from "@/shared/api/client";
import { formatIsoDateTime } from "@/shared/lib/date/calendarGrid";
import {
    getReportReasonLabel,
    getReportStatusLabel,
    getReportTypeLabel,
} from "@/shared/lib/reports";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";

const authStore = useAuthStore();
const adminStore = useAdminStore();

const activeTab = ref("reports");
const reportComments = ref({});
const resolutionDialogOpen = ref(false);
const rejectionDialogOpen = ref(false);
const selectedReport = ref(null);
const selectedResolutionAction = ref("");
const auditDialogOpen = ref(false);
const selectedAuditAction = ref(null);
const selectedAuditReport = ref(null);
const auditDetailsLoading = ref(false);
const userSearch = ref("");
const reportStatusFilter = ref("pending");
const reportTargetFilter = ref("");
const userRoleFilter = ref("");
const userSortMode = ref("alphabet");
const trafficMinutes = ref(60);
const editingUserId = ref(null);
const userForm = ref({
    name: "",
    email: "",
    rank: 0,
    warnings_count: 0,
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
    inappropriate_image: "Неподходящее изображение",
    spam: "Спам",
    abuse: "Оскорбления",
    misinformation: "Недостоверная информация",
    other: "Другое",
};

const resolutionActionLabels = {
    tip_delete_rank: "Понизить ранг и удалить совет",
    block_user: "Заблокировать пользователя",
    tip_warn_rank: "Удалить совет, понизить ранг и выдать предупреждение",
    hide_plant: "Скрыть растение навсегда",
    warn_user: "Предупредить пользователя и скрыть растение",
    delete_plant: "Удалить растение",
};

const filteredUsers = computed(() => {
    const query = userSearch.value.trim().toLowerCase();
    const source = query
        ? adminStore.users.filter((user) =>
              [user.name, user.email, user.role?.name]
                  .filter(Boolean)
                  .join(" ")
                  .toLowerCase()
                  .includes(query),
          )
        : adminStore.users;

    const users = [...source];

    if (userSortMode.value === "rank_desc") {
        return users.sort(
            (left, right) => Number(right.rank || 0) - Number(left.rank || 0),
        );
    }

    if (userSortMode.value === "rank_asc") {
        return users.sort(
            (left, right) => Number(left.rank || 0) - Number(right.rank || 0),
        );
    }

    return users.sort((left, right) =>
        String(left.name || left.email || "").localeCompare(
            String(right.name || right.email || ""),
            "ru",
            { sensitivity: "base" },
        ),
    );
});

const editingUser = computed(
    () =>
        adminStore.users.find(
            (user) => Number(user.id) === Number(editingUserId.value),
        ) || null,
);

const userWarningsWillBlock = computed(
    () => Number(userForm.value.warnings_count || 0) >= 3,
);

const displayedReports = computed(() =>
    adminStore.reports.filter((report) => {
        const matchesStatus = reportStatusFilter.value
            ? report.status === reportStatusFilter.value
            : true;
        const matchesTarget = reportTargetFilter.value
            ? report.target_type === reportTargetFilter.value
            : true;

        return matchesStatus && matchesTarget;
    }),
);

const trafficCards = computed(() => [
    {
        value: Number(adminStore.traffic?.total_requests || 0),
        unit: "запросов",
        title: "Всего запросов",
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

const formatTargetType = (type) => reportTypeLabels[type] || type || "Объект";

const formatRole = (roleName) => roleLabels[roleName] || roleName || "Без роли";

const formatReason = (reason) =>
    reportReasonLabels[reason] || reason || "Без причины";

const reportPlantId = (report) =>
    report.target?.plant?.id ||
    (report.target_type === "plant" ? report.target_id : null);

const reportHasPlantLink = (report) => Boolean(reportPlantId(report));

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
        const ownerRank = report.target?.plant?.owner_rank;
        return ownerName
            ? `Владелец: ${ownerName}${ownerRank !== undefined && ownerRank !== null ? ` · ранг ${ownerRank}` : ""}`
            : "Владелец не определён";
    }

    if (report.target_type === "tip") {
        const authorName =
            report.target?.tip?.author_name || "Неизвестный автор";
        const authorRank = report.target?.tip?.author_rank;
        const plantName = report.target?.plant?.name || "растение без названия";
        return `${authorName}${authorRank !== undefined && authorRank !== null ? ` · ранг ${authorRank}` : ""} · растение «${plantName}»`;
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

const resolutionOptions = (report) => {
    if (report?.target_type === "tip") {
        return [
            { value: "tip_delete_rank", hint: "Совет будет удален." },
            {
                value: "block_user",
                hint: "Автор совета потеряет доступ к аккаунту.",
            },
            {
                value: "tip_warn_rank",
                hint: "Совет удалится, ранг снизится, автор получит предупреждение.",
            },
        ];
    }

    return [
        {
            value: "delete_plant",
            hint: "Растение будет полностью удалено из системы.",
        },
        {
            value: "hide_plant",
            hint: "Растение исчезнет из публичной ленты, а повторная публикация будет заблокирована.",
        },
        { value: "block_user", hint: "Владелец потеряет доступ к аккаунту." },
        {
            value: "warn_user",
            hint: "Владелец получит предупреждение, а растение временно скроется из паблика.",
        },
    ];
};

const reportTargetWarnings = (report) =>
    Number(
        report?.target_type === "tip"
            ? report.target?.tip?.author_warnings_count
            : report.target?.plant?.owner_warnings_count,
    ) || 0;

const selectedResolutionIsWarning = computed(() =>
    ["tip_warn_rank", "warn_user"].includes(selectedResolutionAction.value),
);

const selectedResolutionIsFinalWarning = computed(
    () =>
        selectedResolutionIsWarning.value &&
        reportTargetWarnings(selectedReport.value) >= 2,
);

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
    });

const refreshTraffic = () => adminStore.loadTraffic(trafficMinutes.value);

const refreshActiveTab = () => {
    if (!authStore.isAdmin) return;

    if (activeTab.value === "reports") {
        refreshReports();
        return;
    }

    if (activeTab.value === "users") {
        refreshUsers();
        return;
    }

    refreshTraffic();
};

const reportPlantHref = (report) => `/plants/${reportPlantId(report)}`;

const openRejectionDialog = (report) => {
    selectedReport.value = report;
    rejectionDialogOpen.value = true;
};

const review = async (report, status) => {
    if (status === "accepted") {
        selectedReport.value = report;
        selectedResolutionAction.value =
            resolutionOptions(report)[0]?.value || "";
        resolutionDialogOpen.value = true;
        return;
    }

    if (status === "rejected") {
        openRejectionDialog(report);
        return;
    }

    try {
        const finalComment = reportCommentValue(report).trim();
        const updated = await adminStore.reviewReport(
            report.id,
            status,
            finalComment,
        );
        setReportComment(updated.id, updated.admin_comment || finalComment);
        toast.success(
            status === "accepted" ? "Жалоба принята" : "Жалоба отклонена",
        );
    } catch (error) {
        toast.error(error.message);
    }
};

const closeResolutionDialog = () => {
    resolutionDialogOpen.value = false;
    selectedReport.value = null;
    selectedResolutionAction.value = "";
};

const closeRejectionDialog = () => {
    rejectionDialogOpen.value = false;
    selectedReport.value = null;
};

const submitRejection = async () => {
    if (!selectedReport.value) return;

    const comment = reportCommentValue(selectedReport.value).trim();
    if (!comment) {
        toast.error("Добавьте комментарий модератора.");
        return;
    }

    try {
        const updated = await adminStore.reviewReport(
            selectedReport.value.id,
            "rejected",
            comment,
        );
        setReportComment(updated.id, updated.admin_comment || comment);
        toast.success("Жалоба отклонена");
        closeRejectionDialog();
    } catch (error) {
        toast.error(error.message);
    }
};

const openAuditDetails = async (action) => {
    selectedAuditAction.value = action;
    selectedAuditReport.value = null;
    auditDialogOpen.value = true;

    if (action.action !== "report.review" || !action.target_id) return;

    try {
        auditDetailsLoading.value = true;
        const payload = await apiClient.get(
            `/admin/reports/${action.target_id}`,
        );
        selectedAuditReport.value = payload.data || payload;
    } catch (error) {
        toast.error(error.message);
    } finally {
        auditDetailsLoading.value = false;
    }
};

const closeAuditDetails = () => {
    selectedAuditAction.value = null;
    selectedAuditReport.value = null;
    auditDetailsLoading.value = false;
    auditDialogOpen.value = false;
};

const submitResolution = async () => {
    if (!selectedReport.value || !selectedResolutionAction.value) {
        toast.error("Выберите решение по жалобе.");
        return;
    }

    const comment = reportCommentValue(selectedReport.value).trim();

    try {
        const updated = await adminStore.reviewReport(
            selectedReport.value.id,
            "accepted",
            comment,
            selectedResolutionAction.value,
        );
        setReportComment(updated.id, updated.admin_comment || comment);
        toast.success("Жалоба принята, решение применено");
        closeResolutionDialog();
    } catch (error) {
        toast.error(error.message);
    }
};

const blockUser = async (user) => {
    if (!window.confirm(`Заблокировать пользователя ${user.name}?`)) return;

    try {
        await adminStore.blockUser(user.id, "Блокировка администратором.");
        toast.success("Пользователь заблокирован");
    } catch (error) {
        toast.error(error.message);
    }
};

const unblockUser = async (user) => {
    if (!window.confirm(`Разблокировать пользователя ${user.name}?`)) return;

    try {
        await adminStore.unblockUser(user.id);
        toast.success("Пользователь разблокирован");
    } catch (error) {
        toast.error(error.message);
    }
};

const startEditUser = (user) => {
    if (isSelf(user)) {
        toast.info("Свой профиль нельзя редактировать из админки.");
        return;
    }

    editingUserId.value = user.id;
    userForm.value = {
        name: user.name || "",
        email: user.email || "",
        rank: Number(user.rank || 0),
        warnings_count: Number(user.warnings_count || 0),
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
    if (!user) return;

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
            warnings_count: Number(userForm.value.warnings_count || 0),
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

const deleteUserAvatar = async (user) => {
    if (!user?.hasAvatar) {
        toast.info("У пользователя нет аватара.");
        return;
    }

    if (!window.confirm(`Удалить аватар пользователя ${user.name}?`)) return;

    try {
        await adminStore.deleteUserAvatar(user.id);
        toast.success("Аватар пользователя удалён");
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

watch(activeTab, refreshActiveTab);

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
                class="admin-refresh-button"
                @click="refreshActiveTab"
            >
                <RefreshCw :size="17" />
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
                <UiButton variant="ghost" @click="refreshActiveTab">
                    Повторить загрузку
                </UiButton>
            </div>
            <div v-else-if="adminStore.loading" class="panel admin-state">
                Загрузка данных...
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
                </div>

                <div class="reports-grid">
                    <article
                        v-for="report in displayedReports"
                        :key="report.id"
                        class="panel report-item"
                    >
                        <header class="report-item__header">
                            <div class="report-badges">
                                <span
                                    class="report-badge"
                                    :data-tone="report.status"
                                >
                                    {{
                                        report.status_label ||
                                        getReportStatusLabel(report.status)
                                    }}
                                </span>
                                <span class="report-badge report-badge--soft">
                                    {{ getReportTypeLabel(report.target_type) }}
                                </span>
                                <span class="report-id"
                                    >Жалоба #{{ report.id }}</span
                                >
                            </div>
                            <span class="report-date">
                                {{ formatIsoDateTime(report.created_at) }}
                            </span>
                        </header>

                        <div class="report-item__summary">
                            <strong>{{ reportTargetTitle(report) }}</strong>
                            <span>
                                {{
                                    report.reporter?.name ||
                                    "Неизвестный пользователь"
                                }}
                                ·
                                {{
                                    report.reason_label ||
                                    getReportReasonLabel(report.reason)
                                }}
                            </span>
                        </div>

                        <section class="report-context">
                            <div class="report-context__body">
                                <div class="report-context__label">
                                    Контекст объекта
                                </div>
                                <p>{{ reportTargetMeta(report) }}</p>
                                <a
                                    v-if="reportHasPlantLink(report)"
                                    class="report-link report-context__plant-link"
                                    :href="reportPlantHref(report)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Открыть профиль растения"
                                >
                                    <ExternalLink :size="15" />
                                </a>
                                <p
                                    v-if="reportTargetStatus(report)"
                                    class="report-context__status"
                                >
                                    Статус совета:
                                    {{ reportTargetStatus(report) }}
                                </p>
                            </div>

                            <blockquote
                                v-if="report.target?.tip?.content"
                                class="report-quote"
                            >
                                {{ report.target.tip.content }}
                            </blockquote>
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

                        <div
                            v-if="report.status === 'pending'"
                            class="admin-actions"
                        >
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
                </div>

                <div v-if="!displayedReports.length" class="panel admin-state">
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
                    <UiField label="Сортировка">
                        <select v-model="userSortMode">
                            <option value="alphabet">По алфавиту</option>
                            <option value="rank_desc">
                                Сначала высокий ранг
                            </option>
                            <option value="rank_asc">
                                Сначала низкий ранг
                            </option>
                        </select>
                    </UiField>
                    <UiButton
                        variant="ghost"
                        class="admin-filter-button"
                        @click="refreshUsers"
                    >
                        <Search :size="17" />
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
                            <img
                                v-if="user.avatar_url"
                                :src="user.avatar_url"
                                alt=""
                            />
                            <span v-else>{{
                                (user.name || user.email || "?").slice(0, 1)
                            }}</span>
                        </div>
                        <div class="user-main">
                            <strong>{{ user.name }}</strong>
                            <span>{{ user.email || "Email скрыт" }}</span>
                        </div>
                        <div class="user-meta">
                            <span class="role-pill">
                                {{ formatRole(user.role?.name) }}
                            </span>
                            <span
                                >Предупреждения
                                {{ user.warnings_count || 0 }}/3</span
                            >
                            <span v-if="user.is_blocked" class="user-blocked">
                                Заблокирован
                            </span>
                            <span>Ранг {{ user.rank }}</span>
                        </div>
                        <div class="user-actions">
                            <RouterLink
                                class="user-profile-link"
                                :to="`/users/${user.id}`"
                            >
                                <ExternalLink :size="17" />
                                Профиль
                            </RouterLink>
                            <button
                                class="icon-button"
                                type="button"
                                aria-label="Редактировать пользователя"
                                :disabled="isSelf(user)"
                                @click="startEditUser(user)"
                            >
                                <Pencil :size="18" />
                            </button>
                            <button
                                class="icon-danger"
                                type="button"
                                aria-label="Удалить пользователя"
                                :disabled="isSelf(user)"
                                @click="deleteUser(user)"
                            >
                                <Trash2 :size="18" />
                            </button>
                            <UiButton
                                v-if="!user.is_blocked"
                                variant="ghost"
                                :disabled="isSelf(user)"
                                @click="blockUser(user)"
                            >
                                Заблокировать
                            </UiButton>
                        </div>
                    </div>

                    <div v-if="user.is_blocked" class="user-unblock-row">
                        <UiButton
                            variant="ghost"
                            :disabled="isSelf(user)"
                            @click="unblockUser(user)"
                        >
                            Разблокировать
                        </UiButton>
                    </div>
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
                    <UiButton
                        variant="ghost"
                        class="admin-refresh-button"
                        @click="refreshTraffic"
                    >
                        <RefreshCw :size="17" />
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
                            <button
                                v-if="action.action === 'report.review'"
                                class="audit-details-button"
                                type="button"
                                @click="openAuditDetails(action)"
                            >
                                Детали
                            </button>
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

        <Teleport to="body">
            <div
                v-if="resolutionDialogOpen && selectedReport"
                class="resolution-modal"
                @click.self="closeResolutionDialog"
            >
                <section class="panel resolution-dialog">
                    <div class="resolution-dialog__head">
                        <div>
                            <h2 class="panel__title">Выбор решения</h2>
                            <p>
                                {{ reportTargetTitle(selectedReport) }} ·
                                {{
                                    formatTargetType(selectedReport.target_type)
                                }}
                            </p>
                        </div>
                        <button
                            class="resolution-dialog__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeResolutionDialog"
                        >
                            <X :size="18" />
                        </button>
                    </div>

                    <div class="resolution-dialog__body">
                        <label
                            v-for="option in resolutionOptions(selectedReport)"
                            :key="option.value"
                            class="resolution-option"
                        >
                            <input
                                v-model="selectedResolutionAction"
                                type="radio"
                                :value="option.value"
                            />
                            <span>
                                <strong>{{
                                    resolutionActionLabels[option.value]
                                }}</strong>
                                <small>{{ option.hint }}</small>
                            </span>
                        </label>

                        <UiField label="Комментарий модератора">
                            <textarea
                                :value="reportCommentValue(selectedReport)"
                                rows="3"
                                placeholder="Коротко опишите решение"
                                @input="
                                    setReportComment(
                                        selectedReport.id,
                                        $event.target.value,
                                    )
                                "
                            />
                        </UiField>

                        <p
                            v-if="selectedResolutionIsFinalWarning"
                            class="resolution-warning"
                        >
                            Это третье предупреждение: после применения решения
                            аккаунт будет автоматически заблокирован.
                        </p>
                    </div>
                    <div class="resolution-dialog__actions">
                        <UiButton
                            variant="ghost"
                            @click="closeResolutionDialog"
                        >
                            Отмена
                        </UiButton>
                        <UiButton @click="submitResolution">
                            Применить решение
                        </UiButton>
                    </div>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="rejectionDialogOpen && selectedReport"
                class="resolution-modal"
                @click.self="closeRejectionDialog"
            >
                <section class="panel resolution-dialog rejection-dialog">
                    <div class="resolution-dialog__head">
                        <div>
                            <h2 class="panel__title">Отклонить жалобу</h2>
                            <p>
                                {{ reportTargetTitle(selectedReport) }} ·
                                {{
                                    formatTargetType(selectedReport.target_type)
                                }}
                            </p>
                        </div>
                        <button
                            class="resolution-dialog__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeRejectionDialog"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                    <div class="resolution-dialog__body">
                        <UiField label="Комментарий модератора">
                            <textarea
                                :value="reportCommentValue(selectedReport)"
                                rows="4"
                                placeholder="Коротко объясните причину отказа"
                                @input="
                                    setReportComment(
                                        selectedReport.id,
                                        $event.target.value,
                                    )
                                "
                            />
                        </UiField>
                    </div>

                    <div
                        class="resolution-dialog__actions rejection-dialog__actions"
                    >
                        <UiButton @click="submitRejection">
                            Отклонить
                        </UiButton>
                        <UiButton variant="ghost" @click="closeRejectionDialog">
                            Отмена
                        </UiButton>
                    </div>
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="editingUser"
                class="resolution-modal"
                @click.self="cancelEditUser"
            >
                <section class="panel resolution-dialog user-edit-dialog">
                    <div class="resolution-dialog__head">
                        <div>
                            <h2 class="panel__title">
                                Редактировать пользователя
                            </h2>
                            <p>
                                {{ editingUser.name }} ·
                                {{ editingUser.email || "Email скрыт" }}
                            </p>
                        </div>
                        <button
                            class="resolution-dialog__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="cancelEditUser"
                        >
                            <X :size="18" />
                        </button>
                    </div>

                    <form
                        class="user-edit-modal"
                        @submit.prevent="saveUser(editingUser)"
                    >
                        <div class="user-edit-modal__body">
                            <div class="user-avatar-editor">
                                <div class="user-avatar-editor__preview">
                                    <img
                                        v-if="editingUser.avatar_url"
                                        :src="editingUser.avatar_url"
                                        :alt="editingUser.name"
                                    />
                                    <span v-else>
                                        {{
                                            (
                                                editingUser.name ||
                                                editingUser.email ||
                                                "?"
                                            )
                                                .slice(0, 1)
                                                .toUpperCase()
                                        }}
                                    </span>
                                </div>
                                <div class="user-avatar-editor__content">
                                    <strong>Аватар пользователя</strong>
                                    <small>
                                        {{
                                            editingUser.hasAvatar
                                                ? "Можно удалить текущий аватар, если он не подходит правилам."
                                                : "Будет показан стандартный аватар."
                                        }}
                                    </small>
                                    <UiButton
                                        variant="danger"
                                        type="button"
                                        :disabled="!editingUser.hasAvatar"
                                        @click="deleteUserAvatar(editingUser)"
                                    >
                                        <Trash2 :size="16" />
                                        Удалить аватар
                                    </UiButton>
                                </div>
                            </div>
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
                            <UiField label="Предупреждения">
                                <div class="warning-slider">
                                    <input
                                        v-model.number="userForm.warnings_count"
                                        :style="{
                                            '--warning-progress': `${(Number(userForm.warnings_count || 0) / 3) * 100}%`,
                                        }"
                                        max="3"
                                        min="0"
                                        step="1"
                                        type="range"
                                    />
                                    <div
                                        class="warning-slider__ticks"
                                        aria-hidden="true"
                                    >
                                        <span
                                            v-for="point in [0, 1, 2, 3]"
                                            :key="point"
                                            :class="{
                                                active:
                                                    Number(
                                                        userForm.warnings_count ||
                                                            0,
                                                    ) >= point,
                                            }"
                                        >
                                            <i></i>
                                            {{ point }}
                                        </span>
                                    </div>
                                    <div class="warning-slider__meta">
                                        <strong
                                            >{{
                                                userForm.warnings_count || 0
                                            }}/3</strong
                                        >
                                        <span v-if="userWarningsWillBlock">
                                            При сохранении пользователь сразу
                                            будет заблокирован.
                                        </span>
                                    </div>
                                </div>
                            </UiField>
                            <UiField label="Роль">
                                <select
                                    v-model="userForm.role_name"
                                    :disabled="isSelf(editingUser)"
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
                        </div>
                        <div class="user-edit-modal__actions">
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
                </section>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="auditDialogOpen && selectedAuditAction"
                class="resolution-modal"
                @click.self="closeAuditDetails"
            >
                <section class="panel resolution-dialog">
                    <div class="resolution-dialog__head">
                        <div>
                            <h2 class="panel__title">Детали жалобы</h2>
                            <p>{{ formatAuditTitle(selectedAuditAction) }}</p>
                        </div>
                        <button
                            class="resolution-dialog__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeAuditDetails"
                        >
                            <X :size="18" />
                        </button>
                    </div>

                    <div class="resolution-dialog__body">
                        <div class="audit-details">
                            <span v-if="auditDetailsLoading"
                                >Загрузка деталей...</span
                            >
                            <template v-else>
                                <dl class="audit-details__grid">
                                    <div>
                                        <dt>Жалоба</dt>
                                        <dd>
                                            #{{ selectedAuditAction.target_id }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Объект</dt>
                                        <dd>
                                            {{
                                                formatTargetType(
                                                    selectedAuditReport?.target_type ||
                                                        selectedAuditAction
                                                            .payload
                                                            ?.report_target_type,
                                                )
                                            }}
                                            #{{
                                                selectedAuditReport?.target_id ||
                                                selectedAuditAction.payload
                                                    ?.report_target_id
                                            }}
                                        </dd>
                                    </div>
                                    <div v-if="selectedAuditReport">
                                        <dt>Название</dt>
                                        <dd>
                                            {{
                                                reportTargetTitle(
                                                    selectedAuditReport,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div v-if="selectedAuditReport">
                                        <dt>Заявитель</dt>
                                        <dd>
                                            {{
                                                selectedAuditReport.reporter
                                                    ?.name ||
                                                "Неизвестный пользователь"
                                            }}
                                        </dd>
                                    </div>
                                    <div v-if="selectedAuditReport">
                                        <dt>Причина</dt>
                                        <dd>
                                            {{
                                                formatReason(
                                                    selectedAuditReport.reason,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Статус решения</dt>
                                        <dd>
                                            {{
                                                (selectedAuditReport?.status ||
                                                    selectedAuditAction.payload
                                                        ?.status) === "accepted"
                                                    ? "принята"
                                                    : "отклонена"
                                            }}
                                        </dd>
                                    </div>
                                    <div
                                        v-if="
                                            selectedAuditReport?.resolution_action ||
                                            selectedAuditAction.payload
                                                ?.resolution_action
                                        "
                                    >
                                        <dt>Действие</dt>
                                        <dd>
                                            {{
                                                resolutionActionLabels[
                                                    selectedAuditReport?.resolution_action ||
                                                        selectedAuditAction
                                                            .payload
                                                            .resolution_action
                                                ] ||
                                                selectedAuditReport?.resolution_action ||
                                                selectedAuditAction.payload
                                                    .resolution_action
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <div
                                    v-if="selectedAuditReport?.details"
                                    class="audit-details__note"
                                >
                                    <strong>Комментарий пользователя</strong>
                                    <p>{{ selectedAuditReport.details }}</p>
                                </div>
                                <div
                                    v-if="
                                        selectedAuditReport?.resolution_summary
                                    "
                                    class="audit-details__note"
                                >
                                    <strong>Итоговое решение</strong>
                                    <p>
                                        {{
                                            selectedAuditReport.resolution_summary
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="
                                        selectedAuditReport?.admin_comment ||
                                        selectedAuditAction.payload
                                            ?.admin_comment
                                    "
                                    class="audit-details__note"
                                >
                                    <strong>Комментарий модератора</strong>
                                    <p>
                                        {{
                                            selectedAuditReport?.admin_comment ||
                                            selectedAuditAction.payload
                                                .admin_comment
                                        }}
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="resolution-dialog__actions">
                        <UiButton variant="ghost" @click="closeAuditDetails">
                            Закрыть
                        </UiButton>
                    </div>
                </section>
            </div>
        </Teleport>
    </section>
</template>

<style scoped>
.admin-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.admin-tabs button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    gap: 8px;
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-muted);
    cursor: pointer;
    font-weight: 800;
    text-align: center;
    white-space: normal;
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

.admin-refresh-button {
    white-space: nowrap;
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
    grid-template-columns:
        minmax(0, 1.6fr) minmax(180px, 220px) minmax(210px, auto)
        auto;
}

.admin-filters--wide {
    grid-template-columns: minmax(180px, 240px) auto;
    justify-content: start;
}

.admin-filters input,
.admin-filters select,
.user-item select,
.user-edit-modal input,
.user-edit-modal select,
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

.admin-filter-button {
    align-self: end;
    min-height: 42px;
    white-space: nowrap;
}

.report-item {
    display: flex;
    flex-direction: column;
    gap: 14px;
    height: 100%;
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-auto-rows: 1fr;
    gap: 12px;
}

.reports-grid .report-item {
    align-content: start;
}

.report-item__header,
.report-item__summary,
.report-links,
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

.admin-actions {
    display: grid;
    width: 100%;
    align-self: end;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: auto;
}

.admin-actions :deep(.ui-button) {
    width: 100%;
    min-height: 44px;
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
    overflow: hidden;
    border-radius: 50%;
    color: #fff;
    background: var(--color-green);
    font-weight: 900;
    text-transform: uppercase;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.report-context__plant-link {
    width: fit-content;
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
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: end;
    gap: 8px;
}

.user-profile-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 12px;
    border-radius: var(--radius-sm);
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    font-weight: 800;
    text-decoration: none;
}

.user-unblock-row {
    display: flex;
    justify-content: flex-end;
}

.user-blocked {
    width: fit-content;
    color: var(--color-red) !important;
    font-weight: 900;
}

.resolution-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 18px;
    overflow: hidden;
    background: rgba(7, 30, 15, 0.58);
    backdrop-filter: blur(16px);
}

.resolution-dialog {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: 0;
    isolation: isolate;
    width: min(760px, 100%);
    max-width: calc(100vw - 36px);
    max-height: calc(100vh - 36px);
    max-height: calc(100dvh - 36px);
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 16px;
    line-height: 1.55;
    background: var(--color-surface);
    overflow: hidden;
    overscroll-behavior: contain;
    box-shadow: var(--shadow-soft);
}

.resolution-dialog textarea {
    width: 100%;
    min-height: 112px;
    resize: vertical;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: 16px;
    line-height: 1.5;
    background: var(--color-surface);
}

.resolution-dialog__head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    min-width: 0;
    gap: 12px;
    padding: 16px 16px 18px;
    border-bottom: 1px solid rgba(23, 33, 24, 0.08);
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(16px);
    box-shadow: 0 10px 24px rgba(7, 30, 15, 0.05);
}

.resolution-dialog__head > div {
    min-width: 0;
}

.resolution-dialog__head .panel__title,
.resolution-dialog__head p {
    margin: 0;
}

.resolution-dialog__body {
    display: grid;
    gap: 12px;
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 16px 4px;
    overscroll-behavior: contain;
}

.resolution-dialog__actions {
    display: grid;
    grid-template-columns: 1fr;
    width: 100%;
    gap: 10px;
    padding: 14px 16px max(14px, env(safe-area-inset-bottom));
    border-top: 1px solid rgba(23, 33, 24, 0.08);
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.9),
        var(--color-surface) 34%
    );
}

.resolution-dialog__actions :deep(.ui-button) {
    width: 100%;
    min-height: 46px;
}

.rejection-dialog__actions {
    gap: 10px;
}

.resolution-dialog__head p {
    margin-top: 4px;
    color: var(--color-muted);
    font-size: 15px;
    font-weight: 800;
}

.resolution-dialog__close {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    background: var(--color-surface-soft);
    cursor: pointer;
}

.resolution-option {
    display: flex;
    width: 100%;
    gap: 10px;
    align-items: flex-start;
    padding: 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.64);
    cursor: pointer;
}

.resolution-option strong {
    font-size: 16px;
}

.resolution-option small {
    font-size: 14px;
}

.resolution-option span {
    display: grid;
    gap: 4px;
}

.resolution-option small,
.resolution-warning {
    color: var(--color-muted);
    font-weight: 800;
}

.resolution-warning {
    padding: 12px;
    border-radius: var(--radius-sm);
    color: #815b00;
    background: #fff0b8;
}

.user-edit-dialog {
    width: min(680px, 100%);
}

.user-edit-modal {
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto;
    min-height: 0;
    overflow: hidden;
}

.user-edit-modal__body {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    min-width: 0;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 16px 4px;
    overscroll-behavior: contain;
}

.user-avatar-editor {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: 104px minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.user-avatar-editor__preview {
    display: grid;
    width: 96px;
    aspect-ratio: 1 / 1;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    color: var(--color-green-dark);
    background: var(--color-green-soft);
    font-size: 34px;
    font-weight: 900;
}

.user-avatar-editor__preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar-editor__content {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.user-avatar-editor__content strong,
.user-avatar-editor__content small {
    min-width: 0;
    overflow-wrap: anywhere;
}

.user-avatar-editor__content small {
    color: var(--color-muted);
    font-weight: 800;
}

.user-avatar-editor__content :deep(.ui-button) {
    width: 100%;
    min-height: 42px;
}

.warning-slider {
    display: grid;
    gap: 10px;
}

.warning-slider input[type="range"] {
    --warning-progress: 0%;
    appearance: none;
    width: 100%;
    height: 8px;
    border-radius: 999px;
    outline: 0;
    background: linear-gradient(
        90deg,
        var(--color-green) 0 var(--warning-progress),
        #d7ded2 var(--warning-progress) 100%
    );
}

.warning-slider input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 22px;
    height: 22px;
    border: 3px solid #fff;
    border-radius: 50%;
    background: var(--color-green);
    box-shadow: 0 4px 12px rgba(15, 112, 46, 0.28);
    cursor: pointer;
}

.warning-slider input[type="range"]::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border: 3px solid #fff;
    border-radius: 50%;
    background: var(--color-green);
    box-shadow: 0 4px 12px rgba(15, 112, 46, 0.28);
    cursor: pointer;
}

.warning-slider__ticks {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
}

.warning-slider__ticks span {
    display: grid;
    justify-items: center;
    gap: 5px;
    color: var(--color-muted);
    font-size: 12px;
    font-weight: 900;
}

.warning-slider__ticks span:first-child {
    justify-items: start;
}

.warning-slider__ticks span:last-child {
    justify-items: end;
}

.warning-slider__ticks i {
    display: block;
    width: 12px;
    height: 12px;
    border: 2px solid #c8d2c2;
    border-radius: 50%;
    background: var(--color-surface);
}

.warning-slider__ticks span.active {
    color: var(--color-green-dark);
}

.warning-slider__ticks span.active i {
    border-color: var(--color-green);
    background: var(--color-green);
}

.warning-slider__meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
    color: var(--color-muted);
    font-weight: 800;
}

.warning-slider__meta strong {
    color: var(--color-green-dark);
}

.user-edit-modal__actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 14px 16px max(14px, env(safe-area-inset-bottom));
    border-top: 1px solid rgba(23, 33, 24, 0.08);
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.9),
        var(--color-surface) 34%
    );
}

.user-edit-modal__actions :deep(.ui-button) {
    width: 100%;
    min-height: 46px;
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
.icon-button:disabled,
.user-edit-modal select:disabled {
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
    min-width: 0;
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
}

.audit-row:last-child {
    border-bottom: 0;
}

.audit-row__meta {
    white-space: nowrap;
}

.audit-row__body,
.audit-row__body strong,
.audit-row__body span,
.audit-row__meta,
.audit-details,
.audit-details dd,
.audit-details p {
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.audit-details-button {
    margin-left: 8px;
    border: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 900;
}

.audit-details {
    display: grid;
    gap: 14px;
}

.audit-details__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    min-width: 0;
    gap: 12px;
    margin: 0;
}

.audit-details__grid div,
.audit-details__note {
    display: grid;
    min-width: 0;
    gap: 4px;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.62);
}

.audit-details dt,
.audit-details__note strong {
    margin: 0;
    color: var(--color-green-dark);
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.audit-details dd,
.audit-details p {
    margin: 0;
    color: var(--color-muted);
    font-weight: 800;
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

@media (max-width: 1180px) {
    .reports-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .page-header {
        display: grid;
        gap: 12px;
    }

    .admin-refresh-button {
        width: 100%;
    }

    .audit-details__grid,
    .reports-grid,
    .admin-metrics,
    .admin-filters--reports,
    .admin-filters--users,
    .admin-filters--wide,
    .user-edit-modal__body,
    .user-actions {
        grid-template-columns: 1fr;
    }

    .admin-filters {
        align-items: stretch;
        gap: 10px;
    }

    .admin-filter-button {
        width: 100%;
    }

    .reports-grid {
        grid-auto-rows: auto;
    }

    .admin-tabs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .admin-tabs button {
        flex-direction: column;
        gap: 6px;
        min-height: 68px;
        padding: 10px 8px;
        font-size: 13px;
        line-height: 1.15;
    }

    .admin-tabs span {
        min-width: 26px;
        height: 26px;
        font-size: 12px;
    }

    .report-item__header,
    .audit-row {
        align-items: start;
        flex-direction: column;
    }

    .report-item {
        gap: 12px;
        height: auto;
    }

    .report-item__header {
        gap: 10px;
    }

    .report-badges {
        width: 100%;
    }

    .report-date {
        width: 100%;
    }

    .admin-actions {
        display: grid;
        width: 100%;
        gap: 10px;
        margin-top: 0;
    }

    .admin-actions :deep(.ui-button) {
        width: 100%;
        min-height: 44px;
    }

    .user-item {
        gap: 12px;
    }

    .user-item__summary {
        grid-template-columns: 1fr;
    }

    .user-avatar,
    .user-meta,
    .user-actions {
        grid-column: auto;
    }

    .user-avatar {
        width: 52px;
        height: 52px;
    }

    .user-main strong,
    .user-main span {
        white-space: normal;
    }

    .user-actions {
        width: 100%;
        justify-content: stretch;
    }

    .user-profile-link,
    .user-actions :deep(.ui-button),
    .icon-button,
    .icon-danger {
        width: 100%;
    }

    .icon-button,
    .icon-danger {
        height: 42px;
    }

    .user-unblock-row {
        justify-content: stretch;
    }

    .user-unblock-row :deep(.ui-button) {
        width: 100%;
    }

    .user-avatar-editor {
        grid-template-columns: 1fr;
        justify-items: stretch;
    }

    .user-avatar-editor__preview {
        justify-self: center;
    }

    .resolution-dialog {
        width: 100%;
        max-height: min(92dvh, calc(100vh - 20px));
    }

    .resolution-dialog__head,
    .resolution-dialog__body,
    .resolution-dialog__actions,
    .user-edit-modal__body,
    .user-edit-modal__actions {
        padding-left: 14px;
        padding-right: 14px;
    }

    .resolution-dialog__actions,
    .rejection-dialog__actions,
    .user-edit-modal__actions {
        display: grid;
        width: 100%;
        gap: 10px;
    }

    .resolution-dialog__actions :deep(.ui-button),
    .rejection-dialog__actions :deep(.ui-button),
    .user-edit-modal__actions :deep(.ui-button) {
        width: 100%;
        min-height: 44px;
    }

    .audit-row__meta {
        white-space: normal;
    }
}
</style>
