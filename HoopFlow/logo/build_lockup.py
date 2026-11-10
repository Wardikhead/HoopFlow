"""Composite the HoopFlow bracket icon with the wordmark + motto into lockups."""
from PIL import Image

LOGO = "logo/hoopflow Logo and moto.png"
ICON = "logo/hoopflow icon without background.png"


def trim(im, thresh=8):
    """Crop to the bounding box of visible (non-transparent) pixels."""
    a = im.getchannel("A").point(lambda v: 255 if v > thresh else 0)
    box = a.getbbox()
    return im.crop(box)


def bands(im, min_gap=3, thresh=8):
    """Split into horizontal ink bands, e.g. [wordmark, motto]."""
    w, h = im.size
    alpha = im.getchannel("A").load()
    ink = []
    for y in range(h):
        has = any(alpha[x, y] > thresh for x in range(w))
        ink.append(has)
    result, start, gap = [], None, 0
    for y, has in enumerate(ink):
        if has:
            if start is None:
                start = y
            gap = 0
        elif start is not None:
            gap += 1
            if gap >= min_gap:
                result.append((start, y - gap + 1))
                start = None
    if start is not None:
        result.append((start, h))
    return result


logo = trim(Image.open(LOGO).convert("RGBA"))
icon = trim(Image.open(ICON).convert("RGBA"))

b = bands(logo)
# The "p" descender overlaps the motto line, so the block is one band. That is
# fine: align the icon optically to the whole lockup rather than to a row.
print(f"logo {logo.size}, ink bands {b}")


def scaled(im, height):
    if height == im.size[1]:
        return im.copy()
    w = round(im.size[0] * height / im.size[1])
    return im.resize((w, height), Image.LANCZOS)


def pad(im, px):
    out = Image.new("RGBA", (im.width + 2 * px, im.height + 2 * px), (0, 0, 0, 0))
    out.alpha_composite(im, (px, px))
    return out


# ---------- horizontal lockup: icon left, wordmark right, motto under ----------
icon_h = round(logo.height * 0.88)     # optical match to the wordmark block
icon_hz = scaled(icon, icon_h)
gap = round(icon_hz.width * 0.30)

width = icon_hz.width + gap + logo.width
height = max(logo.height, icon_hz.height)
canvas = Image.new("RGBA", (width, height), (0, 0, 0, 0))

canvas.alpha_composite(icon_hz, (0, (height - icon_hz.height) // 2))
canvas.alpha_composite(logo, (icon_hz.width + gap, (height - logo.height) // 2))

horizontal = pad(canvas, round(height * 0.10))
horizontal = scaled(horizontal, 800) if horizontal.height > 800 else horizontal
horizontal.save("logo/hoopflow lockup horizontal.png")

# ---------- stacked lockup: icon above, wordmark below ----------
icon_w = round(horizontal.width * 0.42)
icon_st = scaled(icon, round(icon.size[1] * icon_w / icon.size[0]))
v_gap = round(icon_st.height * 0.20)
logo_w = max(icon_st.width, logo.width)
width2 = logo_w
height2 = icon_st.height + v_gap + logo.height
canvas2 = Image.new("RGBA", (width2, height2), (0, 0, 0, 0))
canvas2.alpha_composite(icon_st, ((width2 - icon_st.width) // 2, 0))
canvas2.alpha_composite(logo, ((width2 - logo.width) // 2, icon_st.height + v_gap))

stacked = pad(canvas2, round(height2 * 0.08))
stacked = scaled(stacked, 900) if stacked.height > 900 else stacked
stacked.save("logo/hoopflow lockup stacked.png")

# ---------- white-background copies for slides and printing ----------
for im, name in ((horizontal, "hoopflow lockup horizontal on white"), (stacked, "hoopflow lockup stacked on white")):
    flat = Image.new("RGBA", im.size, (255, 255, 255, 255))
    flat.alpha_composite(im)
    flat.convert("RGB").save(f"logo/{name}.png")

print("horizontal:", horizontal.size)
print("stacked   :", stacked.size)
