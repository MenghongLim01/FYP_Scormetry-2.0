from pathlib import Path
import html
import textwrap


OUT = Path("/Users/mac/Downloads/FYP_Scormtry-2.0/docs/chapter3-diagrams/system-architecture-style")
OUT.mkdir(parents=True, exist_ok=True)


def esc(value: str) -> str:
    return html.escape(value, quote=True)


def tspans(text: str, x: float, y: float, width: int = 22, line_height: int = 18, anchor: str = "middle") -> str:
    lines = []
    for part in str(text).split("\n"):
        wrapped = textwrap.wrap(part, width=width) or [""]
        lines.extend(wrapped)
    result = [f'<text x="{x}" y="{y}" text-anchor="{anchor}" class="label">']
    for index, line in enumerate(lines):
        dy = 0 if index == 0 else line_height
        result.append(f'<tspan x="{x}" dy="{dy}">{esc(line)}</tspan>')
    result.append("</text>")
    return "".join(result)


def styles() -> str:
    return """
    <defs>
      <marker id="arrow" markerWidth="12" markerHeight="12" refX="11" refY="6" orient="auto" markerUnits="strokeWidth">
        <path d="M0,0 L12,6 L0,12 Z" fill="#111"/>
      </marker>
      <marker id="arrow-gray" markerWidth="12" markerHeight="12" refX="11" refY="6" orient="auto" markerUnits="strokeWidth">
        <path d="M0,0 L12,6 L0,12 Z" fill="#444"/>
      </marker>
    </defs>
    <style>
      .title { font-family: "Times New Roman", serif; font-size: 38px; font-weight: 700; fill:#000; }
      .caption { font-family: "Times New Roman", serif; font-size: 36px; font-weight: 700; fill:#000; }
      .label { font-family: "Comic Sans MS", "Chalkboard SE", Arial, sans-serif; font-size: 16px; font-weight: 700; fill:#000; }
      .small { font-family: "Comic Sans MS", "Chalkboard SE", Arial, sans-serif; font-size: 13px; font-weight: 700; fill:#000; }
      .tiny { font-family: "Comic Sans MS", "Chalkboard SE", Arial, sans-serif; font-size: 11px; font-weight: 700; fill:#000; }
      .line { stroke:#111; stroke-width:2; fill:none; marker-end:url(#arrow); }
      .line2 { stroke:#444; stroke-width:2; fill:none; marker-end:url(#arrow-gray); stroke-dasharray:5 5; }
      .box { fill:#fff; stroke:#111; stroke-width:2; }
      .round { fill:#fff; stroke:#111; stroke-width:2; rx:28; ry:28; }
      .service { fill:#fff; stroke:#111; stroke-width:2; stroke-dasharray:6 4; }
      .panel-yellow { fill:#fff3bd; stroke:#e0a028; stroke-width:1.5; rx:8; ry:8; }
      .panel-green { fill:#dff2d8; stroke:#77ad62; stroke-width:1.5; rx:8; ry:8; }
      .panel-blue { fill:#dfefff; stroke:#8baed0; stroke-width:1.5; rx:8; ry:8; }
      .db { fill:#def3ff; stroke:#111; stroke-width:2; }
      .cloud { fill:#ff8b1a; stroke:#ff8b1a; stroke-width:2; }
    </style>
    """


def laptop(x: int, y: int, label: str) -> str:
    return f"""
    <g>
      <rect x="{x}" y="{y}" width="70" height="44" class="box" rx="2"/>
      <rect x="{x+10}" y="{y+8}" width="50" height="28" fill="#fff" stroke="#777" stroke-width="1"/>
      <path d="M{x-8},{y+52} L{x+78},{y+52} L{x+65},{y+62} L{x+5},{y+62} Z" fill="#555"/>
      {tspans(label, x+35, y+92, 14)}
    </g>
    """


def cloudflare(x: int, y: int, scale: float = 1.0) -> str:
    return f"""
    <g transform="translate({x},{y}) scale({scale})">
      <ellipse cx="48" cy="32" rx="36" ry="20" class="cloud"/>
      <circle cx="28" cy="29" r="18" class="cloud"/>
      <circle cx="62" cy="24" r="22" class="cloud"/>
      <circle cx="83" cy="35" r="14" class="cloud"/>
      <text x="56" y="76" text-anchor="middle" class="label">CLOUDFLARE</text>
      <text x="56" y="96" text-anchor="middle" class="small">DNS / HTTPS</text>
    </g>
    """


def database(x: int, y: int, label: str = "Database") -> str:
    return f"""
    <g>
      <ellipse cx="{x+45}" cy="{y+15}" rx="45" ry="15" class="db"/>
      <rect x="{x}" y="{y+15}" width="90" height="70" class="db"/>
      <ellipse cx="{x+45}" cy="{y+85}" rx="45" ry="15" class="db"/>
      <ellipse cx="{x+45}" cy="{y+15}" rx="45" ry="15" fill="none" stroke="#111" stroke-width="2"/>
      {tspans(label, x+45, y+125, 14)}
    </g>
    """


def server_icon(x: int, y: int, label: str) -> str:
    return f"""
    <g>
      <rect x="{x}" y="{y}" width="58" height="88" fill="#cfe2f3" stroke="#111" stroke-width="2"/>
      <rect x="{x+9}" y="{y+12}" width="40" height="12" fill="#fff" stroke="#111" stroke-width="1"/>
      <rect x="{x+9}" y="{y+34}" width="40" height="12" fill="#fff" stroke="#111" stroke-width="1"/>
      <rect x="{x+9}" y="{y+56}" width="40" height="12" fill="#fff" stroke="#111" stroke-width="1"/>
      {tspans(label, x+29, y+122, 12)}
    </g>
    """


def google_icon(x: int, y: int, label: str = "Google OAuth") -> str:
    return f"""
    <g>
      <circle cx="{x+42}" cy="{y+38}" r="34" fill="#fff" stroke="#111" stroke-width="2"/>
      <text x="{x+42}" y="{y+50}" text-anchor="middle" font-family="Arial, sans-serif" font-size="44" font-weight="700">G</text>
      {tspans(label, x+42, y+102, 15)}
    </g>
    """


def mail_icon(x: int, y: int, label: str = "SMTP Mail") -> str:
    return f"""
    <g>
      <rect x="{x}" y="{y}" width="78" height="54" fill="#fff" stroke="#111" stroke-width="2" rx="4"/>
      <path d="M{x+4},{y+6} L{x+39},{y+34} L{x+74},{y+6}" fill="none" stroke="#d33" stroke-width="5"/>
      {tspans(label, x+39, y+88, 14)}
    </g>
    """


def network_model() -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="1320" height="900" viewBox="0 0 1320 900">
    {styles()}
    <rect width="1320" height="900" fill="#fff"/>
    <text x="22" y="52" class="title">Network Model</text>
    <rect x="30" y="108" width="1260" height="640" fill="#fff" stroke="#111" stroke-width="1.6"/>

    {laptop(80, 190, "Teacher / Admin")}
    {laptop(80, 310, "Judge / Reviewer")}
    {laptop(80, 430, "Student")}
    <path d="M165,222 H235 V462 H165" fill="none" stroke="#111" stroke-width="2"/>
    <path d="M235,342 H385" class="line"/>
    {cloudflare(390, 300, 0.88)}
    <text x="570" y="360" text-anchor="middle" class="small">scormetry.paragoniu.app</text>
    <path d="M500,342 H620" class="line"/>

    <rect x="625" y="150" width="610" height="430" class="round"/>
    <text x="930" y="190" text-anchor="middle" class="label">SCORMETRY 2.0 SERVER</text>
    <text x="930" y="212" text-anchor="middle" class="small">DigitalOcean or ParagonIU Server Environment</text>

    <rect x="655" y="235" width="398" height="270" class="round"/>
    <rect x="683" y="255" width="165" height="210" class="service"/>
    <text x="765" y="282" text-anchor="middle" class="label">FRONTEND</text>
    <rect x="710" y="305" width="112" height="46" class="panel-yellow"/>
    {tspans("Teacher / Admin Panel", 766, 328, 16)}
    <rect x="710" y="367" width="112" height="46" class="panel-yellow"/>
    {tspans("Judge Panel", 766, 394, 16)}
    <rect x="710" y="429" width="112" height="46" class="panel-green"/>
    {tspans("Student Panel", 766, 456, 16)}

    <rect x="905" y="345" width="130" height="62" fill="#dfefff" stroke="#79a6d2" stroke-width="2" rx="10"/>
    {tspans("LARAVEL BACKEND", 970, 373, 18)}
    <path d="M848,375 H905" class="line"/>
    <path d="M1035,375 H1105" class="line"/>
    {database(1105, 320, "MySQL Database")}

    <rect x="900" y="448" width="145" height="50" class="panel-blue"/>
    {tspans("Private File Storage", 972, 476, 18)}
    <path d="M970,407 V448" class="line"/>

    <rect x="690" y="620" width="140" height="110" class="service"/>
    {google_icon(718, 625, "Google OAuth")}
    <path d="M765,620 V505" class="line"/>

    <rect x="900" y="620" width="140" height="110" class="service"/>
    {mail_icon(930, 638, "SMTP Mail")}
    <path d="M970,620 V407" class="line"/>

    <rect x="1070" y="620" width="150" height="110" class="service"/>
    {tspans("OpenAI Responses API", 1145, 655, 16)}
    {tspans("gpt-5.4-nano", 1145, 700, 16)}
    <path d="M1042,470 H1070" class="line"/>

    <text x="660" y="835" text-anchor="middle" class="caption">Fig. 35. Network Model for Scormetry 2.0</text>
    </svg>"""


def network_topology() -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="1320" height="900" viewBox="0 0 1320 900">
    {styles()}
    <rect width="1320" height="900" fill="#fff"/>
    <text x="22" y="52" class="title">Network Topology</text>
    <rect x="38" y="120" width="1245" height="610" fill="#fff" stroke="#111" stroke-width="1.6"/>

    <g>
      <circle cx="130" cy="410" r="36" fill="#fff" stroke="#111" stroke-width="3"/>
      <path d="M78,520 C88,465 172,465 184,520" fill="none" stroke="#111" stroke-width="3"/>
      {tspans("End User", 130, 565, 12)}
    </g>
    <path d="M200,438 H402" class="line2"/>
    <path d="M402,462 H200" class="line2"/>
    {cloudflare(415, 390, 0.9)}
    <text x="478" y="512" text-anchor="middle" class="small">Cloudflare Firewall</text>
    <path d="M540,438 H675" class="line2"/>
    <path d="M675,462 H540" class="line2"/>

    <g>
      <rect x="690" y="375" width="90" height="95" fill="#c7472e" stroke="#111" stroke-width="1"/>
      <path d="M690,405 H780 M690,435 H780 M720,375 V470 M750,375 V470" stroke="#fff" stroke-width="3"/>
      {tspans("Firewall / HTTPS Layer", 735, 520, 13)}
    </g>
    <path d="M782,438 H940" class="line2"/>
    <path d="M940,462 H782" class="line2"/>

    <g>
      <ellipse cx="1010" cy="450" rx="70" ry="34" fill="#cfe9ff" stroke="#111" stroke-width="2"/>
      <path d="M970,450 H1050 M1010,420 V480 M980,430 L1040,470 M1040,430 L980,470" stroke="#fff" stroke-width="5"/>
      {tspans("Cloud Router", 1010, 520, 14)}
    </g>
    {server_icon(1045, 210, "Proxy Server")}
    <path d="M1010,416 V335" class="line2"/>
    <path d="M1032,335 V416" class="line2"/>

    <g>
      <rect x="895" y="605" width="110" height="42" fill="#333" stroke="#111" stroke-width="2"/>
      <circle cx="910" cy="626" r="4" fill="#68d391"/>
      <circle cx="930" cy="626" r="4" fill="#68d391"/>
      <circle cx="950" cy="626" r="4" fill="#68d391"/>
      {tspans("Managed Switch", 950, 690, 14)}
    </g>
    <path d="M990,484 V605" class="line2"/>
    <path d="M1014,605 V484" class="line2"/>

    <g>
      <rect x="565" y="600" width="115" height="82" fill="#dff0ff" stroke="#111" stroke-width="2"/>
      <path d="M622,620 L650,636 L622,654 L594,636 Z" fill="#8cc5e8" stroke="#111"/>
      {tspans("Scormetry 2.0 Application Server", 622, 725, 16)}
    </g>
    <path d="M680,625 H895" class="line2"/>
    <path d="M895,650 H680" class="line2"/>

    <g>
      <rect x="720" y="620" width="115" height="65" class="box"/>
      {tspans("MySQL + Private Storage", 777, 650, 14)}
    </g>
    <path d="M680,665 H720" class="line"/>

    <rect x="1080" y="605" width="145" height="72" class="service"/>
    {tspans("Google OAuth / SMTP / OpenAI", 1152, 637, 16)}
    <path d="M1040,590 H1080" class="line2"/>

    <text x="660" y="835" text-anchor="middle" class="caption">Fig. 36. Network Topology for Scormetry 2.0</text>
    </svg>"""


def security_diagram() -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="1320" height="900" viewBox="0 0 1320 900">
    {styles()}
    <rect width="1320" height="900" fill="#fff"/>
    <text x="22" y="52" class="title">Security</text>
    <rect x="60" y="105" width="1200" height="650" fill="#fff" stroke="#111" stroke-width="1.6"/>

    <g>
      <rect x="95" y="170" width="70" height="70" fill="#41c7f2" stroke="#111" stroke-width="2"/>
      <rect x="108" y="184" width="44" height="8" fill="#fff"/>
      {tspans("Browser", 130, 285, 12)}
    </g>
    <path d="M170,205 H350" class="line"/>
    {cloudflare(365, 160, 0.82)}
    <text x="418" y="278" text-anchor="middle" class="small">Cloudflare Firewall</text>
    <rect x="575" y="212" width="105" height="42" class="box"/>
    {tspans("DDoS Security", 627, 236, 13)}
    <rect x="805" y="212" width="130" height="42" class="box"/>
    {tspans("Automatic HTTPS Rewrite", 870, 236, 15)}
    <path d="M505,205 H1055" class="line"/>

    <g>
      <rect x="1060" y="166" width="78" height="82" fill="#c7472e" stroke="#111" stroke-width="1"/>
      <path d="M1060,195 H1138 M1060,225 H1138 M1086,166 V248 M1112,166 V248" stroke="#fff" stroke-width="3"/>
      {tspans("Firewall / HTTPS", 1099, 290, 13)}
    </g>
    <path d="M1099,248 V330" class="line"/>

    <rect x="860" y="330" width="220" height="270" class="service"/>
    <text x="970" y="358" text-anchor="middle" class="label">FRONTEND</text>
    <rect x="912" y="382" width="116" height="42" class="panel-yellow"/>
    {tspans("Teacher Panel", 970, 407, 15)}
    <rect x="912" y="442" width="116" height="42" class="panel-yellow"/>
    {tspans("Judge Panel", 970, 467, 15)}
    <rect x="912" y="502" width="116" height="42" class="panel-green"/>
    {tspans("Student Panel", 970, 527, 15)}

    {google_icon(1165, 360, "Google OAuth")}
    <rect x="1115" y="438" width="105" height="40" class="box"/>
    {tspans("Authentication", 1167, 462, 14)}
    <rect x="1115" y="510" width="105" height="40" class="box"/>
    {tspans("Verification", 1167, 534, 14)}
    <path d="M1080,420 H1115" class="line"/>
    <path d="M1115,528 H1080" class="line"/>

    <rect x="355" y="360" width="240" height="120" class="service"/>
    <text x="475" y="430" text-anchor="middle" class="label">LARAVEL BACKEND</text>
    <path d="M860,415 H595" class="line"/>
    <path d="M595,470 H860" class="line"/>
    <rect x="620" y="330" width="88" height="38" class="box"/>
    {tspans("Permission", 664, 353, 13)}
    <rect x="735" y="330" width="88" height="38" class="box"/>
    {tspans("Role", 779, 353, 13)}
    <rect x="800" y="392" width="88" height="38" class="box"/>
    {tspans("Session / Token", 844, 415, 14)}
    <rect x="660" y="525" width="120" height="42" class="box"/>
    {tspans("Authorized Request", 720, 550, 15)}

    {database(120, 395, "MySQL Database")}
    <rect x="145" y="355" width="130" height="42" class="box"/>
    {tspans("Protecting Sensitive Data", 210, 379, 14)}
    <path d="M355,420 H210" class="line2"/>
    <path d="M210,445 H355" class="line2"/>

    <rect x="325" y="575" width="120" height="42" class="box"/>
    {tspans("Secure Configuration", 385, 600, 15)}
    <rect x="470" y="575" width="120" height="42" class="box"/>
    {tspans("Input Validation", 530, 600, 15)}
    <rect x="615" y="575" width="120" height="42" class="box"/>
    {tspans("Exception Handling", 675, 600, 15)}
    <path d="M475,480 V575" stroke="#111" stroke-width="2" fill="none"/>

    <rect x="800" y="650" width="130" height="42" class="box"/>
    {tspans("Authorize User", 865, 675, 15)}
    <rect x="950" y="650" width="130" height="42" class="box"/>
    {tspans("Private File Access", 1015, 675, 15)}
    <rect x="1100" y="650" width="120" height="42" class="box"/>
    {tspans("Review / Rubric Locking", 1160, 675, 16)}
    <path d="M970,600 V650" stroke="#111" stroke-width="2" fill="none"/>

    <text x="660" y="835" text-anchor="middle" class="caption">Fig. 37. Security Architecture for Scormetry 2.0</text>
    </svg>"""


def write() -> None:
    files = {
        "fig-35-network-model-scormetry-20.svg": network_model(),
        "fig-36-network-topology-scormetry-20.svg": network_topology(),
        "fig-37-security-architecture-scormetry-20.svg": security_diagram(),
    }
    for name, content in files.items():
        (OUT / name).write_text(content)

    html_body = """
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Scormetry 2.0 System Architecture Diagrams</title>
  <style>
    body { margin: 0; padding: 24px; background: #f5f5f5; font-family: Arial, sans-serif; }
    h1 { font-family: "Times New Roman", serif; }
    .page { background: white; width: fit-content; margin: 0 auto 28px; padding: 16px; border: 1px solid #ccc; }
    img { width: 1120px; height: auto; display: block; }
  </style>
</head>
<body>
  <h1>Scormetry 2.0 System Architecture Diagrams</h1>
  <div class="page"><img src="fig-35-network-model-scormetry-20.svg" alt="Network Model"></div>
  <div class="page"><img src="fig-36-network-topology-scormetry-20.svg" alt="Network Topology"></div>
  <div class="page"><img src="fig-37-security-architecture-scormetry-20.svg" alt="Security Architecture"></div>
</body>
</html>
"""
    (OUT / "Scormetry2_System_Architecture_Same_Style.html").write_text(html_body)


if __name__ == "__main__":
    write()
