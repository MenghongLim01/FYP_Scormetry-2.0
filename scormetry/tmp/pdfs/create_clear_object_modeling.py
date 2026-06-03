from pathlib import Path
from html import escape

ROOT = Path('/Users/mac/Downloads/FYP_Scormtry-2.0')
OUT_HTML = ROOT / 'docs/chapter3-diagrams/Scormetry2_Object_Modeling_Clear_UseCases.html'
OUT_DIR = ROOT / 'docs/chapter3-diagrams/object-modeling-clear-svg'
OUT_DIR.mkdir(parents=True, exist_ok=True)

W, H = 1550, 980
CSS = '''
  :root { --ink:#111; --line:#222; --muted:#666; --paper:#fff; --outside:#eee; }
  * { box-sizing: border-box; }
  body { margin:0; padding:28px; background:var(--outside); color:var(--ink); font-family:"Times New Roman", Times, serif; }
  .page { width:1240px; max-width:100%; margin:0 auto 34px; padding:36px 42px 30px; background:var(--paper); overflow-x:auto; }
  h1 { margin:0 0 16px; font-size:34px; font-weight:700; text-align:center; }
  h2 { margin:0 0 20px; font-size:31px; font-weight:700; }
  .intro { max-width:980px; margin:0 auto; font-size:20px; line-height:1.45; text-align:center; }
  .caption { margin:22px 0 0; font-size:30px; line-height:1.2; font-weight:700; text-align:center; }
  svg { width:100%; height:auto; display:block; background:#fff; }
  .frame { fill:#fff; stroke:var(--line); stroke-width:2.4; }
  .group { fill:none; stroke:#999; stroke-width:1.4; stroke-dasharray:8 7; }
  .group-title { font-family:"Times New Roman", Times, serif; font-size:20px; font-weight:700; fill:#111; }
  .oval { fill:#fff; stroke:#555; stroke-width:1.9; }
  .actor-line { stroke:#333; stroke-width:2; fill:none; }
  .assoc { stroke:#222; stroke-width:1.8; fill:none; marker-end:url(#arrowSolid); }
  .include { stroke:#111; stroke-width:1.7; stroke-dasharray:10 6; fill:none; marker-end:url(#arrowOpen); }
  .extend { stroke:#555; stroke-width:1.7; stroke-dasharray:2 7; fill:none; marker-end:url(#arrowOpenMuted); }
  .label, .uc-label, .relationship, .legend-text { font-family:"Comic Sans MS", "Comic Sans", "Trebuchet MS", Arial, sans-serif; fill:#111; font-weight:700; }
  .label { font-size:16px; }
  .uc-label { font-size:15px; }
  .relationship { font-size:13px; }
  .legend-text { font-size:14px; }
  .tag { fill:#fff; stroke:#111; stroke-width:1; }
  @media print { body { background:#fff; padding:0; } .page { width:100%; margin:0; page-break-after:always; } }
'''

def text_lines(x, y, lines, cls='uc-label', anchor='middle', gap=18):
    if isinstance(lines, str):
        lines = lines.split('\n')
    total = gap * (len(lines)-1)
    start = y - total/2
    return '\n'.join(f'<text class="{cls}" x="{x}" y="{start+i*gap}" text-anchor="{anchor}">{escape(line)}</text>' for i, line in enumerate(lines))

def defs():
    return '''<defs>
      <marker id="arrowSolid" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth"><path d="M0,0 L0,6 L8,3 z" fill="#222"/></marker>
      <marker id="arrowOpen" markerWidth="11" markerHeight="11" refX="9" refY="4" orient="auto" markerUnits="strokeWidth"><path d="M1,1 L9,4 L1,7" fill="none" stroke="#111" stroke-width="1.6"/></marker>
      <marker id="arrowOpenMuted" markerWidth="11" markerHeight="11" refX="9" refY="4" orient="auto" markerUnits="strokeWidth"><path d="M1,1 L9,4 L1,7" fill="none" stroke="#555" stroke-width="1.6"/></marker>
    </defs>'''

def actor(x, y, name):
    top = y - 82
    lines = name.split('\n')
    return f'''
      <circle class="actor-line" cx="{x}" cy="{top+13}" r="13"/>
      <line class="actor-line" x1="{x}" y1="{top+26}" x2="{x}" y2="{top+76}"/>
      <line class="actor-line" x1="{x-34}" y1="{top+48}" x2="{x+34}" y2="{top+48}"/>
      <line class="actor-line" x1="{x}" y1="{top+76}" x2="{x-31}" y2="{top+116}"/>
      <line class="actor-line" x1="{x}" y1="{top+76}" x2="{x+31}" y2="{top+116}"/>
      {text_lines(x, top+132, lines, 'label')}
    '''

def oval(id_, x, y, label, rx=94, ry=34):
    return f'<ellipse class="oval" id="{id_}" cx="{x}" cy="{y}" rx="{rx}" ry="{ry}"/>\n{text_lines(x, y, label)}'

def line(cls, x1, y1, x2, y2, label=None, label_offset=-8):
    out = [f'<line class="{cls}" x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}"/>']
    if label:
        mx, my = (x1+x2)/2, (y1+y2)/2 + label_offset
        width = 92 if 'include' in label else 88
        out.append(f'<rect class="tag" x="{mx-width/2}" y="{my-16}" width="{width}" height="20" rx="3"/>')
        out.append(f'<text class="relationship" x="{mx}" y="{my}" text-anchor="middle">{escape(label)}</text>')
    return '\n'.join(out)

def poly(cls, points, label=None, lx=None, ly=None):
    pts = ' '.join(f'{x},{y}' for x, y in points)
    out = [f'<polyline class="{cls}" points="{pts}"/>']
    if label and lx is not None and ly is not None:
        width = 92 if 'include' in label else 88
        out.append(f'<rect class="tag" x="{lx-width/2}" y="{ly-16}" width="{width}" height="20" rx="3"/>')
        out.append(f'<text class="relationship" x="{lx}" y="{ly}" text-anchor="middle">{escape(label)}</text>')
    return '\n'.join(out)

def group(x, y, w, h, title):
    return f'<rect class="group" x="{x}" y="{y}" width="{w}" height="{h}" rx="10"/>\n<text class="group-title" x="{x+18}" y="{y+28}">{escape(title)}</text>'

def legend(x, y):
    return f'''
      <rect x="{x}" y="{y}" width="340" height="122" fill="#fff" stroke="#111" stroke-width="1.6"/>
      <text class="group-title" x="{x+16}" y="{y+27}">Legend</text>
      <line class="assoc" x1="{x+22}" y1="{y+54}" x2="{x+92}" y2="{y+54}"/>
      <text class="legend-text" x="{x+110}" y="{y+59}">Direct user action</text>
      <line class="include" x1="{x+22}" y1="{y+82}" x2="{x+92}" y2="{y+82}"/>
      <text class="legend-text" x="{x+110}" y="{y+87}">&lt;&lt;include&gt;&gt; Required sub-function</text>
      <line class="extend" x1="{x+22}" y1="{y+108}" x2="{x+92}" y2="{y+108}"/>
      <text class="legend-text" x="{x+110}" y="{y+113}">&lt;&lt;extend&gt;&gt; Optional/conditional</text>
    '''

def svg_wrap(content, w=W, h=H):
    svg_style = '''<style>
      .frame{fill:#fff;stroke:#222;stroke-width:2.4}.group{fill:none;stroke:#999;stroke-width:1.4;stroke-dasharray:8 7}.group-title{font-family:"Times New Roman",Times,serif;font-size:20px;font-weight:700;fill:#111}.oval{fill:#fff;stroke:#555;stroke-width:1.9}.actor-line{stroke:#333;stroke-width:2;fill:none}.assoc{stroke:#222;stroke-width:1.8;fill:none;marker-end:url(#arrowSolid)}.include{stroke:#111;stroke-width:1.7;stroke-dasharray:10 6;fill:none;marker-end:url(#arrowOpen)}.extend{stroke:#555;stroke-width:1.7;stroke-dasharray:2 7;fill:none;marker-end:url(#arrowOpenMuted)}.label,.uc-label,.relationship,.legend-text{font-family:"Comic Sans MS","Comic Sans","Trebuchet MS",Arial,sans-serif;fill:#111;font-weight:700}.label{font-size:16px}.uc-label{font-size:15px}.relationship{font-size:13px}.legend-text{font-size:14px}.tag{fill:#fff;stroke:#111;stroke-width:1}
    </style>'''
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}" role="img">\n{svg_style}\n{defs()}\n<rect width="100%" height="100%" fill="#fff"/>\n<rect class="frame" x="20" y="24" width="{w-40}" height="{h-78}"/>\n{content}\n</svg>'

# Organizer/Admin Part 1
admin1 = []
admin1 += [legend(1170, 52), actor(105, 525, 'Organizer /\nAdmin')]
admin1 += [group(190, 75, 900, 170, 'Account and User Management')]
admin1 += [oval('login', 330, 160, 'Login / Google\nOAuth'), oval('dash', 555, 160, 'View Admin\nDashboard'), oval('profile', 800, 115, 'Manage Profile /\nSecurity', 112), oval('users', 800, 205, 'Manage System\nUsers', 112), oval('approve', 1030, 160, 'Approve User\nAccounts', 105), oval('role', 1235, 180, 'Update User\nRole', 96), oval('block', 1030, 235, 'Block / Unblock\nUsers', 105), oval('delete', 1235, 260, 'Delete User\nAccount', 96)]
admin1 += [group(190, 295, 1215, 255, 'Subject Room and Member Management')]
admin1 += [oval('subject', 330, 390, 'Create Subject\nRoom'), oval('editSubject', 555, 340, 'Edit / Delete\nSubject'), oval('codes', 555, 442, 'View / Reset\nJoin Codes'), oval('approval', 780, 390, 'Set Approval\nRule'), oval('pending', 1030, 390, 'Approve / Reject\nMembers', 112), oval('students', 330, 502, 'Add / Remove\nStudents'), oval('reviewers', 555, 502, 'Add / Remove\nReviewers'), oval('invite', 780, 502, 'Invite Reviewer\nby Email'), oval('roleLabel', 1030, 502, 'Set Committee\nRole Label'), oval('blocked', 1260, 502, 'Block Rejoining\nby Email', 108)]
admin1 += [group(190, 605, 1215, 235, 'Team and Schedule Management')]
admin1 += [oval('team', 330, 700, 'Create / Delete\nTeams'), oval('teamMember', 575, 700, 'Add / Remove\nTeam Members', 112), oval('assign', 830, 700, 'Assign Reviewer\nto Team', 108), oval('schedule', 1080, 700, 'Set Defense\nSchedule'), oval('notify', 1280, 700, 'Notify Schedule\nChange'), oval('teamStatus', 575, 792, 'View Team /\nMember Status', 112)]
# associations
for target in [(236,160),(236,390),(236,502),(236,700),(463,700),(678,700)]:
    admin1.append(line('assoc', 140, 525, target[0], target[1]))
# relations
admin1 += [line('include', 425,160,460,160,'<<include>>'), line('extend',650,150,690,125,'<<extend>>'), line('include',665,170,695,200,'<<include>>'), line('include',905,200,935,170,'<<include>>'), line('extend',905,205,935,230,'<<extend>>'), line('extend',1128,160,1140,175,'<<extend>>'), line('extend',1128,235,1140,255,'<<extend>>')]
admin1 += [line('extend',425,390,465,345,'<<extend>>'), line('include',425,390,465,435,'<<include>>'), line('include',425,390,685,390,'<<include>>'), line('include',875,390,925,390,'<<include>>'), line('include',425,502,465,502,'<<include>>'), line('include',650,502,685,502,'<<include>>'), line('include',650,502,925,502,'<<include>>'), line('extend',650,502,1152,502,'<<extend>>')]
admin1 += [line('include',425,700,463,700,'<<include>>'), line('include',687,700,725,700,'<<include>>'), line('extend',938,700,982,700,'<<extend>>'), line('include',1175,700,1185,700,'<<include>>'), line('include',425,700,465,782,'<<include>>')]
admin1_svg = svg_wrap('\n'.join(admin1), 1550, 920)

# Organizer/Admin Part 2
admin2 = []
admin2 += [legend(1170, 52), actor(105, 515, 'Organizer /\nAdmin')]
admin2 += [group(190, 75, 1215, 250, 'Rubric Management')]
admin2 += [oval('rubricUpload', 340, 180, 'Upload Official\nRubric PDF', 112), oval('ai', 590, 180, 'AI Convert to\nDynamic Rubric', 112), oval('viewRubric', 835, 180, 'View Rubric PDF /\nStructure', 112), oval('editRubric', 1075, 135, 'Edit Extracted\nStructure', 112), oval('lock', 1280, 180, 'Verify and Lock\nRubric', 100), oval('deleteRubric', 1075, 250, 'Delete Unlocked\nRubric', 112)]
admin2 += [group(190, 390, 1215, 315, 'Document, Score, and Feedback Records')]
admin2 += [oval('papers', 340, 500, 'Monitor Submitted\nPapers', 112), oval('paperPdf', 590, 500, 'View / Download\nPaper PDF', 112), oval('scores', 835, 500, 'View Team\nScores'), oval('reviews', 1075, 455, 'View Judge Reviews\nand Feedback', 122), oval('release', 1280, 500, 'Release Scores\nto Students', 108), oval('override', 1075, 610, 'Override Final\nScore', 100), oval('result', 1280, 610, 'Student Result\nVisible', 100)]
admin2 += [group(190, 760, 1215, 120, 'Audit and Control')]
admin2 += [oval('unlock', 500, 820, 'Unlock Review\nwith Reason', 112), oval('logs', 800, 820, 'View Unlock Logs /\nHistory', 122), oval('locked', 1090, 820, 'Preserve Locked\nReview Record', 122)]
for target in [(228,180),(228,500),(737,500),(390,820)]:
    admin2.append(line('assoc', 140, 515, target[0], target[1]))
admin2 += [line('include',452,180,478,180,'<<include>>'), line('include',702,180,723,180,'<<include>>'), line('extend',947,180,963,145,'<<extend>>'), line('include',947,180,1180,180,'<<include>>'), line('extend',947,180,970,245,'<<extend>>')]
admin2 += [line('include',452,500,478,500,'<<include>>'), line('include',947,500,953,465,'<<include>>'), line('extend',947,500,1172,500,'<<extend>>'), line('extend',947,500,970,610,'<<extend>>'), line('include',1183,500,1185,610,'<<include>>')]
admin2 += [line('include',612,820,678,820,'<<include>>'), line('include',922,820,968,820,'<<include>>'), poly('extend', [(1183,500),(1183,735),(585,735),(585,790)], '<<extend>>', 900, 727)]
admin2_svg = svg_wrap('\n'.join(admin2), 1550, 940)

# Judge
judge = []
judge += [legend(1030, 52), actor(105, 430, 'Judge /\nReviewer')]
judge += [group(190, 75, 1125, 160, 'Access and Room Entry')]
judge += [oval('login', 330, 150, 'Login / Google\nOAuth'), oval('invite', 555, 115, 'Accept Email\nInvitation'), oval('code', 555, 190, 'Join as Reviewer\nby Code'), oval('role', 790, 190, 'Select Committee\nRole'), oval('dashboard', 1030, 150, 'View Reviewer\nDashboard')]
judge += [group(190, 285, 1125, 190, 'Team Room and Evaluation Materials')]
judge += [oval('teams', 330, 380, 'View Assigned\nTeams'), oval('room', 555, 380, 'Open Subject\nRoom'), oval('schedule', 790, 345, 'View Defense\nSchedule'), oval('paper', 1020, 380, 'View Final\nPaper PDF'), oval('rubric', 1230, 380, 'View Locked\nDynamic Rubric', 110)]
judge += [group(190, 535, 1125, 250, 'Review Submission and Results')]
judge += [oval('review', 330, 640, 'Open Review\nForm'), oval('score', 555, 610, 'Score Each\nCriterion'), oval('comment', 790, 610, 'Add Per-Criterion\nComments', 112), oval('feedback', 1020, 610, 'Add Overall\nFeedback'), oval('submit', 1230, 640, 'Submit Review'), oval('deadline', 555, 715, 'Follow Score\nDeadline'), oval('locked', 790, 715, 'Review Becomes\nLocked'), oval('edit', 1020, 715, 'Edit Only if\nUnlocked'), oval('summary', 555, 815, 'View Team Score\nSummary', 112), oval('released', 790, 815, 'View Released\nResult')]
for target in [(236,150),(236,380),(236,640),(452,815)]:
    judge.append(line('assoc', 140, 430, target[0], target[1]))
judge += [line('include',430,150,468,120,'<<include>>'), line('extend',430,150,935,150,'<<extend>>'), line('include',668,190,690,190,'<<include>>'), line('extend',668,190,935,150,'<<extend>>')]
judge += [line('include',430,380,462,380,'<<include>>'), line('include',668,380,692,350,'<<include>>'), line('include',668,380,920,380,'<<include>>'), line('include',1120,380,1128,380,'<<include>>')]
judge += [line('include',430,640,458,615,'<<include>>'), line('include',668,610,678,610,'<<include>>'), line('include',902,610,918,610,'<<include>>'), line('include',1118,610,1132,635,'<<include>>'), line('include',430,640,462,710,'<<include>>'), line('extend',902,715,920,715,'<<extend>>'), line('extend',902,715,1132,645,'<<extend>>'), line('extend',668,815,692,815,'<<extend>>')]
judge_svg = svg_wrap('\n'.join(judge), 1420, 880)

# Student
student = []
student += [legend(1030, 52), actor(105, 420, 'Student')]
student += [group(190, 75, 1125, 160, 'Access and Membership')]
student += [oval('login', 330, 150, 'Login / Google\nOAuth'), oval('join', 555, 150, 'Join Subject\nby Code'), oval('approval', 790, 115, 'Wait for\nApproval'), oval('status', 790, 190, 'View Blocked /\nPending Status'), oval('dashboard', 1030, 150, 'View Student\nDashboard')]
student += [group(190, 285, 1125, 190, 'Team Room and Evaluation Materials')]
student += [oval('joined', 330, 380, 'View Joined\nSubjects'), oval('room', 555, 380, 'Open Team\nRoom'), oval('createTeam', 790, 340, 'Create Team'), oval('member', 1020, 340, 'Add / Invite\nTeam Member', 112), oval('leave', 1230, 340, 'Leave Team'), oval('schedule', 790, 425, 'View Defense\nSchedule'), oval('rubric', 1020, 425, 'View Official\nRubric PDF', 112)]
student += [group(190, 535, 1125, 250, 'Submission and Released Results')]
student += [oval('submission', 330, 640, 'Open Paper\nSubmission'), oval('upload', 555, 640, 'Upload Final\nPaper PDF'), oval('uploadStatus', 790, 640, 'View Upload\nStatus'), oval('own', 330, 735, 'View Own\nPapers'), oval('pending', 555, 735, 'View Result\nPending Page'), oval('released', 790, 735, 'View Released\nResult'), oval('criteria', 1020, 690, 'View Criteria\nBreakdown'), oval('comments', 1020, 780, 'View Judge\nComments'), oval('final', 1230, 735, 'View Final Score /\nOverride Note', 112)]
for target in [(236,150),(236,380),(236,640),(236,735)]:
    student.append(line('assoc', 140, 420, target[0], target[1]))
student += [line('include',430,150,462,150,'<<include>>'), line('include',668,150,692,120,'<<include>>'), line('extend',668,150,692,185,'<<extend>>'), line('extend',902,150,935,150,'<<extend>>')]
student += [line('include',430,380,462,380,'<<include>>'), line('extend',668,380,692,345,'<<extend>>'), line('extend',902,340,910,340,'<<extend>>'), line('extend',1130,340,1135,340,'<<extend>>'), line('include',668,380,692,425,'<<include>>'), line('include',668,380,908,425,'<<include>>')]
student += [line('include',430,640,462,640,'<<include>>'), line('include',668,640,692,640,'<<include>>'), line('extend',430,735,462,735,'<<extend>>'), line('extend',668,735,692,735,'<<extend>>'), line('include',902,735,922,695,'<<include>>'), line('include',902,735,922,775,'<<include>>'), line('include',902,735,1118,735,'<<include>>')]
student_svg = svg_wrap('\n'.join(student), 1420, 880)

pages = [
    ('3.4.5.5 Object Modeling - Organizer/Admin (Part 1)', 'Fig. 1A. Clear Organizer/Admin Use Case Diagram for Scormetry 2.0 - Account, Subject, and Team Management', admin1_svg),
    ('3.4.5.5 Object Modeling - Organizer/Admin (Part 2)', 'Fig. 1B. Clear Organizer/Admin Use Case Diagram for Scormetry 2.0 - Rubric, Records, and Audit Management', admin2_svg),
    ('3.4.5.5 Object Modeling - Judge/Reviewer', 'Fig. 2. Clear Judge/Reviewer Use Case Diagram for Scormetry 2.0', judge_svg),
    ('3.4.5.5 Object Modeling - Student', 'Fig. 3. Clear Student Use Case Diagram for Scormetry 2.0', student_svg),
]

# Write individual SVGs
for idx, (_, caption, svg) in enumerate(pages, 1):
    name = caption.lower().replace('fig. ', 'fig-').replace(' ', '-').replace('/', '-').replace(',', '').replace('.', '').replace('–','-')
    (OUT_DIR / f'{idx:02d}-{name}.svg').write_text(svg, encoding='utf-8')

html = ['<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Scormetry 2.0 Clear Object Modeling Use Case Diagrams</title>', f'<style>{CSS}</style></head><body>']
html.append('<section class="page"><h1>Scormetry 2.0 Clear Object Modeling Use Case Diagrams</h1><p class="intro">This version separates the crowded relationships and uses a legend to clearly distinguish direct associations, required <b>&lt;&lt;include&gt;&gt;</b> relationships, and optional or conditional <b>&lt;&lt;extend&gt;&gt;</b> relationships. The Organizer/Admin diagram is split into two parts to improve readability while preserving the same system scope.</p></section>')
for title, caption, svg in pages:
    html.append(f'<section class="page"><h2>{escape(title)}</h2>{svg}<p class="caption">{escape(caption)}</p></section>')
html.append('</body></html>')
OUT_HTML.write_text('\n'.join(html), encoding='utf-8')
print('HTML:', OUT_HTML)
print('SVG_DIR:', OUT_DIR)
