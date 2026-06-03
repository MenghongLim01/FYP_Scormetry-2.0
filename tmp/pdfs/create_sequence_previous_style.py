from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import math
import html

ROOT = Path('/Users/mac/Downloads/FYP_Scormtry-2.0')
OUT_PDF = ROOT / 'output/pdf/Scormetry2_Sequence_Diagrams_PreviousYearStyle.pdf'
OUT_HTML = ROOT / 'docs/chapter3-diagrams/Scormetry2_Sequence_Diagrams_PreviousYearStyle.html'
OUT_SVG_DIR = ROOT / 'docs/chapter3-diagrams/sequence-previous-year-style-svg'
OUT_PREVIEW_DIR = ROOT / 'tmp/pdfs/sequence-previous-style-preview'
for path in [OUT_PDF.parent, OUT_HTML.parent, OUT_SVG_DIR, OUT_PREVIEW_DIR]:
    path.mkdir(parents=True, exist_ok=True)

TIMES = '/System/Library/Fonts/Supplemental/Times New Roman.ttf'
TIMES_BOLD = '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf'
COMIC = '/System/Library/Fonts/Supplemental/Comic Sans MS.ttf'
COMIC_BOLD = '/System/Library/Fonts/Supplemental/Comic Sans MS Bold.ttf'

PAGE_W, PAGE_H = 1240, 1754
DPI = 300
SCALE = 2
YELLOW = '#ffff88'
BLACK = '#000000'
WHITE = '#ffffff'
GRAY = '#777777'

diagrams = [
    {
        'fig': 'Fig. 8. Create Subject - Sequence Diagram (Admin)',
        'caption': 'Fig. 8. Create Subject Sequence Diagram (Admin)',
        'participants': [('Admin','actor'), ('Vue/Inertia\nSubject Form','box'), ('SubjectController','box'), ('Database','box')],
        'phases': {0:'Subject Creation Flow - Scormetry 2.0'},
        'messages': [
            ('Admin','Vue/Inertia\nSubject Form','Enter subject details','solid'),
            ('Vue/Inertia\nSubject Form','SubjectController','POST /subjects','solid'),
            ('SubjectController','SubjectController','Validate StoreSubjectRequest','self'),
            ('SubjectController','SubjectController','Generate join_code and reviewer_code','self'),
            ('SubjectController','Database','Insert subjects record','solid'),
            ('Database','SubjectController','Subject saved','return'),
            ('SubjectController','Vue/Inertia\nSubject Form','Redirect to subject show page','return'),
            ('Vue/Inertia\nSubject Form','Admin','Display codes and setup actions','return'),
        ],
    },
    {
        'fig': 'Fig. 10. Invite Judge via Email - Sequence Diagram (Admin)',
        'caption': 'Fig. 10. Invite Judge via Email Sequence Diagram (Admin)',
        'participants': [('Admin','actor'), ('Subject\nMember Form','box'), ('InvitationController','box'), ('Database','box'), ('Laravel Mail','box'), ('Judge','actor')],
        'phases': {0:'Judge Invitation Flow - Scormetry 2.0', 7:'Email Delivery Flow'},
        'messages': [
            ('Admin','Subject\nMember Form','Enter judge email and role label','solid'),
            ('Subject\nMember Form','InvitationController','Submit reviewer invitation','solid'),
            ('InvitationController','Database','Check blocked email and existing invitation','solid'),
            ('Database','InvitationController','Return validation result','return'),
            ('InvitationController','InvitationController','Generate secure token','self'),
            ('InvitationController','Database','Store subject_invitations','solid'),
            ('InvitationController','Laravel Mail','Send ReviewerInvitationMail','solid'),
            ('Laravel Mail','Judge','Deliver invitation link','solid'),
            ('InvitationController','Subject\nMember Form','Show invitation confirmation','return'),
        ],
    },
    {
        'fig': 'Fig. 12. Upload Rubric PDF and AI Conversion - Sequence Diagram (Admin)',
        'caption': 'Fig. 12. Upload Rubric PDF and AI Conversion Sequence Diagram (Admin)',
        'participants': [('Admin','actor'), ('Rubric\nUpload Page','box'), ('RubricController','box'), ('File Storage','box'), ('StructureExtractor','box'), ('OpenAI API','box'), ('Database','box')],
        'phases': {0:'Rubric Upload Flow - Scormetry 2.0', 4:'AI Extraction Flow', 8:'Pending Dynamic Rubric Save Flow'},
        'messages': [
            ('Admin','Rubric\nUpload Page','Select official rubric PDF','solid'),
            ('Rubric\nUpload Page','RubricController','POST /subjects/{subject}/rubrics','solid'),
            ('RubricController','RubricController','Validate StoreRubricRequest','self'),
            ('RubricController','File Storage','Store original PDF','solid'),
            ('RubricController','StructureExtractor','Extract rubric structure','solid'),
            ('StructureExtractor','OpenAI API','Upload PDF and request JSON extraction','solid'),
            ('OpenAI API','StructureExtractor','Return structured Dynamic Rubric JSON','return'),
            ('StructureExtractor','RubricController','Return criteria, max_score, weight','return'),
            ('RubricController','Database','Save rubric as pending_verification','solid'),
            ('RubricController','Rubric\nUpload Page','Redirect to rubric verification','return'),
        ],
    },
    {
        'fig': 'Fig. 14. Verify and Lock Dynamic Rubric - Sequence Diagram (Admin)',
        'caption': 'Fig. 14. Verify and Lock Dynamic Rubric Sequence Diagram (Admin)',
        'participants': [('Admin','actor'), ('Rubric\nVerification Page','box'), ('RubricController','box'), ('Database','box')],
        'phases': {0:'Dynamic Rubric Verification Flow - Scormetry 2.0', 5:'Rubric Locking Flow'},
        'messages': [
            ('Admin','Rubric\nVerification Page','Review extracted Dynamic Rubric','solid'),
            ('Admin','Rubric\nVerification Page','Edit structure if needed','solid'),
            ('Rubric\nVerification Page','RubricController','PATCH /rubrics/{rubric}','solid'),
            ('RubricController','Database','Update structure_json','solid'),
            ('Database','RubricController','Structure saved','return'),
            ('Admin','Rubric\nVerification Page','Approve rubric','solid'),
            ('Rubric\nVerification Page','RubricController','POST /rubrics/{rubric}/approve','solid'),
            ('RubricController','Database','Set status = locked','solid'),
            ('RubricController','Rubric\nVerification Page','Show locked rubric','return'),
        ],
    },
    {
        'fig': 'Fig. 15. Accept Invitation via Email - Sequence Diagram (Judge)',
        'caption': 'Fig. 15. Accept Invitation via Email Sequence Diagram (Judge)',
        'participants': [('Judge','actor'), ('Invitation\nEmail','box'), ('InvitationController','box'), ('Database','box'), ('Dashboard','box')],
        'phases': {0:'Invitation Acceptance Flow - Scormetry 2.0'},
        'messages': [
            ('Invitation\nEmail','Judge','Invitation link with token','solid'),
            ('Judge','InvitationController','GET /invitations/{token}','solid'),
            ('InvitationController','Database','Find subject_invitations by token','solid'),
            ('Database','InvitationController','Invitation record','return'),
            ('InvitationController','InvitationController','Validate unused token and email','self'),
            ('InvitationController','Database','Create subject_members reviewer record','solid'),
            ('InvitationController','Database','Mark accepted_at','solid'),
            ('InvitationController','Dashboard','Redirect after login / acceptance','return'),
            ('Dashboard','Judge','Show assigned subjects and teams','return'),
        ],
    },
    {
        'fig': 'Fig. 17. Submit Review - Sequence Diagram (Judge)',
        'caption': 'Fig. 17. Submit Review Sequence Diagram (Judge)',
        'participants': [('Judge','actor'), ('Review Form','box'), ('ReviewController','box'), ('Database','box')],
        'phases': {0:'Review Submission Flow - Scormetry 2.0', 4:'Score Validation and Locking Flow'},
        'messages': [
            ('Judge','Review Form','Enter scores and feedback','solid'),
            ('Review Form','ReviewController','POST /papers/{paper}/reviews','solid'),
            ('ReviewController','Database','Verify reviewer assignment','solid'),
            ('ReviewController','Database','Confirm rubric status is locked','solid'),
            ('ReviewController','ReviewController','Validate scores against max_score','self'),
            ('ReviewController','ReviewController','Calculate weighted total','self'),
            ('ReviewController','Database','Save review (scores_json, comment, locked_at)','solid'),
            ('Database','ReviewController','Review saved','return'),
            ('ReviewController','Review Form','Show submitted and locked review','return'),
        ],
    },
    {
        'fig': 'Fig. 18. Join Subject by Join Code - Sequence Diagram (Student)',
        'caption': 'Fig. 18. Join Subject by Join Code Sequence Diagram (Student)',
        'participants': [('Student','actor'), ('Join Form','box'), ('JoinController','box'), ('Database','box'), ('Admin','actor')],
        'phases': {0:'Student Join Flow - Scormetry 2.0', 7:'Membership Approval Flow'},
        'messages': [
            ('Student','Join Form','Enter join_code','solid'),
            ('Join Form','JoinController','POST /subjects/join','solid'),
            ('JoinController','Database','Find subject by code','solid'),
            ('Database','JoinController','Subject record','return'),
            ('JoinController','JoinController','Validate input','self'),
            ('JoinController','Database','Create member record','solid'),
            ('Database','JoinController','Member created','return'),
            ('JoinController','Join Form','Show join status','return'),
            ('Admin','JoinController','Approve membership (if pending)','solid'),
            ('JoinController','Database','Update to approved','solid'),
            ('JoinController','Student','Notify access granted','return'),
        ],
    },
    {
        'fig': 'Fig. 20. Upload Paper - Sequence Diagram (Student)',
        'caption': 'Fig. 20. Upload Paper Sequence Diagram (Student)',
        'participants': [('Student','actor'), ('Paper\nUpload Page','box'), ('PaperController','box'), ('Database','box'), ('Laravel\nFile Storage','box')],
        'phases': {0:'Paper Upload Flow - Scormetry 2.0', 4:'Secure Storage Flow'},
        'messages': [
            ('Student','Paper\nUpload Page','Select final paper PDF','solid'),
            ('Paper\nUpload Page','PaperController','POST /papers','solid'),
            ('PaperController','Database','Verify approved subject/team membership','solid'),
            ('PaperController','PaperController','Validate PDF upload','self'),
            ('PaperController','Laravel\nFile Storage','Store paper PDF','solid'),
            ('PaperController','Database','Create or update papers record','solid'),
            ('Database','PaperController','Paper saved as draft','return'),
            ('PaperController','Paper\nUpload Page','Show upload confirmation','return'),
        ],
    },
    {
        'fig': 'Fig. 22. Release Scores - Sequence Diagram (Admin)',
        'caption': 'Fig. 22. Release Scores Sequence Diagram (Admin)',
        'participants': [('Admin','actor'), ('Team\nScores Page','box'), ('TeamController','box'), ('Database','box'), ('Student','actor')],
        'phases': {0:'Score Review Flow - Scormetry 2.0', 5:'Score Override Flow', 8:'Score Release Flow'},
        'messages': [
            ('Admin','Team\nScores Page','Open team score overview','solid'),
            ('Team\nScores Page','TeamController','Request submitted reviews','solid'),
            ('TeamController','Database','Retrieve reviews for team papers','solid'),
            ('TeamController','TeamController','Calculate average final_score','self'),
            ('TeamController','Team\nScores Page','Display scores and feedback','return'),
            ('Admin','Team\nScores Page','Optional override with reason','solid'),
            ('Team\nScores Page','TeamController','Save override if provided','solid'),
            ('TeamController','Database','Update final_score_override fields','solid'),
            ('Admin','Team\nScores Page','Release scores','solid'),
            ('Team\nScores Page','TeamController','POST /teams/{team}/release-scores','solid'),
            ('TeamController','Database','Set visibility_status = published','solid'),
            ('Student','Team\nScores Page','Can view released result','return'),
        ],
    },
]

font_cache = {}
def font(path, size):
    key = (path, size)
    if key not in font_cache:
        font_cache[key] = ImageFont.truetype(path, size)
    return font_cache[key]

def txt_size(draw, text, fnt):
    box = draw.textbbox((0,0), text, font=fnt)
    return box[2]-box[0], box[3]-box[1]

def wrap_text(draw, text, fnt, max_width):
    text = text.replace('\n', ' \n ')
    lines = []
    for raw in text.split(' \n '):
        words = raw.split()
        if not words:
            lines.append('')
            continue
        line = words[0]
        for word in words[1:]:
            candidate = line + ' ' + word
            if txt_size(draw, candidate, fnt)[0] <= max_width:
                line = candidate
            else:
                lines.append(line)
                line = word
        lines.append(line)
    return lines

def draw_center_text(draw, cx, cy, lines, fnt, fill=BLACK, line_gap=4):
    heights = [txt_size(draw, line, fnt)[1] for line in lines]
    total = sum(heights) + line_gap*(len(lines)-1)
    y = cy - total/2
    for line, h in zip(lines, heights):
        w, _ = txt_size(draw, line, fnt)
        draw.text((cx - w/2, y), line, font=fnt, fill=fill)
        y += h + line_gap

def draw_line(draw, xy, fill=BLACK, width=2):
    draw.line(xy, fill=fill, width=width)

def draw_dashed_line(draw, p1, p2, fill=GRAY, width=2, dash=7, gap=6):
    x1,y1 = p1; x2,y2 = p2
    length = math.hypot(x2-x1, y2-y1)
    if length == 0:
        return
    dx = (x2-x1)/length; dy = (y2-y1)/length
    dist = 0
    while dist < length:
        end = min(dist+dash, length)
        draw.line((x1+dx*dist, y1+dy*dist, x1+dx*end, y1+dy*end), fill=fill, width=width)
        dist += dash + gap

def draw_arrow(draw, x1, y1, x2, y2, dashed=False, width=2):
    if dashed:
        draw_dashed_line(draw, (x1,y1), (x2,y2), BLACK, width, 11, 8)
    else:
        draw.line((x1,y1,x2,y2), fill=BLACK, width=width)
    angle = math.atan2(y2-y1, x2-x1)
    size = 11
    p1 = (x2 - size*math.cos(angle-math.pi/6), y2 - size*math.sin(angle-math.pi/6))
    p2 = (x2 - size*math.cos(angle+math.pi/6), y2 - size*math.sin(angle+math.pi/6))
    draw.polygon([(x2,y2), p1, p2], fill=BLACK)

def draw_actor(draw, cx, top, label, fnt):
    r = 10
    draw.ellipse((cx-r, top, cx+r, top+2*r), outline=BLACK, width=2)
    draw.line((cx, top+2*r, cx, top+46), fill=BLACK, width=2)
    draw.line((cx-20, top+30, cx+20, top+30), fill=BLACK, width=2)
    draw.line((cx, top+46, cx-16, top+70), fill=BLACK, width=2)
    draw.line((cx, top+46, cx+16, top+70), fill=BLACK, width=2)
    draw_center_text(draw, cx, top+88, wrap_text(draw, label, fnt, 90), fnt)

def svg_text(cx, cy, lines, size, family='Comic Sans MS', weight='normal', fill=BLACK, line_gap=4):
    line_h = size + line_gap
    start = cy - (line_h*(len(lines)-1))/2
    spans = []
    for i, line in enumerate(lines):
        spans.append(f'<text x="{cx:.1f}" y="{start+i*line_h:.1f}" text-anchor="middle" dominant-baseline="middle" font-family="{family}" font-size="{size}" font-weight="{weight}" fill="{fill}">{html.escape(line)}</text>')
    return '\n'.join(spans)

def svg_arrow(x1,y1,x2,y2,dashed=False):
    dash = ' stroke-dasharray="11 8"' if dashed else ''
    return f'<line x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}" stroke="#000" stroke-width="2"{dash} marker-end="url(#arrow)" />'

def make_diagram(diagram, svg_path, png_path):
    img = Image.new('RGB', (PAGE_W*SCALE, PAGE_H*SCALE), WHITE)
    draw = ImageDraw.Draw(img)
    def s(v): return int(round(v*SCALE))
    f_title = font(TIMES_BOLD, 34*SCALE)
    f_caption = font(TIMES_BOLD, 31*SCALE)
    box_font_size = 12 if len(diagram['participants']) >= 6 else 14
    msg_font_size = 11 if len(diagram['participants']) >= 6 else 13
    phase_font_size = 12 if len(diagram['participants']) >= 6 else 14
    f_box = font(COMIC_BOLD, box_font_size*SCALE)
    f_msg = font(COMIC_BOLD, msg_font_size*SCALE)
    f_phase = font(COMIC_BOLD, phase_font_size*SCALE)

    margin_x, diagram_y = 92, 130
    diagram_w, diagram_h = PAGE_W - margin_x*2, 1325
    x0, y0, x1, y1 = margin_x, diagram_y, margin_x+diagram_w, diagram_y+diagram_h
    # Border
    draw.rectangle((s(x0), s(y0), s(x1), s(y1)), outline=BLACK, width=s(2))

    participants = diagram['participants']
    n = len(participants)
    inner_left, inner_right = x0+78, x1-88
    if n == 1:
        xs = [PAGE_W/2]
    else:
        xs = [inner_left + i*(inner_right-inner_left)/(n-1) for i in range(n)]
    pos = {label: xs[i] for i, (label, _) in enumerate(participants)}
    top = y0 + 22
    box_w = max(88, min(142, (inner_right-inner_left)/(max(1,n-1))*0.68))
    box_h = 54
    lifeline_top = top + 76
    lifeline_bottom = y1 - 32

    for label, kind in participants:
        cx = pos[label]
        if kind == 'actor':
            draw_actor(draw, s(cx), s(top-7), label.replace('\n',' '), f_box)
            life_start = top + 108
        else:
            draw.rectangle((s(cx-box_w/2), s(top), s(cx+box_w/2), s(top+box_h)), outline=BLACK, width=s(2), fill=WHITE)
            draw_center_text(draw, s(cx), s(top+box_h/2), wrap_text(draw, label, f_box, s(box_w-14)), f_box)
            life_start = top + box_h
        draw_dashed_line(draw, (s(cx), s(life_start)), (s(cx), s(lifeline_bottom)), GRAY, s(2), s(6), s(6))

    # Rows
    rows = []
    for i, msg in enumerate(diagram['messages']):
        if i in diagram.get('phases', {}):
            rows.append(('phase', diagram['phases'][i]))
        rows.append(('message', msg))
    top_rows = y0 + 116
    bottom_rows = y1 - 82
    units = sum(0.72 if r[0]=='phase' else 1 for r in rows)
    unit_h = (bottom_rows - top_rows) / units
    cur = top_rows
    for typ, data in rows:
        if typ == 'phase':
            h = unit_h * 0.72
            by = cur + h*0.18
            draw.rectangle((s(x0+78), s(by), s(x1-50), s(by+h*0.58)), outline='#d6d67d', width=s(1), fill='#ffff88')
            draw_center_text(draw, s((x0+x1)/2), s(by+h*0.29), [data], f_phase)
            cur += h
            continue
        src, dst, label, style = data
        y = cur + unit_h*0.5
        x_src, x_dst = pos[src], pos[dst]
        if src == dst or style == 'self':
            loop_w = 56 if x_src < x1-120 else -56
            x_loop = x_src + loop_w
            draw.line((s(x_src),s(y),s(x_loop),s(y),s(x_loop),s(y+28),s(x_src),s(y+28)), fill=BLACK, width=s(2))
            # arrowhead returning left/right to lifeline
            end_x, end_y = x_src, y+28
            angle = 0 if loop_w < 0 else math.pi
            size = 11*SCALE
            # point should face toward lifeline
            if loop_w > 0:
                pts = [(s(end_x),s(end_y)), (s(end_x+10),s(end_y-6)), (s(end_x+10),s(end_y+6))]
            else:
                pts = [(s(end_x),s(end_y)), (s(end_x-10),s(end_y-6)), (s(end_x-10),s(end_y+6))]
            draw.polygon(pts, fill=BLACK)
            label_x = x_src + loop_w/2
            label_y = y - 17
        else:
            pad = 7
            start = x_src + (pad if x_dst > x_src else -pad)
            end = x_dst - (pad if x_dst > x_src else -pad)
            dashed = style == 'return'
            draw_arrow(draw, s(start), s(y), s(end), s(y), dashed=dashed, width=s(2))
            label_x = (start + end)/2
            label_y = y - 16
        max_label = max(115, min(310, abs(x_dst-x_src)-20 if src != dst else 190))
        lines = wrap_text(draw, label, f_msg, s(max_label))
        # White backing to keep label readable against lines.
        text_ws = [txt_size(draw, line, f_msg)[0] for line in lines]
        text_hs = [txt_size(draw, line, f_msg)[1] for line in lines]
        bw = max(text_ws) + s(10)
        bh = sum(text_hs) + s(4)*(len(lines)-1) + s(6)
        draw.rectangle((s(label_x)-bw//2, s(label_y)-bh//2, s(label_x)+bw//2, s(label_y)+bh//2), fill=WHITE)
        draw_center_text(draw, s(label_x), s(label_y), lines, f_msg)
        cur += unit_h

    # Caption
    draw_center_text(draw, s(PAGE_W/2), s(y1+62), [diagram['caption']], f_caption)
    img.save(png_path)
    pdf_img = img.convert('RGB')

    # SVG export with same geometry
    svg = []
    svg.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{PAGE_W}" height="{PAGE_H}" viewBox="0 0 {PAGE_W} {PAGE_H}">')
    svg.append('<defs><marker id="arrow" markerWidth="10" markerHeight="8" refX="10" refY="4" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L10,4 L0,8 z" fill="#000"/></marker></defs>')
    svg.append('<rect width="100%" height="100%" fill="#fff"/>')
    svg.append(f'<rect x="{x0}" y="{y0}" width="{diagram_w}" height="{diagram_h}" fill="#fff" stroke="#000" stroke-width="2"/>')
    for label, kind in participants:
        cx = pos[label]
        if kind == 'actor':
            svg.append(f'<circle cx="{cx}" cy="{top+3}" r="10" fill="#fff" stroke="#000" stroke-width="2"/>')
            svg.append(f'<line x1="{cx}" y1="{top+13}" x2="{cx}" y2="{top+53}" stroke="#000" stroke-width="2"/>')
            svg.append(f'<line x1="{cx-20}" y1="{top+37}" x2="{cx+20}" y2="{top+37}" stroke="#000" stroke-width="2"/>')
            svg.append(f'<line x1="{cx}" y1="{top+53}" x2="{cx-16}" y2="{top+77}" stroke="#000" stroke-width="2"/>')
            svg.append(f'<line x1="{cx}" y1="{top+53}" x2="{cx+16}" y2="{top+77}" stroke="#000" stroke-width="2"/>')
            svg.append(svg_text(cx, top+95, wrap_text(draw, label.replace('\n',' '), font(COMIC_BOLD,box_font_size), 90), box_font_size, weight='bold'))
            life_start = top + 108
        else:
            svg.append(f'<rect x="{cx-box_w/2:.1f}" y="{top}" width="{box_w:.1f}" height="{box_h}" fill="#fff" stroke="#000" stroke-width="2"/>')
            svg.append(svg_text(cx, top+box_h/2, wrap_text(draw, label, font(COMIC_BOLD,box_font_size), box_w-14), box_font_size, weight='bold'))
            life_start = top + box_h
        svg.append(f'<line x1="{cx:.1f}" y1="{life_start:.1f}" x2="{cx:.1f}" y2="{lifeline_bottom:.1f}" stroke="#777" stroke-width="2" stroke-dasharray="6 6"/>')
    cur = top_rows
    for typ, data in rows:
        if typ == 'phase':
            h = unit_h * 0.72
            by = cur + h*0.18
            svg.append(f'<rect x="{x0+78:.1f}" y="{by:.1f}" width="{diagram_w-128:.1f}" height="{h*0.58:.1f}" fill="{YELLOW}" stroke="#d6d67d" stroke-width="1"/>')
            svg.append(svg_text(PAGE_W/2, by+h*0.29, [data], phase_font_size, weight='bold'))
            cur += h
            continue
        src, dst, label, style = data
        y = cur + unit_h*0.5
        x_src, x_dst = pos[src], pos[dst]
        if src == dst or style == 'self':
            loop_w = 56 if x_src < x1-120 else -56
            x_loop = x_src + loop_w
            svg.append(f'<polyline points="{x_src:.1f},{y:.1f} {x_loop:.1f},{y:.1f} {x_loop:.1f},{y+28:.1f} {x_src:.1f},{y+28:.1f}" fill="none" stroke="#000" stroke-width="2"/>')
            if loop_w > 0:
                svg.append(f'<polygon points="{x_src:.1f},{y+28:.1f} {x_src+10:.1f},{y+22:.1f} {x_src+10:.1f},{y+34:.1f}" fill="#000"/>')
            else:
                svg.append(f'<polygon points="{x_src:.1f},{y+28:.1f} {x_src-10:.1f},{y+22:.1f} {x_src-10:.1f},{y+34:.1f}" fill="#000"/>')
            label_x = x_src + loop_w/2
            label_y = y - 17
        else:
            pad = 7
            start = x_src + (pad if x_dst > x_src else -pad)
            end = x_dst - (pad if x_dst > x_src else -pad)
            svg.append(svg_arrow(start, y, end, y, dashed=(style=='return')))
            label_x = (start + end)/2
            label_y = y - 16
        max_label = max(115, min(310, abs(x_dst-x_src)-20 if src != dst else 190))
        lines = wrap_text(draw, label, font(COMIC_BOLD,msg_font_size), max_label)
        # SVG cannot measure text accurately here; use backing rectangle approximation.
        approx_w = max(len(line) for line in lines) * 7.4 + 12
        approx_h = len(lines) * (msg_font_size + 5) + 8
        svg.append(f'<rect x="{label_x-approx_w/2:.1f}" y="{label_y-approx_h/2:.1f}" width="{approx_w:.1f}" height="{approx_h:.1f}" fill="#fff"/>')
        svg.append(svg_text(label_x, label_y, lines, msg_font_size, weight='bold'))
        cur += unit_h
    svg.append(svg_text(PAGE_W/2, y1+62, [diagram['caption']], 31, family='Times New Roman', weight='bold'))
    svg.append('</svg>')
    svg_path.write_text('\n'.join(svg), encoding='utf-8')
    return pdf_img

images = []
html_parts = ['<!doctype html><html><head><meta charset="utf-8"><title>Scormetry 2.0 Sequence Diagrams - Previous Year Style</title>',
              '<style>body{margin:0;background:#eee;font-family:"Times New Roman",serif}.page{width:1240px;margin:30px auto;background:white;box-shadow:0 2px 10px #999}.note{width:1240px;margin:20px auto;font-size:22px}</style></head><body>',
              '<div class="note"><b>Scormetry 2.0 Sequence Diagrams</b> - restyled to match the previous-year PDF sequence diagram format.</div>']
for idx, diagram in enumerate(diagrams, 1):
    safe = diagram['fig'].lower().replace('fig. ', 'fig-').replace(' - ', '-').replace(' ', '-').replace('/', '-').replace('(', '').replace(')', '').replace('.', '').replace(':','')
    svg_path = OUT_SVG_DIR / f'{idx:02d}-{safe}.svg'
    png_path = OUT_PREVIEW_DIR / f'{idx:02d}-{safe}.png'
    images.append(make_diagram(diagram, svg_path, png_path))
    html_parts.append(f'<div class="page">{svg_path.read_text(encoding="utf-8")}</div>')
html_parts.append('</body></html>')
OUT_HTML.write_text('\n'.join(html_parts), encoding='utf-8')
images[0].save(OUT_PDF, save_all=True, append_images=images[1:], resolution=DPI)
print('PDF:', OUT_PDF)
print('HTML:', OUT_HTML)
print('SVG_DIR:', OUT_SVG_DIR)
print('PNGS:', OUT_PREVIEW_DIR)
