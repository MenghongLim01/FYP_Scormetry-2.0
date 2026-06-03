from pathlib import Path
from urllib.parse import unquote

from PIL import Image, PngImagePlugin


SOURCE_PNG = Path("/Users/mac/Downloads/FYP.drawio (1).png")
OUTPUT_DIR = Path("/Users/mac/Downloads/FYP_Scormtry-2.0/docs/chapter3-diagrams")
EXTRACTED_DRAWIO = OUTPUT_DIR / "Scormetry2_FYP_Drawio_Extracted.drawio"
FIXED_DRAWIO = OUTPUT_DIR / "Scormetry2_FYP_Drawio_Fixed.drawio"
FIXED_EMBEDDED_PNG = OUTPUT_DIR / "Scormetry2_FYP_Drawio_Fixed_Editable.png"


REPLACEMENTS = {
    "Vue/Interia subject form": "Vue/Inertia subject form",
    "Locked rubeic": "Locked rubric",
    "&quot;Teacher&quot;&lt;br&gt;Releas scores": "&quot;Teacher&quot;&lt;br&gt;Release scores",
    "Select office rubric PDF": "Select official rubric PDF",
    "Upload PDF and request JASON extraction": "Upload PDF and request JSON extraction",
    "JASON": "JSON",
    "Return structured dynamic rubric JSON": "Return Dynamic Rubric JSON",
    "Post / subject": "POST /subjects",
    "Post/subject": "POST /subjects",
    "Post/subject/join": "POST /subjects/join",
    "POST/subject/{subject}/rubrics": "POST /subjects/{subject}/rubrics",
    "POST/Rubric/{rubric}/approve": "POST /rubrics/{rubric}/approve",
    "Post/paper/{paper}/reviewer": "POST /papers/{paper}/reviews",
    "Post/papers": "POST /papers",
    "Validated StoreSubjectRequest": "Validate subject data",
    "Validate StoreRubricRequest": "Validate rubric PDF",
    "Store subject_invitation": "Store subject invitation",
    "Find subject_invitations": "Find subject invitation",
    "Create subject_members": "Create subject member",
    "Logic status": "Login status",
    "Logic Status": "Login status",
    "Paper date": "Paper data",
    "Store / retirevescores": "Store / retrieve scores",
    "Retrieve assignmnents": "Retrieve assignments",
    "View assigned": "View assigned teams",
    "Studnet": "Student",
    "Score each criterion": "Score each criterion",
    "Add per-criterion comments": "Add per-criterion feedback",
    "Invite reviewer by email / class code": "Invite reviewer by email / reviewer code",
    "Block rejoining by email or join code&lt;div&gt;(switch to approval join)&lt;/div&gt;": "Block rejoining by email&lt;div&gt;or require approval&lt;/div&gt;",
    "View team / Member status": "View team / member status",
    "View team score": "View team scores",
    "Override final score": "Override final score with reason",
    "View final score / Override note": "View final score / override note",
    "Add / Remove reviewer": "Add / Remove reviewers",
    "Add / Remove students": "Add / Remove students",
    "Create subject room": "Create subject room",
    "Open subject room": "Open subject room",
    "Join subject by code": "Join subject by code&lt;br&gt;or email invitation",
    "Join class": "Join subject",
    "Create subjects": "Create subject",
    "Paper saved as draft": "Paper saved",
    "submitted review": "Submitted review",
    "Validation credentials": "Validate credentials",
    "Review extracted dynamic rubric": "Review extracted Dynamic Rubric",
    "AI convert to dynamic rubric": "AI convert to Dynamic Rubric",
    "Pending dynamic rubric save flow": "Pending Dynamic Rubric save flow",
    "Dynamic rubric verification flow": "Dynamic Rubric verification flow",
    "View locked dynamic rubric": "View locked Dynamic Rubric",
    "Dynamic rubric status": "Dynamic Rubric status",
    "Pending rubric": "Pending rubric",
    "Upload official rubric PDF": "Upload official rubric PDF",
    "Upload Rubric": "Upload rubric",
    "Rubric upload flow": "Rubric upload flow",
    "Score and Feedback": "Score and feedback",
    "Score review flow": "Score review flow",
    "Score override flow": "Score override flow",
    "Score release flow": "Score release flow",
    "Show score and feedback": "Show score and feedback",
    "Show final score and feedback": "Show final score and feedback",
    "Subject / Team store": "Subject / team store",
    "Rubric / Paper store": "Rubric / paper store",
    "Paper Data Store": "Paper data store",
    "User Data": "User data",
    "Assignment / Room store": "Assignment / room store",
    "Result / Feedback store": "Result / feedback store",
}


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    image = Image.open(SOURCE_PNG)
    raw_xml = image.info.get("mxfile", "")
    if not raw_xml:
        raise RuntimeError(f"No embedded draw.io source found in {SOURCE_PNG}")

    xml = unquote(raw_xml)
    EXTRACTED_DRAWIO.write_text(xml, encoding="utf-8")

    for old, new in REPLACEMENTS.items():
        xml = xml.replace(old, new)

    FIXED_DRAWIO.write_text(xml, encoding="utf-8")

    metadata = PngImagePlugin.PngInfo()
    metadata.add_text("mxfile", xml)
    image.save(FIXED_EMBEDDED_PNG, pnginfo=metadata)

    print(FIXED_DRAWIO)
    print(FIXED_EMBEDDED_PNG)


if __name__ == "__main__":
    main()
