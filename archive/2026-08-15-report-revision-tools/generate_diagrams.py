from __future__ import annotations

import html
import math
import sys
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


PAGE_W = 1123
PAGE_H = 794
DPI = 300
PNG_W = 3508
PNG_H = 2480
SCALE = PNG_W / PAGE_W

FONT_REGULAR = Path(r"C:\Windows\Fonts\times.ttf")
FONT_BOLD = Path(r"C:\Windows\Fonts\timesbd.ttf")
FONT_ITALIC = Path(r"C:\Windows\Fonts\timesi.ttf")


@dataclass
class Node:
    id: str
    x: float
    y: float
    w: float
    h: float
    text: str = ""
    shape: str = "rounded"
    font_pt: int = 16
    bold: bool = False
    title: str | None = None
    lines: list[str] = field(default_factory=list)
    align: str = "center"

    @property
    def cx(self) -> float:
        return self.x + self.w / 2

    @property
    def cy(self) -> float:
        return self.y + self.h / 2


@dataclass
class Edge:
    source: str
    target: str
    label: str = ""
    via: list[tuple[float, float]] = field(default_factory=list)
    label_pos: tuple[float, float] | None = None


@dataclass
class Page:
    name: str
    title: str
    nodes: list[Node]
    edges: list[Edge]
    note: str = ""
    extra_lines: list[list[tuple[float, float]]] = field(default_factory=list)


def px(v: float) -> int:
    return int(round(v * SCALE))


def font(pt: int, bold: bool = False, italic: bool = False) -> ImageFont.FreeTypeFont:
    path = FONT_BOLD if bold else FONT_ITALIC if italic else FONT_REGULAR
    return ImageFont.truetype(str(path), int(round(pt * DPI / 72)))


def wrap_line(draw: ImageDraw.ImageDraw, text: str, fnt: ImageFont.FreeTypeFont, max_width: int) -> list[str]:
    words = text.split()
    if not words:
        return [""]
    lines: list[str] = []
    current = words[0]
    for word in words[1:]:
        candidate = current + " " + word
        if draw.textlength(candidate, font=fnt) <= max_width:
            current = candidate
        else:
            lines.append(current)
            current = word
    lines.append(current)
    return lines


def wrapped(draw: ImageDraw.ImageDraw, text: str, fnt: ImageFont.FreeTypeFont, max_width: int) -> str:
    result: list[str] = []
    for part in text.split("\n"):
        result.extend(wrap_line(draw, part, fnt, max_width))
    return "\n".join(result)


def node_anchor(node: Node, toward: tuple[float, float]) -> tuple[float, float]:
    dx = toward[0] - node.cx
    dy = toward[1] - node.cy
    if abs(dx) >= abs(dy):
        return (node.x + node.w if dx >= 0 else node.x, node.cy)
    return (node.cx, node.y + node.h if dy >= 0 else node.y)


def draw_arrow(draw: ImageDraw.ImageDraw, points: list[tuple[float, float]], width: int = 5) -> None:
    scaled = [(px(x), px(y)) for x, y in points]
    draw.line(scaled, fill="black", width=width, joint="curve")
    if len(scaled) < 2:
        return
    x2, y2 = scaled[-1]
    x1, y1 = scaled[-2]
    angle = math.atan2(y2 - y1, x2 - x1)
    size = 18
    left = (x2 - size * math.cos(angle - math.pi / 6), y2 - size * math.sin(angle - math.pi / 6))
    right = (x2 - size * math.cos(angle + math.pi / 6), y2 - size * math.sin(angle + math.pi / 6))
    draw.polygon([(x2, y2), left, right], fill="black")


def draw_node(draw: ImageDraw.ImageDraw, node: Node) -> None:
    box = [px(node.x), px(node.y), px(node.x + node.w), px(node.y + node.h)]
    line_width = 5
    if node.shape == "ellipse":
        draw.ellipse(box, fill="white", outline="black", width=line_width)
    elif node.shape == "diamond":
        x0, y0, x1, y1 = box
        draw.polygon([((x0 + x1) // 2, y0), (x1, (y0 + y1) // 2), ((x0 + x1) // 2, y1), (x0, (y0 + y1) // 2)], fill="white", outline="black")
        draw.line([((x0 + x1) // 2, y0), (x1, (y0 + y1) // 2), ((x0 + x1) // 2, y1), (x0, (y0 + y1) // 2), ((x0 + x1) // 2, y0)], fill="black", width=line_width)
    elif node.shape == "cylinder":
        x0, y0, x1, y1 = box
        eh = max(18, px(12))
        draw.rectangle([x0, y0 + eh // 2, x1, y1 - eh // 2], fill="white", outline="black", width=line_width)
        draw.ellipse([x0, y0, x1, y0 + eh], fill="white", outline="black", width=line_width)
        draw.arc([x0, y1 - eh, x1, y1], 0, 180, fill="black", width=line_width)
    elif node.shape == "plain":
        pass
    else:
        draw.rounded_rectangle(box, radius=px(8), fill="white", outline="black", width=line_width)

    pad = px(12)
    if node.title is not None:
        title_font = font(18, bold=True)
        body_font = font(node.font_pt)
        x = box[0] + pad
        y = box[1] + pad
        draw.text((x, y), node.title, font=title_font, fill="black")
        title_height = title_font.getbbox("Ag")[3] - title_font.getbbox("Ag")[1]
        y += title_height + px(7)
        line_height = body_font.getbbox("Ag")[3] - body_font.getbbox("Ag")[1] + px(4)
        for raw in node.lines:
            for line in wrap_line(draw, raw, body_font, box[2] - box[0] - 2 * pad):
                draw.text((x, y), line, font=body_font, fill="black")
                y += line_height
        return

    if not node.text:
        return
    fnt = font(node.font_pt, bold=node.bold)
    text = wrapped(draw, node.text, fnt, box[2] - box[0] - 2 * pad)
    bbox = draw.multiline_textbbox((0, 0), text, font=fnt, spacing=px(3), align="center")
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    tx = (box[0] + box[2] - tw) / 2
    ty = (box[1] + box[3] - th) / 2 - bbox[1]
    draw.multiline_text((tx, ty), text, font=fnt, fill="black", spacing=px(3), align="center")


def render_page(page: Page, full_path: Path, word_path: Path) -> None:
    image = Image.new("RGB", (PNG_W, PNG_H), "white")
    draw = ImageDraw.Draw(image)
    title_font = font(22, bold=True)
    tb = draw.textbbox((0, 0), page.title, font=title_font)
    draw.text(((PNG_W - (tb[2] - tb[0])) / 2, px(35)), page.title, font=title_font, fill="black")

    node_map = {n.id: n for n in page.nodes}
    for line in page.extra_lines:
        draw.line([(px(x), px(y)) for x, y in line], fill="black", width=5, joint="curve")
    for edge in page.edges:
        src = node_map[edge.source]
        dst = node_map[edge.target]
        first_target = edge.via[0] if edge.via else (dst.cx, dst.cy)
        last_source = edge.via[-1] if edge.via else (src.cx, src.cy)
        points = [node_anchor(src, first_target), *edge.via, node_anchor(dst, last_source)]
        draw_arrow(draw, points)
        if edge.label:
            if edge.label_pos:
                lx, ly = px(edge.label_pos[0]), px(edge.label_pos[1])
            else:
                mx = (points[0][0] + points[-1][0]) / 2
                my = (points[0][1] + points[-1][1]) / 2
                lx, ly = px(mx), px(my)
            lf = font(14, bold=True)
            bb = draw.textbbox((0, 0), edge.label, font=lf)
            pad = px(4)
            draw.rectangle([lx - pad, ly - pad, lx + (bb[2] - bb[0]) + pad, ly + (bb[3] - bb[1]) + pad], fill="white")
            draw.text((lx, ly), edge.label, font=lf, fill="black")

    for node in page.nodes:
        draw_node(draw, node)

    if page.note:
        nf = font(14, italic=True)
        note = wrapped(draw, page.note, nf, px(1010))
        bb = draw.multiline_textbbox((0, 0), note, font=nf, spacing=px(2), align="center")
        draw.multiline_text(((PNG_W - (bb[2] - bb[0])) / 2, px(730)), note, font=nf, fill="black", spacing=px(2), align="center")

    full_path.parent.mkdir(parents=True, exist_ok=True)
    word_path.parent.mkdir(parents=True, exist_ok=True)
    image.save(full_path, dpi=(DPI, DPI), optimize=True)
    crop = image.crop((0, px(25), PNG_W, px(770)))
    crop.save(word_path, dpi=(DPI, DPI), optimize=True)


def shape_style(node: Node) -> str:
    common = f"whiteSpace=wrap;html=1;fillColor=#FFFFFF;strokeColor=#000000;fontColor=#000000;fontFamily=Times New Roman;fontSize={node.font_pt};align={node.align};verticalAlign=middle;spacing=8;"
    if node.bold:
        common += "fontStyle=1;"
    if node.shape == "plain":
        return common + "strokeColor=none;fillColor=none;"
    if node.shape == "ellipse":
        return "ellipse;" + common
    if node.shape == "diamond":
        return "rhombus;" + common
    if node.shape == "cylinder":
        return "shape=cylinder3;boundedLbl=1;backgroundOutline=1;size=15;" + common
    return "rounded=1;arcSize=12;" + common


def page_to_xml(page: Page) -> ET.Element:
    diagram = ET.Element("diagram", {"id": page.name.replace(" ", "_"), "name": page.name})
    model = ET.SubElement(diagram, "mxGraphModel", {
        "dx": "1422", "dy": "794", "grid": "1", "gridSize": "10", "guides": "1", "tooltips": "1",
        "connect": "1", "arrows": "1", "fold": "1", "page": "1", "pageScale": "1",
        "pageWidth": str(PAGE_W), "pageHeight": str(PAGE_H), "background": "#FFFFFF", "math": "0", "shadow": "0",
    })
    root = ET.SubElement(model, "root")
    ET.SubElement(root, "mxCell", {"id": "0"})
    ET.SubElement(root, "mxCell", {"id": "1", "parent": "0"})
    title = ET.SubElement(root, "mxCell", {
        "id": "title", "value": html.escape(page.title), "style": "text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;whiteSpace=wrap;fontFamily=Times New Roman;fontSize=22;fontStyle=1;", "vertex": "1", "parent": "1",
    })
    ET.SubElement(title, "mxGeometry", {"x": "60", "y": "20", "width": "1003", "height": "50", "as": "geometry"})

    for node in page.nodes:
        if node.title is not None:
            value = "<b>" + html.escape(node.title) + "</b><br>" + "<br>".join(html.escape(x) for x in node.lines)
            style = shape_style(node).replace("align=center", "align=left").replace("verticalAlign=middle", "verticalAlign=top")
        else:
            value = html.escape(node.text).replace("\n", "<br>")
            style = shape_style(node)
        cell = ET.SubElement(root, "mxCell", {"id": node.id, "value": value, "style": style, "vertex": "1", "parent": "1"})
        ET.SubElement(cell, "mxGeometry", {"x": f"{node.x:g}", "y": f"{node.y:g}", "width": f"{node.w:g}", "height": f"{node.h:g}", "as": "geometry"})

    for idx, edge in enumerate(page.edges, 1):
        cell = ET.SubElement(root, "mxCell", {
            "id": f"e{idx}", "value": html.escape(edge.label),
            "style": "edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;fontFamily=Times New Roman;fontSize=14;labelBackgroundColor=#FFFFFF;",
            "edge": "1", "parent": "1", "source": edge.source, "target": edge.target,
        })
        geo = ET.SubElement(cell, "mxGeometry", {"relative": "1", "as": "geometry"})
        if edge.via:
            arr = ET.SubElement(geo, "Array", {"as": "points"})
            for x, y in edge.via:
                ET.SubElement(arr, "mxPoint", {"x": f"{x:g}", "y": f"{y:g}"})

    if page.note:
        note = ET.SubElement(root, "mxCell", {
            "id": "note", "value": html.escape(page.note),
            "style": "text;html=1;strokeColor=none;fillColor=none;align=center;verticalAlign=middle;whiteSpace=wrap;fontFamily=Times New Roman;fontSize=14;fontStyle=2;",
            "vertex": "1", "parent": "1",
        })
        ET.SubElement(note, "mxGeometry", {"x": "55", "y": "720", "width": "1013", "height": "45", "as": "geometry"})
    return diagram


def flow_page(name: str, title: str, texts: dict[str, str], db_text: str) -> Page:
    nodes = [
        Node("start", 25, 165, 95, 60, "Start", "ellipse", 16),
        Node("auth", 155, 135, 170, 120, "Authenticated\nsession active?", "diamond", 16, True),
        Node("load", 365, 145, 185, 100, texts["load"], "rounded", 16),
        Node("action", 590, 135, 180, 120, texts["action"], "diamond", 16, True),
        Node("input", 815, 145, 200, 100, texts["input"], "rounded", 16),
        Node("validate", 820, 380, 190, 145, texts["validate"], "diamond", 16, True),
        Node("error", 830, 585, 180, 85, "Show validation or\naccess error", "rounded", 16),
        Node("process", 585, 400, 190, 105, texts["process"], "rounded", 16),
        Node("db", 400, 400, 160, 105, db_text, "cylinder", 16),
        Node("feedback", 195, 400, 170, 100, "Set success\nfeedback", "rounded", 16),
        Node("reload", 25, 400, 135, 100, "Reload\nuser-scoped view", "rounded", 16),
        Node("redirect", 250, 585, 190, 80, "Redirect to Login", "rounded", 16),
        Node("end", 25, 595, 95, 60, "End", "ellipse", 16),
    ]
    edges = [
        Edge("start", "auth"),
        Edge("auth", "load", "Yes", label_pos=(333, 155)),
        Edge("auth", "redirect", "No", [(240, 300), (380, 300), (380, 560), (345, 560)], (335, 305)),
        Edge("load", "action"),
        Edge("action", "input", "Selected action", label_pos=(778, 105)),
        Edge("input", "validate", via=[(915, 320)]),
        Edge("validate", "process", "Yes", label_pos=(790, 410)),
        Edge("validate", "error", "No", label_pos=(1018, 535)),
        Edge("error", "input", "Correct input", [(1055, 625), (1055, 195)], (1010, 365)),
        Edge("process", "db"),
        Edge("db", "feedback"),
        Edge("feedback", "reload"),
        Edge("reload", "end"),
        Edge("redirect", "end"),
    ]
    return Page(name, title, nodes, edges, "Database changes occur only after input, CSRF and ownership checks succeed.")


def build_pages() -> list[Page]:
    site_nodes = [
        Node("home", 425, 90, 270, 65, "Home / Landing Page", "rounded", 18, True),
        Node("guest", 40, 195, 280, 65, "Guest and Authentication", "rounded", 18, True),
        Node("student", 420, 195, 280, 65, "Authenticated Student", "rounded", 18, True),
        Node("admin", 800, 195, 280, 65, "Authenticated Admin", "rounded", 18, True),
        Node("register", 40, 305, 125, 65, "Register", "rounded", 16),
        Node("login", 195, 305, 125, 65, "Login", "rounded", 16),
        Node("forgot", 40, 400, 125, 70, "Forgot\nPassword", "rounded", 16),
        Node("reset", 195, 400, 125, 70, "Reset\nPassword", "rounded", 16),
        Node("guestnote", 40, 500, 280, 55, "Reset link uses a one-time token", "plain", 14),
        Node("sdash", 455, 300, 210, 65, "Student Dashboard", "rounded", 17, True),
        Node("exercise", 410, 390, 140, 95, "Exercise Tracker\nCRUD + summaries", "rounded", 16),
        Node("journal", 570, 390, 140, 95, "Diary Journal\nentries + drafts", "rounded", 16),
        Node("money", 410, 505, 140, 95, "Money Tracker\ntransactions + goals", "rounded", 16),
        Node("habit", 570, 505, 140, 95, "Habit Tracker\nhabits + logs", "rounded", 16),
        Node("shared", 410, 625, 140, 55, "Shared Navigation", "rounded", 16),
        Node("slogout", 570, 625, 140, 55, "Logout", "rounded", 16),
        Node("adash", 835, 300, 210, 65, "Admin Dashboard", "rounded", 17, True),
        Node("users", 835, 400, 210, 65, "Registered Users", "rounded", 16),
        Node("summaries", 835, 500, 210, 65, "System Summaries", "rounded", 16),
        Node("alogout", 835, 610, 210, 60, "Logout", "rounded", 16),
    ]
    site_edges = [
        Edge("home", "guest", via=[(560, 175), (180, 175)]),
        Edge("home", "student", via=[(560, 175)]),
        Edge("home", "admin", via=[(560, 175), (940, 175)]),
        Edge("guest", "register", via=[(180, 280), (102, 280)]), Edge("guest", "login", via=[(180, 280), (257, 280)]),
        Edge("login", "forgot", via=[(257, 385), (102, 385)]), Edge("forgot", "reset"),
        Edge("student", "sdash"), Edge("sdash", "exercise", via=[(560, 380), (480, 380)]), Edge("sdash", "journal", via=[(560, 380), (640, 380)]),
        Edge("sdash", "money", via=[(560, 495), (480, 495)]), Edge("sdash", "habit", via=[(560, 495), (640, 495)]),
        Edge("exercise", "shared", via=[(480, 610)]), Edge("journal", "slogout", via=[(640, 610)]),
        Edge("admin", "adash"), Edge("adash", "users"), Edge("users", "summaries"), Edge("summaries", "alogout"),
    ]
    pages = [Page("1 - Site Hierarchy", "STUDENT ROUTINE ORGANIZER - SITE HIERARCHY", site_nodes, site_edges, "Protected routes validate the session; module records are filtered by the authenticated user_id.")]

    pages.extend([
        flow_page("2 - Exercise Tracker Flowchart", "EXERCISE TRACKER SYSTEM FLOWCHART", {
            "load": "Load owned workouts, blogs, summaries and filters", "action": "View, create, edit, delete or export?",
            "input": "Enter workout details or select an owned record", "validate": "Valid values, CSRF and ownership?", "process": "Write record or prepare CSV",
        }, "exercise_records\nand exercise_blogs"),
        flow_page("3 - Diary Journal Flowchart", "DIARY JOURNAL SYSTEM FLOWCHART", {
            "load": "Load owned entries, drafts, mood summary and filters", "action": "Read, draft, publish, edit or delete?",
            "input": "Enter journal fields or resume an owned draft", "validate": "Valid fields, CSRF and ownership?", "process": "Save, publish, update or delete",
        }, "journal_entries\nand journal_drafts"),
        flow_page("4 - Money Tracker Flowchart", "MONEY TRACKER SYSTEM FLOWCHART", {
            "load": "Load owned transactions, totals, trends and goals", "action": "View, change, export or manage a goal?",
            "input": "Enter transaction or savings-goal details", "validate": "Valid amount/type, CSRF and ownership?", "process": "Write record or calculate analysis",
        }, "transactions, goals\nand contributions"),
        flow_page("5 - Habit Tracker Flowchart", "HABIT TRACKER SYSTEM FLOWCHART", {
            "load": "Generate due logs and load owned habits and progress", "action": "Change a habit or update a daily log?",
            "input": "Enter schedule or completion/reflection details", "validate": "Valid schedule/status, CSRF and ownership?", "process": "Write habit/log and recalculate progress",
        }, "habits and\nhabit_logs"),
    ])

    erd_nodes = [
        Node("users", 400, 80, 320, 80, "users\nPK user_id | role", "rounded", 18, True),
        Node("password", 25, 225, 220, 90, "password_resets\nFK user_id", "rounded", 16),
        Node("exrec", 285, 225, 220, 90, "exercise_records\nFK user_id", "rounded", 16),
        Node("exblog", 545, 225, 220, 90, "exercise_blogs\nFK user_id", "rounded", 16),
        Node("journal", 805, 225, 220, 90, "journal_entries\nFK user_id", "rounded", 16),
        Node("drafts", 25, 405, 220, 90, "journal_drafts\nFK user_id", "rounded", 16),
        Node("transactions", 285, 405, 220, 90, "money_transactions\nFK user_id", "rounded", 16),
        Node("goals", 545, 405, 220, 90, "money_savings_goals\nFK user_id", "rounded", 16),
        Node("habits", 805, 405, 220, 90, "habits\nFK user_id", "rounded", 16),
        Node("contrib", 545, 585, 220, 100, "money_savings_\ncontributions\nFK goal_id, user_id", "rounded", 16),
        Node("logs", 805, 585, 220, 100, "habit_logs\nFK habit_id, user_id", "rounded", 16),
    ]
    erd_edges: list[Edge] = []
    for target in ["password", "exrec", "exblog", "journal", "drafts", "transactions", "goals", "habits"]:
        node = next(n for n in erd_nodes if n.id == target)
        if node.y < 300:
            via = [(560, 190), (node.cx, 190)]
        else:
            via = [(1080, 120), (1080, 365), (node.cx, 365)]
        erd_edges.append(Edge("users", target, "", via))
    erd_edges += [Edge("goals", "contrib", "1:N", label_pos=(775, 525)), Edge("habits", "logs", "1:N", label_pos=(1035, 525))]
    pages.append(Page("6 - Database ERD", "DATABASE RELATIONSHIP OVERVIEW", erd_nodes, erd_edges, "All direct module tables use users.user_id; contributions and logs also reference their parent goal or habit."))

    schema_a = [
        Node("a_users", 40, 125, 335, 265, shape="rounded", title="users", lines=["PK user_id INT", "full_name VARCHAR(100)", "UQ email VARCHAR(120)", "password_hash VARCHAR(255)", "role ENUM(student, admin)", "created_at TIMESTAMP"]),
        Node("a_reset", 394, 125, 335, 265, shape="rounded", title="password_resets", lines=["PK reset_id INT", "FK user_id INT", "UQ token_hash CHAR(64)", "expires_at DATETIME", "used_at DATETIME NULL", "created_at TIMESTAMP"]),
        Node("a_exrec", 748, 125, 335, 265, shape="rounded", title="exercise_records", lines=["PK exercise_id INT", "FK user_id INT", "activity_type VARCHAR(80)", "duration_minutes INT", "calories_burned INT", "exercise_date DATE", "notes / photo metadata", "created_at / updated_at"]),
        Node("a_exblog", 40, 425, 335, 265, shape="rounded", title="exercise_blogs", lines=["PK blog_id INT", "FK user_id INT", "title VARCHAR(140)", "content TEXT", "blog_date DATE", "created_at / updated_at"]),
        Node("a_journal", 394, 425, 335, 265, shape="rounded", title="journal_entries", lines=["PK journal_id INT", "FK user_id INT", "title / content / mood", "entry_date DATE", "subject / weather / tags", "paper_style / starred", "canvas_json MEDIUMTEXT", "created_at / updated_at"]),
        Node("a_drafts", 748, 425, 335, 265, shape="rounded", title="journal_drafts", lines=["PK draft_id INT", "FK user_id INT", "title / content / mood", "entry_date DATE NULL", "template_key VARCHAR(32)", "subject / weather / tags", "paper_style / starred", "canvas_json MEDIUMTEXT", "created_at / updated_at"]),
    ]
    pages.append(Page("7 - Schema Attributes A", "SCHEMA DETAIL - AUTHENTICATION, EXERCISE AND JOURNAL", schema_a, [], "PK = primary key; FK = foreign key; UQ = unique constraint."))

    schema_b = [
        Node("b_txn", 40, 145, 335, 255, shape="rounded", title="money_transactions", lines=["PK transaction_id INT", "FK user_id INT", "amount DECIMAL(10,2)", "category / description", "transaction_type ENUM", "transaction_date DATE", "created_at / updated_at"]),
        Node("b_goals", 394, 145, 335, 255, shape="rounded", title="money_savings_goals", lines=["PK goal_id INT", "FK user_id INT", "goal_name VARCHAR(120)", "target / weekly amount", "target_date DATE NULL", "auto-save / reminders", "status / completed_at", "created_at / updated_at"]),
        Node("b_contrib", 748, 145, 335, 255, shape="rounded", title="money_savings_contributions", lines=["PK contribution_id INT", "FK goal_id INT", "FK user_id INT", "amount DECIMAL(10,2)", "note VARCHAR(255)", "contribution_date DATE", "created_at TIMESTAMP"]),
        Node("b_habits", 215, 445, 335, 255, shape="rounded", title="habits", lines=["PK habit_id INT", "FK user_id INT", "habit_name VARCHAR(100)", "realm / frequency", "scheduled_days / time", "duration / motivation", "priority / is_active", "created_at / updated_at"]),
        Node("b_logs", 573, 445, 335, 255, shape="rounded", title="habit_logs", lines=["PK log_id INT", "FK habit_id INT", "FK user_id INT", "scheduled_date DATE", "completion_status ENUM", "completed_at / reflection", "deleted_at DATETIME", "UQ habit_id + date"]),
    ]
    pages.append(Page("8 - Schema Attributes B", "SCHEMA DETAIL - MONEY AND HABIT TRACKING", schema_b, [], "PK = primary key; FK = foreign key; UQ = unique constraint."))

    arch_nodes = [
        Node("presentation", 90, 150, 943, 120, "PRESENTATION TIER\nBrowser pages, HTML forms, CSS, JavaScript, shared navigation and accessible feedback", "rounded", 18, True),
        Node("application", 90, 340, 943, 120, "APPLICATION TIER\nPHP controllers and helpers: authentication, validation, CRUD logic, summaries, CSRF checks and error handling", "rounded", 18, True),
        Node("data", 90, 530, 943, 120, "DATA TIER\nMySQL/MariaDB: users, module records, ownership foreign keys, indexes and transactions", "rounded", 18, True),
    ]
    arch_edges = [Edge("presentation", "application", "HTTP request / response", label_pos=(585, 290)), Edge("application", "data", "prepared SQL / result sets", label_pos=(585, 480))]
    pages.append(Page("9 - Three-Tier Architecture", "THREE-TIER APPLICATION ARCHITECTURE", arch_nodes, arch_edges, "A shared session identity and user_id constraints connect all four modules without exposing cross-user records."))
    return pages


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit("Usage: generate_diagrams.py OUTPUT_DIR DRAWIO_PATH")
    out_dir = Path(sys.argv[1])
    drawio_path = Path(sys.argv[2])
    pages = build_pages()
    mxfile = ET.Element("mxfile", {"host": "app.diagrams.net", "modified": "2026-08-14T00:00:00.000Z", "agent": "Codex", "version": "26.0.9", "type": "device", "compressed": "false"})
    for idx, page in enumerate(pages, 1):
        mxfile.append(page_to_xml(page))
        slug = page.name.split(" - ", 1)[1].lower().replace(" ", "-")
        render_page(page, out_dir / "a4" / f"{idx:02d}-{slug}.png", out_dir / "word" / f"figure-{idx}.png")
    ET.indent(mxfile, space="  ")
    drawio_path.parent.mkdir(parents=True, exist_ok=True)
    ET.ElementTree(mxfile).write(drawio_path, encoding="utf-8", xml_declaration=True)
    print(f"Generated {len(pages)} editable Draw.io pages and {len(pages)} A4 PNG exports.")


if __name__ == "__main__":
    main()
