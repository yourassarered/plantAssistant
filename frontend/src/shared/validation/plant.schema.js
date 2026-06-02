import { toTypedSchema } from "@vee-validate/zod";
import { z } from "zod";

const decimalNumber = (message) =>
    z.preprocess(
        (value) => {
            if (typeof value === "string") {
                const normalized = value.trim().replace(",", ".");
                return normalized === "" ? value : Number(normalized);
            }

            return value;
        },
        z.number({ invalid_type_error: message }).min(1, message),
    );

export const plantFormSchema = toTypedSchema(
    z.object({
        name: z.string().min(2, "Минимум 2 символа"),
        room: z.string().min(2, "Укажите комнату"),
        height: decimalNumber("Высота должна быть больше 0"),
        plantedAt: z.string().min(1, "Выберите дату посадки"),
        isPublic: z.boolean().default(false),
        waterEnabled: z.boolean().default(true),
        waterEveryDays: z.coerce.number().min(1).max(60),
        feedEnabled: z.boolean().default(true),
        feedEveryDays: z.coerce.number().min(1).max(90),
        pruneEnabled: z.boolean().default(true),
        pruneEveryDays: z.coerce.number().min(1).max(120),
        rotateEnabled: z.boolean().default(true),
        rotateEveryDays: z.coerce.number().min(1).max(60),
    }),
);
