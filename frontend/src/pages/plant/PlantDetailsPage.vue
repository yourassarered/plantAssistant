<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    Droplets,
    Heart,
    Leaf,
    MapPin,
    MessageCircle,
    Pencil,
    Flag,
    RotateCw,
    Scissors,
    Send,
    Trash2,
} from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import CalendarMonthGrid from "@/entities/calendar/ui/CalendarMonthGrid.vue";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useSocialStore } from "@/entities/social/model/social.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { apiCareTypeToUi, careTypes } from "@/shared/lib/careTypes";
import { shiftMonth, toIsoDate } from "@/shared/lib/date/calendarGrid";
import UiBadge from "@/shared/ui/UiBadge.vue";
import UiButton from "@/shared/ui/UiButton.vue";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const plantStore = usePlantStore();
const socialStore = useSocialStore();
const taskStore = useTaskStore();

const tipContent = ref("");
const reportReason = ref("other");
const reportDetails = ref("");
const pageLoading = ref(false);
const pageError = ref("");

const activeCalendarDate = ref(new Date());
const selectedCalendarDate = ref(toIsoDate(new Date()));

const plant = computed(() => plantStore.byId(route.params.id));
const plantApiId = computed(() => plant.value?.apiId || route.params.id);

const tasks = computed(() =>
    taskStore.all.filter((task) => String(task.plantId) === String(plantApiId.value)),
);
const hasCare = computed(() => Boolean(plant.value && Object.keys(plant.value.care || {}).length));
const tips = computed(() => socialStore.tipsFor(plantApiId.value));

const isOwnPlant = computed(
    () =>
        authStore.isAuthenticated &&
        plant.value?.userId &&
        String(plant.value.userId) === String(authStore.user?.id),
);

const canManagePlant = computed(() => authStore.isAdmin || isOwnPlant.value);
const canSuggestForPlant = computed(() => authStore.isAuthenticated && !isOwnPlant.value);
const canReportPlant = computed(() => authStore.isAuthenticated && !isOwnPlant.value);
const canReportTip = computed(() => authStore.isAuthenticated && !isOwnPlant.value);
const canShowCalendar = computed(() => Boolean(plant.value));
const needsFullPlantLoad = computed(
    () =>
        !plant.value ||
        !plant.value.raw?.user ||
        !Array.isArray(plant.value.raw?.care_settings),
);

const authorId = computed(
    () =>
        plant.value?.ownerId ??
        plant.value?.userId ??
        plant.value?.raw?.user_id ??
        plant.value?.raw?.user?.data?.id ??
        plant.value?.raw?.user?.id ??
        null,
);
const authorName = computed(
    () =>
        plant.value?.ownerName ||
        plant.value?.raw?.user?.data?.name ||
        plant.value?.raw?.user?.name ||
        (authorId.value ? `Пользователь #${authorId.value}` : "Пользователь"),
);
const authorRank = computed(() => {
    const rank =
        plant.value?.ownerRank ??
        plant.value?.raw?.user?.data?.rank ??
        plant.value?.raw?.user?.rank;
    return rank === null || rank === undefined ? null : rank;
});

const tipAuthorId = (tip) => String(tip.author?.data?.id || tip.author?.id || "");

const visibleTips = computed(() => {
    const currentUserId = String(authStore.user?.id || "");

    if (isOwnPlant.value) return [];
    if (canManagePlant.value) return tips.value;

    return tips.value.filter((tip) => authStore.isAuthenticated && tipAuthorId(tip) === currentUserId);
});

const reviewTip = computed(() => {
    if (!isOwnPlant.value) return null;

    const currentUserId = String(authStore.user?.id || "");
    const foreignTips = tips.value.filter((tip) => tipAuthorId(tip) !== currentUserId);
    if (!foreignTips.length) return null;

    return [...foreignTips].sort((a, b) => {
        const ta = new Date(a.created_at || a.updated_at || 0).getTime();
        const tb = new Date(b.created_at || b.updated_at || 0).getTime();
        if (tb !== ta) return tb - ta;
        return Number(b.id || 0) - Number(a.id || 0);
    })[0];
});

const tipsCount = computed(() =>
    isOwnPlant.value ? (reviewTip.value ? 1 : 0) : visibleTips.value.length,
);

const likesCount = computed(() => {
    const key = String(plantApiId.value);
    return socialStore.likeCounts[key] ?? plant.value?.likesCount ?? 0;
});
const liked = computed(() => socialStore.isLiked(plantApiId.value));
const careLogs = computed(() => taskStore.logsByPlant[String(plantApiId.value)] || []);

const calendarMarkersByDate = computed(() => {
    const map = {};

    tasks.value.forEach((task) => {
        const key = String(task.dueAt);
        map[key] = map[key] || [];
        map[key].push({ id: `task-${task.id}`, type: task.type });
    });

    careLogs.value.forEach((log) => {
        const key = String(log.performed_at || "").slice(0, 10);
        const uiType = apiCareTypeToUi[log.type];
        if (!key || !uiType) return;
        map[key] = map[key] || [];
        map[key].push({ id: `log-${log.id}`, type: uiType });
    });

    return map;
});

const selectedDayItems = computed(() => {
    const selected = selectedCalendarDate.value;

    const planned = tasks.value
        .filter((task) => String(task.dueAt) === selected)
        .map((task) => ({
            id: `task-${task.id}`,
            title: careTypes[task.type]?.label || task.type,
            meta: "Запланировано",
            date: task.dueAt,
            tone: "soon",
        }));

    const history = careLogs.value
        .filter((log) => String(log.performed_at || "").slice(0, 10) === selected)
        .map((log) => {
            const uiType = apiCareTypeToUi[log.type];
            return {
                id: `log-${log.id}`,
                title: careTypes[uiType]?.label || log.type,
                meta: log.comment || "Выполнено",
                date: String(log.performed_at).slice(0, 16).replace("T", " "),
                tone: "today",
            };
        });

    return [...planned, ...history];
});

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};

const tipStatusLabels = {
    accepted: "Принят",
    rejected: "Отклонён",
    pending: "На проверке",
};

const formatTipStatus = (status) => tipStatusLabels[status] || status;

const loadPage = async () => {
    pageLoading.value = true;
    pageError.value = "";

    try {
        if (!plantStore.all.length) {
            await plantStore.loadPlants("public");
            taskStore.syncFromPlants(plantStore.all);
        }

        if (needsFullPlantLoad.value) {
            await plantStore.loadPlant(route.params.id);
            taskStore.syncFromPlants(plantStore.all);
        }

        if (!plant.value) {
            pageError.value = "Растение не найдено или недоступно.";
            return;
        }

        taskStore.syncFromPlants(plantStore.all);

        if (authStore.isAuthenticated) {
            await socialStore.loadPlantSocial(plantApiId.value);
        }

        if (authStore.isAuthenticated && canManagePlant.value) {
            await taskStore.loadCareLogs(plantApiId.value);
        }
    } catch (error) {
        pageError.value = error?.message || "Не удалось загрузить страницу растения.";
    } finally {
        pageLoading.value = false;
    }
};

const toggleLike = async () => {
    try {
        await socialStore.toggleLike(plantApiId.value);
    } catch (error) {
        toast.error(error.message);
    }
};

const sendTip = async () => {
    const content = tipContent.value.trim();
    if (!content) return;
    if (content.length < 6) {
        toast.error("Совет слишком короткий.");
        return;
    }

    try {
        await socialStore.createTip(plantApiId.value, content);
        tipContent.value = "";
        toast.success("Совет отправлен");
    } catch (error) {
        toast.error(error.message);
    }
};

const reportPlant = async () => {
    if (!reportDetails.value.trim()) {
        toast.error("Добавьте подробности жалобы.");
        return;
    }

    try {
        await socialStore.reportPlant(plantApiId.value, reportReason.value, reportDetails.value);
        reportDetails.value = "";
        toast.success("Жалоба отправлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const reportTip = async (tip) => {
    try {
        await socialStore.reportTip(tip.id, "other", "Жалоба со страницы растения");
        toast.success("Жалоба на совет отправлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const updateTipStatus = async (tip, status) => {
    try {
        await socialStore.updateTipStatus(plantApiId.value, tip.id, status);
        toast.success(status === "accepted" ? "Совет принят" : "Совет отклонён");
    } catch (error) {
        toast.error(error.message);
    }
};

const remove = async () => {
    if (!window.confirm("Удалить это растение навсегда?")) return;

    try {
        await plantStore.deletePlant(plant.value.apiId);
        toast.success("Растение удалено");
        router.push("/feed");
    } catch (error) {
        toast.error(error.message);
    }
};

const openUserProfile = (tip) => {
    const userId = tip.author?.data?.id || tip.author?.id || null;
    if (!userId) return;
    router.push(`/users/${userId}`);
};

const nextMonth = () => {
    activeCalendarDate.value = shiftMonth(activeCalendarDate.value, 1);
};

const prevMonth = () => {
    activeCalendarDate.value = shiftMonth(activeCalendarDate.value, -1);
};

const selectDate = (iso) => {
    selectedCalendarDate.value = iso;
};

onMounted(loadPage);
watch(() => route.params.id, loadPage);
</script>

<template>
    <section v-if="pageLoading" class="page">
        <div class="panel muted">Загружаем детали растения...</div>
    </section>

    <section v-else-if="pageError" class="page">
        <div class="panel error-panel">
            <p class="muted">{{ pageError }}</p>
            <UiButton variant="ghost" @click="loadPage">Повторить</UiButton>
            <RouterLink to="/feed">
                <UiButton>Назад к ленте</UiButton>
            </RouterLink>
        </div>
    </section>

    <section v-if="plant" class="page plant-page">
        <div class="plant-top panel">
            <div class="plant-cover-wrap">
                <img :src="plant.image" :alt="plant.name" class="plant-cover" />
                <UiBadge class="plant-room" tone="soon"><MapPin :size="13" /> {{ plant.room }}</UiBadge>
            </div>

            <div class="plant-main">
                <h1>{{ plant.name }}</h1>
                <p class="muted">{{ plant.note }}</p>

                <RouterLink v-if="authorId" :to="`/users/${authorId}`" class="owner-link">
                    Автор: {{ authorName }}
                    <span v-if="authorRank !== null"> · ранг {{ authorRank }}</span>
                </RouterLink>
                <p v-else class="owner-link owner-link--plain">
                    Автор: {{ authorName }}
                    <span v-if="authorRank !== null"> · ранг {{ authorRank }}</span>
                </p>

                <div class="plant-stats">
                    <div>
                        <strong>{{ plant.height || "нет данных" }}</strong>
                        <span>Высота</span>
                    </div>
                    <div>
                        <strong>{{ likesCount }}</strong>
                        <span>Лайки</span>
                    </div>
                    <div>
                        <strong>{{ plant.isPublic ? "Да" : "Нет" }}</strong>
                        <span>Публичное</span>
                    </div>
                </div>

                <div class="actions-row">
                    <UiButton v-if="authStore.isAuthenticated" variant="ghost" @click="toggleLike">
                        <Heart :size="16" :fill="liked ? 'currentColor' : 'none'" />
                        {{ liked ? "Убрать лайк" : "Лайк" }}
                    </UiButton>
                    <RouterLink v-if="canManagePlant" :to="`/plants/${plant.id}/edit`">
                        <UiButton variant="ghost">
                            <Pencil :size="16" /> Редактировать
                        </UiButton>
                    </RouterLink>
                    <UiButton v-if="canManagePlant" variant="danger" @click="remove">
                        <Trash2 :size="16" /> Удалить
                    </UiButton>
                </div>
            </div>
        </div>

        <div class="desktop-grid">
            <div class="page">
                <section class="panel">
                    <h2 class="panel__title">График ухода</h2>
                    <div v-if="hasCare" class="care-settings">
                        <article v-for="(schedule, type) in plant.care" :key="type">
                            <span :style="{ backgroundColor: careTypes[type].color }">
                                <component :is="icons[type]" :size="18" />
                            </span>
                            <strong>{{ careTypes[type].label }}</strong>
                            <small>каждые {{ schedule.everyDays }} дн. · {{ schedule.nextAt }}</small>
                        </article>
                    </div>
                    <p v-else class="muted">Для этого растения нет видимого графика.</p>
                </section>

                <section v-if="canManagePlant" class="panel">
                    <h2 class="panel__title">Задачи по растению</h2>
                    <div v-if="tasks.length" class="task-stack">
                        <TaskItem v-for="task in tasks" :key="task.id" :task="task" />
                    </div>
                    <p v-else class="muted">Активных задач нет.</p>
                </section>

                <section v-if="canShowCalendar" class="panel care-calendar">
                    <h2 class="panel__title">{{ canManagePlant ? "Календарь и история ухода" : "Календарь ухода" }}</h2>

                    <CalendarMonthGrid
                        :active-date="activeCalendarDate"
                        :selected-date="selectedCalendarDate"
                        :tasks-by-date="calendarMarkersByDate"
                        @next="nextMonth"
                        @prev="prevMonth"
                        @select="selectDate"
                    />

                    <div class="care-calendar__list">
                        <article v-for="item in selectedDayItems" :key="item.id" class="history-row">
                            <strong>{{ item.title }}</strong>
                            <UiBadge :tone="item.tone">{{ item.meta }}</UiBadge>
                            <p>{{ item.date }}</p>
                        </article>
                        <p v-if="!selectedDayItems.length" class="muted">На выбранную дату событий нет.</p>
                    </div>
                </section>
            </div>

            <aside class="page">
                <section class="panel tips-panel">
                    <div class="tips-panel__head">
                        <h2 class="panel__title">Советы</h2>
                        <UiBadge tone="neutral">
                            <MessageCircle :size="13" />
                            {{ tipsCount }}
                        </UiBadge>
                    </div>

                    <form v-if="canSuggestForPlant" class="tip-form" @submit.prevent="sendTip">
                        <textarea v-model="tipContent" rows="3" placeholder="Напишите свой совет" />
                        <UiButton type="submit">
                            <Send :size="16" />
                            Отправить
                        </UiButton>
                    </form>

                    <article v-if="isOwnPlant && reviewTip" class="tip-item tip-item--review">
                        <p class="tip-item__label">Новый совет от другого пользователя</p>
                        <button type="button" class="tip-author" @click="openUserProfile(reviewTip)">
                            {{ reviewTip.author?.data?.name || reviewTip.author?.name || "Пользователь" }}
                        </button>
                        <p>{{ reviewTip.content }}</p>
                        <div class="tip-item__footer">
                            <UiBadge :tone="reviewTip.status === 'accepted' ? 'soon' : 'neutral'">
                                {{ formatTipStatus(reviewTip.status) }}
                            </UiBadge>
                            <div class="tip-actions">
                                <button
                                    v-if="reviewTip.status !== 'accepted'"
                                    type="button"
                                    class="link-button"
                                    @click="updateTipStatus(reviewTip, 'accepted')"
                                >
                                    Принять
                                </button>
                                <button
                                    v-if="reviewTip.status !== 'rejected'"
                                    type="button"
                                    class="link-button"
                                    @click="updateTipStatus(reviewTip, 'rejected')"
                                >
                                    Отклонить
                                </button>
                            </div>
                        </div>
                    </article>

                    <p v-else-if="isOwnPlant" class="muted">Новых советов от других пользователей пока нет.</p>

                    <div v-if="visibleTips.length" class="tips-list">
                        <article v-for="tip in visibleTips" :key="tip.id" class="tip-item">
                            <button type="button" class="tip-author" @click="openUserProfile(tip)">
                                {{ tip.author?.data?.name || tip.author?.name || "Пользователь" }}
                            </button>
                            <p>{{ tip.content }}</p>
                            <div class="tip-item__footer">
                                <UiBadge :tone="tip.status === 'accepted' ? 'soon' : 'neutral'">
                                    {{ formatTipStatus(tip.status) }}
                                </UiBadge>
                                <div v-if="canManagePlant" class="tip-actions">
                                    <button
                                        v-if="tip.status !== 'accepted'"
                                        type="button"
                                        class="link-button"
                                        @click="updateTipStatus(tip, 'accepted')"
                                    >
                                        Принять
                                    </button>
                                    <button
                                        v-if="tip.status !== 'rejected'"
                                        type="button"
                                        class="link-button"
                                        @click="updateTipStatus(tip, 'rejected')"
                                    >
                                        Отклонить
                                    </button>
                                </div>
                                <button
                                    v-if="canReportTip"
                                    type="button"
                                    class="link-button"
                                    @click="reportTip(tip)"
                                >
                                    Пожаловаться
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section v-if="canReportPlant" class="panel report-panel">
                    <h2 class="panel__title">Пожаловаться на растение</h2>
                    <select v-model="reportReason">
                        <option value="inappropriate_image">Неподходящее изображение</option>
                        <option value="spam">Спам</option>
                        <option value="abuse">Оскорбления</option>
                        <option value="misinformation">Недостоверная информация</option>
                        <option value="other">Другое</option>
                    </select>
                    <textarea v-model="reportDetails" rows="3" placeholder="Подробности жалобы" />
                    <UiButton variant="ghost" @click="reportPlant">
                        <Flag :size="16" />
                        Отправить жалобу
                    </UiButton>
                </section>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.plant-page {
    display: grid;
    gap: 12px;
}

.plant-top {
    display: grid;
    gap: 12px;
    padding: 12px;
}

.plant-cover-wrap {
    position: relative;
}

.plant-cover {
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-md);
    object-fit: cover;
}

.plant-room {
    position: absolute;
    top: 10px;
    left: 10px;
}

.plant-main {
    display: grid;
    gap: 10px;
}

.plant-main h1 {
    margin: 0;
    font-size: clamp(24px, 6vw, 34px);
    line-height: 1.02;
}

.owner-link {
    width: fit-content;
    color: var(--color-green-dark);
    font-weight: 800;
    text-decoration: underline;
}

.owner-link--plain {
    margin: 0;
    text-decoration: none;
    cursor: default;
}

.plant-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.plant-stats div {
    display: grid;
    gap: 2px;
    padding: 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.plant-stats span,
.muted,
.history-row p,
.tip-item p {
    color: var(--color-muted);
}

.actions-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.actions-row > * {
    min-width: 0;
}

.actions-row :deep(.ui-button) {
    width: 100%;
    min-height: 56px;
    padding: 0 10px;
}

.care-settings,
.task-stack,
.tips-list,
.tips-panel,
.report-panel,
.care-calendar {
    display: grid;
    gap: 10px;
}

.care-settings article {
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 3px 10px;
    align-items: center;
}

.care-settings span {
    grid-row: span 2;
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border-radius: var(--radius-sm);
    color: #fff;
}

.error-panel {
    display: grid;
    justify-items: start;
    gap: 10px;
}

.tips-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.tip-form {
    display: grid;
    gap: 8px;
}

.tip-form textarea,
.report-panel select,
.report-panel textarea {
    width: 100%;
    resize: vertical;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.tip-item {
    display: grid;
    gap: 6px;
    padding: 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.tip-item--review {
    border: 1px solid #d8e7cf;
    background: #f6fbf2;
}

.tip-item__label {
    margin: 0;
    color: var(--color-green-dark);
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.tip-author {
    width: fit-content;
    border: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
}

.tip-item__footer {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 8px;
}

.tip-actions {
    display: flex;
    gap: 8px;
}

.link-button {
    justify-self: end;
    border: 0;
    color: var(--color-red);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
}

.history-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 4px 10px;
    padding: 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.history-row p {
    grid-column: 1 / -1;
    margin: 0;
}

@media (max-width: 520px) {
    .plant-stats {
        grid-template-columns: 1fr;
    }

    .actions-row {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 900px) {
    .plant-top {
        grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
        align-items: stretch;
        gap: 16px;
    }

    .plant-cover {
        height: 100%;
        min-height: 360px;
        aspect-ratio: auto;
    }
}
</style>
