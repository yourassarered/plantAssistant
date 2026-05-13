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
import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useSocialStore } from "@/entities/social/model/social.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import TaskItem from "@/entities/task/ui/TaskItem.vue";
import { careTypes } from "@/shared/lib/careTypes";
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

const plant = computed(() => plantStore.byId(route.params.id));
const plantApiId = computed(() => plant.value?.apiId || route.params.id);
const tasks = computed(() =>
    taskStore.all.filter((task) => String(task.plantId) === String(plantApiId.value)),
);
const hasCare = computed(() => plant.value && Object.keys(plant.value.care || {}).length > 0);
const tips = computed(() => socialStore.tipsFor(plantApiId.value));
const likesCount = computed(() => {
    const key = String(plantApiId.value);
    return socialStore.likeCounts[key] ?? plant.value?.likesCount ?? 0;
});
const liked = computed(() => socialStore.isLiked(plantApiId.value));
const careLogs = computed(() => taskStore.logsByPlant[String(plantApiId.value)] || []);
const canManagePlant = computed(
    () =>
        authStore.isAdmin ||
        (authStore.isAuthenticated &&
            plant.value?.userId &&
            String(plant.value.userId) === String(authStore.user?.id)),
);

const icons = {
    water: Droplets,
    feed: Leaf,
    prune: Scissors,
    rotate: RotateCw,
};

const loadPage = async () => {
    pageLoading.value = true;
    pageError.value = "";

    try {
        if (!plantStore.all.length) {
            await plantStore.loadPlants();
            taskStore.syncFromPlants(plantStore.all);
        }

        if (!plant.value && authStore.isAuthenticated) {
            await plantStore.loadPlant(route.params.id);
            taskStore.syncFromPlants(plantStore.all);
        }

        if (plant.value) {
            await socialStore.loadPlantSocial(plantApiId.value);
            if (authStore.isAuthenticated) {
                await taskStore.loadCareLogs(plantApiId.value);
            }
        } else {
            pageError.value = "Растение не найдено или недоступно.";
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
        toast.error("Добавьте детали жалобы.");
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
        await socialStore.reportTip(tip.id, "other", "Жалоба из интерфейса растения");
        toast.success("Жалоба на совет отправлена");
    } catch (error) {
        toast.error(error.message);
    }
};

const updateTipStatus = async (tip, status) => {
    try {
        await socialStore.updateTipStatus(plantApiId.value, tip.id, status);
        toast.success(status === "accepted" ? "Совет принят" : "Совет отклонен");
    } catch (error) {
        toast.error(error.message);
    }
};

const remove = async () => {
    if (!window.confirm("Удалить растение без возможности восстановления?")) return;

    try {
        await plantStore.deletePlant(plant.value.apiId);
        toast.success("Растение удалено");
        router.push("/feed");
    } catch (error) {
        toast.error(error.message);
    }
};

onMounted(loadPage);
watch(() => route.params.id, loadPage);
</script>

<template>
    <section v-if="pageLoading" class="page">
        <div class="panel muted">Загружаем карточку растения...</div>
    </section>

    <section v-else-if="pageError" class="page">
        <div class="panel error-panel">
            <p class="muted">{{ pageError }}</p>
            <UiButton variant="ghost" @click="loadPage">Повторить</UiButton>
            <RouterLink to="/feed">
                <UiButton>Вернуться в ленту</UiButton>
            </RouterLink>
        </div>
    </section>

    <section v-if="plant" class="page plant-details">
        <div class="plant-hero">
            <img :src="plant.image" :alt="plant.name" />
            <div class="plant-hero__content">
                <UiBadge tone="soon"><MapPin :size="13" /> {{ plant.room }}</UiBadge>
                <h1>{{ plant.name }}</h1>
                <p>{{ plant.note }}</p>
            </div>
        </div>

        <div class="desktop-grid">
            <div class="page">
                <section class="panel">
                    <h2 class="panel__title">Расписание ухода</h2>
                    <div v-if="hasCare" class="care-settings">
                        <article v-for="(schedule, type) in plant.care" :key="type">
                            <span :style="{ backgroundColor: careTypes[type].color }">
                                <component :is="icons[type]" :size="18" />
                            </span>
                            <strong>{{ careTypes[type].label }}</strong>
                            <small>каждые {{ schedule.everyDays }} дн. · {{ schedule.nextAt }}</small>
                        </article>
                    </div>
                    <p v-else class="muted">
                        Для публичной ленты расписание ухода не показывается.
                    </p>
                </section>

                <section v-if="canManagePlant" class="panel">
                    <h2 class="panel__title">Задачи растения</h2>
                    <div v-if="tasks.length" class="task-stack">
                        <TaskItem v-for="task in tasks" :key="task.id" :task="task" />
                    </div>
                    <p v-else class="muted">Активных задач нет.</p>
                </section>

                <section v-if="canManagePlant" class="panel care-history">
                    <h2 class="panel__title">История ухода</h2>
                    <article v-for="log in careLogs" :key="log.id" class="history-row">
                        <strong>{{ log.type }}</strong>
                        <span>{{ String(log.performed_at).slice(0, 10) }}</span>
                        <p>{{ log.comment || "Без комментария" }}</p>
                    </article>
                    <p v-if="!careLogs.length" class="muted">Истории ухода пока нет.</p>
                </section>

                <section class="panel tips-panel">
                    <div class="tips-panel__head">
                        <h2 class="panel__title">Советы</h2>
                        <UiBadge tone="neutral">
                            <MessageCircle :size="13" />
                            {{ tips.length }}
                        </UiBadge>
                    </div>

                    <form v-if="authStore.isAuthenticated" class="tip-form" @submit.prevent="sendTip">
                        <textarea v-model="tipContent" rows="3" placeholder="Напишите совет по уходу" />
                        <UiButton type="submit">
                            <Send :size="16" />
                            Отправить
                        </UiButton>
                    </form>
                    <p v-else class="muted">Войдите, чтобы оставлять советы.</p>

                    <div v-if="tips.length" class="tips-list">
                        <article v-for="tip in tips" :key="tip.id" class="tip-item">
                            <strong>{{ tip.author?.data?.name || tip.author?.name || "Пользователь" }}</strong>
                            <p>{{ tip.content }}</p>
                            <div class="tip-item__footer">
                                <UiBadge :tone="tip.status === 'accepted' ? 'soon' : 'neutral'">
                                    {{ tip.status }}
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
                                    v-if="authStore.isAuthenticated"
                                    type="button"
                                    class="link-button"
                                    @click="reportTip(tip)"
                                >
                                    Пожаловаться
                                </button>
                            </div>
                        </article>
                    </div>
                    <p v-else class="muted">Советов пока нет.</p>
                </section>

                <section v-if="authStore.isAuthenticated" class="panel report-panel">
                    <h2 class="panel__title">Пожаловаться</h2>
                    <select v-model="reportReason">
                        <option value="inappropriate_image">Неподходящее фото</option>
                        <option value="spam">Спам</option>
                        <option value="abuse">Оскорбления</option>
                        <option value="misinformation">Недостоверная информация</option>
                        <option value="other">Другое</option>
                    </select>
                    <textarea v-model="reportDetails" rows="3" placeholder="Детали жалобы" />
                    <UiButton variant="ghost" @click="reportPlant">
                        <Flag :size="16" />
                        Отправить жалобу
                    </UiButton>
                </section>
            </div>

            <aside class="panel plant-metrics">
                <h2 class="panel__title">Параметры</h2>
                <div>
                    <strong>{{ plant.height || "не указана" }}</strong><span>Высота</span>
                </div>
                <div>
                    <strong>{{ likesCount }}</strong><span>Лайки</span>
                </div>
                <div>
                    <strong>{{ plant.isPublic ? "Да" : "Нет" }}</strong><span>Публичное</span>
                </div>

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
            </aside>
        </div>
    </section>
</template>

<style scoped>
.plant-hero {
    position: relative;
    min-height: 260px;
    overflow: hidden;
    border-radius: var(--radius-md);
}

.plant-hero img {
    width: 100%;
    height: 100%;
    min-height: 260px;
    object-fit: cover;
}

.plant-hero::after {
    position: absolute;
    inset: 0;
    content: "";
    background: linear-gradient(180deg, transparent, rgba(10, 38, 18, 0.76));
}

.plant-hero__content {
    position: absolute;
    right: 16px;
    bottom: 16px;
    left: 16px;
    z-index: 1;
    color: #fff;
}

.plant-hero h1 {
    margin: 10px 0 6px;
    font-size: 32px;
    line-height: 1;
}

.plant-hero p {
    max-width: 560px;
    margin: 0;
}

.care-settings,
.plant-metrics,
.task-stack,
.tips-list,
.tips-panel,
.report-panel,
.care-history {
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

.care-settings small,
.muted {
    color: var(--color-muted);
}

.error-panel {
    display: grid;
    justify-items: start;
    gap: 10px;
}

.plant-metrics div {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
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

.tip-form textarea {
    width: 100%;
    resize: vertical;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
}

.tip-item {
    display: grid;
    gap: 6px;
    padding: 10px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-soft);
}

.tip-item p {
    margin: 0;
    color: var(--color-muted);
}

.tip-item__footer,
.report-panel {
    display: grid;
    gap: 8px;
}

.tip-item__footer {
    grid-template-columns: auto 1fr auto;
    align-items: center;
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

.report-panel select,
.report-panel textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
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
    color: var(--color-muted);
}
</style>
