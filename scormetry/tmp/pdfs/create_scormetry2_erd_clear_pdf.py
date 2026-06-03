from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path("/Users/mac/Downloads/FYP_Scormtry-2.0")
OUT_DIR = ROOT / "output/pdf"
PNG_PATH = OUT_DIR / "Scormetry2_ERD_Clear_Connections_PreviousYearStyle.png"
PDF_PATH = OUT_DIR / "Scormetry2_ERD_Clear_Connections_PreviousYearStyle.pdf"

PAGE_W, PAGE_H = 4961, 3508
FRAME = (140, 530, 4820, 3015)

FONT_DIR = Path("/System/Library/Fonts/Supplemental")
TIMES_BOLD = str(FONT_DIR / "Times New Roman Bold.ttf")
COMIC = str(FONT_DIR / "Comic Sans MS.ttf")
COMIC_BOLD = str(FONT_DIR / "Comic Sans MS Bold.ttf")


def font(path: str, size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(path, size)


heading_font = font(TIMES_BOLD, 106)
caption_font = font(TIMES_BOLD, 112)
title_font = font(COMIC_BOLD, 38)
body_font = font(COMIC, 34)
key_font = font(COMIC_BOLD, 31)
json_key_font = font(COMIC_BOLD, 24)


tables: dict[str, dict] = {
    "subject_invitations": {
        "title": "Subject Invitation",
        "x": 210,
        "y": 720,
        "w": 560,
        "fields": [
            ("PK", "id"),
            ("FK", "subject_id"),
            ("", "email"),
            ("", "committee_role"),
            ("", "role_label"),
            ("", "token"),
            ("", "accepted_at"),
            ("", "created_at"),
        ],
    },
    "subject_members": {
        "title": "Subject Member",
        "x": 900,
        "y": 635,
        "w": 565,
        "fields": [
            ("PK", "id"),
            ("FK", "subject_id"),
            ("FK", "user_id"),
            ("", "role"),
            ("", "role_label"),
            ("", "status"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "subjects": {
        "title": "Subject",
        "x": 1615,
        "y": 640,
        "w": 610,
        "fields": [
            ("PK", "id"),
            ("FK", "teacher_id"),
            ("", "title"),
            ("", "description"),
            ("", "passing_score"),
            ("", "join_code"),
            ("", "reviewer_code"),
            ("", "require_approval"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "rubrics": {
        "title": "Rubric",
        "x": 2580,
        "y": 555,
        "w": 600,
        "fields": [
            ("PK", "id"),
            ("FK", "subject_id"),
            ("", "pdf_path"),
            ("", "structure_json"),
            ("", "status"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "rubric_structure": {
        "title": "Dynamic Rubric Structure",
        "x": 3495,
        "y": 535,
        "w": 720,
        "fields": [
            ("JSON", "criteria"),
            ("JSON", "levels"),
            ("JSON", "weights"),
            ("JSON", "score_ranges"),
            ("JSON", "verification_state"),
        ],
    },
    "subject_blocked_emails": {
        "title": "Subject Blocked Email",
        "x": 210,
        "y": 1685,
        "w": 650,
        "fields": [
            ("PK", "id"),
            ("FK", "subject_id"),
            ("", "email"),
            ("FK", "blocked_by"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "teams": {
        "title": "Team",
        "x": 1000,
        "y": 1640,
        "w": 645,
        "fields": [
            ("PK", "id"),
            ("FK", "subject_id"),
            ("", "name"),
            ("", "defense_date"),
            ("", "defense_time"),
            ("", "defense_duration"),
            ("", "defense_room"),
            ("", "score_deadline_at"),
            ("", "results_released_at"),
        ],
    },
    "team_members": {
        "title": "Team Member",
        "x": 1840,
        "y": 1810,
        "w": 560,
        "fields": [
            ("PK", "id"),
            ("FK", "team_id"),
            ("FK", "user_id"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "users": {
        "title": "User",
        "x": 2550,
        "y": 1685,
        "w": 600,
        "fields": [
            ("PK", "id"),
            ("", "name"),
            ("", "email"),
            ("", "google_id"),
            ("", "role"),
            ("", "status"),
            ("", "is_blocked"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "papers": {
        "title": "Paper",
        "x": 3435,
        "y": 1525,
        "w": 675,
        "fields": [
            ("PK", "id"),
            ("FK", "team_id"),
            ("FK", "subject_id"),
            ("", "file_path"),
            ("", "final_score"),
            ("", "final_score_override"),
            ("", "override_reason"),
            ("FK", "override_by"),
            ("", "visibility_status"),
        ],
    },
    "reviews": {
        "title": "Review",
        "x": 4245,
        "y": 1225,
        "w": 540,
        "fields": [
            ("PK", "id"),
            ("FK", "paper_id"),
            ("FK", "reviewer_id"),
            ("", "committee_role"),
            ("", "scores_json"),
            ("", "comment"),
            ("", "is_submitted"),
            ("", "locked_at"),
            ("", "unlocked_at"),
            ("FK", "unlocked_by"),
        ],
    },
    "review_unlock_logs": {
        "title": "Review Unlock Log",
        "x": 3650,
        "y": 2390,
        "w": 690,
        "fields": [
            ("PK", "id"),
            ("FK", "review_id"),
            ("FK", "team_id"),
            ("FK", "judge_id"),
            ("FK", "unlocked_by"),
            ("", "reason"),
            ("", "created_at"),
        ],
    },
    "app_settings": {
        "title": "App Setting",
        "x": 2585,
        "y": 2550,
        "w": 600,
        "fields": [
            ("PK", "id"),
            ("", "key"),
            ("", "value"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
}

HEADER_H = 72
ROW_H = 58
KEY_W = 98


def table_height(name: str) -> int:
    return HEADER_H + ROW_H * len(tables[name]["fields"])


def field_index(name: str, field: str) -> int:
    for idx, (_key, value) in enumerate(tables[name]["fields"]):
        if value == field:
            return idx
    raise KeyError(f"{name}.{field}")


def anchor(name: str, field: str, side: str) -> tuple[int, int, str]:
    table = tables[name]
    idx = field_index(name, field)
    y = table["y"] + HEADER_H + idx * ROW_H + ROW_H // 2
    if side == "left":
        return table["x"], y, side
    if side == "right":
        return table["x"] + table["w"], y, side
    if side == "top":
        return table["x"] + table["w"] // 2, table["y"], side
    if side == "bottom":
        return table["x"] + table["w"] // 2, table["y"] + table_height(name), side
    raise ValueError(side)


def orthogonalize(points: list[tuple[int, int]]) -> list[tuple[int, int]]:
    fixed = [points[0]]
    for target in points[1:]:
        current = fixed[-1]
        if current[0] != target[0] and current[1] != target[1]:
            fixed.append((target[0], current[1]))
        fixed.append(target)
    return fixed


def draw_polyline(draw: ImageDraw.ImageDraw, points: list[tuple[int, int]], width: int = 4) -> None:
    points = orthogonalize(points)
    for start, end in zip(points, points[1:]):
        draw.line([start, end], fill=(78, 78, 78), width=width)


def double_bar(draw: ImageDraw.ImageDraw, x: int, y: int, side: str) -> None:
    fill = (20, 20, 20)
    if side == "left":
        for dx in (-14, -7):
            draw.line([(x + dx, y - 12), (x + dx, y + 12)], fill=fill, width=3)
    elif side == "right":
        for dx in (7, 14):
            draw.line([(x + dx, y - 12), (x + dx, y + 12)], fill=fill, width=3)
    elif side == "top":
        for dy in (-14, -7):
            draw.line([(x - 12, y + dy), (x + 12, y + dy)], fill=fill, width=3)
    elif side == "bottom":
        for dy in (7, 14):
            draw.line([(x - 12, y + dy), (x + 12, y + dy)], fill=fill, width=3)


def crowfoot(draw: ImageDraw.ImageDraw, x: int, y: int, side: str) -> None:
    fill = (20, 20, 20)
    width = 3
    if side == "left":
        base = (x - 16, y)
        ends = [(x - 2, y), (x - 2, y - 12), (x - 2, y + 12)]
        circle = (x - 26, y)
    elif side == "right":
        base = (x + 16, y)
        ends = [(x + 2, y), (x + 2, y - 12), (x + 2, y + 12)]
        circle = (x + 26, y)
    elif side == "top":
        base = (x, y - 16)
        ends = [(x, y - 2), (x - 12, y - 2), (x + 12, y - 2)]
        circle = (x, y - 26)
    else:
        base = (x, y + 16)
        ends = [(x, y + 2), (x - 12, y + 2), (x + 12, y + 2)]
        circle = (x, y + 26)
    for end in ends:
        draw.line([base, end], fill=fill, width=width)
    cx, cy = circle
    r = 6
    draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=fill, width=width)


def draw_relation(
    draw: ImageDraw.ImageDraw,
    parent: str,
    parent_field: str,
    child: str,
    child_field: str,
    parent_side: str,
    child_side: str,
    route: list[tuple[int, int]] | None = None,
) -> None:
    px, py, ps = anchor(parent, parent_field, parent_side)
    cx, cy, cs = anchor(child, child_field, child_side)
    if route is None:
        if parent_side in {"left", "right"} and child_side in {"left", "right"}:
            mid_x = (px + cx) // 2
            points = [(px, py), (mid_x, py), (mid_x, cy), (cx, cy)]
        elif parent_side in {"top", "bottom"} and child_side in {"top", "bottom"}:
            mid_y = (py + cy) // 2
            points = [(px, py), (px, mid_y), (cx, mid_y), (cx, cy)]
        else:
            points = [(px, py), (px, cy), (cx, cy)]
    else:
        points = [(px, py), *route, (cx, cy)]
    draw_polyline(draw, points)
    double_bar(draw, px, py, ps)
    crowfoot(draw, cx, cy, cs)


def text_center(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], text: str, fnt, fill=(0, 0, 0)) -> None:
    x1, y1, x2, y2 = box
    bbox = draw.textbbox((0, 0), text, font=fnt)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.text((x1 + (x2 - x1 - tw) / 2, y1 + (y2 - y1 - th) / 2 - 2), text, font=fnt, fill=fill)


def draw_table(draw: ImageDraw.ImageDraw, name: str) -> None:
    table = tables[name]
    x, y, w = table["x"], table["y"], table["w"]
    h = table_height(name)
    border = (150, 185, 226)
    header = (219, 234, 251)
    row = (210, 224, 242)
    draw.rectangle((x, y, x + w, y + h), fill=(255, 255, 255), outline=border, width=5)
    draw.rectangle((x, y, x + w, y + HEADER_H), fill=header, outline=border, width=5)
    text_center(draw, (x, y, x + w, y + HEADER_H), table["title"], title_font)
    draw.line((x, y + HEADER_H, x + w, y + HEADER_H), fill=(45, 45, 45), width=3)
    draw.line((x + KEY_W, y + HEADER_H, x + KEY_W, y + h), fill=row, width=4)
    for idx, (key, field) in enumerate(table["fields"]):
        row_y = y + HEADER_H + idx * ROW_H
        if idx > 0:
            draw.line((x, row_y, x + w, row_y), fill=row, width=3)
        draw.text((x + 12, row_y + 12), key, font=json_key_font if key == "JSON" else key_font, fill=(0, 0, 0))
        draw.text((x + KEY_W + 24, row_y + 12), field, font=body_font, fill=(0, 0, 0))


def create() -> None:
    img = Image.new("RGB", (PAGE_W, PAGE_H), "white")
    draw = ImageDraw.Draw(img)

    draw.text((130, 130), "3.5.2 Data Design", font=heading_font, fill=(0, 0, 0))
    draw.text((130, 355), "3.5.2.1 Entity Relationship Diagram", font=heading_font, fill=(0, 0, 0))
    draw.rectangle(FRAME, outline=(0, 0, 0), width=7)

    # Core subject and membership relationships.
    draw_relation(draw, "subjects", "id", "subject_invitations", "subject_id", "left", "right", [(820, 705), (820, 865)])
    draw_relation(draw, "subjects", "id", "subject_members", "subject_id", "left", "right")
    draw_relation(draw, "users", "id", "subject_members", "user_id", "left", "right", [(2420, 1375), (1510, 1375), (1510, 807)])
    draw_relation(draw, "users", "id", "subjects", "teacher_id", "left", "right", [(2420, 1375), (2325, 1375), (2325, 785)])

    # Subject-owned records.
    draw_relation(draw, "subjects", "id", "subject_blocked_emails", "subject_id", "left", "right", [(920, 1425), (920, 1815)])
    draw_relation(draw, "users", "id", "subject_blocked_emails", "blocked_by", "left", "right", [(2420, 1495), (900, 1495), (900, 1931)])
    draw_relation(draw, "subjects", "id", "teams", "subject_id", "bottom", "top", [(1920, 1475), (1322, 1475)])
    draw_relation(draw, "subjects", "id", "rubrics", "subject_id", "right", "left")
    draw_relation(draw, "rubrics", "structure_json", "rubric_structure", "criteria", "right", "left", [(3325, 729), (3325, 636)])

    # Team and document workflow.
    draw_relation(draw, "teams", "id", "team_members", "team_id", "right", "left")
    draw_relation(draw, "users", "id", "team_members", "user_id", "left", "right")
    draw_relation(draw, "teams", "id", "papers", "team_id", "right", "left", [(1750, 1515), (3330, 1515), (3330, 1684)])
    draw_relation(draw, "subjects", "id", "papers", "subject_id", "right", "left", [(2380, 1375), (3330, 1375), (3330, 1699)])
    draw_relation(draw, "users", "id", "papers", "override_by", "right", "left")

    # Review and unlock records.
    draw_relation(draw, "papers", "id", "reviews", "paper_id", "right", "left")
    draw_relation(draw, "users", "id", "reviews", "reviewer_id", "top", "left", [(2850, 1120), (4245, 1120)])
    draw_relation(draw, "users", "id", "reviews", "unlocked_by", "right", "left", [(3335, 2170), (3335, 1825), (4245, 1825)])
    draw_relation(draw, "reviews", "id", "review_unlock_logs", "review_id", "bottom", "top")
    draw_relation(draw, "teams", "id", "review_unlock_logs", "team_id", "right", "left", [(1745, 2340), (3650, 2340)])
    draw_relation(draw, "users", "id", "review_unlock_logs", "judge_id", "bottom", "left", [(2850, 2310), (3510, 2310), (3510, 2607)])
    draw_relation(draw, "users", "id", "review_unlock_logs", "unlocked_by", "bottom", "left", [(3020, 2365), (3565, 2365), (3565, 2665)])

    for name in tables:
        draw_table(draw, name)

    caption = "Fig. 31. Entity Relationship Diagram for Scormetry 2.0"
    bbox = draw.textbbox((0, 0), caption, font=caption_font)
    draw.text(((PAGE_W - (bbox[2] - bbox[0])) / 2, 3195), caption, font=caption_font, fill=(0, 0, 0))

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    img.save(PNG_PATH, quality=100)
    img.save(PDF_PATH, "PDF", resolution=300.0)
    print(PNG_PATH)
    print(PDF_PATH)


if __name__ == "__main__":
    create()
