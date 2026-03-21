from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter


ROOT = Path.cwd()
OUTPUT_DIR = ROOT / ".wordpress-org"
SOURCE_DIR = OUTPUT_DIR / "source"

INK = "#17313b"
TEAL = "#2a7f87"
MINT = "#9ed6c2"
SAND = "#f5efe4"
WHITE = "#ffffff"
LINE = "#d8d2c7"
ACCENT = "#5ea9b0"


def load_font(size: int, bold: bool = False):
    candidates = []
    if bold:
        candidates = [
            r"C:\Windows\Fonts\segoeuib.ttf",
            r"C:\Windows\Fonts\arialbd.ttf",
        ]
    else:
        candidates = [
            r"C:\Windows\Fonts\segoeui.ttf",
            r"C:\Windows\Fonts\arial.ttf",
        ]

    for candidate in candidates:
        path = Path(candidate)
        if path.exists():
            return ImageFont.truetype(str(path), size=size)

    return ImageFont.load_default()


def rounded_rect(draw, box, radius, fill, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def draw_qr_modules(draw, origin_x, origin_y, module, gap, color, layout):
    for col, row, span_x, span_y in layout:
        x0 = origin_x + col * (module + gap)
        y0 = origin_y + row * (module + gap)
        x1 = x0 + module * span_x + gap * (span_x - 1)
        y1 = y0 + module * span_y + gap * (span_y - 1)
        draw.rounded_rectangle((x0, y0, x1, y1), radius=max(4, module // 4), fill=color)


def draw_pattern(draw, width, height):
    spacing = 38
    for x in range(-height, width, spacing):
        draw.line((x, 0, x + height, height), fill=LINE, width=1)

    for y in range(0, height, spacing):
        draw.line((0, y, width, y), fill="#f0e8db", width=1)


def draw_feature_chips(draw, x, y, labels, font):
    current_x = x
    for label in labels:
        bbox = draw.textbbox((0, 0), label, font=font)
        chip_w = bbox[2] - bbox[0] + 28
        chip_h = bbox[3] - bbox[1] + 16
        rounded_rect(draw, (current_x, y, current_x + chip_w, y + chip_h), 14, WHITE, outline="#d7dfdf", width=2)
        draw.text((current_x + 14, y + 8), label, fill=INK, font=font)
        current_x += chip_w + 10


def create_banner(width, height, filename):
    image = Image.new("RGBA", (width, height), SAND)
    draw = ImageDraw.Draw(image)
    draw_pattern(draw, width, height)

    shadow = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    shadow_draw = ImageDraw.Draw(shadow)
    shadow_draw.rounded_rectangle((width - 315, 28, width - 40, height - 28), radius=28, fill=(23, 49, 59, 45))
    shadow = shadow.filter(ImageFilter.GaussianBlur(12))
    image.alpha_composite(shadow)

    panel_box = (width - 330, 20, width - 30, height - 20)
    rounded_rect(draw, panel_box, 28, WHITE, outline="#dce3e3", width=2)
    rounded_rect(draw, (panel_box[0] + 18, panel_box[1] + 18, panel_box[2] - 18, panel_box[1] + 56), 18, "#eff4f2")
    draw.ellipse((panel_box[0] + 32, panel_box[1] + 31, panel_box[0] + 42, panel_box[1] + 41), fill=MINT)
    draw.ellipse((panel_box[0] + 48, panel_box[1] + 31, panel_box[0] + 58, panel_box[1] + 41), fill="#f0c36b")
    draw.ellipse((panel_box[0] + 64, panel_box[1] + 31, panel_box[0] + 74, panel_box[1] + 41), fill="#e68a7a")

    qr_box = (panel_box[0] + 34, panel_box[1] + 78, panel_box[2] - 34, panel_box[1] + 238)
    rounded_rect(draw, qr_box, 24, "#f7fbfa", outline="#dce8e8", width=2)

    module = 18 if width < 1000 else 36
    gap = 6 if width < 1000 else 10
    origin_x = qr_box[0] + 18
    origin_y = qr_box[1] + 18
    modules = [
        (0, 0, 2, 2), (2, 0, 1, 1), (0, 2, 1, 1), (2, 2, 1, 1),
        (5, 0, 2, 2), (7, 0, 1, 1), (5, 2, 1, 1),
        (0, 5, 2, 2), (2, 7, 1, 1), (1, 5, 1, 1),
        (4, 4, 1, 1), (5, 5, 1, 2), (7, 4, 1, 1), (6, 7, 2, 1),
        (3, 6, 1, 1), (4, 8, 2, 1), (8, 6, 1, 1)
    ]
    draw_qr_modules(draw, origin_x, origin_y, module, gap, INK, modules)

    ring_box = (qr_box[2] - module * 3 - 34, qr_box[3] - module * 3 - 30, qr_box[2] - 22, qr_box[3] - 18)
    draw.ellipse(ring_box, outline=TEAL, width=max(5, module // 4))
    inner = (
        ring_box[0] + 16,
        ring_box[1] + 16,
        ring_box[2] - 16,
        ring_box[3] - 16,
    )
    draw.ellipse(inner, fill=MINT)
    draw.line((inner[0] + 18, (inner[1] + inner[3]) // 2, inner[2] - 18, (inner[1] + inner[3]) // 2), fill=WHITE, width=max(5, module // 3))
    draw.line((((inner[0] + inner[2]) // 2), inner[1] + 18, ((inner[0] + inner[2]) // 2), inner[3] - 18), fill=WHITE, width=max(5, module // 3))

    eyebrow_font = load_font(17 if width < 1000 else 34, bold=False)
    title_font = load_font(30 if width < 1000 else 60, bold=True)
    body_font = load_font(17 if width < 1000 else 30, bold=False)
    chip_font = load_font(13 if width < 1000 else 22, bold=False)

    draw.text((34, 34), "Privacy-friendly WordPress QR workflows", fill=TEAL, font=eyebrow_font)
    draw.text((34, 68 if width < 1000 else 84), "Client-Side QR", fill=INK, font=title_font)
    draw.text((34, 104 if width < 1000 else 150), "Code Generator", fill=INK, font=title_font)

    body_text = "Generate styled QR codes in the browser for links, campaigns,\ncontact sharing, WiFi, and payments."
    draw.multiline_text((34, 154 if width < 1000 else 250), body_text, fill=INK, font=body_font, spacing=8)

    chips_y = height - (48 if width < 1000 else 84)
    draw_feature_chips(draw, 34, chips_y, ["Block + Shortcode", "Client-Side", "Campaign Ready"], chip_font)

    image.save(OUTPUT_DIR / filename)


def create_icon(size, filename):
    image = Image.new("RGBA", (size, size), (0, 0, 0, 0))

    shadow = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    shadow_draw = ImageDraw.Draw(shadow)
    shadow_draw.rounded_rectangle((12, 14, size - 12, size - 8), radius=size // 5, fill=(23, 49, 59, 55))
    shadow = shadow.filter(ImageFilter.GaussianBlur(size // 18))
    image.alpha_composite(shadow)

    draw = ImageDraw.Draw(image)
    rounded_rect(draw, (10, 10, size - 10, size - 10), size // 5, fill=SAND)
    rounded_rect(draw, (18, 18, size - 18, size - 18), size // 6, fill=WHITE, outline="#dce3e3", width=max(2, size // 64))

    module = size // 11
    gap = max(4, size // 64)
    origin_x = size // 5
    origin_y = size // 5

    corner_layout = [
        (0, 0, 2, 2), (2, 0, 1, 1), (0, 2, 1, 1), (2, 2, 1, 1),
        (5, 0, 2, 2), (7, 0, 1, 1), (5, 2, 1, 1),
        (0, 5, 2, 2), (2, 7, 1, 1), (1, 5, 1, 1)
    ]
    draw_qr_modules(draw, origin_x, origin_y, module, gap, INK, corner_layout)

    path_w = size // 8
    path_points = [
        (size * 0.42, size * 0.67),
        (size * 0.42, size * 0.48),
        (size * 0.58, size * 0.48),
        (size * 0.58, size * 0.36),
        (size * 0.74, size * 0.52),
        (size * 0.58, size * 0.68),
        (size * 0.58, size * 0.56),
        (size * 0.42, size * 0.56),
    ]
    draw.rounded_rectangle((size * 0.34, size * 0.32, size * 0.82, size * 0.74), radius=size // 10, fill=MINT)
    draw.polygon(path_points, fill=TEAL)
    draw.ellipse((size * 0.62, size * 0.30, size * 0.86, size * 0.54), fill=ACCENT)
    draw.line((size * 0.70, size * 0.39, size * 0.78, size * 0.39), fill=WHITE, width=max(4, size // 28))
    draw.line((size * 0.74, size * 0.35, size * 0.74, size * 0.43), fill=WHITE, width=max(4, size // 28))

    image.save(OUTPUT_DIR / filename)


def add_window_chrome(draw, box, title, subtitle=None):
    x0, y0, x1, y1 = box
    rounded_rect(draw, box, 28, WHITE, outline="#dce3e3", width=2)
    rounded_rect(draw, (x0 + 18, y0 + 18, x1 - 18, y0 + 58), 18, "#eff4f2")
    draw.ellipse((x0 + 30, y0 + 32, x0 + 40, y0 + 42), fill=MINT)
    draw.ellipse((x0 + 46, y0 + 32, x0 + 56, y0 + 42), fill="#f0c36b")
    draw.ellipse((x0 + 62, y0 + 32, x0 + 72, y0 + 42), fill="#e68a7a")
    title_font = load_font(22, bold=True)
    body_font = load_font(15, bold=False)
    draw.text((x0 + 92, y0 + 28), title, fill=INK, font=title_font)
    if subtitle:
        draw.text((x0 + 92, y0 + 53), subtitle, fill=TEAL, font=body_font)


def input_row(draw, x, y, w, label, value="", highlight=False):
    label_font = load_font(16, bold=True)
    value_font = load_font(15, bold=False)
    draw.text((x, y), label, fill=INK, font=label_font)
    box_y = y + 24
    rounded_rect(draw, (x, box_y, x + w, box_y + 38), 10, "#fbfcfc", outline=TEAL if highlight else "#d6dede", width=2)
    if value:
        draw.text((x + 14, box_y + 11), value, fill="#51666d", font=value_font)


def chip(draw, x, y, text, active=False):
    font = load_font(14, bold=active)
    bbox = draw.textbbox((0, 0), text, font=font)
    w = bbox[2] - bbox[0] + 24
    fill = "#e5f5fa" if active else "#ffffff"
    outline = TEAL if active else "#d6dede"
    rounded_rect(draw, (x, y, x + w, y + 34), 17, fill, outline=outline, width=2)
    draw.text((x + 12, y + 9), text, fill=INK, font=font)
    return w


def draw_qr_card(draw, x, y, size):
    rounded_rect(draw, (x, y, x + size, y + size), 18, "#f7fbfa", outline="#dce8e8", width=2)
    module = size // 8
    gap = max(4, size // 38)
    origin_x = x + 16
    origin_y = y + 16
    modules = [
        (0, 0, 2, 2), (2, 0, 1, 1), (0, 2, 1, 1), (2, 2, 1, 1),
        (4, 0, 2, 2), (6, 0, 1, 1), (4, 2, 1, 1),
        (0, 4, 2, 2), (2, 6, 1, 1), (1, 4, 1, 1),
        (3, 3, 1, 1), (4, 4, 1, 2), (6, 3, 1, 1), (5, 6, 2, 1)
    ]
    draw_qr_modules(draw, origin_x, origin_y, module, gap, INK, modules)
    ring_box = (x + size - 74, y + size - 74, x + size - 18, y + size - 18)
    draw.ellipse(ring_box, outline=TEAL, width=6)
    inner = (ring_box[0] + 14, ring_box[1] + 14, ring_box[2] - 14, ring_box[3] - 14)
    draw.ellipse(inner, fill=MINT)
    cx = (inner[0] + inner[2]) / 2
    cy = (inner[1] + inner[3]) / 2
    draw.line((cx, inner[1] + 7, cx, inner[3] - 7), fill=WHITE, width=5)
    draw.line((inner[0] + 7, cy, inner[2] - 7, cy), fill=WHITE, width=5)


def create_screenshot_frontend(filename):
    width, height = 1280, 960
    image = Image.new("RGBA", (width, height), SAND)
    draw = ImageDraw.Draw(image)
    draw_pattern(draw, width, height)
    title_font = load_font(34, bold=True)
    body_font = load_font(18, bold=False)

    draw.text((60, 48), "Frontend QR generator with accessible payload tabs and exports", fill=INK, font=title_font)
    draw.text((60, 96), "Representative view of the public interface for links, campaigns, WiFi, contact sharing, and downloads.", fill="#52676e", font=body_font)

    add_window_chrome(draw, (70, 150, 1210, 900), "Client-Side QR Code Generator", "Frontend experience")

    panel_x, panel_y = 110, 245
    w = chip(draw, panel_x, panel_y, "URL", active=True)
    w2 = chip(draw, panel_x + w + 10, panel_y, "WiFi")
    w3 = chip(draw, panel_x + w + w2 + 20, panel_y, "vCard")
    chip(draw, panel_x + w + w2 + w3 + 30, panel_y, "PayPal")

    input_row(draw, 110, 305, 430, "URL or text", "https://example.com/spring-campaign")
    input_row(draw, 110, 390, 200, "UTM source", "print")
    input_row(draw, 325, 390, 215, "UTM medium", "poster", highlight=True)
    input_row(draw, 110, 475, 430, "UTM campaign", "spring-launch")

    rounded_rect(draw, (110, 565, 540, 740), 18, "#f6f7f7", outline="#d6dede", width=2)
    draw.text((132, 590), "Design options", fill=INK, font=load_font(22, bold=True))
    input_row(draw, 132, 630, 125, "Foreground", "#17313b")
    input_row(draw, 275, 630, 125, "Background", "#ffffff")
    input_row(draw, 418, 630, 90, "Size", "256")

    draw_qr_card(draw, 720, 300, 300)
    draw.text((760, 628), "QR code ready for download.", fill=TEAL, font=load_font(18, bold=True))
    chip(draw, 720, 680, "Download PNG", active=True)
    chip(draw, 880, 680, "Download SVG")
    chip(draw, 1038, 680, "Copy image")

    image.save(OUTPUT_DIR / filename)


def create_screenshot_editor(filename):
    width, height = 1280, 960
    image = Image.new("RGBA", (width, height), "#eef1ef")
    draw = ImageDraw.Draw(image)
    title_font = load_font(34, bold=True)
    body_font = load_font(18, bold=False)

    draw.text((60, 48), "Gutenberg block controls for design and payload defaults", fill=INK, font=title_font)
    draw.text((60, 96), "Representative editor view showing design controls, payload toggles, and live preview behavior.", fill="#52676e", font=body_font)

    add_window_chrome(draw, (50, 150, 1230, 910), "Block Editor", "Client-Side QR Code block")

    rounded_rect(draw, (90, 220, 860, 860), 24, WHITE, outline="#dce3e3", width=2)
    draw.text((120, 255), "Client-Side QR Code Preview", fill=INK, font=load_font(30, bold=True))
    draw.text((120, 300), "The public block renders a fully interactive client-side QR form.", fill="#52676e", font=load_font(18))
    draw_qr_card(draw, 270, 390, 360)

    rounded_rect(draw, (900, 220, 1190, 860), 22, "#f7f9f8", outline="#d6dede", width=2)
    draw.text((928, 255), "Design Defaults", fill=INK, font=load_font(24, bold=True))
    input_row(draw, 928, 300, 230, "Dot style", "Rounded")
    input_row(draw, 928, 382, 230, "Foreground color 1", "#17313b")
    input_row(draw, 928, 464, 230, "Background color", "#ffffff")
    input_row(draw, 928, 546, 230, "Error correction", "High (30%)")

    draw.text((928, 640), "Available Payload Types", fill=INK, font=load_font(22, bold=True))
    chip(draw, 928, 678, "URL", active=True)
    chip(draw, 1000, 678, "WiFi", active=True)
    chip(draw, 1078, 678, "vCard", active=True)
    chip(draw, 928, 724, "Email")
    chip(draw, 1017, 724, "SMS")
    chip(draw, 1083, 724, "PayPal")

    image.save(OUTPUT_DIR / filename)


def create_screenshot_settings(filename):
    width, height = 1280, 960
    image = Image.new("RGBA", (width, height), SAND)
    draw = ImageDraw.Draw(image)
    draw_pattern(draw, width, height)
    title_font = load_font(34, bold=True)
    body_font = load_font(18, bold=False)

    draw.text((60, 48), "Lightweight settings page for site-wide defaults", fill=INK, font=title_font)
    draw.text((60, 96), "Representative admin settings view for defaults that keep new block and shortcode instances consistent.", fill="#52676e", font=body_font)

    add_window_chrome(draw, (70, 150, 1210, 900), "Settings > Client-Side QR", "WordPress admin")
    rounded_rect(draw, (110, 225, 1170, 850), 22, WHITE, outline="#dce3e3", width=2)
    draw.text((145, 260), "Client-Side QR Code Generator", fill=INK, font=load_font(32, bold=True))
    draw.text((145, 306), "Set lightweight defaults for new QR block and shortcode instances.", fill="#52676e", font=load_font(18))

    input_row(draw, 145, 365, 180, "Default QR size", "256")
    input_row(draw, 360, 365, 220, "Foreground color", "#111111")
    input_row(draw, 615, 365, 220, "Background color", "#ffffff")
    input_row(draw, 870, 365, 220, "Error correction", "High (30%)")

    draw.text((145, 475), "Payload types enabled by default", fill=INK, font=load_font(24, bold=True))
    x, y = 145, 520
    for label in ["URL / Text", "WiFi", "vCard", "Email", "SMS", "Crypto", "PayPal"]:
        width_chip = chip(draw, x, y, label, active=True)
        x += width_chip + 12
        if x > 980:
            x = 145
            y += 48

    rounded_rect(draw, (145, 690, 340, 748), 18, TEAL)
    draw.text((205, 707), "Save Changes", fill=WHITE, font=load_font(22, bold=True))

    image.save(OUTPUT_DIR / filename)


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    SOURCE_DIR.mkdir(parents=True, exist_ok=True)

    create_banner(772, 250, "banner-772x250.png")
    create_banner(1544, 500, "banner-1544x500.png")
    create_icon(128, "icon-128x128.png")
    create_icon(256, "icon-256x256.png")
    create_screenshot_frontend("screenshot-1.png")
    create_screenshot_editor("screenshot-2.png")
    create_screenshot_settings("screenshot-3.png")

    print("Generated WordPress.org banner, icon, and screenshot assets.")


if __name__ == "__main__":
    main()
