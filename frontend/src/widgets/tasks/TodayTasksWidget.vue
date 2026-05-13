<script setup>
import emblaCarouselVue from "embla-carousel-vue";

import TaskItem from "@/entities/task/ui/TaskItem.vue";

defineProps({
    tasks: { type: Array, required: true },
});

const [emblaRef] = emblaCarouselVue({ align: "start", dragFree: true });
</script>

<template>
    <section class="today-tasks">
        <h2 class="panel__title">Сегодня и просрочено</h2>
        <div v-if="tasks.length" ref="emblaRef" class="today-tasks__viewport">
            <div class="today-tasks__container">
                <div
                    v-for="task in tasks"
                    :key="task.id"
                    class="today-tasks__slide"
                >
                    <TaskItem :task="task" />
                </div>
            </div>
        </div>
        <p v-else class="today-tasks__empty">На сегодня нет ухода.</p>
    </section>
</template>

<style scoped>
.today-tasks {
    display: grid;
    gap: 10px;
}

.today-tasks__viewport {
    overflow: hidden;
}

.today-tasks__container {
    display: flex;
    gap: 10px;
}

.today-tasks__slide {
    flex: 0 0 min(420px, 88%);
}

.today-tasks__empty {
    margin: 0;
    color: var(--color-muted);
}
</style>
