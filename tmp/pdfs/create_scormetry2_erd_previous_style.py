from __future__ import annotations

from html import escape
from pathlib import Path


ROOT = Path("/Users/mac/Downloads/FYP_Scormtry-2.0")
OUT_DIR = ROOT / "docs/chapter3-diagrams"
SVG_DIR = OUT_DIR / "erd-previous-year-style-svg"
HTML_PATH = OUT_DIR / "Scormetry2_ERD_PreviousYearStyle.html"
SVG_PATH = SVG_DIR / "fig-31-entity-relationship-diagram-scormetry-20.svg"


W = 1480
H = 1120


tables = {
    "subject_invitations": {
        "title": "Subject Invitation",
        "x": 55,
        "y": 245,
        "w": 165,
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
        "x": 250,
        "y": 220,
        "w": 165,
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
        "x": 455,
        "y": 215,
        "w": 175,
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
        "x": 725,
        "y": 120,
        "w": 170,
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
        "x": 965,
        "y": 105,
        "w": 205,
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
        "x": 55,
        "y": 520,
        "w": 185,
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
        "x": 285,
        "y": 510,
        "w": 175,
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
        "x": 505,
        "y": 560,
        "w": 160,
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
        "x": 695,
        "y": 515,
        "w": 170,
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
        "x": 930,
        "y": 465,
        "w": 215,
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
        "x": 1200,
        "y": 415,
        "w": 205,
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
        "x": 1010,
        "y": 765,
        "w": 200,
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
        "x": 720,
        "y": 840,
        "w": 170,
        "fields": [
            ("PK", "id"),
            ("", "key"),
            ("", "value"),
            ("", "created_at"),
            ("", "updated_at"),
        ],
    },
}


relations = [
    ("subjects", "subject_invitations", "subject_id"),
    ("subjects", "subject_members", "subject_id"),
    ("subjects", "subject_blocked_emails", "subject_id"),
    ("subjects", "teams", "subject_id"),
    ("subjects", "rubrics", "subject_id"),
    ("subjects", "papers", "subject_id"),
    ("rubrics", "rubric_structure", "structure_json"),
    ("teams", "team_members", "team_id"),
    ("teams", "papers", "team_id"),
    ("teams", "review_unlock_logs", "team_id"),
    ("papers", "reviews", "paper_id"),
    ("reviews", "review_unlock_logs", "review_id"),
    ("users", "subjects", "teacher_id"),
    ("users", "subject_members", "user_id"),
    ("users", "team_members", "user_id"),
    ("users", "reviews", "reviewer_id"),
    ("users", "review_unlock_logs", "judge_id"),
    ("users", "review_unlock_logs", "unlocked_by"),
    ("users", "subject_blocked_emails", "blocked_by"),
    ("users", "papers", "override_by"),
]


def table_height(table: dict) -> int:
    return 24 + 18 * len(table["fields"])


def point(table_name: str, side: str) -> tuple[float, float]:
    t = tables[table_name]
    x = t["x"]
    y = t["y"]
    w = t["w"]
    h = table_height(t)
    if side == "left":
        return x, y + h / 2
    if side == "right":
        return x + w, y + h / 2
    if side == "top":
        return x + w / 2, y
    if side == "bottom":
        return x + w / 2, y + h
    raise ValueError(side)


def choose_sides(a: str, b: str) -> tuple[str, str]:
    ta = tables[a]
    tb = tables[b]
    ax = ta["x"] + ta["w"] / 2
    ay = ta["y"] + table_height(ta) / 2
    bx = tb["x"] + tb["w"] / 2
    by = tb["y"] + table_height(tb) / 2
    if abs(ax - bx) > abs(ay - by):
        return ("right", "left") if ax < bx else ("left", "right")
    return ("bottom", "top") if ay < by else ("top", "bottom")


def line_path(a: str, b: str) -> str:
    side_a, side_b = choose_sides(a, b)
    x1, y1 = point(a, side_a)
    x2, y2 = point(b, side_b)
    if side_a in {"left", "right"}:
        mid_x = (x1 + x2) / 2
        return f"M {x1:.1f} {y1:.1f} L {mid_x:.1f} {y1:.1f} L {mid_x:.1f} {y2:.1f} L {x2:.1f} {y2:.1f}"
    mid_y = (y1 + y2) / 2
    return f"M {x1:.1f} {y1:.1f} L {x1:.1f} {mid_y:.1f} L {x2:.1f} {mid_y:.1f} L {x2:.1f} {y2:.1f}"


def connector_marks(a: str, b: str) -> str:
    side_a, side_b = choose_sides(a, b)
    x1, y1 = point(a, side_a)
    x2, y2 = point(b, side_b)
    # Small endpoint ticks emulate the previous ERD connector style without adding colored symbols.
    def tick(x: float, y: float, side: str) -> str:
        if side in {"left", "right"}:
            return f'<line x1="{x}" y1="{y - 6}" x2="{x}" y2="{y + 6}" class="cardinality"/>'
        return f'<line x1="{x - 6}" y1="{y}" x2="{x + 6}" y2="{y}" class="cardinality"/>'

    return tick(x1, y1, side_a) + tick(x2, y2, side_b)


def render_table(table: dict) -> str:
    x = table["x"]
    y = table["y"]
    w = table["w"]
    h = table_height(table)
    rows = []
    rows.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" class="entity"/>')
    rows.append(f'<rect x="{x}" y="{y}" width="{w}" height="24" class="entity-head"/>')
    rows.append(
        f'<text x="{x + w / 2}" y="{y + 16}" class="entity-title" text-anchor="middle">{escape(table["title"])}</text>'
    )
    rows.append(f'<line x1="{x}" y1="{y + 24}" x2="{x + w}" y2="{y + 24}" class="entity-sep"/>')
    rows.append(f'<line x1="{x + 32}" y1="{y + 24}" x2="{x + 32}" y2="{y + h}" class="entity-sep light"/>')
    for idx, (key, field) in enumerate(table["fields"]):
        row_y = y + 24 + idx * 18
        if idx:
            rows.append(f'<line x1="{x}" y1="{row_y}" x2="{x + w}" y2="{row_y}" class="row-sep"/>')
        key_class = "field-key json" if key == "JSON" else "field-key"
        rows.append(f'<text x="{x + 5}" y="{row_y + 13}" class="{key_class}">{escape(key)}</text>')
        rows.append(f'<text x="{x + 40}" y="{row_y + 13}" class="field-name">{escape(field)}</text>')
    return "\n".join(rows)


style = """
<style>
  .page-bg { fill: #ffffff; }
  .outer-frame { fill: #ffffff; stroke: #000000; stroke-width: 2.4; }
  .relationship { fill: none; stroke: #555555; stroke-width: 1.35; stroke-linejoin: round; stroke-linecap: square; }
  .cardinality { stroke: #333333; stroke-width: 1.25; }
  .entity { fill: #ffffff; stroke: #9bbbe0; stroke-width: 1.2; }
  .entity-head { fill: #dbeafb; stroke: #9bbbe0; stroke-width: 1.2; }
  .entity-title { font-family: "Comic Sans MS", "Trebuchet MS", Arial, sans-serif; font-size: 12px; font-weight: 700; fill: #111111; }
  .field-key { font-family: "Comic Sans MS", "Trebuchet MS", Arial, sans-serif; font-size: 10.5px; font-weight: 700; fill: #111111; }
  .field-key.json { font-size: 9.2px; }
  .field-name { font-family: "Comic Sans MS", "Trebuchet MS", Arial, sans-serif; font-size: 10.5px; fill: #111111; }
  .entity-sep { stroke: #333333; stroke-width: 0.9; }
  .entity-sep.light { stroke: #b5ccea; stroke-width: 0.85; }
  .row-sep { stroke: #d8e4f2; stroke-width: 0.65; }
</style>
""".strip()

relationship_svg = []
for src, dst, _label in relations:
    relationship_svg.append(f'<path d="{line_path(src, dst)}" class="relationship"/>')
for src, dst, _label in relations:
    relationship_svg.append(connector_marks(src, dst))

table_svg = "\n".join(render_table(table) for table in tables.values())

svg = f"""<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">
{style}
<rect x="0" y="0" width="{W}" height="{H}" class="page-bg"/>
<rect x="35" y="35" width="{W - 70}" height="{H - 70}" class="outer-frame"/>
{"".join(relationship_svg)}
{table_svg}
</svg>
"""

html = f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Scormetry 2.0 ERD - Previous Year Style</title>
  <style>
    body {{
      margin: 0;
      background: #ffffff;
      color: #000000;
      font-family: "Times New Roman", Times, serif;
    }}
    .page {{
      width: 1500px;
      margin: 0 auto;
      padding: 54px 38px 38px;
      background: #ffffff;
      box-sizing: border-box;
    }}
    h1 {{
      margin: 0 0 48px 0;
      font-size: 36px;
      font-weight: 700;
      line-height: 1.1;
    }}
    h2 {{
      margin: 0 0 58px 0;
      font-size: 36px;
      font-weight: 700;
      line-height: 1.1;
    }}
    .svg-wrap {{
      width: 100%;
      display: flex;
      justify-content: center;
    }}
    svg {{
      width: 1420px;
      height: auto;
      display: block;
    }}
    .caption {{
      margin: 34px 0 0;
      text-align: center;
      font-size: 38px;
      font-weight: 700;
      line-height: 1.1;
    }}
    @media print {{
      @page {{ size: landscape; margin: 0.35in; }}
      .page {{ width: 100%; padding: 32px; }}
      svg {{ width: 100%; }}
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
    <p class="caption">Fig. 31. Entity Relationship Diagram for Scormetry 2.0</p>
  </main>
</body>
</html>
"""

SVG_DIR.mkdir(parents=True, exist_ok=True)
SVG_PATH.write_text(svg, encoding="utf-8")
HTML_PATH.write_text(html, encoding="utf-8")

print(HTML_PATH)
print(SVG_PATH)
