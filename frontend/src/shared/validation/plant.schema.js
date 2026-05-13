import { toTypedSchema } from "@vee-validate/zod";
import { z } from "zod";

export const plantFormSchema = toTypedSchema(
    z.object({
        name: z.string().min(2, "Минимум 2 символа"),
        room: z.string().min(2, "Укажите комнату"),
        height: z.coerce.number().min(1, "Высота должна быть больше 0"),
        plantedAt: z.string().min(1, "Выберите дату посадки"),
        isPublic: z.boolean().default(false),
        waterEveryDays: z.coerce.number().min(1).max(60),
        feedEveryDays: z.coerce.number().min(1).max(90),
        pruneEveryDays: z.coerce.number().min(1).max(120),
        rotateEveryDays: z.coerce.number().min(1).max(60),
    }),
);
