<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    ChevronLeft,
    ChevronRight,
    Check,
    Droplets,
    Flag,
    Heart,
    Leaf,
    MapPin,
    Maximize2,
    MessageCircle,
    Images,
    RotateCw,
    Scissors,
    Send,
    Settings2,
    CalendarClock,
    Trash2,
    X,
} from "lucide-vue-next";
import { toast } from "vue-sonner";

import { useAuthStore } from "@/entities/auth/model/auth.store";
import CalendarMonthGrid from "@/entities/calendar/ui/CalendarMonthGrid.vue";
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useSocialStore } from "@/entities/social/model/social.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { apiCareTypeToUi, careTypes } from "@/shared/lib/careTypes";
import {
    formatIsoDate,
    formatIsoDateTime,
    shiftMonth,
    toIsoDate,
} from "@/shared/lib/date/calendarGrid";
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
const plantImages = ref([]);
const activeImageIndex = ref(0);
const galleryDirection = ref("next");
const galleryHasNavigated = ref(false);
const isGalleryFullscreen = ref(false);
const isTipsDialogOpen = ref(false);

const activeCalendarDate = ref(new Date());
const selectedCalendarDate = ref(toIsoDate(new Date()));

const plant = computed(() => plantStore.byId(route.params.id));
const plantApiId = computed(() => plant.value?.apiId || route.params.id);
const galleryImages = computed(() => {
    if (plantImages.value.length) return plantImages.value;
    if (!plant.value?.image) return [];

    return [
        {
            id: "fallback",
            url: plant.value.image,
            originalName: plant.value.name,
        },
    ];
});
const activeImage = computed(
    () => galleryImages.value[activeImageIndex.value] || galleryImages.value[0],
);
const hasMultipleImages = computed(() => galleryImages.value.length > 1);
const galleryTransitionName = computed(() => {
    if (!galleryHasNavigated.value) return "";

    return galleryDirection.value === "next"
        ? "plant-cover-slide-next"
        : "plant-cover-slide-prev";
});

const tasks = computed(() =>
    taskStore.all
        .filter((task) => String(task.plantId) === String(plantApiId.value))
        .filter((task) => !task.completed)
        .map((task) => ({
            ...task,
            everyDays: plant.value?.care?.[task.type]?.everyDays ?? null,
        })),
);
const hasCare = computed(() =>
    Boolean(plant.value && Object.keys(plant.value.care || {}).length),
);
const canShowCareSchedule = computed(
    () => hasCare.value && !canCompleteCareForPlant.value,
);
const tips = computed(() => socialStore.tipsFor(plantApiId.value));

const isOwnPlant = computed(
    () =>
        authStore.isAuthenticated &&
        plant.value?.userId &&
        String(plant.value.userId) === String(authStore.user?.id),
);

const canManagePlant = computed(() => Boolean(plant.value?.canManage));
const canDeletePlant = computed(() => Boolean(plant.value?.canDelete));
const canCompleteCareForPlant = computed(() =>
    Boolean(plant.value?.canCompleteCare),
);
const canSuggestForPlant = computed(
    () => authStore.isAuthenticated && !isOwnPlant.value,
);
const canReportPlant = computed(
    () => authStore.isAuthenticated && !isOwnPlant.value,
);
const canLikePlant = computed(
    () =>
        authStore.isAuthenticated && plant.value?.isPublic && !isOwnPlant.value,
);
const canShowCalendar = computed(() => Boolean(plant.value));

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

const tipAuthorId = (tip) =>
    String(tip.author?.data?.id || tip.author?.id || "");

const TIP_DISPLAY_LIMIT = 3;
const TIP_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;
const ACCEPTED_TIP_VISIBLE_MS = 24 * 60 * 60 * 1000;

const tipDateMs = (...values) => {
    const value = values.find(Boolean);
    if (!value) return null;

    const time = new Date(value).getTime();
    return Number.isNaN(time) ? null : time;
};

const tipDateValue = (...values) => {
    const value = values.find(Boolean);
    if (!value) return "";

    const time = tipDateMs(value);
    return time ? new Date(time).toISOString() : String(value);
};

const formatTipDate = (...values) => {
    const value = tipDateValue(...values);
    return value ? formatIsoDateTime(value) : "";
};

const formatTipCreatedAt = (tip) =>
    formatTipDate(tip.created_at, tip.createdAt);

const formatTipStatusChangedAt = (tip) =>
    formatTipDate(
        tip.status_changed_at,
        tip.statusChangedAt,
        tip.updated_at,
        tip.updatedAt,
    );

const isTipVisibleByAge = (tip) => {
    if (tip.status === "rejected") return false;

    const now = Date.now();
    const createdAt = tipDateMs(tip.created_at, tip.createdAt);

    if (createdAt && now - createdAt > TIP_MAX_AGE_MS) return false;

    if (tip.status === "accepted") {
        const acceptedAt = tipDateMs(
            tip.status_changed_at,
            tip.statusChangedAt,
            tip.updated_at,
            tip.updatedAt,
            tip.created_at,
            tip.createdAt,
        );

        if (acceptedAt && now - acceptedAt > ACCEPTED_TIP_VISIBLE_MS) {
            return false;
        }
    }

    return true;
};

const sortTipsByDateDesc = (items) =>
    [...items].sort((a, b) => {
        const ta =
            tipDateMs(a.created_at, a.updated_at, a.createdAt, a.updatedAt) ||
            0;
        const tb =
            tipDateMs(b.created_at, b.updated_at, b.createdAt, b.updatedAt) ||
            0;
        if (tb !== ta) return tb - ta;
        return Number(b.id || 0) - Number(a.id || 0);
    });

const limitTips = (items) =>
    sortTipsByDateDesc(items.filter(isTipVisibleByAge)).slice(
        0,
        TIP_DISPLAY_LIMIT,
    );

const displayableTips = (items) =>
    sortTipsByDateDesc(items.filter(isTipVisibleByAge));

const visibleTips = computed(() => {
    if (isOwnPlant.value) return [];
    if (canManagePlant.value) return limitTips(tips.value);

    return [];
});

const reviewTips = computed(() => {
    if (!isOwnPlant.value) return [];

    const currentUserId = String(authStore.user?.id || "");
    const foreignTips = tips.value.filter(
        (tip) => tipAuthorId(tip) !== currentUserId,
    );

    return limitTips(foreignTips);
});

const dialogTips = computed(() => {
    if (isOwnPlant.value) {
        const currentUserId = String(authStore.user?.id || "");
        return displayableTips(
            tips.value.filter((tip) => tipAuthorId(tip) !== currentUserId),
        );
    }

    if (canManagePlant.value) return displayableTips(tips.value);

    return [];
});

const tipsCount = computed(() =>
    isOwnPlant.value ? reviewTips.value.length : visibleTips.value.length,
);

const canReportTip = (tip) =>
    authStore.isAuthenticated &&
    tipAuthorId(tip) !== String(authStore.user?.id || "");

const likesCount = computed(() => {
    const key = String(plantApiId.value);
    return socialStore.likeCounts[key] ?? plant.value?.likesCount ?? 0;
});
const liked = computed(() => socialStore.isLiked(plantApiId.value));
const careLogs = computed(
    () => taskStore.logsByPlant[String(plantApiId.value)] || [],
);

const careLogDateKey = (log) => {
    const performedAt = log.performed_at || log.performedAt;
    if (!performedAt) return "";

    const date = new Date(performedAt);
    if (!Number.isNaN(date.getTime())) return toIsoDate(date);

    return String(performedAt).slice(0, 10);
};

const calendarMarkersByDate = computed(() => {
    const map = {};

    tasks.value.forEach((task) => {
        const key = String(task.dueAt);
        map[key] = map[key] || [];
        map[key].push({ id: `task-${task.id}`, type: task.type });
    });

    careLogs.value.forEach((log) => {
        const key = careLogDateKey(log);
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
            date: formatIsoDate(task.dueAt),
        }));

    const history = careLogs.value
        .filter((log) => careLogDateKey(log) === selected)
        .map((log) => {
            const uiType = apiCareTypeToUi[log.type];
            return {
                id: `log-${log.id}`,
                title: careTypes[uiType]?.label || log.type,
                date: formatIsoDateTime(log.performed_at || log.performedAt),
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
    plantImages.value = [];
    activeImageIndex.value = 0;
    galleryHasNavigated.value = false;

    try {
        await plantStore.loadPlant(route.params.id);

        if (!plant.value) {
            pageError.value = "Растение не найдено или недоступно.";
            return;
        }

        taskStore.syncFromPlants(plantStore.all);

        socialStore.applyPlantSnapshot(plant.value);

        try {
            plantImages.value = await plantStore.loadPlantImages(
                plantApiId.value,
            );
        } catch {
            plantImages.value = [];
        }

        if (canLikePlant.value) {
            await socialStore.loadLikeStatus(plantApiId.value);
        }

        if (authStore.isAuthenticated && canCompleteCareForPlant.value) {
            await taskStore.loadCareLogs(plantApiId.value);
        }
    } catch (error) {
        pageError.value =
            error?.message || "Не удалось загрузить страницу растения.";
    } finally {
        pageLoading.value = false;
    }
};

const showPrevImage = () => {
    if (!hasMultipleImages.value) return;
    galleryDirection.value = "prev";
    galleryHasNavigated.value = true;
    activeImageIndex.value =
        (activeImageIndex.value - 1 + galleryImages.value.length) %
        galleryImages.value.length;
};

const showNextImage = () => {
    if (!hasMultipleImages.value) return;
    galleryDirection.value = "next";
    galleryHasNavigated.value = true;
    activeImageIndex.value =
        (activeImageIndex.value + 1) % galleryImages.value.length;
};

const selectImage = (index) => {
    if (index === activeImageIndex.value) return;

    galleryDirection.value = index < activeImageIndex.value ? "prev" : "next";
    galleryHasNavigated.value = true;
    activeImageIndex.value = index;
};

const openGalleryFullscreen = () => {
    if (!activeImage.value) return;
    isGalleryFullscreen.value = true;
};

const closeGalleryFullscreen = () => {
    isGalleryFullscreen.value = false;
};

const toggleLike = async () => {
    try {
        if (!canLikePlant.value) return;
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
        await socialStore.reportPlant(
            plantApiId.value,
            reportReason.value,
            reportDetails.value,
        );
        reportDetails.value = "";
        toast.success("Жалоба отправлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const reportTip = async (tip) => {
    try {
        await socialStore.reportTip(
            tip.id,
            "other",
            "Жалоба со страницы растения",
        );
        toast.success("Жалоба на совет отправлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const updateTipStatus = async (tip, status) => {
    try {
        await socialStore.updateTipStatus(plantApiId.value, tip.id, status);
        toast.success(
            status === "accepted" ? "Совет принят" : "Совет отклонён",
        );
    } catch (error) {
        toast.error(error.message);
    }
};

const remove = async () => {
    if (!canDeletePlant.value) return;
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
        <div class="plant-hero-grid">
            <div class="plant-top panel">
                <div class="plant-cover-wrap">
                    <button
                        type="button"
                        class="plant-cover-button"
                        aria-label="Открыть фото на весь экран"
                        @click="openGalleryFullscreen"
                    >
                        <Transition :name="galleryTransitionName" mode="out-in">
                            <img
                                :key="
                                    activeImage?.id ||
                                    activeImage?.url ||
                                    plant.image
                                "
                                :src="activeImage?.url || plant.image"
                                :alt="activeImage?.originalName || plant.name"
                                class="plant-cover"
                            />
                        </Transition>
                        <span class="gallery-open-icon">
                            <Maximize2 :size="18" />
                        </span>
                    </button>
                    <UiBadge class="plant-room" tone="soon"
                        ><MapPin :size="13" /> {{ plant.room }}</UiBadge
                    >
                    <button
                        v-if="hasMultipleImages"
                        type="button"
                        class="gallery-nav gallery-nav--prev"
                        aria-label="Предыдущее фото"
                        @click="showPrevImage"
                    >
                        <ChevronLeft :size="22" />
                    </button>
                    <button
                        v-if="hasMultipleImages"
                        type="button"
                        class="gallery-nav gallery-nav--next"
                        aria-label="Следующее фото"
                        @click="showNextImage"
                    >
                        <ChevronRight :size="22" />
                    </button>
                    <UiBadge
                        v-if="hasMultipleImages"
                        class="gallery-count"
                        tone="neutral"
                    >
                        {{ activeImageIndex + 1 }} / {{ galleryImages.length }}
                    </UiBadge>
                    <div v-if="hasMultipleImages" class="gallery-thumbs">
                        <button
                            v-for="(image, index) in galleryImages"
                            :key="image.id"
                            type="button"
                            :class="{ 'is-active': index === activeImageIndex }"
                            @click="selectImage(index)"
                        >
                            <img :src="image.url" :alt="image.originalName" />
                        </button>
                    </div>
                </div>

                <div class="plant-main">
                    <h1>{{ plant.name }}</h1>
                    <p class="muted">{{ plant.note }}</p>

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
                        <UiButton
                            v-if="canLikePlant"
                            variant="ghost"
                            @click="toggleLike"
                        >
                            <Heart
                                :size="16"
                                :fill="liked ? 'currentColor' : 'none'"
                            />
                            {{ liked ? "Убрать лайк" : "Лайк" }}
                        </UiButton>
                        <RouterLink
                            v-if="canManagePlant"
                            :to="`/plants/${plant.id}/edit`"
                        >
                            <UiButton variant="ghost">
                                <Settings2 :size="16" /> Растение
                            </UiButton>
                        </RouterLink>
                        <RouterLink
                            v-if="canManagePlant"
                            :to="`/plants/${plant.id}/care`"
                        >
                            <UiButton variant="ghost">
                                <CalendarClock :size="16" /> Уход
                            </UiButton>
                        </RouterLink>
                        <RouterLink
                            v-if="canManagePlant"
                            :to="`/plants/${plant.id}/photos`"
                            class="action-icon-link"
                        >
                            <UiButton
                                class="icon-action-button"
                                variant="ghost"
                                aria-label="Фото"
                            >
                                <Images :size="18" />
                            </UiButton>
                        </RouterLink>
                        <UiButton
                            v-if="canDeletePlant"
                            class="delete-plant-button icon-action-button"
                            variant="danger"
                            aria-label="Удалить"
                            @click="remove"
                        >
                            <Trash2 :size="18" />
                        </UiButton>
                    </div>
                </div>
            </div>

            <section v-if="canShowCalendar" class="panel care-calendar">
                <h2 class="panel__title">
                    {{
                        canCompleteCareForPlant
                            ? "Календарь и история ухода"
                            : "Календарь ухода"
                    }}
                </h2>

                <CalendarMonthGrid
                    :active-date="activeCalendarDate"
                    :selected-date="selectedCalendarDate"
                    :tasks-by-date="calendarMarkersByDate"
                    @next="nextMonth"
                    @prev="prevMonth"
                    @select="selectDate"
                />

                <div class="care-calendar__list">
                    <article
                        v-for="item in selectedDayItems"
                        :key="item.id"
                        class="history-row"
                    >
                        <strong>{{ item.title }}</strong>
                        <p>{{ item.date }}</p>
                    </article>
                    <p v-if="!selectedDayItems.length" class="muted">
                        На выбранную дату событий нет.
                    </p>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="isGalleryFullscreen"
                class="gallery-fullscreen"
                @click="closeGalleryFullscreen"
            >
                <button
                    type="button"
                    class="gallery-fullscreen__close"
                    aria-label="Закрыть фото"
                    @click.stop="closeGalleryFullscreen"
                >
                    <X :size="24" />
                </button>
                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="gallery-fullscreen__nav gallery-fullscreen__nav--prev"
                    aria-label="Предыдущее фото"
                    @click.stop="showPrevImage"
                >
                    <ChevronLeft :size="32" />
                </button>
                <Transition :name="galleryTransitionName" mode="out-in">
                    <img
                        :key="
                            activeImage?.id || activeImage?.url || plant.image
                        "
                        class="gallery-fullscreen__image"
                        :src="activeImage?.url || plant.image"
                        :alt="activeImage?.originalName || plant.name"
                        @click.stop
                    />
                </Transition>
                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="gallery-fullscreen__nav gallery-fullscreen__nav--next"
                    aria-label="Следующее фото"
                    @click.stop="showNextImage"
                >
                    <ChevronRight :size="32" />
                </button>
                <div
                    v-if="hasMultipleImages"
                    class="gallery-fullscreen__thumbs"
                    @click.stop
                >
                    <button
                        v-for="(image, index) in galleryImages"
                        :key="image.id"
                        type="button"
                        :class="{ 'is-active': index === activeImageIndex }"
                        @click="selectImage(index)"
                    >
                        <img :src="image.url" :alt="image.originalName" />
                    </button>
                </div>
            </div>
        </Teleport>

        <div class="desktop-grid">
            <div class="page plant-secondary">
                <section v-if="canShowCareSchedule" class="panel">
                    <h2 class="panel__title">График ухода</h2>
                    <div class="care-settings">
                        <article
                            v-for="(schedule, type) in plant.care"
                            :key="type"
                        >
                            <span
                                :style="{
                                    backgroundColor: careTypes[type].color,
                                }"
                            >
                                <component :is="icons[type]" :size="18" />
                            </span>
                            <strong>{{ careTypes[type].label }}</strong>
                            <small
                                >каждые {{ schedule.everyDays }} дн. ·
                                {{ formatIsoDate(schedule.nextAt) }}</small
                            >
                        </article>
                    </div>
                </section>

                <section v-if="canCompleteCareForPlant" class="panel">
                    <h2 class="panel__title">Задачи по растению</h2>
                    <TransitionGroup
                        v-if="tasks.length"
                        name="task-replace"
                        tag="div"
                        class="task-stack"
                    >
                        <TaskItem
                            v-for="task in tasks"
                            :key="task.id"
                            :task="task"
                        />
                    </TransitionGroup>
                    <p v-else class="muted">Активных задач нет.</p>
                </section>
            </div>

            <aside class="page plant-sidebar">
                <section class="panel tips-panel">
                    <div class="tips-panel__head">
                        <h2 class="panel__title">Советы</h2>
                        <UiBadge tone="neutral">
                            <MessageCircle :size="13" />
                            {{ tipsCount }}
                        </UiBadge>
                        <button
                            v-if="dialogTips.length"
                            type="button"
                            class="link-button"
                            @click="isTipsDialogOpen = true"
                        >
                            Все
                        </button>
                    </div>

                    <form
                        v-if="canSuggestForPlant"
                        class="tip-form"
                        @submit.prevent="sendTip"
                    >
                        <textarea
                            v-model="tipContent"
                            rows="3"
                            placeholder="Напишите свой совет"
                        />
                        <UiButton type="submit">
                            <Send :size="16" />
                            Отправить
                        </UiButton>
                    </form>

                    <div
                        v-if="isOwnPlant && reviewTips.length"
                        class="tips-list"
                    >
                        <article
                            v-for="tip in reviewTips"
                            :key="tip.id"
                            class="tip-item tip-item--review"
                        >
                            <div class="tip-item__header">
                                <div class="tip-item__author-block">
                                    <button
                                        type="button"
                                        class="tip-author"
                                        @click="openUserProfile(tip)"
                                    >
                                        {{
                                            tip.author?.data?.name ||
                                            tip.author?.name ||
                                            "\u041f\u043e\u043b\u044c\u0437\u043e\u0432\u0430\u0442\u0435\u043b\u044c"
                                        }}
                                    </button>
                                </div>
                                <div class="tip-actions">
                                    <button
                                        v-if="tip.status === 'pending'"
                                        type="button"
                                        class="tip-icon-button tip-icon-button--accept"
                                        aria-label="&#1055;&#1088;&#1080;&#1085;&#1103;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="
                                            updateTipStatus(tip, 'accepted')
                                        "
                                    >
                                        <Check :size="16" />
                                    </button>
                                    <button
                                        v-if="tip.status === 'pending'"
                                        type="button"
                                        class="tip-icon-button tip-icon-button--danger"
                                        aria-label="&#1054;&#1090;&#1082;&#1083;&#1086;&#1085;&#1080;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="
                                            updateTipStatus(tip, 'rejected')
                                        "
                                    >
                                        <X :size="16" />
                                    </button>
                                    <button
                                        v-if="canReportTip(tip)"
                                        type="button"
                                        class="tip-icon-button tip-icon-button--danger"
                                        aria-label="&#1055;&#1086;&#1078;&#1072;&#1083;&#1086;&#1074;&#1072;&#1090;&#1100;&#1089;&#1103; &#1085;&#1072; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="reportTip(tip)"
                                    >
                                        <Flag :size="15" />
                                    </button>
                                </div>
                            </div>
                            <p>{{ tip.content }}</p>
                        </article>
                    </div>

                    <p v-else-if="isOwnPlant" class="muted">
                        Новых советов от других пользователей пока нет.
                    </p>

                    <div v-if="visibleTips.length" class="tips-list">
                        <article
                            v-for="tip in visibleTips"
                            :key="tip.id"
                            class="tip-item"
                        >
                            <div class="tip-item__header">
                                <button
                                    type="button"
                                    class="tip-author"
                                    @click="openUserProfile(tip)"
                                >
                                    {{
                                        tip.author?.data?.name ||
                                        tip.author?.name ||
                                        "\u041f\u043e\u043b\u044c\u0437\u043e\u0432\u0430\u0442\u0435\u043b\u044c"
                                    }}
                                </button>
                                <div class="tip-actions">
                                    <button
                                        v-if="
                                            canManagePlant &&
                                            tip.status === 'pending'
                                        "
                                        type="button"
                                        class="tip-icon-button tip-icon-button--accept"
                                        aria-label="&#1055;&#1088;&#1080;&#1085;&#1103;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="
                                            updateTipStatus(tip, 'accepted')
                                        "
                                    >
                                        <Check :size="16" />
                                    </button>
                                    <button
                                        v-if="
                                            canManagePlant &&
                                            tip.status === 'pending'
                                        "
                                        type="button"
                                        class="tip-icon-button tip-icon-button--danger"
                                        aria-label="&#1054;&#1090;&#1082;&#1083;&#1086;&#1085;&#1080;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="
                                            updateTipStatus(tip, 'rejected')
                                        "
                                    >
                                        <X :size="16" />
                                    </button>
                                    <button
                                        v-if="canReportTip(tip)"
                                        type="button"
                                        class="tip-icon-button tip-icon-button--danger"
                                        aria-label="&#1055;&#1086;&#1078;&#1072;&#1083;&#1086;&#1074;&#1072;&#1090;&#1100;&#1089;&#1103; &#1085;&#1072; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                        @click="reportTip(tip)"
                                    >
                                        <Flag :size="15" />
                                    </button>
                                </div>
                            </div>
                            <p>{{ tip.content }}</p>
                        </article>
                    </div>
                </section>

                <section v-if="canReportPlant" class="panel report-panel">
                    <h2 class="panel__title">Пожаловаться на растение</h2>
                    <select v-model="reportReason">
                        <option value="inappropriate_image">
                            Неподходящее изображение
                        </option>
                        <option value="spam">Спам</option>
                        <option value="abuse">Оскорбления</option>
                        <option value="misinformation">
                            Недостоверная информация
                        </option>
                        <option value="other">Другое</option>
                    </select>
                    <textarea
                        v-model="reportDetails"
                        rows="3"
                        placeholder="Подробности жалобы"
                    />
                    <UiButton variant="ghost" @click="reportPlant">
                        <Flag :size="16" />
                        Отправить жалобу
                    </UiButton>
                </section>
            </aside>
        </div>
    </section>

    <div
        v-if="isTipsDialogOpen"
        class="tips-dialog"
        @click.self="isTipsDialogOpen = false"
    >
        <section class="tips-dialog__card">
            <header class="tips-dialog__head">
                <h2 class="panel__title">Все советы</h2>
                <button
                    type="button"
                    class="tips-dialog__close"
                    aria-label="Закрыть"
                    @click="isTipsDialogOpen = false"
                >
                    <X :size="20" />
                </button>
            </header>

            <div v-if="dialogTips.length" class="tips-list">
                <article
                    v-for="tip in dialogTips"
                    :key="tip.id"
                    class="tip-item tip-item--dialog"
                >
                    <div class="tip-item__header">
                        <div class="tip-item__author-block">
                            <button
                                type="button"
                                class="tip-author"
                                @click="openUserProfile(tip)"
                            >
                                {{
                                    tip.author?.data?.name ||
                                    tip.author?.name ||
                                    "\u041f\u043e\u043b\u044c\u0437\u043e\u0432\u0430\u0442\u0435\u043b\u044c"
                                }}
                            </button>
                            <dl class="tip-meta">
                                <div v-if="formatTipCreatedAt(tip)">
                                    <dt>
                                        &#1057;&#1086;&#1079;&#1076;&#1072;&#1085;
                                    </dt>
                                    <dd>{{ formatTipCreatedAt(tip) }}</dd>
                                </div>
                                <div
                                    v-if="
                                        tip.status === 'accepted' &&
                                        formatTipStatusChangedAt(tip)
                                    "
                                >
                                    <dt>
                                        &#1055;&#1088;&#1080;&#1085;&#1103;&#1090;
                                    </dt>
                                    <dd>{{ formatTipStatusChangedAt(tip) }}</dd>
                                </div>
                            </dl>
                        </div>
                        <UiBadge
                            :tone="
                                tip.status === 'accepted' ? 'soon' : 'neutral'
                            "
                        >
                            {{ formatTipStatus(tip.status) }}
                        </UiBadge>
                    </div>
                    <p>{{ tip.content }}</p>
                    <div class="tip-item__footer">
                        <div
                            v-if="canManagePlant && tip.status === 'pending'"
                            class="tip-actions"
                        >
                            <button
                                type="button"
                                class="tip-icon-button tip-icon-button--accept"
                                aria-label="&#1055;&#1088;&#1080;&#1085;&#1103;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                @click="updateTipStatus(tip, 'accepted')"
                            >
                                <Check :size="16" />
                            </button>
                            <button
                                type="button"
                                class="tip-icon-button tip-icon-button--danger"
                                aria-label="&#1054;&#1090;&#1082;&#1083;&#1086;&#1085;&#1080;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                                @click="updateTipStatus(tip, 'rejected')"
                            >
                                <X :size="16" />
                            </button>
                        </div>
                        <button
                            v-if="canReportTip(tip)"
                            type="button"
                            class="tip-icon-button tip-icon-button--danger"
                            aria-label="&#1055;&#1086;&#1078;&#1072;&#1083;&#1086;&#1074;&#1072;&#1090;&#1100;&#1089;&#1103; &#1085;&#1072; &#1089;&#1086;&#1074;&#1077;&#1090;"
                            @click="reportTip(tip)"
                        >
                            <Flag :size="15" />
                        </button>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<style scoped>
.plant-page {
    display: grid;
    gap: 12px;
    min-width: 0;
}

.plant-hero-grid {
    display: grid;
    gap: 12px;
    min-width: 0;
}

.desktop-grid,
.plant-secondary,
.plant-sidebar,
.panel {
    min-width: 0;
}

.plant-top {
    display: grid;
    gap: 12px;
    padding: 12px;
}

.plant-cover-wrap {
    position: relative;
    overflow: hidden;
    height: clamp(300px, 62vh, 560px);
    border-radius: var(--radius-md);
    background: #edf3ea;
}

.plant-cover-button {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: zoom-in;
}

.plant-cover {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.plant-cover-slide-next-enter-active,
.plant-cover-slide-next-leave-active,
.plant-cover-slide-prev-enter-active,
.plant-cover-slide-prev-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.26s ease;
}

.plant-cover-slide-next-enter-from,
.plant-cover-slide-prev-leave-to {
    opacity: 0;
    transform: translateX(34px);
}

.plant-cover-slide-next-leave-to,
.plant-cover-slide-prev-enter-from {
    opacity: 0;
    transform: translateX(-34px);
}

.gallery-open-icon {
    position: absolute;
    right: 10px;
    top: 10px;
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    transition: opacity 0.16s ease;
}

.plant-cover-button:hover .gallery-open-icon,
.plant-cover-button:focus-visible .gallery-open-icon {
    opacity: 1;
}

.plant-room {
    position: absolute;
    top: 10px;
    left: 10px;
}

.gallery-nav {
    position: absolute;
    top: 50%;
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: var(--color-ink);
    background: rgba(255, 255, 255, 0.86);
    box-shadow: var(--shadow-soft);
    cursor: pointer;
    transform: translateY(-50%);
}

.gallery-nav--prev {
    left: 10px;
}

.gallery-nav--next {
    right: 10px;
}

.gallery-count {
    position: absolute;
    right: 10px;
    bottom: 10px;
}

.gallery-thumbs {
    position: absolute;
    right: 10px;
    bottom: 48px;
    left: 10px;
    display: flex;
    gap: 7px;
    overflow-x: auto;
    padding-bottom: 2px;
}

.gallery-thumbs button {
    flex: 0 0 auto;
    width: 54px;
    height: 42px;
    padding: 0;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.72);
    border-radius: var(--radius-xs);
    background: var(--color-surface);
    cursor: pointer;
    opacity: 0.74;
}

.gallery-thumbs button.is-active {
    border-color: var(--color-lime);
    opacity: 1;
}

.gallery-thumbs img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.plant-main {
    display: flex;
    min-height: 100%;
    flex-direction: column;
    gap: 10px;
}

.plant-main h1 {
    margin: 0;
    font-size: clamp(24px, 6vw, 34px);
    line-height: 1.02;
}

.plant-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: auto;
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
    grid-template-columns: repeat(2, minmax(0, 1fr)) repeat(2, 56px);
    align-items: start;
    gap: 8px;
}

.actions-row > * {
    min-width: 0;
}

.actions-row :deep(.ui-button) {
    width: 100%;
    height: 56px;
    min-height: 56px;
    padding: 0 10px;
}

.action-icon-link {
    width: 56px;
}

.actions-row :deep(.icon-action-button) {
    width: 56px;
    padding: 0;
}

.actions-row .delete-plant-button {
    align-self: start;
}

.gallery-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    grid-template-rows: minmax(0, 1fr) auto;
    align-items: center;
    padding: 22px;
    background: rgba(0, 0, 0, 0.94);
}

.gallery-fullscreen__image {
    grid-row: 1;
    justify-self: center;
    max-width: 100%;
    max-height: calc(100vh - 132px);
    object-fit: contain;
}

.gallery-fullscreen__close,
.gallery-fullscreen__nav {
    position: fixed;
    z-index: 2;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: #fff;
    background: rgba(255, 255, 255, 0.16);
    cursor: pointer;
}

.gallery-fullscreen__close {
    top: 18px;
    right: 18px;
    width: 48px;
    height: 48px;
}

.gallery-fullscreen__nav {
    top: 50%;
    width: 56px;
    height: 56px;
    transform: translateY(-50%);
}

.gallery-fullscreen__nav--prev {
    left: 18px;
}

.gallery-fullscreen__nav--next {
    right: 18px;
}

.gallery-fullscreen__thumbs {
    grid-row: 2;
    display: flex;
    justify-content: center;
    gap: 8px;
    max-width: 100%;
    overflow-x: auto;
    padding-top: 14px;
}

.gallery-fullscreen__thumbs button {
    flex: 0 0 auto;
    width: 72px;
    height: 54px;
    padding: 0;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.24);
    border-radius: var(--radius-xs);
    background: #111;
    cursor: pointer;
    opacity: 0.74;
}

.gallery-fullscreen__thumbs button.is-active {
    border-color: var(--color-lime);
    opacity: 1;
}

.gallery-fullscreen__thumbs img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

.task-stack {
    position: relative;
    overflow: hidden;
}

.task-replace-enter-active,
.task-replace-leave-active,
.task-replace-move {
    transition:
        opacity 0.42s cubic-bezier(0.2, 0.72, 0.18, 1),
        transform 0.42s cubic-bezier(0.2, 0.72, 0.18, 1),
        box-shadow 0.42s ease;
}

.task-replace-enter-active {
    animation: task-replace-highlight 0.7s ease;
}

.task-replace-leave-active {
    position: absolute;
    right: 0;
    left: 0;
    z-index: 1;
}

.task-replace-enter-from,
.task-replace-leave-to {
    opacity: 0;
}

.task-replace-enter-from {
    transform: translateX(-36px) scale(0.985);
}

.task-replace-leave-to {
    transform: translateX(48px) scale(0.985);
}

@keyframes task-replace-highlight {
    0% {
        box-shadow: 0 0 0 0 rgba(22, 132, 58, 0);
    }

    34% {
        box-shadow:
            0 0 0 2px rgba(22, 132, 58, 0.22),
            0 12px 28px rgba(22, 132, 58, 0.16);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(22, 132, 58, 0);
    }
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
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 10px;
}

.tips-panel__head .panel__title {
    min-width: 0;
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
    min-width: 0;
    gap: 8px;
    padding: 12px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.tip-item p {
    overflow-wrap: anywhere;
}

.tip-item--review {
    border: 1px solid #d8e7cf;
    background: #f6fbf2;
}

.tip-item--dialog {
    gap: 10px;
    padding: 14px;
}

.tip-item__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    min-width: 0;
}

.tip-item__author-block {
    display: grid;
    min-width: 0;
    gap: 3px;
}

.tip-item__label {
    margin: 0;
    color: var(--color-green-dark);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0;
    text-transform: uppercase;
}

.tip-author {
    width: min(100%, fit-content);
    min-width: 0;
    overflow-wrap: anywhere;
    border: 0;
    color: var(--color-green-dark);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
    padding: 0;
    text-align: left;
}

.tip-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
    margin: 0;
    color: var(--color-muted);
    font-size: 12px;
}

.tip-meta div {
    display: flex;
    gap: 4px;
}

.tip-meta dt,
.tip-meta dd {
    margin: 0;
}

.tip-meta dt {
    font-weight: 800;
}

.tip-item__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.tip-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
}

.tip-icon-button {
    display: grid;
    width: 34px;
    height: 34px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    cursor: pointer;
}

.tip-icon-button--accept {
    color: #fff;
    border-color: var(--color-green);
    background: var(--color-green);
}

.tip-icon-button--accept:hover {
    background: var(--color-green-dark);
}

.tip-icon-button--danger {
    color: var(--color-red);
}

.tip-icon-button--danger:hover {
    border-color: rgba(179, 38, 30, 0.32);
    background: #fff1ef;
}

.link-button {
    min-width: 0;
    border: 0;
    color: var(--color-red);
    background: transparent;
    cursor: pointer;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.link-button--accept {
    min-height: 34px;
    padding: 0 12px;
    border-radius: var(--radius-sm);
    color: #fff;
    background: var(--color-green);
}

.link-button--accept:hover {
    background: var(--color-green-dark);
}

.tips-dialog {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(18, 31, 24, 0.52);
}

.tips-dialog__card {
    display: grid;
    width: min(640px, 100%);
    max-height: min(720px, calc(100vh - 32px));
    gap: 12px;
    overflow: auto;
    padding: 16px;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: var(--shadow-soft);
}

.tips-dialog__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.tips-dialog__close {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: var(--color-ink);
    background: var(--color-surface-soft);
    cursor: pointer;
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
        grid-template-columns: repeat(2, minmax(0, 1fr)) repeat(2, 56px);
    }
}

@media (min-width: 900px) {
    .plant-hero-grid {
        grid-template-columns: minmax(360px, 440px) minmax(420px, 1fr);
        align-items: stretch;
        gap: 12px;
    }

    .plant-top {
        grid-template-columns: 1fr;
        align-content: start;
        gap: 12px;
    }

    .plant-cover-wrap {
        height: clamp(360px, 54vh, 560px);
    }

    .plant-main h1 {
        font-size: clamp(24px, 3vw, 32px);
    }

    .plant-hero-grid > .care-calendar {
        min-height: 100%;
    }

    .desktop-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
        align-items: start;
        gap: 12px;
    }

    .plant-sidebar {
        max-width: 320px;
    }
}

@media (min-width: 1180px) {
    .desktop-grid {
        grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
    }

    .plant-sidebar {
        max-width: 340px;
    }
}

@media (max-width: 899px) {
    .desktop-grid {
        display: grid;
        gap: 12px;
    }

    .plant-sidebar {
        max-width: none;
    }
}

@media (max-width: 420px) {
    .tips-panel__head {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .tips-panel__head .link-button {
        grid-column: 1 / -1;
        justify-self: start;
    }

    .tip-item__footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .tip-actions {
        width: 100%;
    }
}
</style>
