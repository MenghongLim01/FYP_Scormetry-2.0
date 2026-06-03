// Scormetry 2.0 Prototype Builder
// Development plugin that generates frames + prototype connections for a clickable demo.
// Import this plugin via: Figma Desktop → Plugins → Development → Import plugin from manifest…

/* global figma */

(async () => {
  const CANVAS = {
    width: 1440,
    height: 900,
    sidebarWidth: 280,
    topbarHeight: 72,
    gutter: 24,
  };

  const COLORS = {
    background: '#F8FAFC',
    card: '#FFFFFF',
    border: '#E5E7EB',
    text: '#111827',
    mutedText: '#64748B',
    primary: '#1B2D6E',
    primaryText: '#FFFFFF',
    orange: '#F97316',
    red: '#DC2626',
    green: '#16A34A',
    leftPanel: '#0F1322',
    leftPanelLine: '#3B82F6',
  };

  const EFFECTS = {
    cardShadow: [
      {
        type: 'DROP_SHADOW',
        color: { r: 0, g: 0, b: 0, a: 0.08 },
        offset: { x: 0, y: 8 },
        radius: 24,
        visible: true,
        blendMode: 'NORMAL',
      },
    ],
    softShadow: [
      {
        type: 'DROP_SHADOW',
        color: { r: 0, g: 0, b: 0, a: 0.06 },
        offset: { x: 0, y: 4 },
        radius: 16,
        visible: true,
        blendMode: 'NORMAL',
      },
    ],
  };

  function hexToRgb01(hex) {
    const cleaned = String(hex).replace('#', '').trim();
    const full = cleaned.length === 3
      ? cleaned.split('').map((c) => c + c).join('')
      : cleaned;
    const num = parseInt(full, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return { r: r / 255, g: g / 255, b: b / 255 };
  }

  function solid(hex, opacity = 1) {
    return { type: 'SOLID', color: hexToRgb01(hex), opacity };
  }

  async function loadFonts() {
    // Use Inter (default) to avoid missing fonts on collaborators.
    const families = [
      { family: 'Inter', style: 'Regular' },
      { family: 'Inter', style: 'Medium' },
      { family: 'Inter', style: 'Semi Bold' },
      { family: 'Inter', style: 'Bold' },
    ];

    for (const f of families) {
      try {
        // eslint-disable-next-line no-await-in-loop
        await figma.loadFontAsync(f);
      } catch (e) {
        // Ignore missing font styles; fall back to Regular where needed.
      }
    }
  }

  function setFills(node, paints) {
    node.fills = paints;
  }

  function setStrokes(node, paints, weight = 1) {
    node.strokes = paints;
    node.strokeWeight = weight;
  }

  function makeFrame(name, w, h, { fillHex = null, strokeHex = null, radius = 0, effects = null } = {}) {
    const frame = figma.createFrame();
    frame.name = name;
    frame.resize(w, h);
    frame.clipsContent = true;
    frame.cornerRadius = radius;
    if (fillHex) {
      setFills(frame, [solid(fillHex)]);
    } else {
      frame.fills = [];
    }
    if (strokeHex) {
      setStrokes(frame, [solid(strokeHex)], 1);
    } else {
      frame.strokes = [];
    }
    if (effects) {
      frame.effects = effects;
    }
    return frame;
  }

  function makeRect(name, x, y, w, h, { fillHex = null, strokeHex = null, radius = 0, opacity = 1 } = {}) {
    const r = figma.createRectangle();
    r.name = name;
    r.resize(w, h);
    r.x = x;
    r.y = y;
    r.cornerRadius = radius;
    if (fillHex) {
      r.fills = [solid(fillHex, opacity)];
    } else {
      r.fills = [];
    }
    if (strokeHex) {
      r.strokes = [solid(strokeHex)];
      r.strokeWeight = 1;
    } else {
      r.strokes = [];
    }
    return r;
  }

  function makeLine(name, x1, y1, x2, y2, hex = COLORS.border, opacity = 1) {
    const line = figma.createLine();
    line.name = name;
    line.x = x1;
    line.y = y1;
    line.resize(Math.max(1, x2 - x1), Math.max(1, y2 - y1));
    line.strokes = [solid(hex, opacity)];
    line.strokeWeight = 1;
    return line;
  }

  function makeText(name, text, x, y, { size = 14, style = 'Regular', colorHex = COLORS.text, align = 'LEFT' } = {}) {
    const t = figma.createText();
    t.name = name;
    t.x = x;
    t.y = y;
    t.fontName = { family: 'Inter', style };
    t.fontSize = size;
    t.fills = [solid(colorHex)];
    t.textAutoResize = 'WIDTH_AND_HEIGHT';
    t.textAlignHorizontal = align;
    t.characters = text;
    return t;
  }

  function centerIn(containerW, containerH, node) {
    node.x = Math.round((containerW - node.width) / 2);
    node.y = Math.round((containerH - node.height) / 2);
  }

  function addBadge(parent, label, x, y, { colorHex = COLORS.orange, bgOpacity = 0.12 } = {}) {
    const badge = makeFrame(`Badge / ${label}`, 10, 10, { fillHex: colorHex, radius: 999 });
    badge.effects = [];
    badge.clipsContent = false;
    badge.fills = [solid(colorHex, bgOpacity)];
    badge.strokes = [solid(colorHex, 0.25)];
    badge.strokeWeight = 1;
    badge.x = x;
    badge.y = y;

    const text = makeText(`Label`, label, 0, 0, { size: 11, style: 'Medium', colorHex });
    parent.appendChild(badge);
    badge.appendChild(text);

    // Padding
    const padX = 10;
    const padY = 6;
    badge.resize(text.width + padX * 2, text.height + padY);
    text.x = padX;
    text.y = Math.round((badge.height - text.height) / 2);

    return badge;
  }

  function addButton(parent, label, x, y, { variant = 'primary', width = 160 } = {}) {
    const height = 40;
    const isPrimary = variant === 'primary';
    const isOutline = variant === 'outline';
    const isDestructive = variant === 'destructive';
    const isWarning = variant === 'warning';

    const bg = isPrimary
      ? COLORS.primary
      : isDestructive
        ? COLORS.red
        : isWarning
          ? COLORS.orange
          : COLORS.card;
    const textColor = isOutline ? COLORS.primary : isPrimary || isDestructive || isWarning ? COLORS.primaryText : COLORS.text;
    const stroke = isOutline ? COLORS.border : null;

    const btn = makeFrame(`Button / ${label}`, width, height, {
      fillHex: bg,
      strokeHex: stroke,
      radius: 10,
      effects: isPrimary ? EFFECTS.softShadow : null,
    });
    btn.x = x;
    btn.y = y;
    btn.clipsContent = false;

    const t = makeText('Text', label, 0, 0, {
      size: 14,
      style: isPrimary ? 'Semi Bold' : 'Medium',
      colorHex: textColor,
    });
    btn.appendChild(t);
    t.x = Math.round((btn.width - t.width) / 2);
    t.y = Math.round((btn.height - t.height) / 2);

    parent.appendChild(btn);
    return btn;
  }

  function addLinkText(parent, label, x, y, { colorHex = COLORS.primary } = {}) {
    const t = makeText(`Link / ${label}`, label, x, y, { size: 13, style: 'Medium', colorHex });
    parent.appendChild(t);
    return t;
  }

  function addInput(parent, label, x, y, { placeholder = '', width = 420 } = {}) {
    const group = figma.createFrame();
    group.name = `Input / ${label}`;
    group.resize(width, 72);
    group.x = x;
    group.y = y;
    group.fills = [];
    group.strokes = [];
    group.clipsContent = false;
    parent.appendChild(group);

    const lab = makeText('Label', label, 0, 0, { size: 12, style: 'Medium', colorHex: COLORS.mutedText });
    group.appendChild(lab);
    lab.x = 0;
    lab.y = 0;

    const field = makeFrame('Field', width, 44, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 10 });
    field.x = 0;
    field.y = 24;
    field.effects = [];
    group.appendChild(field);

    const ph = makeText('Placeholder', placeholder, 14, 12, { size: 13, style: 'Regular', colorHex: '#94A3B8' });
    field.appendChild(ph);

    return { group, field };
  }

  function addSidebar(parent, roleLabel, active) {
    const sidebar = makeFrame('Sidebar', CANVAS.sidebarWidth, CANVAS.height, {
      fillHex: COLORS.card,
      strokeHex: COLORS.border,
      radius: 0,
    });
    sidebar.x = 0;
    sidebar.y = 0;
    sidebar.effects = [];
    parent.appendChild(sidebar);

    const logoWrap = makeFrame('Logo', CANVAS.sidebarWidth - 32, 44, { fillHex: null, radius: 12 });
    logoWrap.fills = [];
    logoWrap.strokes = [];
    logoWrap.x = 16;
    logoWrap.y = 16;
    sidebar.appendChild(logoWrap);

    const mark = makeRect('Mark', 0, 6, 32, 32, { fillHex: COLORS.primary, radius: 10 });
    logoWrap.appendChild(mark);

    const title = makeText('Product', 'Scormetry 2.0', 44, 4, { size: 14, style: 'Semi Bold', colorHex: COLORS.text });
    logoWrap.appendChild(title);
    const role = makeText('Role', roleLabel, 44, 22, { size: 11, style: 'Medium', colorHex: COLORS.mutedText });
    logoWrap.appendChild(role);

    const navStartY = 84;
    const itemH = 40;
    const itemW = CANVAS.sidebarWidth - 32;
    const items = [
      'Dashboard',
      'Rooms',
      'Scores',
      'Assigned Teams',
      'Submitted Scores',
      'Upload Documents',
      'Schedule',
      'Results',
      'Settings/Profile',
    ];

    const hotspots = {};
    let y = navStartY;
    for (const label of items) {
      const isActive = label === active;
      const item = makeFrame(`Nav / ${label}`, itemW, itemH, {
        fillHex: isActive ? '#EEF2FF' : null,
        strokeHex: isActive ? '#C7D2FE' : null,
        radius: 12,
      });
      item.fills = isActive ? [solid('#EEF2FF')] : [];
      item.strokes = isActive ? [solid('#C7D2FE')] : [];
      item.strokeWeight = 1;
      item.x = 16;
      item.y = y;
      item.clipsContent = false;
      sidebar.appendChild(item);

      const t = makeText('Label', label, 14, 11, { size: 13, style: isActive ? 'Semi Bold' : 'Medium', colorHex: COLORS.text });
      item.appendChild(t);

      hotspots[label] = item;
      y += itemH + 6;
    }

    return { sidebar, navHotspots: hotspots };
  }

  function addTopbar(parent, title, subtitle) {
    const topbar = makeFrame('Topbar', CANVAS.width - CANVAS.sidebarWidth, CANVAS.topbarHeight, {
      fillHex: COLORS.background,
      strokeHex: COLORS.border,
      radius: 0,
    });
    topbar.effects = [];
    topbar.x = CANVAS.sidebarWidth;
    topbar.y = 0;
    parent.appendChild(topbar);

    const t = makeText('Title', title, 24, 18, { size: 16, style: 'Semi Bold', colorHex: COLORS.text });
    topbar.appendChild(t);
    if (subtitle) {
      const s = makeText('Subtitle', subtitle, 24, 40, { size: 12, style: 'Regular', colorHex: COLORS.mutedText });
      topbar.appendChild(s);
    }

    // Right user chip
    const chip = makeFrame('User Chip', 210, 36, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 999 });
    chip.effects = [];
    chip.x = topbar.width - 24 - chip.width;
    chip.y = 18;
    topbar.appendChild(chip);
    const dot = makeRect('Avatar', 12, 8, 20, 20, { fillHex: '#CBD5E1', radius: 999 });
    chip.appendChild(dot);
    const name = makeText('Name', 'Dr. Sokha Chan', 40, 10, { size: 12, style: 'Medium', colorHex: COLORS.text });
    chip.appendChild(name);

    return topbar;
  }

  function addContentSurface(parent) {
    const content = figma.createFrame();
    content.name = 'Content';
    content.resize(CANVAS.width - CANVAS.sidebarWidth, CANVAS.height - CANVAS.topbarHeight);
    content.x = CANVAS.sidebarWidth;
    content.y = CANVAS.topbarHeight;
    content.fills = [];
    content.strokes = [];
    content.clipsContent = false;
    parent.appendChild(content);
    return content;
  }

  function addCard(parent, title, x, y, w, h, { subtitle = '', badge = null } = {}) {
    const card = makeFrame(`Card / ${title}`, w, h, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    card.x = x;
    card.y = y;
    card.clipsContent = false;
    parent.appendChild(card);

    const t = makeText('Title', title, 18, 16, { size: 14, style: 'Semi Bold', colorHex: COLORS.text });
    card.appendChild(t);
    if (subtitle) {
      const s = makeText('Subtitle', subtitle, 18, 38, { size: 12, style: 'Regular', colorHex: COLORS.mutedText });
      card.appendChild(s);
    }
    if (badge) {
      addBadge(card, badge.label, w - 18 - 90, 14, { colorHex: badge.colorHex || COLORS.orange });
    }

    return card;
  }

  async function setNavigate(node, destinationFrame, { overlay = false } = {}) {
    if (!node || !destinationFrame) return;
    await node.setReactionsAsync([
      {
        trigger: { type: 'ON_CLICK' },
        actions: [
          {
            type: 'NODE',
            destinationId: destinationFrame.id,
            navigation: overlay ? 'OVERLAY' : 'NAVIGATE',
            transition: null,
          },
        ],
      },
    ]);
  }

  async function setCloseOverlay(node) {
    if (!node) return;
    await node.setReactionsAsync([
      {
        trigger: { type: 'ON_CLICK' },
        actions: [{ type: 'CLOSE' }],
      },
    ]);
  }

  // ---- Guard: don't generate twice
  const existingLogin = figma.currentPage.findOne((n) => n.type === 'FRAME' && n.name === 'Auth / Login');
  if (existingLogin) {
    figma.currentPage.selection = [existingLogin];
    figma.viewport.scrollAndZoomIntoView([existingLogin]);
    figma.notify('Scormetry prototype already exists. Focused Auth / Login.');
    figma.closePlugin();
    return;
  }

  await loadFonts();

  // Keep everything on ONE page so prototype navigation works reliably.
  figma.currentPage.name = 'Scormetry 2.0 Prototype';

  // Coordinate groups. Kept as plain objects for maximum compatibility with older Figma desktop builds.
  const gap = 240;
  const sections = {
    auth: { name: 'Auth', x: 0, y: 0, width: 1800, height: 1200 },
    flow: { name: 'Flow Map', x: 1800 + gap, y: 0, width: 1200, height: 1600 },
    instructor: { name: 'Instructor / Admin', x: 0, y: 1200 + gap, width: 5200, height: 3400 },
    judge: { name: 'Judge / Reviewer', x: 5200 + gap, y: 1200 + gap, width: 2200, height: 2200 },
    student: { name: 'Student', x: 5200 + gap, y: 1200 + gap + 2200 + gap, width: 2200, height: 2400 },
  };

  // ---- Create all screens
  const frames = {};
  const hotspots = {};

  function placeInSection(section, index, cols, frame) {
    const cellW = CANVAS.width + 180;
    const cellH = CANVAS.height + 180;
    const col = index % cols;
    const row = Math.floor(index / cols);
    frame.x = section.x + 80 + col * cellW;
    frame.y = section.y + 90 + row * cellH;
  }

  // Flow map (simple diagram frame inside Flow section)
  (function buildFlowMap() {
    const flow = makeFrame('Flow / User Journey', 1120, 1520, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 20 });
    flow.clipsContent = false;
    placeInSection(sections.flow, 0, 1, flow);

    const title = makeText('Title', 'Scormetry 2.0 — Demo User Journey', 32, 28, { size: 20, style: 'Bold' });
    flow.appendChild(title);
    const sub = makeText('Sub', 'Login → Instructor setup → Student upload → Judge scoring → Results', 32, 56, { size: 13, style: 'Regular', colorHex: COLORS.mutedText });
    flow.appendChild(sub);

    const steps = [
      'Login',
      'Instructor Dashboard',
      'Create/Open Room',
      'Invite Students',
      'Create Teams',
      'Assign Judges',
      'Set Defense Schedule',
      'Upload/Convert Rubric',
      'Verify/Lock Rubric',
      'Student Upload Documents',
      'Judge Scoring',
      'Instructor Reviews Scores',
      'Release Results',
      'Student Views Breakdown',
    ];

    const startX = 64;
    let y = 110;
    for (let i = 0; i < steps.length; i += 1) {
      const box = makeFrame(`Step / ${steps[i]}`, 520, 54, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 14, effects: EFFECTS.softShadow });
      box.clipsContent = false;
      box.x = startX;
      box.y = y;
      flow.appendChild(box);

      const no = makeText('No', String(i + 1).padStart(2, '0'), 0, 0, { size: 12, style: 'Semi Bold', colorHex: COLORS.mutedText });
      box.appendChild(no);
      no.x = 18;
      no.y = 18;
      const label = makeText('Label', steps[i], 64, 16, { size: 14, style: 'Semi Bold' });
      box.appendChild(label);
      label.x = 18;
      label.y = 18;

      y += 70;
    }

    const note = makeFrame('Note', 1000, 220, { fillHex: '#EEF2FF', strokeHex: '#C7D2FE', radius: 18 });
    note.effects = [];
    note.x = 64;
    note.y = 1220;
    note.clipsContent = false;
    flow.appendChild(note);
    const n1 = makeText('NoteTitle', 'Tip for presenting', 20, 18, { size: 14, style: 'Semi Bold' });
    note.appendChild(n1);
    const n2 = makeText('NoteBody',
      'All screens are on one Figma page so prototype navigation works reliably.\nUse the Login frame as the starting point when presenting.',
      20,
      44,
      { size: 12, style: 'Regular', colorHex: COLORS.mutedText },
    );
    note.appendChild(n2);
  })();

  // ---- Auth screens
  function buildAuthLogin() {
    const frame = makeFrame('Auth / Login', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;

    // Left panel
    const left = makeFrame('Left Panel', 720, CANVAS.height, { fillHex: COLORS.leftPanel, radius: 24 });
    left.x = 0;
    left.y = 0;
    left.effects = [];
    frame.appendChild(left);

    const accent = makeRect('Accent Line', 0, 0, 720, 1, { fillHex: COLORS.leftPanelLine, radius: 0, opacity: 0.35 });
    left.appendChild(accent);

    left.appendChild(makeText('Brand', 'Scormetry 2.0', 36, 32, { size: 16, style: 'Semi Bold', colorHex: '#FFFFFF' }));
    left.appendChild(makeText('Tagline', 'AI Rubric Generation & Secure Document Management', 36, 58, { size: 12, style: 'Regular', colorHex: '#94A3B8' }));

    // Floating cards (simple)
    const mock = makeFrame('Mock Card', 360, 220, { fillHex: '#FFFFFF', strokeHex: '#1F2937', radius: 18, effects: EFFECTS.cardShadow });
    mock.x = 180;
    mock.y = 220;
    mock.fills = [solid('#FFFFFF', 0.06)];
    mock.strokes = [solid('#FFFFFF', 0.1)];
    mock.effects = EFFECTS.softShadow;
    left.appendChild(mock);
    mock.clipsContent = false;
    const mockTitle = makeText('MockTitle', 'Team Alpha', 18, 18, { size: 13, style: 'Semi Bold', colorHex: '#E5E7EB' });
    mock.appendChild(mockTitle);
    addBadge(mock, 'Active', 270, 16, { colorHex: '#22C55E', bgOpacity: 0.18 });
    mock.appendChild(makeText('MockLine', 'Final Score: 87.4', 18, 54, { size: 12, style: 'Regular', colorHex: '#CBD5E1' }));
    const barBg = makeRect('Bar BG', 18, 78, 320, 8, { fillHex: '#FFFFFF', radius: 999, opacity: 0.10 });
    mock.appendChild(barBg);
    const bar = makeRect('Bar', 18, 78, 230, 8, { fillHex: '#3B82F6', radius: 999, opacity: 0.9 });
    mock.appendChild(bar);

    // Right panel (form)
    const right = makeFrame('Right Panel', 720, CANVAS.height, { fillHex: COLORS.background, radius: 24 });
    right.effects = [];
    right.x = 720;
    right.y = 0;
    frame.appendChild(right);

    const card = makeFrame('Login Card', 420, 460, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 20, effects: EFFECTS.cardShadow });
    card.clipsContent = false;
    card.x = 150;
    card.y = 180;
    right.appendChild(card);

    card.appendChild(makeText('Title', 'Sign in', 24, 24, { size: 18, style: 'Bold' }));
    card.appendChild(makeText('Desc', 'Use your PIU account to continue.', 24, 52, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    addInput(card, 'Email', 24, 92, { placeholder: 'name@piu.edu.kh', width: 372 });
    addInput(card, 'Password', 24, 170, { placeholder: '••••••••', width: 372 });

    const loginBtn = addButton(card, 'Log in', 24, 270, { variant: 'primary', width: 372 });
    const googleBtn = addButton(card, 'Continue with Google', 24, 320, { variant: 'outline', width: 372 });

    const forgot = addLinkText(card, 'Forgot password?', 24, 372, { colorHex: COLORS.primary });
    const signup = addLinkText(card, 'Sign up', 302, 372, { colorHex: COLORS.primary });

    hotspots['auth.login.logIn'] = loginBtn;
    hotspots['auth.login.google'] = googleBtn;
    hotspots['auth.login.forgot'] = forgot;
    hotspots['auth.login.signup'] = signup;

    return frame;
  }

  function buildAuthSignup() {
    const frame = makeFrame('Auth / Sign Up', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;

    const card = makeFrame('Sign Up Card', 480, 560, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 20, effects: EFFECTS.cardShadow });
    card.clipsContent = false;
    card.x = 480;
    card.y = 160;
    frame.appendChild(card);

    card.appendChild(makeText('Title', 'Create your account', 24, 24, { size: 18, style: 'Bold' }));
    card.appendChild(makeText('Desc', 'For capstone demo, accounts can be created instantly.', 24, 52, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    addInput(card, 'Full name', 24, 92, { placeholder: 'Student Name', width: 432 });
    addInput(card, 'Email', 24, 170, { placeholder: 'name@piu.edu.kh', width: 432 });
    addInput(card, 'Password', 24, 248, { placeholder: '••••••••', width: 432 });

    const create = addButton(card, 'Create account', 24, 352, { variant: 'primary', width: 432 });
    const already = addLinkText(card, 'Already have an account?', 24, 412, { colorHex: COLORS.primary });

    hotspots['auth.signup.create'] = create;
    hotspots['auth.signup.already'] = already;

    return frame;
  }

  function buildAuthForgot() {
    const frame = makeFrame('Auth / Forgot Password', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;

    const card = makeFrame('Forgot Card', 480, 360, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 20, effects: EFFECTS.cardShadow });
    card.clipsContent = false;
    card.x = 480;
    card.y = 260;
    frame.appendChild(card);

    card.appendChild(makeText('Title', 'Reset your password', 24, 24, { size: 18, style: 'Bold' }));
    card.appendChild(makeText('Desc', 'We will send a reset link to your PIU email.', 24, 52, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));
    addInput(card, 'Email', 24, 92, { placeholder: 'name@piu.edu.kh', width: 432 });
    const send = addButton(card, 'Send reset link', 24, 196, { variant: 'primary', width: 432 });
    const back = addLinkText(card, 'Back to Login', 24, 252, { colorHex: COLORS.primary });

    hotspots['auth.forgot.send'] = send;
    hotspots['auth.forgot.back'] = back;

    return frame;
  }

  frames.authLogin = buildAuthLogin();
  frames.authSignup = buildAuthSignup();
  frames.authForgot = buildAuthForgot();
  placeInSection(sections.auth, 0, 2, frames.authLogin);
  placeInSection(sections.auth, 1, 2, frames.authSignup);
  placeInSection(sections.auth, 2, 2, frames.authForgot);

  // ---- Instructor screens
  function buildInstructorDashboard() {
    const frame = makeFrame('Instructor / Dashboard', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;

    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Dashboard');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Dashboard', 'Overview of rooms, reviewers, teams, and pending work');

    // Overview cards
    const cardW = 270;
    const cardH = 120;
    const baseX = 24;
    const baseY = 24;
    addCard(content, 'Total Rooms', baseX, baseY, cardW, cardH, { subtitle: '12 rooms' });
    addCard(content, 'Active Judges', baseX + (cardW + 18), baseY, cardW, cardH, { subtitle: '18 reviewers' });
    addCard(content, 'Student Teams', baseX + (cardW + 18) * 2, baseY, cardW, cardH, { subtitle: '24 teams' });
    addCard(content, 'Pending Documents', baseX + (cardW + 18) * 3, baseY, cardW, cardH, { subtitle: '5 pending', badge: { label: 'Pending', colorHex: COLORS.orange } });

    // Quick actions
    const actions = makeFrame('Quick Actions', 1110, 76, { fillHex: null, radius: 0 });
    actions.fills = [];
    actions.strokes = [];
    actions.x = 24;
    actions.y = 170;
    content.appendChild(actions);
    const createRoom = addButton(actions, 'Create Room', 0, 0, { variant: 'primary', width: 180 });
    const viewRooms = addButton(actions, 'View Rooms', 196, 0, { variant: 'outline', width: 160 });
    const uploadRubric = addButton(actions, 'Upload Rubric', 372, 0, { variant: 'outline', width: 170 });

    hotspots['instructor.dashboard.createRoom'] = createRoom;
    hotspots['instructor.dashboard.viewRooms'] = viewRooms;
    hotspots['instructor.dashboard.uploadRubric'] = uploadRubric;

    // KPI cards as big clickable tiles
    const pending = addCard(content, 'Pending Scores', 24, 270, 520, 150, { subtitle: '2 teams awaiting score release', badge: { label: 'Review', colorHex: COLORS.orange } });
    const upcoming = addCard(content, 'Upcoming Defenses', 568, 270, 520, 150, { subtitle: '3 defenses scheduled this week', badge: { label: 'Soon', colorHex: COLORS.primary } });
    hotspots['instructor.dashboard.pendingScores'] = pending;
    hotspots['instructor.dashboard.upcomingDefenses'] = upcoming;

    // Recent activity table (simple)
    const table = makeFrame('Recent Activity', 1064, 350, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 450;
    content.appendChild(table);
    table.appendChild(makeText('Title', 'Recent Activity', 18, 16, { size: 14, style: 'Semi Bold' }));
    table.appendChild(makeText('Sub', 'Latest actions across rooms', 18, 38, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    const rows = [
      'Rubric uploaded — FYP Defense AY2025–2026',
      'Team created — Scormetry 2.0 Team',
      'Review submitted — Judge: Ms. Dara Lim',
      'Result released — Team Beta',
    ];
    let y = 78;
    for (const r of rows) {
      const row = makeFrame(`Row`, 1028, 52, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
      row.fills = [];
      row.effects = [];
      row.x = 18;
      row.y = y;
      row.clipsContent = false;
      table.appendChild(row);
      row.appendChild(makeText('Text', r, 14, 16, { size: 12, style: 'Regular', colorHex: COLORS.text }));
      y += 58;
    }

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildInstructorRooms() {
    const frame = makeFrame('Instructor / Rooms', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Rooms', 'Subjects / team rooms');

    const createRoom = addButton(content, 'Create Room', 24, 24, { variant: 'primary', width: 180 });
    hotspots['instructor.rooms.createRoom'] = createRoom;

    // Search field (visual)
    addInput(content, 'Search', 220, 16, { placeholder: 'Search rooms…', width: 460 });

    // Room cards
    const room1 = addCard(content, 'FYP Defense AY2025–2026', 24, 110, 520, 170, { subtitle: 'Teams: 6 • Judges: 12 • Status: Active', badge: { label: 'Active', colorHex: COLORS.green } });
    const open1 = addButton(room1, 'Open', 18, 110, { variant: 'primary', width: 120 });
    hotspots['instructor.rooms.openRoom'] = open1;

    const room2 = addCard(content, 'Capstone Demo Session', 568, 110, 520, 170, { subtitle: 'Teams: 2 • Judges: 4 • Status: Draft', badge: { label: 'Draft', colorHex: COLORS.orange } });
    addButton(room2, 'Open', 18, 110, { variant: 'outline', width: 120 });

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildInstructorCreateRoom() {
    const frame = makeFrame('Instructor / Create Room', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Create Room', 'Create or edit a subject room for evaluation');

    addInput(content, 'Room name', 24, 24, { placeholder: 'FYP Defense AY2025–2026', width: 620 });
    addInput(content, 'Description', 24, 110, { placeholder: 'Final Year Project Defense Rubric + scoring workflow', width: 620 });
    addInput(content, 'Require approval', 24, 196, { placeholder: 'Yes (default)', width: 300 });

    const save = addButton(content, 'Save Room', 24, 300, { variant: 'primary', width: 170 });
    const cancel = addButton(content, 'Cancel', 210, 300, { variant: 'outline', width: 120 });

    hotspots['instructor.createRoom.save'] = save;
    hotspots['instructor.createRoom.cancel'] = cancel;

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildRoomDetailOverview() {
    const frame = makeFrame('Instructor / Room Detail (Overview)', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'FYP Defense AY2025–2026', 'Room summary and progress');

    // Tabs
    const tabBar = makeFrame('Tabs', 980, 44, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 14, effects: EFFECTS.softShadow });
    tabBar.clipsContent = false;
    tabBar.x = 24;
    tabBar.y = 24;
    content.appendChild(tabBar);

    const tabs = ['Overview', 'Members', 'Teams', 'Papers/Documents', 'Rubric', 'Defense Schedule', 'Scores'];
    const tabHotspots = {};
    let x = 10;
    for (const t of tabs) {
      const pill = makeFrame(`Tab / ${t}`, 10, 32, { fillHex: t === 'Overview' ? '#EEF2FF' : null, strokeHex: t === 'Overview' ? '#C7D2FE' : null, radius: 999 });
      pill.fills = t === 'Overview' ? [solid('#EEF2FF')] : [];
      pill.strokes = t === 'Overview' ? [solid('#C7D2FE')] : [];
      pill.effects = [];
      pill.clipsContent = false;
      tabBar.appendChild(pill);
      const tt = makeText('Label', t, 0, 0, { size: 12, style: t === 'Overview' ? 'Semi Bold' : 'Medium', colorHex: COLORS.text });
      pill.appendChild(tt);
      pill.resize(tt.width + 20, 32);
      pill.x = x;
      pill.y = 6;
      tt.x = 10;
      tt.y = 8;
      tabHotspots[t] = pill;
      x += pill.width + 8;
    }

    // Actions
    const invite = addButton(content, 'Invite Members', 1020, 24, { variant: 'primary', width: 200 });
    const assign = addButton(content, 'Assign Judges', 1020, 74, { variant: 'outline', width: 200 });
    const uploadRubric = addButton(content, 'Upload Rubric', 1020, 124, { variant: 'outline', width: 200 });
    const setSchedule = addButton(content, 'Set Schedule', 1020, 174, { variant: 'outline', width: 200 });
    const createTeam = addButton(content, 'Create Team', 1020, 224, { variant: 'outline', width: 200 });

    hotspots['roomDetail.tabs'] = tabHotspots;
    hotspots['roomDetail.invite'] = invite;
    hotspots['roomDetail.assignJudges'] = assign;
    hotspots['roomDetail.uploadRubric'] = uploadRubric;
    hotspots['roomDetail.setSchedule'] = setSchedule;
    hotspots['roomDetail.createTeam'] = createTeam;

    // Summary cards
    addCard(content, 'Student Members', 24, 90, 360, 140, { subtitle: '12 students • 2 pending approval', badge: { label: 'Pending', colorHex: COLORS.orange } });
    addCard(content, 'Teams', 402, 90, 360, 140, { subtitle: '6 teams created', badge: { label: 'OK', colorHex: COLORS.green } });
    addCard(content, 'Rubric', 780, 90, 360, 140, { subtitle: 'Uploaded • Not locked', badge: { label: 'Action', colorHex: COLORS.orange } });

    // Judge submission status
    const table = makeFrame('Judge Submissions', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 260;
    content.appendChild(table);
    table.appendChild(makeText('Title', 'Judge submission status', 18, 16, { size: 14, style: 'Semi Bold' }));
    table.appendChild(makeText('Sub', 'Track completion per team and judge', 18, 38, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    const headers = ['Team', 'Judges', 'Submitted', 'Status'];
    let hx = 18;
    for (const h of headers) {
      const ht = makeText('H', h, hx, 76, { size: 11, style: 'Semi Bold', colorHex: COLORS.mutedText });
      table.appendChild(ht);
      hx += 240;
    }

    const row = makeFrame('Row', 1080, 56, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
    row.fills = [];
    row.effects = [];
    row.x = 18;
    row.y = 102;
    row.clipsContent = false;
    table.appendChild(row);
    row.appendChild(makeText('Team', 'Scormetry 2.0 Team', 14, 18, { size: 12, style: 'Medium' }));
    row.appendChild(makeText('Judges', '3', 254, 18, { size: 12, style: 'Medium' }));
    row.appendChild(makeText('Submitted', '1/3', 494, 18, { size: 12, style: 'Medium' }));
    addBadge(row, 'In progress', 720, 12, { colorHex: COLORS.orange });

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildMembers(baseName, state) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Members', 'Invite and approve room members');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const invite = addButton(content, 'Invite/Add User', 200, 24, { variant: 'primary', width: 190 });
    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.invite`] = invite;

    const table = makeFrame('Members Table', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 90;
    content.appendChild(table);
    table.appendChild(makeText('Title', `Room Members`, 18, 16, { size: 14, style: 'Semi Bold' }));

    const row = makeFrame('Member Row', 1080, 72, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
    row.fills = [];
    row.effects = [];
    row.x = 18;
    row.y = 60;
    row.clipsContent = false;
    table.appendChild(row);
    row.appendChild(makeText('Name', 'Sreynith Phan', 14, 18, { size: 12, style: 'Semi Bold' }));
    row.appendChild(makeText('Email', 'sreynith.phan@piu.edu.kh', 14, 40, { size: 11, style: 'Regular', colorHex: COLORS.mutedText }));

    const statusLabel = state === 'approved' ? 'Approved' : state === 'rejected' ? 'Rejected' : 'Pending';
    const statusColor = state === 'approved' ? COLORS.green : state === 'rejected' ? COLORS.red : COLORS.orange;
    addBadge(row, statusLabel, 700, 20, { colorHex: statusColor, bgOpacity: 0.14 });

    const approve = addButton(row, 'Approve', 860, 16, { variant: 'primary', width: 100 });
    const reject = addButton(row, 'Reject', 970, 16, { variant: 'outline', width: 90 });
    hotspots[`${baseName}.approve`] = approve;
    hotspots[`${baseName}.reject`] = reject;

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildTeams(baseName, withAdded) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Teams', 'Create teams and assign judges');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const create = addButton(content, 'Create Team', 200, 24, { variant: 'primary', width: 160 });
    const assign = addButton(content, 'Assign Judges', 376, 24, { variant: 'outline', width: 170 });
    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.createTeam`] = create;
    hotspots[`${baseName}.assignJudges`] = assign;

    const card = addCard(content, 'Scormetry 2.0 Team', 24, 90, 520, 180, {
      subtitle: 'Members: 4 • Judges: 3 • Status: Active',
      badge: { label: 'Ready', colorHex: COLORS.green },
    });
    const manage = addButton(card, 'Manage', 18, 118, { variant: 'primary', width: 120 });
    hotspots[`${baseName}.teamManage`] = manage;

    if (withAdded) {
      const toast = makeFrame('Toast', 520, 56, { fillHex: '#ECFDF5', strokeHex: '#BBF7D0', radius: 14, effects: EFFECTS.softShadow });
      toast.effects = EFFECTS.softShadow;
      toast.clipsContent = false;
      toast.x = 24;
      toast.y = 290;
      content.appendChild(toast);
      toast.appendChild(makeText('ToastText', 'Team created successfully.', 18, 18, { size: 12, style: 'Medium', colorHex: '#065F46' }));
    }

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildTeamDetail() {
    const frame = makeFrame('Instructor / Team Detail', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Team: Scormetry 2.0 Team', 'Members, documents, and judge progress');

    const back = addButton(content, 'Back', 24, 24, { variant: 'outline', width: 120 });
    hotspots['instructor.teamDetail.back'] = back;

    addCard(content, 'Members', 24, 90, 520, 170, { subtitle: 'Sreynith • Vannak • Dara • Sokun' });
    addCard(content, 'Documents', 568, 90, 520, 170, { subtitle: 'Final Report.pdf • Slides.pptx', badge: { label: 'Submitted', colorHex: COLORS.green } });
    addCard(content, 'Judges', 24, 280, 520, 170, { subtitle: 'Ms. Dara Lim • Mr. Sovann • Dr. Chenda' });

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildDefenseSchedule(baseName, scheduled) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Defense Schedule', 'Plan dates, times, and rooms');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const edit = addButton(content, 'Edit Schedule', 200, 24, { variant: 'primary', width: 170 });
    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.edit`] = edit;

    const table = makeFrame('Schedule Table', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 90;
    content.appendChild(table);
    table.appendChild(makeText('Title', scheduled ? 'Scheduled defenses' : 'No schedule set yet', 18, 16, { size: 14, style: 'Semi Bold' }));
    table.appendChild(makeText('Sub', scheduled ? 'AY2025–2026 defense week' : 'Click “Edit Schedule” to create a schedule.', 18, 38, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    if (scheduled) {
      const row = makeFrame('Row', 1080, 72, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
      row.fills = [];
      row.effects = [];
      row.x = 18;
      row.y = 70;
      row.clipsContent = false;
      table.appendChild(row);
      row.appendChild(makeText('Team', 'Scormetry 2.0 Team', 14, 18, { size: 12, style: 'Semi Bold' }));
      row.appendChild(makeText('Time', 'Tue 10:30 AM', 360, 18, { size: 12, style: 'Medium' }));
      row.appendChild(makeText('Room', 'Room A-203', 560, 18, { size: 12, style: 'Medium' }));
      addBadge(row, 'Confirmed', 820, 20, { colorHex: COLORS.green, bgOpacity: 0.14 });
    }

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildPapersReview() {
    const frame = makeFrame('Instructor / Papers Review', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Papers/Documents', 'Review team submissions');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    hotspots['instructor.papers.back'] = back;

    const table = makeFrame('Documents Table', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 90;
    content.appendChild(table);
    table.appendChild(makeText('Title', 'Team Submissions', 18, 16, { size: 14, style: 'Semi Bold' }));

    const row = makeFrame('Row', 1080, 72, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
    row.fills = [];
    row.effects = [];
    row.x = 18;
    row.y = 60;
    row.clipsContent = false;
    table.appendChild(row);
    row.appendChild(makeText('Team', 'Scormetry 2.0 Team', 14, 18, { size: 12, style: 'Semi Bold' }));
    row.appendChild(makeText('Docs', 'Final Report.pdf • Slides.pptx', 14, 40, { size: 11, style: 'Regular', colorHex: COLORS.mutedText }));
    const open = addButton(row, 'Open Document', 760, 16, { variant: 'primary', width: 140 });
    const viewTeam = addButton(row, 'View Team', 910, 16, { variant: 'outline', width: 120 });
    hotspots['instructor.papers.openDoc'] = open;
    hotspots['instructor.papers.viewTeam'] = viewTeam;

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildDocumentPreview() {
    const frame = makeFrame('Document Preview', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const content = makeFrame('Preview Surface', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    content.x = 0;
    content.y = 0;
    content.fills = [solid(COLORS.background)];
    content.strokes = [solid(COLORS.border)];
    frame.appendChild(content);

    content.appendChild(makeText('Title', 'Document Preview', 24, 24, { size: 16, style: 'Semi Bold' }));
    content.appendChild(makeText('Sub', 'Final Report.pdf (preview placeholder)', 24, 48, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    const preview = makeFrame('PDF Placeholder', 1200, 760, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    preview.clipsContent = false;
    preview.x = 120;
    preview.y = 96;
    content.appendChild(preview);
    preview.appendChild(makeText('Hint', 'PDF pages would render here in the live app.', 24, 24, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    const back = addButton(content, 'Back', 24, 820, { variant: 'outline', width: 120 });
    hotspots['docPreview.back'] = back;

    return frame;
  }

  function buildRubricUpload(baseName, uploaded) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Rubric', 'Upload official rubric PDF and convert to dynamic form');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const upload = addButton(content, 'Upload PDF', 200, 24, { variant: 'primary', width: 160 });
    const convert = addButton(content, 'Convert with AI', 376, 24, { variant: uploaded ? 'primary' : 'outline', width: 190 });

    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.upload`] = upload;
    hotspots[`${baseName}.convert`] = convert;

    const card = addCard(content, 'Final Year Project Defense Rubric', 24, 90, 860, 220, {
      subtitle: uploaded ? 'PDF uploaded successfully.' : 'No file uploaded yet.',
      badge: uploaded ? { label: 'Uploaded', colorHex: COLORS.green } : { label: 'Missing', colorHex: COLORS.orange },
    });
    card.appendChild(makeText('Hint', 'Preview: criteria table (placeholder)', 18, 86, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));

    const warn = makeFrame('Warning', 860, 84, { fillHex: '#FFF7ED', strokeHex: '#FED7AA', radius: 16 });
    warn.effects = EFFECTS.softShadow;
    warn.clipsContent = false;
    warn.x = 24;
    warn.y = 330;
    content.appendChild(warn);
    warn.appendChild(makeText('Warn', 'Locking the rubric prevents edits during scoring.', 18, 18, { size: 12, style: 'Medium', colorHex: '#9A3412' }));

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildAIConversion(baseName, loading) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'AI Rubric Conversion', 'Convert PDF rubric into digital scoring form');

    const cancel = addButton(content, 'Cancel', 24, 24, { variant: 'outline', width: 120 });
    const regen = addButton(content, 'Regenerate', 156, 24, { variant: 'outline', width: 140 });
    const verify = addButton(content, 'Verify Rubric', 312, 24, { variant: 'primary', width: 160 });
    hotspots[`${baseName}.cancel`] = cancel;
    hotspots[`${baseName}.regen`] = regen;
    hotspots[`${baseName}.verify`] = verify;

    const card = addCard(content, 'Converted Rubric Preview', 24, 90, 1116, 580, {
      subtitle: loading ? 'Converting… please wait' : 'Review criteria, weights, and score ranges',
      badge: loading ? { label: 'Loading', colorHex: COLORS.orange } : { label: 'Preview', colorHex: COLORS.primary },
    });
    const tableY = 90;
    const cols = ['Criteria', 'Range', 'Weight', 'Description'];
    let cx = 18;
    for (const c of cols) {
      card.appendChild(makeText('H', c, cx, tableY, { size: 11, style: 'Semi Bold', colorHex: COLORS.mutedText }));
      cx += 260;
    }
    const rows = [
      ['Problem & Objectives', '0–10', '20%', 'Clarity of goals and scope'],
      ['Methodology', '0–10', '30%', 'Technical soundness and approach'],
      ['Implementation', '0–10', '30%', 'Execution quality and completeness'],
      ['Presentation', '0–10', '20%', 'Communication and Q&A'],
    ];
    let ry = tableY + 26;
    for (const r of rows) {
      const row = makeFrame('Row', 1040, 48, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
      row.fills = [];
      row.effects = [];
      row.x = 18;
      row.y = ry;
      row.clipsContent = false;
      card.appendChild(row);
      row.appendChild(makeText('C', r[0], 14, 16, { size: 11, style: 'Medium' }));
      row.appendChild(makeText('R', r[1], 274, 16, { size: 11, style: 'Medium' }));
      row.appendChild(makeText('W', r[2], 534, 16, { size: 11, style: 'Medium' }));
      row.appendChild(makeText('D', r[3], 694, 16, { size: 11, style: 'Regular', colorHex: COLORS.mutedText }));
      ry += 56;
    }

    if (loading) {
      const loader = makeFrame('Loader', 340, 56, { fillHex: '#EEF2FF', strokeHex: '#C7D2FE', radius: 999, effects: EFFECTS.softShadow });
      loader.effects = EFFECTS.softShadow;
      loader.clipsContent = false;
      loader.x = 420;
      loader.y = 700;
      content.appendChild(loader);
      loader.appendChild(makeText('Txt', 'AI is converting the rubric…', 18, 18, { size: 12, style: 'Medium', colorHex: COLORS.primary }));
    }

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildRubricVerify(baseName, mode) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Rooms');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Rubric Verification', 'Verify criteria then lock for scoring');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const edit = addButton(content, 'Edit Criterion', 200, 24, { variant: 'outline', width: 160 });
    const lock = addButton(content, 'Lock Rubric', 376, 24, { variant: 'warning', width: 150 });
    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.edit`] = edit;
    hotspots[`${baseName}.lock`] = lock;

    const card = addCard(content, 'Rubric Criteria', 24, 90, 1116, 580, {
      subtitle: mode === 'locked' ? 'Rubric is locked. Editing disabled during scoring.' : 'You can review and adjust criteria.',
      badge: mode === 'locked' ? { label: 'Locked', colorHex: COLORS.green } : { label: 'Editable', colorHex: COLORS.primary },
    });

    if (mode === 'edit') {
      const editor = makeFrame('Inline Editor', 520, 180, { fillHex: '#EEF2FF', strokeHex: '#C7D2FE', radius: 18, effects: EFFECTS.softShadow });
      editor.effects = EFFECTS.softShadow;
      editor.clipsContent = false;
      editor.x = 520;
      editor.y = 520;
      content.appendChild(editor);
      editor.appendChild(makeText('T', 'Editing: Methodology', 18, 18, { size: 13, style: 'Semi Bold' }));
      editor.appendChild(makeText('D', 'Update weight/description then save (demo state).', 18, 44, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));
    }

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildScoreReview(baseName, released) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Scores');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Scores', 'Review scores and release results');

    const back = addButton(content, 'Back to Room', 24, 24, { variant: 'outline', width: 160 });
    const release = addButton(content, 'Release Result', 200, 24, { variant: 'primary', width: 170 });
    hotspots[`${baseName}.back`] = back;
    hotspots[`${baseName}.release`] = release;

    const table = makeFrame('Scores Table', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 90;
    content.appendChild(table);
    table.appendChild(makeText('Title', 'Team score review', 18, 16, { size: 14, style: 'Semi Bold' }));
    if (released) {
      table.appendChild(makeText('Notice', 'Results have been released.', 18, 38, { size: 12, style: 'Regular', colorHex: COLORS.green }));
    }
    const row = makeFrame('Row', 1080, 72, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
    row.fills = [];
    row.effects = [];
    row.x = 18;
    row.y = 60;
    row.clipsContent = false;
    table.appendChild(row);
    row.appendChild(makeText('Team', 'Scormetry 2.0 Team', 14, 18, { size: 12, style: 'Semi Bold' }));
    row.appendChild(makeText('Avg', released ? '87.4' : '—', 420, 18, { size: 12, style: 'Medium' }));
    addBadge(row, released ? 'Released' : 'Pending', 560, 20, { colorHex: released ? COLORS.green : COLORS.orange, bgOpacity: 0.14 });
    const view = addButton(row, 'View Scores', 860, 16, { variant: 'primary', width: 140 });
    hotspots[`${baseName}.viewScores`] = view;

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildTeamScoreDetail() {
    const frame = makeFrame('Instructor / Team Score Detail', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Scores');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Team Scores: Scormetry 2.0 Team', 'Judge submissions and comments');

    const back = addButton(content, 'Back to Scores', 24, 24, { variant: 'outline', width: 160 });
    const release = addButton(content, 'Release Result', 200, 24, { variant: 'primary', width: 170 });
    const unlock = addButton(content, 'Unlock Score', 386, 24, { variant: 'warning', width: 150 });
    hotspots['instructor.teamScore.back'] = back;
    hotspots['instructor.teamScore.release'] = release;
    hotspots['instructor.teamScore.unlock'] = unlock;

    addCard(content, 'Final score summary', 24, 90, 520, 180, { subtitle: 'Average: 87.4 • Judges: 3' });
    addCard(content, 'Judge comments', 568, 90, 520, 180, { subtitle: '“Strong methodology…”, “Great presentation…”' });

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildSettings() {
    const frame = makeFrame('Settings / Profile', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Instructor / Admin', 'Settings/Profile');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Settings', 'Profile and notifications');

    const card = addCard(content, 'Profile', 24, 24, 520, 220, { subtitle: 'Role: Instructor/Admin' });
    addButton(card, 'Logout', 18, 150, { variant: 'outline', width: 120 });

    addCard(content, 'Notifications', 568, 24, 520, 220, { subtitle: 'Email reminders for defenses and results' });

    hotspots['instructor.sidebar.dashboard'] = navHotspots['Dashboard'];
    hotspots['instructor.sidebar.rooms'] = navHotspots['Rooms'];
    hotspots['instructor.sidebar.scores'] = navHotspots['Scores'];
    hotspots['instructor.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  // Modals (overlay frames)
  function buildModal(name, title, bodyLines) {
    const modal = makeFrame(name, 640, 420, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 20, effects: EFFECTS.cardShadow });
    modal.clipsContent = false;
    modal.appendChild(makeText('Title', title, 24, 24, { size: 16, style: 'Bold' }));
    modal.appendChild(makeText('Body', bodyLines.join('\n'), 24, 60, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));
    const close = addButton(modal, 'X', 588, 18, { variant: 'outline', width: 36 });
    hotspots[`${name}.close`] = close;
    return modal;
  }

  frames.instructorDashboard = buildInstructorDashboard();
  frames.instructorRooms = buildInstructorRooms();
  frames.instructorCreateRoom = buildInstructorCreateRoom();
  frames.roomDetailOverview = buildRoomDetailOverview();
  frames.members = buildMembers('Instructor / Members', 'pending');
  frames.membersApproved = buildMembers('Instructor / Members (Approved)', 'approved');
  frames.membersRejected = buildMembers('Instructor / Members (Rejected)', 'rejected');
  frames.membersInviteSuccess = buildMembers('Instructor / Members (Invite Success)', 'pending');
  frames.teams = buildTeams('Instructor / Teams', false);
  frames.teamsAdded = buildTeams('Instructor / Teams (Team Added)', true);
  frames.teamDetail = buildTeamDetail();
  frames.defenseSchedule = buildDefenseSchedule('Instructor / Defense Schedule', false);
  frames.defenseScheduleScheduled = buildDefenseSchedule('Instructor / Defense Schedule (Scheduled)', true);
  frames.papersReview = buildPapersReview();
  frames.docPreview = buildDocumentPreview();
  frames.rubricUpload = buildRubricUpload('Instructor / Rubric Upload', false);
  frames.rubricUploaded = buildRubricUpload('Instructor / Rubric Upload (Uploaded)', true);
  frames.aiConversion = buildAIConversion('Instructor / AI Rubric Conversion', false);
  frames.aiLoading = buildAIConversion('Instructor / AI Rubric Conversion (Loading)', true);
  frames.rubricVerify = buildRubricVerify('Instructor / Rubric Verify & Lock', 'editable');
  frames.rubricLocked = buildRubricVerify('Instructor / Rubric Locked State', 'locked');
  frames.rubricEdit = buildRubricVerify('Instructor / Rubric Editable Criterion State', 'edit');
  frames.scoreReview = buildScoreReview('Instructor / Score Review', false);
  frames.scoreReviewReleased = buildScoreReview('Instructor / Score Review (Released)', true);
  frames.teamScoreDetail = buildTeamScoreDetail();
  frames.settings = buildSettings();

  frames.inviteMemberModal = buildModal('Modal / Invite Member', 'Invite Member', [
    'Invite users by PIU email and assign role:',
    '- Student',
    '- Judge',
    '- Teacher',
  ]);
  const inviteSend = addButton(frames.inviteMemberModal, 'Send Invite', 24, 330, { variant: 'primary', width: 160 });
  const inviteCancel = addButton(frames.inviteMemberModal, 'Cancel', 196, 330, { variant: 'outline', width: 120 });
  hotspots['modal.invite.send'] = inviteSend;
  hotspots['modal.invite.cancel'] = inviteCancel;

  frames.createTeamModal = buildModal('Modal / Create Team', 'Create Team', [
    'Create a new student team and optionally set initial members.',
  ]);
  hotspots['modal.team.save'] = addButton(frames.createTeamModal, 'Save Team', 24, 330, { variant: 'primary', width: 160 });
  hotspots['modal.team.cancel'] = addButton(frames.createTeamModal, 'Cancel', 196, 330, { variant: 'outline', width: 120 });

  frames.assignJudgesModal = buildModal('Modal / Assign Judges', 'Assign Judges', [
    'Assign judges/reviewers to a team. (Demo state)',
  ]);
  hotspots['modal.assign.save'] = addButton(frames.assignJudgesModal, 'Save Assignment', 24, 330, { variant: 'primary', width: 190 });
  hotspots['modal.assign.cancel'] = addButton(frames.assignJudgesModal, 'Cancel', 230, 330, { variant: 'outline', width: 120 });

  frames.editScheduleModal = buildModal('Modal / Edit Schedule', 'Edit Schedule', [
    'Set date/time/room for team defenses.',
  ]);
  hotspots['modal.schedule.save'] = addButton(frames.editScheduleModal, 'Save Schedule', 24, 330, { variant: 'primary', width: 170 });
  hotspots['modal.schedule.cancel'] = addButton(frames.editScheduleModal, 'Cancel', 206, 330, { variant: 'outline', width: 120 });

  frames.submitConfirmModal = buildModal('Modal / Submit Confirmation', 'Submit Final Score?', [
    'This will lock your score submission.',
  ]);
  hotspots['modal.submit.confirm'] = addButton(frames.submitConfirmModal, 'Confirm Submit', 24, 330, { variant: 'primary', width: 170 });
  hotspots['modal.submit.cancel'] = addButton(frames.submitConfirmModal, 'Cancel', 206, 330, { variant: 'outline', width: 120 });

  frames.releaseScoresModal = buildModal('Modal / Release Scores', 'Release Results', [
    'Release results to students. This action is typically final.',
  ]);
  hotspots['modal.release.confirm'] = addButton(frames.releaseScoresModal, 'Confirm Release', 24, 330, { variant: 'primary', width: 180 });
  hotspots['modal.release.cancel'] = addButton(frames.releaseScoresModal, 'Cancel', 220, 330, { variant: 'outline', width: 120 });

  frames.unlockScoreModal = buildModal('Modal / Unlock Score', 'Unlock Score', [
    'Unlock a submitted score for corrections (admin/instructor action).',
  ]);
  hotspots['modal.unlock.confirm'] = addButton(frames.unlockScoreModal, 'Confirm Unlock', 24, 330, { variant: 'warning', width: 180 });
  hotspots['modal.unlock.cancel'] = addButton(frames.unlockScoreModal, 'Cancel', 220, 330, { variant: 'outline', width: 120 });

  // Place instructor frames
  const instructorFrames = [
    frames.instructorDashboard,
    frames.instructorRooms,
    frames.instructorCreateRoom,
    frames.roomDetailOverview,
    frames.members,
    frames.membersApproved,
    frames.membersRejected,
    frames.membersInviteSuccess,
    frames.teams,
    frames.teamsAdded,
    frames.teamDetail,
    frames.defenseSchedule,
    frames.defenseScheduleScheduled,
    frames.papersReview,
    frames.rubricUpload,
    frames.rubricUploaded,
    frames.aiConversion,
    frames.aiLoading,
    frames.rubricVerify,
    frames.rubricLocked,
    frames.rubricEdit,
    frames.scoreReview,
    frames.scoreReviewReleased,
    frames.teamScoreDetail,
    frames.settings,
    frames.docPreview,
    frames.inviteMemberModal,
    frames.createTeamModal,
    frames.assignJudgesModal,
    frames.editScheduleModal,
    frames.releaseScoresModal,
    frames.unlockScoreModal,
  ];
  for (let i = 0; i < instructorFrames.length; i += 1) {
    placeInSection(sections.instructor, i, 3, instructorFrames[i]);
  }

  // ---- Judge screens
  function buildJudgeDashboard() {
    const frame = makeFrame('Judge / Assigned Teams', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Judge / Reviewer', 'Assigned Teams');
    const content = addContentSurface(frame);
    addTopbar(frame, 'My Assigned Teams', 'View papers and score using the dynamic rubric');

    const team = addCard(content, 'Scormetry 2.0 Team', 24, 24, 520, 180, { subtitle: 'Rubric: FYP Defense Rubric', badge: { label: 'To score', colorHex: COLORS.orange } });
    const view = addButton(team, 'View Paper & Rubric', 18, 118, { variant: 'outline', width: 200 });
    const start = addButton(team, 'Start Scoring', 228, 118, { variant: 'primary', width: 160 });
    hotspots['judge.dashboard.view'] = view;
    hotspots['judge.dashboard.start'] = start;

    hotspots['judge.sidebar.assigned'] = navHotspots['Assigned Teams'];
    hotspots['judge.sidebar.submitted'] = navHotspots['Submitted Scores'];

    return frame;
  }

  function buildJudgePaperPreview() {
    const frame = makeFrame('Judge / Paper & Rubric Preview', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Judge / Reviewer', 'Assigned Teams');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Team: Scormetry 2.0 Team', 'Paper + rubric preview');

    const back = addButton(content, 'Back', 24, 24, { variant: 'outline', width: 120 });
    const open = addButton(content, 'Open Paper', 156, 24, { variant: 'outline', width: 140 });
    const start = addButton(content, 'Start Scoring', 312, 24, { variant: 'primary', width: 160 });
    hotspots['judge.preview.back'] = back;
    hotspots['judge.preview.open'] = open;
    hotspots['judge.preview.start'] = start;

    addCard(content, 'Rubric', 24, 90, 520, 220, { subtitle: 'Converted rubric criteria (preview)' });
    addCard(content, 'Paper', 568, 90, 520, 220, { subtitle: 'Final Report.pdf (preview)' });

    hotspots['judge.sidebar.assigned'] = navHotspots['Assigned Teams'];
    hotspots['judge.sidebar.submitted'] = navHotspots['Submitted Scores'];

    return frame;
  }

  function buildJudgeScoreEntry(baseName, state) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Judge / Reviewer', 'Assigned Teams');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Score Entry', 'Digital rubric scoring form (demo)');

    const cancel = addButton(content, 'Cancel', 24, 24, { variant: 'outline', width: 120 });
    const draft = addButton(content, 'Save Draft', 156, 24, { variant: 'outline', width: 140 });
    const submit = addButton(content, 'Submit Final Score', 312, 24, { variant: 'primary', width: 200 });
    hotspots[`${baseName}.cancel`] = cancel;
    hotspots[`${baseName}.draft`] = draft;
    hotspots[`${baseName}.submit`] = submit;

    const form = addCard(content, 'Rubric Form', 24, 90, 1116, 650, { subtitle: 'Criteria rows with scores + comments' });
    const rows = [
      'Problem & Objectives',
      'Methodology',
      'Implementation',
      'Presentation',
    ];
    let y = 90;
    for (const r of rows) {
      const row = makeFrame('Criterion', 1040, 110, { fillHex: null, strokeHex: COLORS.border, radius: 14 });
      row.fills = [];
      row.effects = [];
      row.x = 18;
      row.y = y;
      row.clipsContent = false;
      form.appendChild(row);
      row.appendChild(makeText('Name', r, 14, 16, { size: 12, style: 'Semi Bold' }));
      row.appendChild(makeText('Hint', 'Score: [dropdown]  Comment: [text]', 14, 42, { size: 11, style: 'Regular', colorHex: COLORS.mutedText }));
      y += 122;
    }

    const total = makeFrame('Total', 520, 72, { fillHex: '#EEF2FF', strokeHex: '#C7D2FE', radius: 16 });
    total.effects = EFFECTS.softShadow;
    total.clipsContent = false;
    total.x = 24;
    total.y = 760;
    content.appendChild(total);
    total.appendChild(makeText('T', 'Auto total score: 87.0', 18, 16, { size: 12, style: 'Semi Bold', colorHex: COLORS.primary }));
    total.appendChild(makeText('S', 'Average updates as you score', 18, 38, { size: 11, style: 'Regular', colorHex: COLORS.mutedText }));

    if (state === 'draft') {
      const toast = makeFrame('Toast', 360, 56, { fillHex: '#ECFDF5', strokeHex: '#BBF7D0', radius: 14, effects: EFFECTS.softShadow });
      toast.effects = EFFECTS.softShadow;
      toast.clipsContent = false;
      toast.x = 580;
      toast.y = 760;
      content.appendChild(toast);
      toast.appendChild(makeText('Txt', 'Draft saved.', 18, 18, { size: 12, style: 'Medium', colorHex: '#065F46' }));
    }

    hotspots['judge.sidebar.assigned'] = navHotspots['Assigned Teams'];
    hotspots['judge.sidebar.submitted'] = navHotspots['Submitted Scores'];

    return frame;
  }

  function buildJudgeLockedScore() {
    const frame = makeFrame('Judge / Locked Score State', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Judge / Reviewer', 'Submitted Scores');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Score Submitted', 'Your score is locked');

    const back = addButton(content, 'Back to Assigned Teams', 24, 24, { variant: 'primary', width: 220 });
    hotspots['judge.locked.back'] = back;

    addCard(content, 'Submission', 24, 90, 520, 180, { subtitle: 'Scormetry 2.0 Team • Submitted • Avg: 87.0', badge: { label: 'Locked', colorHex: COLORS.green } });

    hotspots['judge.sidebar.assigned'] = navHotspots['Assigned Teams'];
    hotspots['judge.sidebar.submitted'] = navHotspots['Submitted Scores'];

    return frame;
  }

  function buildJudgeSubmittedScores() {
    const frame = makeFrame('Judge / Submitted Scores', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Judge / Reviewer', 'Submitted Scores');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Submitted Scores', 'Your completed reviews');

    addCard(content, 'Scormetry 2.0 Team', 24, 24, 520, 180, { subtitle: 'Submitted • Locked', badge: { label: 'Done', colorHex: COLORS.green } });

    hotspots['judge.sidebar.assigned'] = navHotspots['Assigned Teams'];
    hotspots['judge.sidebar.submitted'] = navHotspots['Submitted Scores'];
    return frame;
  }

  frames.judgeDashboard = buildJudgeDashboard();
  frames.judgePreview = buildJudgePaperPreview();
  frames.judgeScore = buildJudgeScoreEntry('Judge / Score Entry', 'normal');
  frames.judgeDraftSaved = buildJudgeScoreEntry('Judge / Draft Saved State', 'draft');
  frames.judgeLockedScore = buildJudgeLockedScore();
  frames.judgeSubmitted = buildJudgeSubmittedScores();

  const judgeFrames = [
    frames.judgeDashboard,
    frames.judgePreview,
    frames.judgeScore,
    frames.judgeDraftSaved,
    frames.judgeLockedScore,
    frames.judgeSubmitted,
    frames.submitConfirmModal,
  ];
  for (let i = 0; i < judgeFrames.length; i += 1) {
    placeInSection(sections.judge, i, 2, judgeFrames[i]);
  }

  // ---- Student screens
  function buildStudentDashboard() {
    const frame = makeFrame('Student / Dashboard', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Student', 'Dashboard');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Student Dashboard', 'Your team room, uploads, schedule, and results');

    const myRoom = addCard(content, 'My Team Room', 24, 24, 520, 180, { subtitle: 'FYP Defense AY2025–2026', badge: { label: 'Active', colorHex: COLORS.green } });
    const openRoom = addButton(myRoom, 'Open', 18, 118, { variant: 'primary', width: 120 });
    hotspots['student.dashboard.openRoom'] = openRoom;

    const upload = addCard(content, 'Upload Documents', 568, 24, 520, 180, { subtitle: 'Final report + slides', badge: { label: 'Pending', colorHex: COLORS.orange } });
    const goUpload = addButton(upload, 'Upload', 18, 118, { variant: 'primary', width: 120 });
    hotspots['student.dashboard.upload'] = goUpload;

    const schedule = addCard(content, 'View Schedule', 24, 230, 520, 160, { subtitle: 'Defense timetable', badge: { label: 'TBD', colorHex: COLORS.orange } });
    const goSchedule = addButton(schedule, 'View', 18, 98, { variant: 'outline', width: 120 });
    hotspots['student.dashboard.schedule'] = goSchedule;

    const result = addCard(content, 'View Result', 568, 230, 520, 160, { subtitle: 'Result available after release', badge: { label: 'Pending', colorHex: COLORS.orange } });
    const goResult = addButton(result, 'View', 18, 98, { variant: 'outline', width: 120 });
    hotspots['student.dashboard.result'] = goResult;

    hotspots['student.sidebar.upload'] = navHotspots['Upload Documents'];
    hotspots['student.sidebar.schedule'] = navHotspots['Schedule'];
    hotspots['student.sidebar.results'] = navHotspots['Results'];
    hotspots['student.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildStudentTeamRoom() {
    const frame = makeFrame('Student / Team Room', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Student', 'Dashboard');
    const content = addContentSurface(frame);
    addTopbar(frame, 'My Team Room', 'Team: Scormetry 2.0 Team');

    const upload = addButton(content, 'Upload Documents', 24, 24, { variant: 'primary', width: 190 });
    const schedule = addButton(content, 'View Schedule', 230, 24, { variant: 'outline', width: 160 });
    const result = addButton(content, 'View Result', 406, 24, { variant: 'outline', width: 140 });
    hotspots['student.room.upload'] = upload;
    hotspots['student.room.schedule'] = schedule;
    hotspots['student.room.result'] = result;

    addCard(content, 'Documents', 24, 90, 520, 180, { subtitle: 'Final Report.pdf • Slides.pptx', badge: { label: 'Missing', colorHex: COLORS.orange } });
    addCard(content, 'Rubric', 568, 90, 520, 180, { subtitle: 'Locked for scoring (once locked)' });

    hotspots['student.sidebar.upload'] = navHotspots['Upload Documents'];
    hotspots['student.sidebar.schedule'] = navHotspots['Schedule'];
    hotspots['student.sidebar.results'] = navHotspots['Results'];
    hotspots['student.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildStudentUploads(baseName, state) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Student', 'Upload Documents');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Upload Documents', 'Submit your final report and presentation slides');

    const up1 = addButton(content, 'Upload Final Report', 24, 24, { variant: 'primary', width: 220 });
    const up2 = addButton(content, 'Upload Slides', 260, 24, { variant: 'primary', width: 160 });
    const replace = addButton(content, 'Replace File', 436, 24, { variant: 'outline', width: 150 });
    const back = addButton(content, 'Back to Dashboard', 602, 24, { variant: 'outline', width: 190 });
    hotspots[`${baseName}.uploadReport`] = up1;
    hotspots[`${baseName}.uploadSlides`] = up2;
    hotspots[`${baseName}.replace`] = replace;
    hotspots[`${baseName}.back`] = back;

    const table = makeFrame('Docs Table', 1116, 520, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 16, effects: EFFECTS.cardShadow });
    table.clipsContent = false;
    table.x = 24;
    table.y = 90;
    content.appendChild(table);
    table.appendChild(makeText('Title', 'My Documents', 18, 16, { size: 14, style: 'Semi Bold' }));

    const row = makeFrame('Row', 1080, 72, { fillHex: null, strokeHex: COLORS.border, radius: 12 });
    row.fills = [];
    row.effects = [];
    row.x = 18;
    row.y = 60;
    row.clipsContent = false;
    table.appendChild(row);
    row.appendChild(makeText('Doc', 'Final Report.pdf', 14, 18, { size: 12, style: 'Semi Bold' }));
    const status = state === 'success' ? 'Submitted' : state === 'replace' ? 'Replaced' : 'Missing';
    const statusColor = state === 'success' ? COLORS.green : state === 'replace' ? COLORS.orange : COLORS.orange;
    addBadge(row, status, 820, 20, { colorHex: statusColor, bgOpacity: 0.14 });

    if (state === 'success') {
      const toast = makeFrame('Toast', 360, 56, { fillHex: '#ECFDF5', strokeHex: '#BBF7D0', radius: 14, effects: EFFECTS.softShadow });
      toast.effects = EFFECTS.softShadow;
      toast.clipsContent = false;
      toast.x = 24;
      toast.y = 630;
      content.appendChild(toast);
      toast.appendChild(makeText('Txt', 'Upload successful.', 18, 18, { size: 12, style: 'Medium', colorHex: '#065F46' }));
    }

    hotspots['student.sidebar.upload'] = navHotspots['Upload Documents'];
    hotspots['student.sidebar.schedule'] = navHotspots['Schedule'];
    hotspots['student.sidebar.results'] = navHotspots['Results'];
    hotspots['student.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildStudentSchedule() {
    const frame = makeFrame('Student / Schedule', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Student', 'Schedule');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Schedule', 'Your defense schedule');

    const back = addButton(content, 'Back to Dashboard', 24, 24, { variant: 'outline', width: 190 });
    hotspots['student.schedule.back'] = back;
    addCard(content, 'Defense Slot', 24, 90, 520, 180, { subtitle: 'Tue 10:30 AM • Room A-203', badge: { label: 'Scheduled', colorHex: COLORS.green } });

    hotspots['student.sidebar.upload'] = navHotspots['Upload Documents'];
    hotspots['student.sidebar.schedule'] = navHotspots['Schedule'];
    hotspots['student.sidebar.results'] = navHotspots['Results'];
    hotspots['student.sidebar.settings'] = navHotspots['Settings/Profile'];
    return frame;
  }

  function buildStudentResult(baseName, pending) {
    const frame = makeFrame(baseName, CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const { navHotspots } = addSidebar(frame, 'Student', 'Results');
    const content = addContentSurface(frame);
    addTopbar(frame, 'Results', pending ? 'Results not released yet' : 'Score breakdown');

    const back = addButton(content, 'Back to Dashboard', 24, 24, { variant: 'outline', width: 190 });
    hotspots[`${baseName}.back`] = back;
    if (pending) {
      addCard(content, 'Result Pending', 24, 90, 520, 180, { subtitle: 'Please check again after instructor releases results.', badge: { label: 'Pending', colorHex: COLORS.orange } });
    } else {
      addCard(content, 'Final Score', 24, 90, 520, 180, { subtitle: 'Average: 87.4', badge: { label: 'Released', colorHex: COLORS.green } });
      addCard(content, 'Judge Comments', 568, 90, 520, 180, { subtitle: '“Great work…”' });
    }

    hotspots['student.sidebar.upload'] = navHotspots['Upload Documents'];
    hotspots['student.sidebar.schedule'] = navHotspots['Schedule'];
    hotspots['student.sidebar.results'] = navHotspots['Results'];
    hotspots['student.sidebar.settings'] = navHotspots['Settings/Profile'];

    return frame;
  }

  function buildStudentNotification() {
    const frame = makeFrame('Student / Notification State', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    frame.clipsContent = false;
    const content = makeFrame('Surface', CANVAS.width, CANVAS.height, { fillHex: COLORS.background, strokeHex: COLORS.border, radius: 24 });
    content.x = 0;
    content.y = 0;
    frame.appendChild(content);

    content.appendChild(makeText('Title', 'Notification', 24, 24, { size: 16, style: 'Semi Bold' }));
    const notif = makeFrame('Notif Card', 720, 220, { fillHex: COLORS.card, strokeHex: COLORS.border, radius: 18, effects: EFFECTS.cardShadow });
    notif.clipsContent = false;
    notif.x = 360;
    notif.y = 260;
    content.appendChild(notif);
    notif.appendChild(makeText('N1', 'New update available', 24, 24, { size: 14, style: 'Semi Bold' }));
    notif.appendChild(makeText('N2', 'Results and schedule updates are ready to view.', 24, 52, { size: 12, style: 'Regular', colorHex: COLORS.mutedText }));
    const viewResult = addButton(notif, 'View Result', 24, 130, { variant: 'primary', width: 140 });
    const viewSched = addButton(notif, 'View Defense Schedule', 180, 130, { variant: 'outline', width: 220 });
    hotspots['student.notif.result'] = viewResult;
    hotspots['student.notif.schedule'] = viewSched;
    return frame;
  }

  frames.studentDashboard = buildStudentDashboard();
  frames.studentTeamRoom = buildStudentTeamRoom();
  frames.studentUploads = buildStudentUploads('Student / Document Upload', 'base');
  frames.studentUploadSuccess = buildStudentUploads('Student / Document Upload (Success)', 'success');
  frames.studentReplace = buildStudentUploads('Student / Document Upload (Replace State)', 'replace');
  frames.studentSchedule = buildStudentSchedule();
  frames.studentResult = buildStudentResult('Student / Result Breakdown', false);
  frames.studentResultPending = buildStudentResult('Student / Result Pending State', true);
  frames.studentNotification = buildStudentNotification();

  const studentFrames = [
    frames.studentDashboard,
    frames.studentTeamRoom,
    frames.studentUploads,
    frames.studentUploadSuccess,
    frames.studentReplace,
    frames.studentSchedule,
    frames.studentResultPending,
    frames.studentResult,
    frames.studentNotification,
  ];
  for (let i = 0; i < studentFrames.length; i += 1) {
    placeInSection(sections.student, i, 2, studentFrames[i]);
  }

  // ---- Wire prototype interactions
  // Auth
  await setNavigate(hotspots['auth.login.logIn'], frames.instructorDashboard);
  await setNavigate(hotspots['auth.login.google'], frames.instructorDashboard);
  await setNavigate(hotspots['auth.login.signup'], frames.authSignup);
  await setNavigate(hotspots['auth.login.forgot'], frames.authForgot);

  await setNavigate(hotspots['auth.signup.create'], frames.authLogin);
  await setNavigate(hotspots['auth.signup.already'], frames.authLogin);
  await setNavigate(hotspots['auth.forgot.back'], frames.authLogin);
  await setNavigate(hotspots['auth.forgot.send'], frames.authLogin);

  // Instructor: dashboard quick actions
  await setNavigate(hotspots['instructor.dashboard.createRoom'], frames.instructorCreateRoom);
  await setNavigate(hotspots['instructor.dashboard.viewRooms'], frames.instructorRooms);
  await setNavigate(hotspots['instructor.dashboard.uploadRubric'], frames.rubricUpload);
  await setNavigate(hotspots['instructor.dashboard.pendingScores'], frames.scoreReview);
  await setNavigate(hotspots['instructor.dashboard.upcomingDefenses'], frames.defenseSchedule);

  // Instructor: sidebar
  await setNavigate(hotspots['instructor.sidebar.dashboard'], frames.instructorDashboard);
  await setNavigate(hotspots['instructor.sidebar.rooms'], frames.instructorRooms);
  await setNavigate(hotspots['instructor.sidebar.scores'], frames.scoreReview);
  await setNavigate(hotspots['instructor.sidebar.settings'], frames.settings);

  // Rooms list
  await setNavigate(hotspots['instructor.rooms.createRoom'], frames.instructorCreateRoom);
  await setNavigate(hotspots['instructor.rooms.openRoom'], frames.roomDetailOverview);

  // Create room
  await setNavigate(hotspots['instructor.createRoom.save'], frames.roomDetailOverview);
  await setNavigate(hotspots['instructor.createRoom.cancel'], frames.instructorRooms);

  // Room detail tabs
  const roomTabs = hotspots['roomDetail.tabs'] || {};
  await setNavigate(roomTabs['Overview'], frames.roomDetailOverview);
  await setNavigate(roomTabs['Members'], frames.members);
  await setNavigate(roomTabs['Teams'], frames.teams);
  await setNavigate(roomTabs['Papers/Documents'], frames.papersReview);
  await setNavigate(roomTabs['Rubric'], frames.rubricUpload);
  await setNavigate(roomTabs['Defense Schedule'], frames.defenseSchedule);
  await setNavigate(roomTabs['Scores'], frames.scoreReview);

  // Room detail actions
  await setNavigate(hotspots['roomDetail.invite'], frames.inviteMemberModal, { overlay: true });
  await setNavigate(hotspots['roomDetail.assignJudges'], frames.assignJudgesModal, { overlay: true });
  await setNavigate(hotspots['roomDetail.uploadRubric'], frames.rubricUpload);
  await setNavigate(hotspots['roomDetail.setSchedule'], frames.defenseSchedule);
  await setNavigate(hotspots['roomDetail.createTeam'], frames.teams);

  // Members
  await setNavigate(hotspots['Instructor / Members.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Members.invite'], frames.inviteMemberModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Members.approve'], frames.membersApproved);
  await setNavigate(hotspots['Instructor / Members.reject'], frames.membersRejected);

  await setNavigate(hotspots['Instructor / Members (Approved).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Members (Approved).invite'], frames.inviteMemberModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Members (Approved).approve'], frames.membersApproved);
  await setNavigate(hotspots['Instructor / Members (Approved).reject'], frames.membersRejected);

  await setNavigate(hotspots['Instructor / Members (Rejected).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Members (Rejected).invite'], frames.inviteMemberModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Members (Rejected).approve'], frames.membersApproved);
  await setNavigate(hotspots['Instructor / Members (Rejected).reject'], frames.membersRejected);

  // Invite modal
  await setNavigate(hotspots['Modal / Invite Member.close'], frames.members);
  await setNavigate(hotspots['modal.invite.send'], frames.membersInviteSuccess);
  await setNavigate(hotspots['modal.invite.cancel'], frames.members);

  // Teams
  await setNavigate(hotspots['Instructor / Teams.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Teams.createTeam'], frames.createTeamModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Teams.assignJudges'], frames.assignJudgesModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Teams.teamManage'], frames.teamDetail);

  await setNavigate(hotspots['Instructor / Teams (Team Added).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Teams (Team Added).createTeam'], frames.createTeamModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Teams (Team Added).assignJudges'], frames.assignJudgesModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Teams (Team Added).teamManage'], frames.teamDetail);

  // Create team modal
  await setNavigate(hotspots['Modal / Create Team.close'], frames.teams);
  await setNavigate(hotspots['modal.team.save'], frames.teamsAdded);
  await setNavigate(hotspots['modal.team.cancel'], frames.teams);

  // Assign judges modal
  await setNavigate(hotspots['Modal / Assign Judges.close'], frames.teams);
  await setNavigate(hotspots['modal.assign.cancel'], frames.teams);
  await setNavigate(hotspots['modal.assign.save'], frames.teams);

  // Team detail
  await setNavigate(hotspots['instructor.teamDetail.back'], frames.teams);

  // Defense schedule
  await setNavigate(hotspots['Instructor / Defense Schedule.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Defense Schedule.edit'], frames.editScheduleModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Defense Schedule (Scheduled).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Defense Schedule (Scheduled).edit'], frames.editScheduleModal, { overlay: true });

  // Edit schedule modal
  await setNavigate(hotspots['Modal / Edit Schedule.close'], frames.defenseSchedule);
  await setNavigate(hotspots['modal.schedule.save'], frames.defenseScheduleScheduled);
  await setNavigate(hotspots['modal.schedule.cancel'], frames.defenseSchedule);

  // Papers review
  await setNavigate(hotspots['instructor.papers.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['instructor.papers.openDoc'], frames.docPreview);
  await setNavigate(hotspots['instructor.papers.viewTeam'], frames.teamDetail);

  // Document preview back
  await setNavigate(hotspots['docPreview.back'], frames.papersReview);

  // Rubric upload
  await setNavigate(hotspots['Instructor / Rubric Upload.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Rubric Upload.upload'], frames.rubricUploaded);
  await setNavigate(hotspots['Instructor / Rubric Upload.convert'], frames.aiConversion);
  await setNavigate(hotspots['Instructor / Rubric Upload (Uploaded).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Rubric Upload (Uploaded).upload'], frames.rubricUploaded);
  await setNavigate(hotspots['Instructor / Rubric Upload (Uploaded).convert'], frames.aiConversion);

  // AI conversion
  await setNavigate(hotspots['Instructor / AI Rubric Conversion.cancel'], frames.rubricUpload);
  await setNavigate(hotspots['Instructor / AI Rubric Conversion.regen'], frames.aiLoading);
  await setNavigate(hotspots['Instructor / AI Rubric Conversion.verify'], frames.rubricVerify);
  await setNavigate(hotspots['Instructor / AI Rubric Conversion (Loading).cancel'], frames.rubricUpload);
  await setNavigate(hotspots['Instructor / AI Rubric Conversion (Loading).regen'], frames.aiLoading);
  await setNavigate(hotspots['Instructor / AI Rubric Conversion (Loading).verify'], frames.rubricVerify);

  // Verify & lock
  await setNavigate(hotspots['Instructor / Rubric Verify & Lock.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Rubric Verify & Lock.edit'], frames.rubricEdit);
  await setNavigate(hotspots['Instructor / Rubric Verify & Lock.lock'], frames.rubricLocked);
  await setNavigate(hotspots['Instructor / Rubric Locked State.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Rubric Locked State.edit'], frames.rubricLocked);
  await setNavigate(hotspots['Instructor / Rubric Locked State.lock'], frames.rubricLocked);
  await setNavigate(hotspots['Instructor / Rubric Editable Criterion State.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Rubric Editable Criterion State.edit'], frames.rubricEdit);
  await setNavigate(hotspots['Instructor / Rubric Editable Criterion State.lock'], frames.rubricLocked);

  // Scores
  await setNavigate(hotspots['Instructor / Score Review.back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Score Review.release'], frames.releaseScoresModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Score Review.viewScores'], frames.teamScoreDetail);

  await setNavigate(hotspots['Instructor / Score Review (Released).back'], frames.roomDetailOverview);
  await setNavigate(hotspots['Instructor / Score Review (Released).release'], frames.releaseScoresModal, { overlay: true });
  await setNavigate(hotspots['Instructor / Score Review (Released).viewScores'], frames.teamScoreDetail);

  await setNavigate(hotspots['instructor.teamScore.back'], frames.scoreReview);
  await setNavigate(hotspots['instructor.teamScore.release'], frames.releaseScoresModal, { overlay: true });
  await setNavigate(hotspots['instructor.teamScore.unlock'], frames.unlockScoreModal, { overlay: true });

  // Release modal
  await setNavigate(hotspots['Modal / Release Scores.close'], frames.scoreReview);
  await setNavigate(hotspots['modal.release.confirm'], frames.scoreReviewReleased);
  await setNavigate(hotspots['modal.release.cancel'], frames.scoreReview);

  // Unlock modal
  await setNavigate(hotspots['Modal / Unlock Score.close'], frames.teamScoreDetail);
  await setNavigate(hotspots['modal.unlock.cancel'], frames.teamScoreDetail);
  await setNavigate(hotspots['modal.unlock.confirm'], frames.teamScoreDetail);

  // Judge
  await setNavigate(hotspots['judge.dashboard.view'], frames.judgePreview);
  await setNavigate(hotspots['judge.dashboard.start'], frames.judgeScore);
  await setNavigate(hotspots['judge.preview.back'], frames.judgeDashboard);
  await setNavigate(hotspots['judge.preview.open'], frames.docPreview);
  await setNavigate(hotspots['judge.preview.start'], frames.judgeScore);
  await setNavigate(hotspots['Judge / Score Entry.cancel'], frames.judgeDashboard);
  await setNavigate(hotspots['Judge / Score Entry.draft'], frames.judgeDraftSaved);
  await setNavigate(hotspots['Judge / Score Entry.submit'], frames.submitConfirmModal, { overlay: true });
  await setNavigate(hotspots['Judge / Draft Saved State.cancel'], frames.judgeDashboard);
  await setNavigate(hotspots['Judge / Draft Saved State.draft'], frames.judgeDraftSaved);
  await setNavigate(hotspots['Judge / Draft Saved State.submit'], frames.submitConfirmModal, { overlay: true });

  await setNavigate(hotspots['Modal / Submit Confirmation.close'], frames.judgeScore);
  await setNavigate(hotspots['modal.submit.confirm'], frames.judgeLockedScore);
  await setNavigate(hotspots['modal.submit.cancel'], frames.judgeScore);

  await setNavigate(hotspots['judge.locked.back'], frames.judgeDashboard);
  await setNavigate(hotspots['judge.sidebar.assigned'], frames.judgeDashboard);
  await setNavigate(hotspots['judge.sidebar.submitted'], frames.judgeSubmitted);

  // Student
  await setNavigate(hotspots['student.dashboard.openRoom'], frames.studentTeamRoom);
  await setNavigate(hotspots['student.dashboard.upload'], frames.studentUploads);
  await setNavigate(hotspots['student.dashboard.schedule'], frames.studentSchedule);
  await setNavigate(hotspots['student.dashboard.result'], frames.studentResultPending);

  await setNavigate(hotspots['student.room.upload'], frames.studentUploads);
  await setNavigate(hotspots['student.room.schedule'], frames.studentSchedule);
  await setNavigate(hotspots['student.room.result'], frames.studentResult);

  await setNavigate(hotspots['Student / Document Upload.uploadReport'], frames.studentUploadSuccess);
  await setNavigate(hotspots['Student / Document Upload.uploadSlides'], frames.studentUploadSuccess);
  await setNavigate(hotspots['Student / Document Upload.replace'], frames.studentReplace);
  await setNavigate(hotspots['Student / Document Upload.back'], frames.studentDashboard);
  await setNavigate(hotspots['Student / Document Upload (Success).back'], frames.studentDashboard);
  await setNavigate(hotspots['Student / Document Upload (Replace State).back'], frames.studentDashboard);

  await setNavigate(hotspots['student.schedule.back'], frames.studentDashboard);
  await setNavigate(hotspots['Student / Result Pending State.back'], frames.studentDashboard);
  await setNavigate(hotspots['Student / Result Breakdown.back'], frames.studentDashboard);

  await setNavigate(hotspots['student.notif.result'], frames.studentResult);
  await setNavigate(hotspots['student.notif.schedule'], frames.studentSchedule);

  // Sidebar shortcuts
  await setNavigate(hotspots['student.sidebar.upload'], frames.studentUploads);
  await setNavigate(hotspots['student.sidebar.schedule'], frames.studentSchedule);
  await setNavigate(hotspots['student.sidebar.results'], frames.studentResultPending);

  // Zoom to the login frame for convenience.
  figma.viewport.scrollAndZoomIntoView([frames.authLogin]);
  figma.notify('Scormetry 2.0 prototype frames + interactions generated.');
  figma.closePlugin();
})();
