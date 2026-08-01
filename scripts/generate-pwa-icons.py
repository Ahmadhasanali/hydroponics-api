#!/usr/bin/env python3
from PIL import Image, ImageDraw
import os

OUT_DIR = "public/icons"
BG = (255, 206, 84, 255)   # #ffce54
FG = (26, 28, 30, 255)     # #1a1c1e


def droplet(size, scale, center):
    cx, cy = center
    r = size * scale
    return [
        (cx, cy - r * 1.35),
        (cx - r * 1.05, cy - r * 0.15),
        (cx - r, cy + r * 0.45),
        (cx - r * 0.55, cy + r * 0.95),
        (cx, cy + r * 1.2),
        (cx + r * 0.55, cy + r * 0.95),
        (cx + r, cy + r * 0.45),
        (cx + r * 1.05, cy - r * 0.15),
    ]


def make_icon(size, path, maskable=False):
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if maskable:
        d.rectangle([0, 0, size - 1, size - 1], fill=BG)
        d.polygon(droplet(size, 0.21, (size / 2, size / 2)), fill=FG)
    else:
        d.rounded_rectangle([0, 0, size - 1, size - 1], radius=int(size * 0.22), fill=BG)
        d.polygon(droplet(size, 0.28, (size / 2, size / 2 + size * 0.03)), fill=FG)
    img.save(path)


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    make_icon(192, f"{OUT_DIR}/icon-192x192.png")
    make_icon(512, f"{OUT_DIR}/icon-512x512.png")
    make_icon(512, f"{OUT_DIR}/icon-maskable-512x512.png", maskable=True)
    print("Icons generated in", OUT_DIR)
