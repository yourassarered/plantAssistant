import { toast } from "vue-sonner";

import { useTaskStore } from "@/entities/task/model/task.store";

export const useCompleteTask = () => {
    const taskStore = useTaskStore();

    const toggle = async (task) => {
        try {
            if (task.completed) {
                return false;
            }

            await taskStore.completeTask(task);
            toast.success("Уход отмечен");
            return true;
        } catch (error) {
            toast.error(error.message);
            return false;
        }
    };

    return { toggle };
};
