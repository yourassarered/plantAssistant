<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useField, useForm } from "vee-validate";
import { useRoute, useRouter } from "vue-router";
import {
  CalendarClock,
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
  MoreVertical,
  ImagePlus,
  RotateCw,
  Save,
  Scissors,
  Send,
  Settings2,
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
  todayIsoDate,
  toIsoDate,
} from "@/shared/lib/date/calendarGrid";
import { taskDateState } from "@/shared/lib/date/taskMarkers";
import UiBadge from "@/shared/ui/UiBadge.vue";
import UiButton from "@/shared/ui/UiButton.vue";
import UiField from "@/shared/ui/UiField.vue";
import { plantFormSchema } from "@/shared/validation/plant.schema";

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
const quickPhotoInput = ref(null);
const quickPhotoUploading = ref(false);
const activeImageIndex = ref(0);
const galleryDirection = ref("next");
const galleryHasNavigated = ref(false);
const isGalleryFullscreen = ref(false);
const isTipsDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isReportDialogOpen = ref(false);
const isRejectTipDialogOpen = ref(false);
const selectedRejectedTip = ref(null);
const rejectTipReport = ref(false);
const rejectTipReportDetails = ref("");
const isPlantMenuOpen = ref(false);
let loadRequestId = 0;

const activeCalendarDate = ref(new Date());
const selectedCalendarDate = ref(toIsoDate(new Date()));
const today = todayIsoDate();

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
  () => authStore.isAuthenticated && plant.value?.isPublic && !isOwnPlant.value,
);
const isPublicLocked = computed(() => Boolean(plant.value?.isPublicLocked));
const canShowCalendar = computed(() => Boolean(plant.value));

const {
  handleSubmit: handleEditSubmit,
  errors: editErrors,
  isSubmitting: isEditSubmitting,
  resetForm: resetEditForm,
} = useForm({
  validationSchema: plantFormSchema,
  initialValues: {
    name: "",
    room: "",
    height: 1,
    plantedAt: today,
    isPublic: false,
    waterEnabled: true,
    waterEveryDays: 4,
    feedEnabled: true,
    feedEveryDays: 21,
    pruneEnabled: true,
    pruneEveryDays: 30,
    rotateEnabled: true,
    rotateEveryDays: 7,
  },
});

const editName = useField("name").value;
const editRoom = useField("room").value;
const editHeight = useField("height").value;
const editPlantedAt = useField("plantedAt").value;
const editIsPublic = useField("isPublic").value;
const editWaterEnabled = useField("waterEnabled").value;
const editWaterEveryDays = useField("waterEveryDays").value;
const editFeedEnabled = useField("feedEnabled").value;
const editFeedEveryDays = useField("feedEveryDays").value;
const editPruneEnabled = useField("pruneEnabled").value;
const editPruneEveryDays = useField("pruneEveryDays").value;
const editRotateEnabled = useField("rotateEnabled").value;
const editRotateEveryDays = useField("rotateEveryDays").value;

const editCareIcons = {
  water: Droplets,
  feed: Leaf,
  prune: Scissors,
  rotate: RotateCw,
};

const editCareRows = computed(() => [
  {
    type: "water",
    icon: editCareIcons.water,
    config: careTypes.water,
    enabled: editWaterEnabled,
    interval: editWaterEveryDays,
    error: editErrors.waterEveryDays,
  },
  {
    type: "feed",
    icon: editCareIcons.feed,
    config: careTypes.feed,
    enabled: editFeedEnabled,
    interval: editFeedEveryDays,
    error: editErrors.feedEveryDays,
  },
  {
    type: "prune",
    icon: editCareIcons.prune,
    config: careTypes.prune,
    enabled: editPruneEnabled,
    interval: editPruneEveryDays,
    error: editErrors.pruneEveryDays,
  },
  {
    type: "rotate",
    icon: editCareIcons.rotate,
    config: careTypes.rotate,
    enabled: editRotateEnabled,
    interval: editRotateEveryDays,
    error: editErrors.rotateEveryDays,
  },
]);

const normalizedNumber = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) && number > 0 ? number : fallback;
};

const editCareValue = (type, fallbackDays) => {
  const setting = plant.value?.careSettings?.[type] || plant.value?.care?.[type];

  return {
    enabled: setting ? Boolean(setting.isEnabled) : true,
    everyDays: normalizedNumber(setting?.everyDays, fallbackDays),
  };
};

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
      tipDateMs(a.created_at, a.updated_at, a.createdAt, a.updatedAt) || 0;
    const tb =
      tipDateMs(b.created_at, b.updated_at, b.createdAt, b.updatedAt) || 0;
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

const ownerTipsSummary = computed(() => {
  if (!isOwnPlant.value) {
    return { pending: 0, accepted: 0, total: 0 };
  }

  const currentUserId = String(authStore.user?.id || "");
  const foreignTips = tips.value.filter(
    (tip) => tipAuthorId(tip) !== currentUserId && isTipVisibleByAge(tip),
  );

  return {
    total: foreignTips.length,
    pending: foreignTips.filter((tip) => tip.status === "pending").length,
    accepted: foreignTips.filter((tip) => tip.status === "accepted").length,
  };
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
      type: task.type,
      title: careTypes[task.type]?.label || task.type,
      subtitle: "Запланировано",
      state: taskDateState(task),
      date: formatIsoDate(task.dueAt),
    }));

  const history = careLogs.value
    .filter((log) => careLogDateKey(log) === selected)
    .map((log) => {
      const uiType = apiCareTypeToUi[log.type];
      return {
        id: `log-${log.id}`,
        type: uiType,
        title: careTypes[uiType]?.label || log.type,
        subtitle: "Выполнено",
        state: "soon",
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

const resetTransientUi = () => {
  isGalleryFullscreen.value = false;
  isTipsDialogOpen.value = false;
  isEditDialogOpen.value = false;
  isReportDialogOpen.value = false;
  isPlantMenuOpen.value = false;
};

const loadPage = async () => {
  const requestId = ++loadRequestId;
  const routePlantId = route.params.id;
  pageLoading.value = true;
  pageError.value = "";
  plantImages.value = [];
  activeImageIndex.value = 0;
  galleryHasNavigated.value = false;

  try {
    const loadedPlant = await plantStore.loadPlant(routePlantId);
    if (requestId !== loadRequestId) return;

    if (!loadedPlant) {
      pageError.value = "Растение не найдено или недоступно.";
      return;
    }

    taskStore.syncFromPlants([loadedPlant]);

    socialStore.applyPlantSnapshot(loadedPlant);

    try {
      const images = await plantStore.loadPlantImages(
        loadedPlant.apiId || routePlantId,
      );
      if (requestId !== loadRequestId) return;
      plantImages.value = images;
    } catch {
      if (requestId !== loadRequestId) return;
      plantImages.value = [];
    }

    if (loadedPlant.isPublic && !isOwnPlant.value && authStore.isAuthenticated) {
      await socialStore.loadLikeStatus(loadedPlant.apiId || routePlantId);
      if (requestId !== loadRequestId) return;
    }

    if (authStore.isAuthenticated && loadedPlant.canCompleteCare) {
      await taskStore.loadCareLogs(loadedPlant.apiId || routePlantId);
      if (requestId !== loadRequestId) return;
    }
  } catch (error) {
    if (requestId !== loadRequestId) return;
    pageError.value =
      error?.message || "Не удалось загрузить страницу растения.";
  } finally {
    if (requestId === loadRequestId) {
      pageLoading.value = false;
    }
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

const openQuickPhotoPicker = () => {
  quickPhotoInput.value?.click();
};

const uploadQuickPhoto = async (event) => {
  const file = event.target.files?.[0] || null;
  event.target.value = "";

  if (!file || !canManagePlant.value || quickPhotoUploading.value) return;

  const allowedTypes = ["image/png", "image/jpeg", "image/webp"];
  if (!allowedTypes.includes(file.type)) {
    toast.error(
      "\u0424\u043e\u0442\u043e \u0434\u043e\u043b\u0436\u043d\u043e \u0431\u044b\u0442\u044c \u0432 \u0444\u043e\u0440\u043c\u0430\u0442\u0435 PNG, JPG \u0438\u043b\u0438 WEBP.",
    );
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    toast.error(
      "\u041c\u0430\u043a\u0441\u0438\u043c\u0430\u043b\u044c\u043d\u044b\u0439 \u0440\u0430\u0437\u043c\u0435\u0440 \u0444\u043e\u0442\u043e 5 \u041c\u0411.",
    );
    return;
  }

  quickPhotoUploading.value = true;
  try {
    await plantStore.addPlantImage(plantApiId.value, file);
    plantImages.value = await plantStore.loadPlantImages(plantApiId.value);
    activeImageIndex.value = 0;
    galleryHasNavigated.value = false;
    toast.success(
      "\u0424\u043e\u0442\u043e \u0437\u0430\u0433\u0440\u0443\u0436\u0435\u043d\u043e",
    );
  } catch (error) {
    toast.error(error.message);
  } finally {
    quickPhotoUploading.value = false;
  }
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
    isReportDialogOpen.value = false;
    toast.success("Жалоба отправлена");
  } catch (error) {
    toast.error(error.message);
  }
};

const openReportDialog = () => {
  reportReason.value = "other";
  reportDetails.value = "";
  isReportDialogOpen.value = true;
};

const closeReportDialog = () => {
  isReportDialogOpen.value = false;
};

const reportTip = async (tip) => {
  try {
    await socialStore.reportTip(tip.id, "other", "Жалоба со страницы растения");
    toast.success("Жалоба на совет отправлена");
  } catch (error) {
    toast.error(error.message);
  }
};

const openRejectTipDialog = (tip) => {
  selectedRejectedTip.value = tip;
  rejectTipReport.value = false;
  rejectTipReportDetails.value = "";
  isRejectTipDialogOpen.value = true;
};

const closeRejectTipDialog = () => {
  isRejectTipDialogOpen.value = false;
  selectedRejectedTip.value = null;
  rejectTipReport.value = false;
  rejectTipReportDetails.value = "";
};

const confirmRejectTip = async () => {
  const tip = selectedRejectedTip.value;
  if (!tip) return;

  if (rejectTipReport.value && !rejectTipReportDetails.value.trim()) {
    toast.error("Добавьте комментарий к жалобе.");
    return;
  }

  try {
    await socialStore.updateTipStatus(plantApiId.value, tip.id, "rejected");
    if (rejectTipReport.value) {
      await socialStore.reportTip(tip.id, "other", rejectTipReportDetails.value.trim());
    }
    toast.success(rejectTipReport.value ? "Совет отклонён, жалоба отправлена" : "Совет отклонён");
    closeRejectTipDialog();
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

const openEditDialog = () => {
  if (!plant.value || !canManagePlant.value) return;

  const waterCare = editCareValue("water", 4);
  const feedCare = editCareValue("feed", 21);
  const pruneCare = editCareValue("prune", 30);
  const rotateCare = editCareValue("rotate", 7);

  resetEditForm({
    values: {
      name: plant.value.name || "",
      room: plant.value.room || "",
      height: normalizedNumber(plant.value.height, 1),
      plantedAt: plant.value.plantedAt || today,
      isPublic: isPublicLocked.value ? false : Boolean(plant.value.isPublic),
      waterEnabled: waterCare.enabled,
      waterEveryDays: waterCare.everyDays,
      feedEnabled: feedCare.enabled,
      feedEveryDays: feedCare.everyDays,
      pruneEnabled: pruneCare.enabled,
      pruneEveryDays: pruneCare.everyDays,
      rotateEnabled: rotateCare.enabled,
      rotateEveryDays: rotateCare.everyDays,
    },
  });

  isEditDialogOpen.value = true;
};

const closeEditDialog = () => {
  if (isEditSubmitting.value) return;
  isEditDialogOpen.value = false;
};

const saveEditDialog = handleEditSubmit(async (values) => {
  if (!plant.value || !canManagePlant.value) return;

  try {
    await plantStore.updatePlantDetails(plant.value, values, { reload: false });
    await plantStore.updatePlantCare(plant.value, values, { reload: false });
    await plantStore.loadPlant(plantApiId.value);
    taskStore.syncFromPlants(plantStore.all);
    isEditDialogOpen.value = false;
    toast.success("Изменения сохранены");
  } catch (error) {
    toast.error(error.message);
  }
});

const deleteActivePhoto = async () => {
  if (
    !canManagePlant.value ||
    !activeImage.value?.id ||
    activeImage.value.id === "fallback"
  ) {
    return;
  }

  if (!window.confirm("Удалить это фото?")) return;

  try {
    await plantStore.deletePlantImage(activeImage.value.id, plantApiId.value);
    plantImages.value = await plantStore.loadPlantImages(plantApiId.value);
    activeImageIndex.value = Math.min(
      activeImageIndex.value,
      Math.max(plantImages.value.length - 1, 0),
    );
    galleryHasNavigated.value = false;
    toast.success("Фото удалено");
  } catch (error) {
    toast.error(error.message);
  }
};

const removePlant = async () => {
  if (!canDeletePlant.value) return;
  if (!window.confirm("Удалить это растение навсегда?")) return;

  try {
    isPlantMenuOpen.value = false;
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
onBeforeUnmount(() => {
  loadRequestId += 1;
  resetTransientUi();
});
watch(() => route.params.id, () => {
  if (route.name === "plant-details") {
    loadPage();
  }
});
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

  <section
    v-if="plant"
    class="page plant-page"
    :class="{
      'plant-page--owner': isOwnPlant,
      'plant-page--guest': !isOwnPlant,
    }"
  >
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
                :key="activeImage?.id || activeImage?.url || plant.image"
                :src="activeImage?.url || plant.image"
                :alt="activeImage?.originalName || plant.name"
                class="plant-cover"
              />
            </Transition>
            <span class="gallery-open-icon">
              <Maximize2 :size="18" />
            </span>
          </button>
          <button
            v-if="
              canManagePlant &&
              activeImage?.id &&
              activeImage.id !== 'fallback'
            "
            type="button"
            class="plant-cover-delete"
            aria-label="Удалить фото"
            @click.stop="deleteActivePhoto"
          >
            <Trash2 :size="18" />
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
          <div class="plant-title-row">
            <h1>{{ plant.name }}</h1>
            <div
              v-if="canManagePlant || canReportPlant"
              class="plant-title-actions"
              :class="{
                'plant-title-actions--report-only':
                  canReportPlant && !canManagePlant,
              }"
            >
              <div v-if="canManagePlant" class="plant-title-actions__group">
                <UiButton
                  v-if="canManagePlant"
                  class="plant-action-button quick-photo-button"
                  type="button"
                  :disabled="quickPhotoUploading"
                  aria-label="Добавить фото"
                  @click="openQuickPhotoPicker"
                >
                  <ImagePlus :size="16" />
                  <span v-if="quickPhotoUploading">Загружаем...</span>
                  <span v-else>Добавить фото</span>
                </UiButton>
                <UiButton
                  v-if="canManagePlant"
                  class="plant-action-button"
                  variant="ghost"
                  type="button"
                  aria-label="Редактировать растение"
                  @click="openEditDialog"
                >
                  <Settings2 :size="16" />
                  Редактировать
                </UiButton>
              </div>
              <button
                v-if="canReportPlant"
                type="button"
                class="plant-menu__button report-trigger-button"
                aria-label="Пожаловаться на растение"
                title="Пожаловаться"
                @click="openReportDialog"
              >
                <Flag :size="18" />
              </button>
              <div v-if="canManagePlant" class="plant-menu">
                <button
                  type="button"
                  class="plant-menu__button"
                  aria-label="Дополнительные действия"
                  @click="isPlantMenuOpen = !isPlantMenuOpen"
                >
                  <MoreVertical :size="20" />
                </button>
                <div v-if="isPlantMenuOpen" class="plant-menu__dropdown">
                  <button
                    v-if="canDeletePlant"
                    type="button"
                    class="plant-menu__danger"
                    @click="removePlant"
                  >
                    <Trash2 :size="16" />
                    Удалить растение
                  </button>
                </div>
              </div>
              <input
                ref="quickPhotoInput"
                class="quick-photo-input"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                @change="uploadQuickPhoto"
              />
            </div>
          </div>
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

          <div v-if="canLikePlant" class="actions-row">
            <UiButton v-if="canLikePlant" variant="ghost" @click="toggleLike">
              <Heart :size="16" :fill="liked ? 'currentColor' : 'none'" />
              {{ liked ? "Убрать лайк" : "Лайк" }}
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

        <TransitionGroup
          v-if="selectedDayItems.length"
          name="calendar-detail"
          tag="div"
          class="care-calendar__list"
        >
          <article
            v-for="item in selectedDayItems"
            :key="item.id"
            class="history-row"
          >
            <span
              class="history-row__icon"
              :style="{ backgroundColor: careTypes[item.type]?.color || '#7ea885' }"
            >
              <component :is="icons[item.type]" :size="14" />
            </span>

            <div class="history-row__content">
              <strong>{{ item.title }}</strong>
              <p>{{ item.subtitle }}</p>
            </div>

            <UiBadge :tone="item.state">
              <CalendarClock :size="12" />
              {{ item.date }}
            </UiBadge>
          </article>
        </TransitionGroup>
        <div v-else class="care-calendar__list">
          <p class="muted">На выбранную дату событий нет.</p>
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
            :key="activeImage?.id || activeImage?.url || plant.image"
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

    <div
      class="desktop-grid"
      :class="{
        'desktop-grid--guest': !isOwnPlant,
        'desktop-grid--owner': isOwnPlant,
      }"
    >
      <section v-if="canShowCareSchedule" class="panel plant-care-panel">
        <h2 class="panel__title">График ухода</h2>
        <div class="care-settings">
          <article v-for="(schedule, type) in plant.care" :key="type">
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

      <section v-if="false" class="panel report-panel">
        <h2 class="panel__title">Пожаловаться на растение</h2>
        <select v-model="reportReason">
          <option value="inappropriate_image">Неподходящее изображение</option>
          <option value="spam">Спам</option>
          <option value="abuse">Оскорбления</option>
          <option value="misinformation">Недостоверная информация</option>
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

      <section v-if="canCompleteCareForPlant" class="panel plant-tasks-panel">
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
            :show-plant-name="false"
            :show-room="false"
            :hide-mobile-due-badge="true"
          />
        </TransitionGroup>
        <p v-else class="muted">Активных задач нет.</p>
      </section>

      <section
        class="panel tips-panel plant-tips-panel"
        :class="{
          'tips-panel--owner': isOwnPlant,
          'tips-panel--guest': !isOwnPlant,
        }"
      >
          <div class="tips-panel__head">
            <h2 class="panel__title">Советы</h2>
            <UiBadge v-if="isOwnPlant" tone="neutral">
              <MessageCircle :size="13" />
              {{ tipsCount }}
            </UiBadge>
            <button
              v-if="isOwnPlant && dialogTips.length"
              type="button"
              class="link-button"
              @click="isTipsDialogOpen = true"
            >
              Все
            </button>
          </div>

          <div v-if="isOwnPlant" class="owner-tips-summary">
            <article class="owner-tips-summary__item">
              <strong>{{ ownerTipsSummary.pending }}</strong>
              <span>На проверке</span>
            </article>
            <article class="owner-tips-summary__item">
              <strong>{{ ownerTipsSummary.accepted }}</strong>
              <span>Принято</span>
            </article>
            <article class="owner-tips-summary__item">
              <strong>{{ ownerTipsSummary.total }}</strong>
              <span>Всего советов</span>
            </article>
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

          <div v-if="isOwnPlant && reviewTips.length" class="tips-list owner-tips-list">
            <article
              v-for="tip in reviewTips"
              :key="tip.id"
              class="tip-item tip-item--review"
            >
              <div class="tip-item__header">
                <div class="tip-item__author-block">
                  <p class="tip-item__label">Новый совет</p>
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
                  <dl class="tip-meta" v-if="formatTipCreatedAt(tip)">
                    <div>
                      <dt>Создан</dt>
                      <dd>{{ formatTipCreatedAt(tip) }}</dd>
                    </div>
                  </dl>
                </div>
                <UiBadge :tone="tip.status === 'accepted' ? 'soon' : 'neutral'">
                  {{ formatTipStatus(tip.status) }}
                </UiBadge>
              </div>
              <p>{{ tip.content }}</p>
              <div class="owner-tip-actions" v-if="tip.status === 'pending'">
                <button
                  type="button"
                  class="owner-tip-action owner-tip-action--accept"
                  @click="updateTipStatus(tip, 'accepted')"
                >
                  <Check :size="16" />
                  Принять
                </button>
                <button
                  type="button"
                  class="owner-tip-action owner-tip-action--reject"
                  @click="openRejectTipDialog(tip)"
                >
                  <X :size="16" />
                  Отклонить
                </button>
              </div>
            </article>
          </div>

          <div v-else-if="isOwnPlant" class="owner-tips-empty">
            <strong>Пока тихо</strong>
            <p class="muted">Новых советов от других пользователей пока нет.</p>
          </div>

          <div v-if="visibleTips.length" class="tips-list">
            <article v-for="tip in visibleTips" :key="tip.id" class="tip-item">
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
                    v-if="canManagePlant && tip.status === 'pending'"
                    type="button"
                    class="tip-icon-button tip-icon-button--accept"
                    aria-label="&#1055;&#1088;&#1080;&#1085;&#1103;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                    @click="updateTipStatus(tip, 'accepted')"
                  >
                    <Check :size="16" />
                  </button>
                  <button
                    v-if="canManagePlant && tip.status === 'pending'"
                    type="button"
                    class="tip-icon-button tip-icon-button--danger"
                    aria-label="&#1054;&#1090;&#1082;&#1083;&#1086;&#1085;&#1080;&#1090;&#1100; &#1089;&#1086;&#1074;&#1077;&#1090;"
                    @click="openRejectTipDialog(tip)"
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

          <div v-else-if="!isOwnPlant && !canSuggestForPlant" class="guest-tips-empty">
            <strong>Советов пока нет</strong>
            <p class="muted">Станьте первым, кто оставит практическую рекомендацию.</p>
          </div>
      </section>
    </div>
  </section>

  <div
    v-if="isRejectTipDialogOpen"
    class="edit-dialog"
    @click.self="closeRejectTipDialog"
  >
    <section class="edit-dialog__card reject-tip-dialog">
      <header class="edit-dialog__head">
        <h2 class="panel__title">Отклонить совет</h2>
        <button
          type="button"
          class="edit-dialog__close"
          aria-label="Закрыть"
          @click="closeRejectTipDialog"
        >
          <X :size="18" />
        </button>
      </header>

      <p class="muted">Совет будет отклонён и больше не будет виден в списке.</p>
      <div class="reject-tip-dialog__report-card">
        <label class="reject-tip-dialog__check">
          <input v-model="rejectTipReport" type="checkbox" />
          <span class="reject-tip-dialog__icon">
            <Flag :size="17" />
          </span>
          <span class="reject-tip-dialog__copy">
            <strong>Отправить жалобу модераторам</strong>
            <small>Если совет нарушает правила, добавьте короткий комментарий.</small>
          </span>
        </label>
        <textarea
          v-if="rejectTipReport"
          v-model="rejectTipReportDetails"
          rows="4"
          placeholder="Комментарий к жалобе"
        />
      </div>

      <div class="edit-dialog__actions reject-tip-dialog__actions">
        <UiButton type="button" class="reject-tip-dialog__submit" @click="confirmRejectTip">
          <Flag v-if="rejectTipReport" :size="16" />
          {{ rejectTipReport ? "Отклонить и отправить" : "Только отклонить" }}
        </UiButton>
        <UiButton type="button" variant="ghost" @click="closeRejectTipDialog">
          Отмена
        </UiButton>
      </div>
    </section>
  </div>

  <div
    v-if="isEditDialogOpen"
    class="edit-dialog"
    @click.self="closeEditDialog"
  >
    <form class="edit-dialog__card" @submit.prevent="saveEditDialog">
      <header class="edit-dialog__head">
        <h2 class="panel__title">Редактировать растение</h2>
        <button
          type="button"
          class="edit-dialog__close"
          aria-label="Закрыть"
          @click="closeEditDialog"
        >
          <X :size="20" />
        </button>
      </header>

      <section class="edit-section">
        <h3>Параметры</h3>
        <div class="edit-fields">
          <UiField label="Название" :error="editErrors.name">
            <input v-model="editName" placeholder="Фикус" />
          </UiField>
          <UiField label="Комната" :error="editErrors.room">
            <input v-model="editRoom" />
          </UiField>
          <UiField label="Высота, см" :error="editErrors.height">
            <input
              v-model="editHeight"
              inputmode="decimal"
              placeholder="12,5"
              type="text"
            />
          </UiField>
          <UiField label="Дата посадки" :error="editErrors.plantedAt">
            <input v-model="editPlantedAt" type="date" />
          </UiField>
          <label class="edit-public-toggle">
            <span class="edit-public-toggle__text">
              <strong>Публичность</strong>
              <small>
                {{
                  isPublicLocked
                    ? "Растение скрыто модератором: повторная публикация недоступна"
                    : editIsPublic
                    ? "Растение видно в публичной ленте"
                    : "Растение видно только вам"
                }}
              </small>
            </span>
            <span class="edit-public-toggle__control">
              <input v-model="editIsPublic" :disabled="isPublicLocked" type="checkbox" />
              <span></span>
            </span>
          </label>
        </div>
      </section>

      <section class="edit-section">
        <h3>График ухода</h3>
        <div class="edit-care-grid">
          <article
            v-for="row in editCareRows"
            :key="row.type"
            class="edit-care-card"
            :class="{ 'edit-care-card--off': !row.enabled.value }"
          >
            <div class="edit-care-card__head">
              <span
                class="edit-care-card__icon"
                :style="{ backgroundColor: row.config.color }"
              >
                <component :is="row.icon" :size="18" />
              </span>
              <div>
                <h4>{{ row.config.label }}</h4>
                <p>{{ row.enabled.value ? "Активно" : "Отключено" }}</p>
              </div>
              <label class="edit-switch">
                <input v-model="row.enabled.value" type="checkbox" />
                <span></span>
              </label>
            </div>
            <UiField label="Интервал, дней" :error="row.error">
              <input
                v-model="row.interval.value"
                :disabled="!row.enabled.value"
                min="1"
                type="number"
              />
            </UiField>
          </article>
        </div>
      </section>

      <footer class="edit-dialog__footer">
        <UiButton type="button" variant="ghost" @click="closeEditDialog">
          Отмена
        </UiButton>
        <UiButton type="submit" :disabled="isEditSubmitting">
          <Save :size="17" />
          {{ isEditSubmitting ? "Сохраняем..." : "Сохранить" }}
        </UiButton>
      </footer>
    </form>
  </div>

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
                  <dt>&#1057;&#1086;&#1079;&#1076;&#1072;&#1085;</dt>
                  <dd>{{ formatTipCreatedAt(tip) }}</dd>
                </div>
                <div
                  v-if="
                    tip.status === 'accepted' && formatTipStatusChangedAt(tip)
                  "
                >
                  <dt>&#1055;&#1088;&#1080;&#1085;&#1103;&#1090;</dt>
                  <dd>{{ formatTipStatusChangedAt(tip) }}</dd>
                </div>
              </dl>
            </div>
            <UiBadge :tone="tip.status === 'accepted' ? 'soon' : 'neutral'">
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
                @click="openRejectTipDialog(tip)"
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

  <div
    v-if="isReportDialogOpen"
    class="report-dialog"
    @click.self="closeReportDialog"
  >
    <form class="report-dialog__card" @submit.prevent="reportPlant">
      <header class="report-dialog__head">
        <h2 class="panel__title">Пожаловаться на растение</h2>
        <button
          type="button"
          class="report-dialog__close"
          aria-label="Закрыть"
          @click="closeReportDialog"
        >
          <X :size="20" />
        </button>
      </header>

      <div class="report-dialog__form">
        <select v-model="reportReason">
          <option value="inappropriate_image">Неподходящее изображение</option>
          <option value="spam">Спам</option>
          <option value="abuse">Оскорбления</option>
          <option value="misinformation">Недостоверная информация</option>
          <option value="other">Другое</option>
        </select>
        <textarea
          v-model="reportDetails"
          rows="4"
          placeholder="Подробности жалобы"
        />
      </div>

      <footer class="report-dialog__footer">
        <UiButton type="button" variant="ghost" @click="closeReportDialog">
          Отмена
        </UiButton>
        <UiButton type="submit">
          <Flag :size="16" />
          Отправить жалобу
        </UiButton>
      </footer>
    </form>
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
.plant-cover-button:focus-visible .gallery-open-icon,
.plant-cover-wrap:hover .plant-cover-delete,
.plant-cover-wrap:focus-within .plant-cover-delete {
  opacity: 1;
}

.plant-cover-delete {
  position: absolute;
  top: 10px;
  right: 56px;
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border: 0;
  border-radius: 50%;
  color: #fff;
  background: rgba(179, 38, 30, 0.84);
  box-shadow: var(--shadow-soft);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.16s ease;
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

.plant-title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.plant-title-row h1 {
  min-width: 0;
}

.plant-title-actions {
  position: relative;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
  margin-left: auto;
}

.plant-title-actions__group {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: center;
}

.plant-action-button {
  min-height: 42px;
  padding: 0 16px;
}

.quick-photo-button {
  flex: 0 0 auto;
}

.quick-photo-input {
  display: none;
}

.plant-menu {
  position: relative;
}

.plant-menu__button {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-ink);
  background: var(--color-surface);
  cursor: pointer;
}

.report-trigger-button {
  color: var(--color-red);
}

.report-trigger-button:hover {
  border-color: rgba(179, 38, 30, 0.24);
  background: #fff3f1;
}

.plant-menu__dropdown {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 12;
  display: grid;
  min-width: 190px;
  padding: 6px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: var(--shadow-soft);
}

.plant-menu__dropdown button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 38px;
  padding: 0 10px;
  border: 0;
  border-radius: var(--radius-xs);
  background: transparent;
  cursor: pointer;
  font-weight: 800;
  text-align: left;
}

.plant-menu__danger {
  color: var(--color-red);
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
  grid-template-columns: minmax(0, 1fr);
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

.tips-panel--owner {
  gap: 14px;
  background:
    linear-gradient(180deg, rgba(247, 250, 244, 0.98), rgba(255, 255, 255, 0.98));
}

.tips-panel--guest {
  background:
    linear-gradient(180deg, rgba(252, 253, 250, 0.98), rgba(246, 249, 244, 0.98));
}

.owner-tips-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.owner-tips-summary__item {
  display: grid;
  gap: 4px;
  padding: 12px;
  border: 1px solid rgba(219, 226, 214, 0.92);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.9);
}

.owner-tips-summary__item strong {
  font-size: 24px;
  line-height: 1;
}

.owner-tips-summary__item span {
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 800;
}

.tip-form textarea,
.reject-tip-dialog textarea,
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
  grid-template-rows: auto minmax(0, 1fr) auto;
  height: 100%;
  border: 1px solid #d8e7cf;
  background: #f6fbf2;
}

.owner-tips-list {
  gap: 10px;
}

.tips-panel--owner .tip-item--review {
  gap: 12px;
  padding: 14px;
  border-color: #d6e6d1;
  background: #fff;
  box-shadow: 0 8px 20px rgba(28, 55, 31, 0.05);
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

.owner-tip-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-self: end;
  gap: 8px;
  margin-top: auto;
}

.owner-tip-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  cursor: pointer;
  font-weight: 800;
}

.owner-tip-action--accept {
  color: #fff;
  border-color: var(--color-green);
  background: var(--color-green);
}

.owner-tip-action--reject {
  color: var(--color-red);
  border-color: rgba(224, 69, 50, 0.22);
  background: #fff3f1;
}

.reject-tip-dialog {
  display: grid;
  gap: 12px;
}

.reject-tip-dialog__check {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border: 1px solid rgba(224, 69, 50, 0.18);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  background: #fff8f5;
  font-weight: 800;
  cursor: pointer;
}

.reject-tip-dialog__check input {
  margin-top: 6px;
  accent-color: var(--color-red);
}

.reject-tip-dialog__report-card {
  display: grid;
  gap: 10px;
}

.reject-tip-dialog__icon {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 50%;
  color: var(--color-red);
  background: #ffe7df;
}

.reject-tip-dialog__copy {
  display: grid;
  gap: 3px;
}

.reject-tip-dialog__copy small {
  color: var(--color-muted);
  font-weight: 700;
  line-height: 1.35;
}

.reject-tip-dialog__actions {
  justify-content: flex-start;
  gap: 14px;
}

.reject-tip-dialog__submit :deep(svg) {
  margin-right: 6px;
}

.owner-tips-empty {
  display: grid;
  gap: 4px;
  padding: 14px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.76);
}

.owner-tips-empty strong,
.owner-tips-empty p {
  margin: 0;
}

.guest-tips-empty {
  display: grid;
  gap: 4px;
  padding: 14px;
  border: 1px dashed rgba(22, 132, 58, 0.22);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.86);
}

.guest-tips-empty strong,
.guest-tips-empty p {
  margin: 0;
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

.edit-dialog {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(18, 31, 24, 0.52);
}

.edit-dialog__card {
  display: grid;
  width: min(760px, 100%);
  max-height: min(820px, calc(100vh - 32px));
  gap: 14px;
  overflow: auto;
  padding: 16px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-soft);
}

.edit-dialog__head,
.edit-dialog__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.edit-dialog__footer {
  justify-content: flex-end;
}

.edit-dialog__close {
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

.edit-section {
  display: grid;
  gap: 10px;
}

.edit-section h3,
.edit-care-card h4,
.edit-care-card p {
  margin: 0;
}

.edit-fields,
.edit-care-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.edit-care-card {
  display: grid;
  gap: 12px;
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
}

.edit-public-toggle {
  display: flex;
  grid-column: 1 / -1;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  cursor: pointer;
}

.edit-public-toggle__text {
  display: grid;
  gap: 3px;
}

.edit-public-toggle__text small {
  color: var(--color-muted);
  font-weight: 800;
}

.edit-public-toggle__control {
  position: relative;
  flex: 0 0 auto;
  width: 48px;
  height: 28px;
}

.edit-public-toggle__control input {
  position: absolute;
  opacity: 0;
}

.edit-public-toggle__control span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd7ca;
  transition: background 0.16s ease;
}

.edit-public-toggle__control span::after {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 7px rgba(23, 33, 24, 0.18);
  content: "";
  transition: transform 0.16s ease;
}

.edit-public-toggle__control input:checked + span {
  background: var(--color-green);
}

.edit-public-toggle__control input:checked + span::after {
  transform: translateX(20px);
}

.edit-care-card--off {
  background: #f4f5f2;
}

.edit-care-card__head {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
}

.edit-care-card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border-radius: var(--radius-sm);
  color: #fff;
}

.edit-care-card p {
  color: var(--color-muted);
  font-size: 13px;
  font-weight: 800;
}

.edit-switch {
  position: relative;
  width: 48px;
  height: 28px;
}

.edit-switch input {
  position: absolute;
  opacity: 0;
}

.edit-switch span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd7ca;
  cursor: pointer;
  transition: background 0.16s ease;
}

.edit-switch span::after {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 7px rgba(23, 33, 24, 0.18);
  content: "";
  transition: transform 0.16s ease;
}

.edit-switch input:checked + span {
  background: var(--color-green);
}

.edit-switch input:checked + span::after {
  transform: translateX(20px);
}

.edit-care-card input:disabled {
  color: var(--color-muted);
  background: #eef0eb;
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

.report-dialog {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(18, 31, 24, 0.52);
}

.report-dialog__card {
  display: grid;
  width: min(520px, 100%);
  gap: 14px;
  padding: 16px;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-soft);
}

.report-dialog__head,
.report-dialog__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.report-dialog__footer {
  justify-content: flex-end;
}

.report-dialog__close {
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

.report-dialog__form {
  display: grid;
  gap: 10px;
}

.report-dialog__form select,
.report-dialog__form textarea {
  width: 100%;
  resize: vertical;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}

.history-row {
  display: grid;
  grid-template-columns: 30px minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: #f7faf5;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.history-row__icon {
  display: grid;
  width: 30px;
  height: 30px;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 8px;
  color: #fff;
}

.history-row__content {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.history-row__content strong,
.history-row__content p {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.history-row p {
  margin: 0;
}

.calendar-detail-enter-active,
.calendar-detail-leave-active,
.calendar-detail-move {
  transition:
    opacity 0.18s ease,
    transform 0.18s ease;
}

.calendar-detail-enter-from,
.calendar-detail-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

.calendar-detail-leave-active {
  position: absolute;
  right: 0;
  left: 0;
}

@media (hover: hover) and (pointer: fine) {
  .history-row:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(24, 45, 28, 0.08);
  }
}

@media (min-width: 900px) {
  .care-calendar__list {
    position: relative;
    max-height: 216px;
    overflow: auto;
    padding-right: 4px;
  }

  .desktop-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, 332px);
    align-items: stretch;
    gap: 16px;
  }

  .plant-care-panel,
  .plant-tasks-panel {
    grid-column: 1;
  }

  .plant-tips-panel {
    grid-column: 2;
  }

  .plant-care-panel,
  .plant-tips-panel {
    height: 100%;
  }

  .desktop-grid--owner {
    grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
    align-items: stretch;
  }

  .plant-page--owner .plant-title-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .plant-page--owner .plant-title-actions {
    width: 100%;
    justify-content: center;
    margin-left: 0;
  }

  .plant-page--owner .plant-hero-grid {
    grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
  }

  .desktop-grid--owner .plant-care-panel {
    grid-column: 1 / -1;
  }

  .desktop-grid--owner .plant-tasks-panel {
    grid-column: 1;
    grid-row: 2;
    align-self: stretch;
    height: 100%;
  }

  .desktop-grid--owner .plant-tips-panel {
    grid-column: 2;
    grid-row: 2;
    align-self: stretch;
    height: 100%;
  }

  .desktop-grid--owner .tips-panel--owner {
    align-content: start;
    gap: 16px;
  }

  .desktop-grid--owner .owner-tips-list {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: stretch;
    gap: 12px;
  }

  .desktop-grid--owner .tip-item--review {
    height: 100%;
  }

  .desktop-grid--owner .owner-tip-actions {
    grid-template-columns: 1fr;
  }

  .desktop-grid--guest {
    grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
  }

  .desktop-grid--guest .plant-care-panel {
    grid-column: 1;
  }

  .desktop-grid--guest .plant-tips-panel {
    grid-column: 2;
  }

  .desktop-grid--guest .plant-care-panel,
  .desktop-grid--guest .plant-tips-panel {
    height: 100%;
  }

  .desktop-grid--guest .tips-panel--guest {
    align-content: start;
    gap: 14px;
  }

  .desktop-grid--guest .tips-list {
    gap: 12px;
    max-height: 320px;
    overflow: auto;
    padding-right: 4px;
  }

  .desktop-grid--guest .tip-form {
    padding: 12px;
    border: 1px solid rgba(219, 226, 214, 0.92);
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.88);
  }

  .desktop-grid--guest .tip-item {
    padding: 14px;
    border: 1px solid rgba(219, 226, 214, 0.92);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 10px 24px rgba(24, 45, 28, 0.06);
  }
}

@media (max-width: 520px) {
  .plant-stats {
    grid-template-columns: 1fr;
  }

  .owner-tips-summary,
  .owner-tip-actions {
    grid-template-columns: 1fr;
  }

  .plant-title-actions {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 42px;
    align-items: stretch;
    gap: 8px;
    justify-content: center;
    margin-left: 0;
  }

  .plant-title-actions--report-only {
    width: auto;
    grid-template-columns: 42px;
    justify-content: end;
    margin-left: auto;
  }

  .plant-title-actions__group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .plant-title-actions__group :deep(.ui-button),
  .plant-action-button {
    width: 100%;
    min-width: 0;
    height: 44px;
    min-height: 44px;
    padding: 0 12px;
    flex: 1 1 auto;
  }

  .actions-row {
    grid-template-columns: 1fr;
  }

  .edit-fields,
  .edit-care-grid,
  .edit-dialog__footer {
    grid-template-columns: 1fr;
  }

  .edit-dialog__footer {
    display: grid;
  }

  .report-dialog__footer {
    display: grid;
  }

  .history-row {
    grid-template-columns: 30px minmax(0, 1fr);
  }

  .history-row :deep(.ui-badge) {
    grid-column: 1 / -1;
    justify-self: start;
  }
}

@media (min-width: 900px) {
  .plant-hero-grid {
    grid-template-columns: minmax(360px, 440px) minmax(420px, 1fr);
    align-items: stretch;
    gap: 12px;
  }

  .plant-page--guest .plant-hero-grid {
    grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
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
}

@media (min-width: 1180px) {
  .desktop-grid {
    grid-template-columns: minmax(0, 1fr) minmax(312px, 356px);
  }

  .desktop-grid--owner {
    grid-template-columns: minmax(400px, 460px) minmax(0, 1fr);
  }

  .plant-page--owner .plant-hero-grid {
    grid-template-columns: minmax(400px, 460px) minmax(0, 1fr);
  }

  .desktop-grid--guest {
    grid-template-columns: minmax(400px, 460px) minmax(0, 1fr);
  }

  .plant-page--guest .plant-hero-grid {
    grid-template-columns: minmax(400px, 460px) minmax(0, 1fr);
  }
}

@media (max-width: 899px) {
  .desktop-grid {
    display: grid;
    gap: 12px;
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
