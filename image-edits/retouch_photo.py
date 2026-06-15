from pathlib import Path
from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageOps


ROOT = Path(__file__).resolve().parent
SRC = ROOT / "photo_2026-06-14_21-40-25-original.jpg"
OUT = ROOT / "photo_2026-06-14_21-40-25-retouched.jpg"
MASK_PREVIEW = ROOT / "photo_2026-06-14_21-40-25-mask-preview.png"


def avg_pixel(px, x, y, w, h, radius=3):
    total = [0, 0, 0]
    count = 0
    for yy in range(max(0, y - radius), min(h, y + radius + 1)):
        for xx in range(max(0, x - radius), min(w, x + radius + 1)):
            r, g, b = px[xx, yy]
            total[0] += r
            total[1] += g
            total[2] += b
            count += 1
    return tuple(v // count for v in total)


def build_phone_mask(size):
    w, h = size
    mask = Image.new("L", size, 0)
    draw = ImageDraw.Draw(mask)

    # Main phone body plus the hand/black holder silhouette that touches it.
    draw.rounded_rectangle((43, 1010, 292, h + 30), radius=30, fill=255)
    draw.polygon(
        [
            (24, 1044),
            (286, 1002),
            (326, h),
            (0, h),
            (0, 1188),
            (38, 1138),
        ],
        fill=255,
    )

    # Small top/side protrusions and the bright screen corners.
    draw.ellipse((50, 1000, 118, 1065), fill=255)
    draw.ellipse((235, 1018, 316, 1110), fill=255)
    draw.rectangle((72, 1042, 272, 1279), fill=255)

    return mask.filter(ImageFilter.GaussianBlur(1.5))


def denoise_and_sharpen(img):
    # Smooth chroma a little more than luma to reduce color noise without
    # wiping out the harsh stage-light texture.
    y, cb, cr = img.convert("YCbCr").split()
    y_soft = Image.blend(y, y.filter(ImageFilter.MedianFilter(3)), 0.18)
    cb_soft = cb.filter(ImageFilter.GaussianBlur(0.85))
    cr_soft = cr.filter(ImageFilter.GaussianBlur(0.85))
    cleaned = Image.merge("YCbCr", (y_soft, cb_soft, cr_soft)).convert("RGB")

    # The source has fine horizontal display/camera banding; a tiny vertical
    # softening pass calms that down before sharpening.
    vertical_soft = cleaned.filter(
        ImageFilter.Kernel(
            (3, 3),
            [
                0, 1, 0,
                0, 2, 0,
                0, 1, 0,
            ],
            scale=4,
        )
    )
    cleaned = Image.blend(cleaned, vertical_soft, 0.42)
    cleaned = ImageEnhance.Color(cleaned).enhance(1.03)
    cleaned = ImageEnhance.Contrast(cleaned).enhance(1.04)
    return cleaned.filter(ImageFilter.UnsharpMask(radius=1.35, percent=115, threshold=5))


def remove_phone(base, mask):
    w, h = base.size
    source = base.load()
    replacement = base.copy()
    dest = replacement.load()
    mask_px = mask.load()

    for y in range(h):
        xs = [x for x in range(w) if mask_px[x, y] > 8]
        if not xs:
            continue

        x0, x1 = min(xs), max(xs)
        left_x = max(0, x0 - 28)
        right_x = min(w - 1, x1 + 34)
        left = avg_pixel(source, left_x, y, w, h, 4)
        right = avg_pixel(source, right_x, y, w, h, 4)

        for x in xs:
            t = (x - x0) / max(1, x1 - x0)
            grad = tuple(int(left[i] * (1 - t) + right[i] * t) for i in range(3))

            # Extend the tabletop/edge down from the clean band just above the
            # phone. Lower down, blend into the naturally dark foreground.
            table_sample = source[x, max(0, y - 74)]
            sx = min(w - 1, x + 330)
            sy = min(h - 1, y + 22)
            dark_sample = source[sx, sy]
            dark_sample = tuple(int(c * 0.48) for c in dark_sample)

            if y < 1068:
                fill = tuple(int(table_sample[i] * 0.78 + grad[i] * 0.22) for i in range(3))
            elif y < 1130:
                t2 = (y - 1068) / 62
                upper = tuple(int(table_sample[i] * 0.62 + grad[i] * 0.38) for i in range(3))
                fill = tuple(int(upper[i] * (1 - t2) + dark_sample[i] * t2) for i in range(3))
            else:
                fill = tuple(int(dark_sample[i] * 0.82 + grad[i] * 0.18) for i in range(3))

            if y > 1110:
                shade = min(0.9, (y - 1110) / 190)
                fill = tuple(int(fill[i] * (1 - shade) + 4 * shade) for i in range(3))

            dest[x, y] = fill

    softened = replacement.filter(ImageFilter.GaussianBlur(1.1))
    replacement = Image.composite(softened, replacement, mask.filter(ImageFilter.GaussianBlur(2.0)))
    return Image.composite(replacement, base, mask.filter(ImageFilter.GaussianBlur(7.5)))


def main():
    img = Image.open(SRC).convert("RGB")
    img = ImageOps.exif_transpose(img)
    base = denoise_and_sharpen(img)
    mask = build_phone_mask(base.size)
    result = remove_phone(base, mask)

    # A final gentle clarity pass after the retouch blend.
    result = result.filter(ImageFilter.UnsharpMask(radius=0.8, percent=45, threshold=7))
    result.save(OUT, quality=94, subsampling=1, optimize=True)

    overlay = base.copy()
    red = Image.new("RGB", base.size, (255, 30, 30))
    overlay = Image.composite(red, overlay, mask.point(lambda p: 150 if p > 16 else 0))
    Image.blend(base, overlay, 0.45).save(MASK_PREVIEW)


if __name__ == "__main__":
    main()
