<script setup>
import { onMounted } from "vue";

import { usePlantStore } from "@/entities/plant/model/plant.store";
import { useTaskStore } from "@/entities/task/model/task.store";
import CalendarWidget from "@/widgets/calendar/CalendarWidget.vue";

const plantStore = usePlantStore();
const taskStore = useTaskStore();

onMounted(async () => {
    if (!plantStore.all.length) {
        await plantStore.loadPlants();
        taskStore.syncFromPlants(plantStore.all);
    }
});
</script>

<template>
    <section class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Календарь</h1>
                <p class="page-subtitle">
                    Месяц формируется динамически по care settings ваших растений.
                </p>
            </div>
        </header>

        <div class="panel">
            <CalendarWidget :tasks="taskStore.all" />
        </div>
    </section>
</template>
