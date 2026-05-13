import { toast } from "vue-sonner";

import { useTaskStore } from "@/entities/task/model/task.store";

export const useCompleteTask = () => {
    const taskStore = useTaskStore();

    const toggle = async (task) => {
        try {
            if (!task.completed) {
                await taskStore.completeTask(task);
                toast.success("Уход отмечен");
                return;
            }

            taskStore.toggleTask(task.id);
            toast.success("Задача снова активна");
        } catch (error) {
            toast.error(error.message);
        }
    };

    return { toggle };
};
