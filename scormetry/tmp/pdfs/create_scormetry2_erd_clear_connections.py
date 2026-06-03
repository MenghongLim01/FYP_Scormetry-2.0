from __future__ import annotations

from html import escape
from pathlib import Path


ROOT = Path("/Users/mac/Downloads/FYP_Scormtry-2.0")
OUT_DIR = ROOT / "docs/chapter3-diagrams"
SVG_DIR = OUT_DIR / "erd-clear-connections-svg"
HTML_PATH = OUT_DIR / "Scormetry2_ERD_ClearConnections.html"
SVG_PATH = SVG_DIR / "fig-31-entity-relationship-diagram-scormetry-20-clear-connections.svg"

W = 1580
H = 1060
FRAME = (35, 35, 1510, 940)
HEAD = 24
ROW = 18
KEY_W = 34


TABLES = {
    "subject_invitations": {
        "x": 70,
        "y": 115,
        "w": 205,
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
        "x": 330,
        "y": 115,
        "w": 205,
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
        "x": 615,
        "y": 105,
        "w": 220,
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
        "x": 970,
        "y": 120,
        "w": 215,
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
    "dynamic_rubric_structure": {
        "x": 1275,
        "y": 125,
        "w": 210,
        "fields": [
            ("JSON", "criteria"),
            ("JSON", "levels"),
            ("JSON", "weights"),
            ("JSON", "score_ranges"),
            ("JSON", "verification_state"),
        ],
    },
    "subject_blocked_emails": {
        "x": 70,
        "y": 430,
        "w": 215,
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
        "x": 360,
        "y": 415,
        "w": 230,
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
        "x": 655,
        "y": 465,
        "w": 195,
        "fields": [
            ("PK", "id"),
            ("FK", "team_id"),
            ("FK", "user_id"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "users": {
        "x": 655,
        "y": 690,
        "w": 210,
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
        "x": 965,
        "y": 445,
        "w": 255,
        "fields": [
            ("PK", "id"),
            ("FK", "team_id"),
            ("FK", "subject_id"),
            ("", "file_path"),
            ("", "final_score"),
            ("", "final_score_override"),
            ("", "final_score_override_reason"),
            ("FK", "final_score_override_by"),
            ("", "visibility_status"),
        ],
    },
    "reviews": {
        "x": 1290,
        "y": 400,
        "w": 220,
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
            ("", "unlock_reason"),
            ("FK", "unlocked_by"),
        ],
    },
    "review_unlock_logs": {
        "x": 1040,
        "y": 735,
        "w": 230,
        "fields": [
            ("PK", "id"),
            ("FK", "review_id"),
            ("FK", "team_id"),
            ("FK", "judge_id"),
            ("FK", "unlocked_by"),
            ("", "reason"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
    "app_settings": {
        "x": 395,
        "y": 760,
        "w": 205,
        "fields": [
            ("PK", "id"),
            ("", "key"),
            ("", "value"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
}


def height(table: dict) -> int:
    return HEAD + ROW * len(table["fields"])


def attach(name: str, side: str, offset: float) -> tuple[float, float]:
    table = TABLES[name]
    x, y, w, h = table["x"], table["y"], table["w"], height(table)
    if side == "L":
        return x, y + h * offset
    if side == "R":
        return x + w, y + h * offset
    if side == "T":
        return x + w * offset, y
    return x + w * offset, y + h


def text(x: float, y: float, value: str, cls: str, anchor: str = "start") -> str:
    return f'<text x="{x:.1f}" y="{y:.1f}" class="{cls}" text-anchor="{anchor}">{escape(value)}</text>'


def polyline(points: list[tuple[float, float]], cls: str = "rel") -> str:
    values = " ".join(f"{x:.1f},{y:.1f}" for x, y in points)
    return f'<polyline class="{cls}" points="{values}" />'


def line(a: tuple[float, float], b: tuple[float, float], cls: str = "rel") -> str:
    return f'<line class="{cls}" x1="{a[0]:.1f}" y1="{a[1]:.1f}" x2="{b[0]:.1f}" y2="{b[1]:.1f}" />'


def endpoint_symbol(point: tuple[float, float], side: str, kind: str) -> str:
    """Draw simple ERD-style endpoint symbols. kind = one | optional | many."""
    x, y = point
    out = []

    def l(x1: float, y1: float, x2: float, y2: float) -> None:
        out.append(f'<line class="symbol" x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}" />')

    if side in ("L", "R"):
        dx = -1 if side == "L" else 1
        if kind in ("one", "optional"):
            l(x + dx * 5, y - 7, x + dx * 5, y + 7)
        if kind == "optional":
            out.append(f'<circle class="symbol-circle" cx="{x + dx * 13:.1f}" cy="{y:.1f}" r="4.4" />')
        if kind == "many":
            l(x + dx * 2, y, x + dx * 14, y - 9)
            l(x + dx * 2, y, x + dx * 14, y)
            l(x + dx * 2, y, x + dx * 14, y + 9)
    else:
        dy = -1 if side == "T" else 1
        if kind in ("one", "optional"):
            l(x - 7, y + dy * 5, x + 7, y + dy * 5)
        if kind == "optional":
            out.append(f'<circle class="symbol-circle" cx="{x:.1f}" cy="{y + dy * 13:.1f}" r="4.4" />')
        if kind == "many":
            l(x, y + dy * 2, x - 9, y + dy * 14)
            l(x, y + dy * 2, x, y + dy * 14)
            l(x, y + dy * 2, x + 9, y + dy * 14)
    return "\n".join(out)


def draw_table(name: str) -> str:
    table = TABLES[name]
    x, y, w, h = table["x"], table["y"], table["w"], height(table)
    out = [
        f'<g class="entity" id="{escape(name)}">',
        f'<rect class="entity-body" x="{x}" y="{y}" width="{w}" height="{h}" />',
        f'<rect class="entity-head" x="{x}" y="{y}" width="{w}" height="{HEAD}" />',
        f'<line class="divider-strong" x1="{x}" y1="{y+HEAD}" x2="{x+w}" y2="{y+HEAD}" />',
        f'<line class="divider" x1="{x+KEY_W}" y1="{y+HEAD}" x2="{x+KEY_W}" y2="{y+h}" />',
        text(x + w / 2, y + 16, name, "entity-title", "middle"),
    ]

    for row, (tag, field) in enumerate(table["fields"]):
        row_y = y + HEAD + row * ROW
        out.append(f'<line class="row-line" x1="{x}" y1="{row_y+ROW}" x2="{x+w}" y2="{row_y+ROW}" />')
        key_cls = "field-key json-key" if tag == "JSON" else "field-key"
        field_cls = "field-name pk" if tag == "PK" else "field-name"
        if tag:
            out.append(text(x + 6, row_y + 13, tag, key_cls))
        out.append(text(x + KEY_W + 9, row_y + 13, field, field_cls))
    out.append("</g>")
    return "\n".join(out)


def route(child: tuple[str, str, float], parent: tuple[str, str, float], via: list[tuple[float, float]] | None = None) -> list[tuple[float, float]]:
    c = attach(*child)
    p = attach(*parent)
    if via:
        return [c, *via, p]

    c_side = child[1]
    p_side = parent[1]
    # clean orthogonal default: leave child side, use midpoint, enter parent side
    if c_side in ("L", "R") and p_side in ("L", "R"):
        mid_x = (c[0] + p[0]) / 2
        return [c, (mid_x, c[1]), (mid_x, p[1]), p]
    if c_side in ("T", "B") and p_side in ("T", "B"):
        mid_y = (c[1] + p[1]) / 2
        return [c, (c[0], mid_y), (p[0], mid_y), p]
    if c_side in ("L", "R"):
        return [c, (p[0], c[1]), p]
    return [c, (c[0], p[1]), p]


# Each relation is child table FK -> parent table PK.
# The child endpoint is drawn with a crow's foot; the parent endpoint is drawn with "one".
RELATIONS = [
    (("subject_invitations", "R", 0.28), ("subjects", "L", 0.22), None),
    (("subject_members", "R", 0.28), ("subjects", "L", 0.36), None),
    (("rubrics", "L", 0.28), ("subjects", "R", 0.22), None),
    (("dynamic_rubric_structure", "L", 0.54), ("rubrics", "R", 0.50), None),
    (("teams", "T", 0.55), ("subjects", "B", 0.38), None),
    (("subject_blocked_emails", "R", 0.30), ("subjects", "L", 0.78), [(305, 475), (305, 315), (615, 315)]),
    (("papers", "T", 0.28), ("subjects", "R", 0.76), [(1036, 350), (835, 350)]),
    (("subjects", "B", 0.70), ("users", "T", 0.36), [(769, 345), (730, 345)]),
    (("subject_members", "B", 0.55), ("users", "T", 0.16), [(443, 640), (689, 640)]),
    (("subject_blocked_emails", "B", 0.72), ("users", "L", 0.55), [(225, 665), (620, 665)]),
    (("team_members", "L", 0.42), ("teams", "R", 0.52), None),
    (("team_members", "B", 0.55), ("users", "T", 0.62), [(762, 665), (785, 665)]),
    (("papers", "L", 0.24), ("teams", "R", 0.74), [(915, 505), (915, 555), (590, 555)]),
    (("papers", "L", 0.78), ("users", "R", 0.40), None),
    (("reviews", "L", 0.22), ("papers", "R", 0.28), None),
    (("reviews", "L", 0.35), ("users", "R", 0.26), [(1245, 476), (1245, 735), (865, 735)]),
    (("reviews", "L", 0.85), ("users", "R", 0.64), [(1260, 578), (1260, 790), (865, 790)]),
    (("review_unlock_logs", "T", 0.24), ("reviews", "B", 0.82), [(1095, 690), (1470, 690)]),
    (("review_unlock_logs", "L", 0.36), ("teams", "B", 0.40), [(890, 830), (452, 830)]),
    (("review_unlock_logs", "L", 0.57), ("users", "R", 0.78), [(900, 840), (865, 840)]),
    (("review_unlock_logs", "T", 0.73), ("users", "B", 0.80), [(1208, 720), (823, 720)]),
]


def build_svg() -> str:
    relation_paths = []
    relation_symbols = []
    for child, parent, via in RELATIONS:
        points = route(child, parent, via)
        relation_paths.append(polyline(points))
        relation_symbols.append(endpoint_symbol(attach(*child), child[1], "many"))
        relation_symbols.append(endpoint_symbol(attach(*parent), parent[1], "one"))

    entities = "\n".join(draw_table(name) for name in TABLES)
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">
  <style>
    .frame {{ fill: #ffffff; stroke: #000000; stroke-width: 2.4; }}
    .rel {{ fill: none; stroke: #676767; stroke-width: 1.7; stroke-linejoin: miter; stroke-linecap: square; }}
    .symbol {{ stroke: #424242; stroke-width: 1.5; stroke-linecap: square; }}
    .symbol-circle {{ fill: #ffffff; stroke: #424242; stroke-width: 1.5; }}
    .entity-body {{ fill: #ffffff; stroke: #8db5de; stroke-width: 1.4; }}
    .entity-head {{ fill: #d8e8fa; stroke: #8db5de; stroke-width: 1.4; }}
    .divider-strong {{ stroke: #262626; stroke-width: 1.1; }}
    .divider {{ stroke: #a7bdd5; stroke-width: 1.0; }}
    .row-line {{ stroke: #d8e1eb; stroke-width: 0.9; }}
    .entity-title {{ font-family: Arial, Helvetica, sans-serif; font-size: 13px; font-weight: 700; fill: #111111; }}
    .field-key {{ font-family: Arial, Helvetica, sans-serif; font-size: 10px; font-weight: 700; fill: #111111; }}
    .json-key {{ font-size: 8.4px; }}
    .field-name {{ font-family: Arial, Helvetica, sans-serif; font-size: 11px; fill: #111111; }}
    .field-name.pk {{ font-weight: 700; text-decoration: underline; }}
    .caption {{ font-family: "Times New Roman", Times, serif; font-size: 38px; font-weight: 700; fill: #000000; }}
  </style>
  <rect class="frame" x="{FRAME[0]}" y="{FRAME[1]}" width="{FRAME[2]}" height="{FRAME[3]}" />
  <g id="relationship-lines">
    {' '.join(relation_paths)}
  </g>
  <g id="entities">
    {entities}
  </g>
  <g id="relationship-symbols">
    {' '.join(relation_symbols)}
  </g>
  <text class="caption" x="{W / 2:.1f}" y="1030" text-anchor="middle">Fig. 31. Entity Relationship Diagram for Scormetry 2.0</text>
</svg>
"""


def build_html(svg: str) -> str:
    return f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Scormetry 2.0 - Clear ERD Connections</title>
<style>
  body {{
    margin: 0;
    background: #ffffff;
    color: #000000;
    font-family: "Times New Roman", Times, serif;
  }}
  .page {{
    width: 1660px;
    margin: 0 auto;
    padding: 52px 40px 36px;
    box-sizing: border-box;
    background: #ffffff;
  }}
  h1 {{
    margin: 0 0 24px 0;
    font-size: 34px;
    font-weight: 700;
    line-height: 1.15;
  }}
  h2 {{
    margin: 0 0 34px 0;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.15;
  }}
  .svg-wrap {{
    display: flex;
    justify-content: center;
    width: 100%;
  }}
  svg {{
    width: 100%;
    height: auto;
    display: block;
  }}
  @media print {{
    @page {{ size: landscape; margin: 0.25in; }}
    .page {{ width: 100%; padding: 24px; }}
  }}
</style>
</head>
<body>
<main class="page">
  <h1>3.5.2 Data Design</h1>
  <h2>3.5.2.1 Entity Relationship Diagram</h2>
  <div class="svg-wrap">
{svg}
  </div>
</main>
</body>
</html>
"""


def main() -> None:
    SVG_DIR.mkdir(parents=True, exist_ok=True)
    svg = build_svg()
    SVG_PATH.write_text(svg, encoding="utf-8")
    HTML_PATH.write_text(build_html(svg), encoding="utf-8")


if __name__ == "__main__":
    main()
