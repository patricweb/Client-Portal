"""Build the reviewed, static v2 template library; never issue or send an invoice.

Dependencies: reportlab, pypdf. Run from any directory. The original v1 is not read
or modified. Only the explicit English allowlist enters the client-facing PDF.
"""

from __future__ import annotations

import hashlib
import json
import re
from html import escape
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from pypdf import PdfReader
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageBreak, PageTemplate, Paragraph, Spacer, Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs" / "client-documents-v2"
OUTPUT = ROOT / "output" / "pdf"
QA = ROOT / "tmp" / "pdfs"
PDF_NAME = "Matei_Patric_US_Client_Document_Pack_v2.pdf"
FILES = [
    "01_master_services_agreement.md",
    "02_proposal.md",
    "03_statement_of_work.md",
    "04_change_order.md",
    "05_advance_invoice.md",
    "06_final_invoice.md",
    "07_delivery_acceptance.md",
    "08_final_handover.md",
    "09_care_support_agreement.md",
    "10_client_messages.md",
]
EXPECTED_PAGES = [4, 1, 2, 1, 1, 1, 1, 1, 2, 2]
INK = colors.HexColor("#173246")
ACCENT = colors.HexColor("#267F7C")
MUTED = colors.HexColor("#61717D")
PALE = colors.HexColor("#F0F5F6")
RULE = colors.HexColor("#D4DFE4")
WIDTH, HEIGHT = A4
MARGIN = 48
CONTENT_WIDTH = WIDTH - 2 * MARGIN


def register_fonts() -> tuple[str, str]:
    candidates = [
        (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/arialbd.ttf")),
        (Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
         Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")),
    ]
    for regular, bold in candidates:
        if regular.is_file() and bold.is_file():
            pdfmetrics.registerFont(TTFont("PackRegular", str(regular)))
            pdfmetrics.registerFont(TTFont("PackBold", str(bold)))
            return "PackRegular", "PackBold"
    return "Helvetica", "Helvetica-Bold"


REGULAR, BOLD = register_fonts()
STYLES = {
    "title": ParagraphStyle("Title", fontName=BOLD, fontSize=22, leading=25,
                            textColor=INK, spaceAfter=7, keepWithNext=True),
    "subtitle": ParagraphStyle("Subtitle", fontName=REGULAR, fontSize=9.4,
                               leading=13, textColor=MUTED, spaceAfter=10,
                               keepWithNext=True),
    "heading": ParagraphStyle("Heading", fontName=BOLD, fontSize=10.8,
                              leading=14, textColor=ACCENT, spaceBefore=6,
                              spaceAfter=4, keepWithNext=True),
    "body": ParagraphStyle("Body", fontName=REGULAR, fontSize=9.6, leading=12.5,
                           textColor=INK, spaceAfter=5, alignment=TA_LEFT),
    "cell": ParagraphStyle("Cell", fontName=REGULAR, fontSize=8.8, leading=11.5,
                           textColor=INK),
    "cellhead": ParagraphStyle("CellHead", fontName=BOLD, fontSize=8.6,
                               leading=11.3, textColor=colors.white),
}


def p(text: str, style: str = "body") -> Paragraph:
    return Paragraph("<br/>".join(escape(line) for line in text.splitlines()), STYLES[style])


def table(lines: list[str]) -> Table:
    rows = []
    for line in lines:
        parts = [cell.strip() for cell in line.strip().strip("|").split("|")]
        if all(re.fullmatch(r":?-+:?", part.replace(" ", "")) for part in parts):
            continue
        rows.append(parts)
    columns = len(rows[0])
    if any(len(row) != columns for row in rows):
        raise ValueError(f"Inconsistent table: {lines}")
    ratios = [0.72, 0.28] if columns == 2 and "Amount" in rows[0][-1] else (
        [0.36, 0.64] if columns == 2 else [0.25, 0.40, 0.35])
    values = [[p(cell, "cellhead" if i == 0 else "cell") for cell in row]
              for i, row in enumerate(rows)]
    result = Table(values, colWidths=[CONTENT_WIDTH * x for x in ratios],
                   repeatRows=1, hAlign="LEFT", spaceBefore=3, spaceAfter=9)
    result.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), INK),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [PALE, colors.white]),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 4.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4.5),
        ("LINEBELOW", (0, -1), (-1, -1), 0.5, RULE),
    ]))
    return result


def page_flowables(markdown: str) -> list:
    lines = markdown.strip().splitlines()
    if not lines[0].startswith("# "):
        raise ValueError("Each intentional page must begin with a title")
    flow = [p(lines[0][2:], "title"), p(lines[1], "subtitle")]
    index = 2
    while index < len(lines):
        line = lines[index].strip()
        if not line:
            index += 1
            continue
        if line.startswith("## "):
            flow.append(p(line[3:], "heading"))
            index += 1
        elif line.startswith("|"):
            group = []
            while index < len(lines) and lines[index].strip().startswith("|"):
                group.append(lines[index])
                index += 1
            flow.append(table(group))
        else:
            group = []
            while index < len(lines) and lines[index].strip() and not lines[index].startswith("## "):
                group.append(lines[index])
                index += 1
            flow.append(p("\n".join(group)))
    return flow


class PackDoc(BaseDocTemplate):
    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and flowable.style.name == "Title":
            key = f"page-{self.page}"
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(flowable.getPlainText(), key, 0, False)


def main() -> None:
    OUTPUT.mkdir(parents=True, exist_ok=True)
    QA.mkdir(parents=True, exist_ok=True)
    page_meta, story, sources = [], [], []
    for name, expected in zip(FILES, EXPECTED_PAGES, strict=True):
        path = SOURCE / name
        content = path.read_text(encoding="utf-8")
        if not content.isascii():
            raise ValueError(f"Client source must use portable ASCII: {name}")
        pages = content.split("<!-- page -->")
        if len(pages) != expected:
            raise ValueError(f"Wrong intentional page count in {name}")
        for local, content_page in enumerate(pages, 1):
            if story:
                story.append(PageBreak())
            story.extend(page_flowables(content_page))
            page_meta.append((name, local, len(pages)))
        sources.append({"file": name, "sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
                        "pages": len(pages)})
    count = len(page_meta)

    def decorate(canvas, doc):
        canvas.saveState()
        canvas.setFillColor(INK)
        canvas.setFont(BOLD, 9)
        canvas.drawString(MARGIN, HEIGHT - 31, "MATEI PATRIC")
        canvas.setFont(REGULAR, 7.8)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(WIDTH - MARGIN, HEIGHT - 31, "SOFTWARE SERVICES  /  CLIENT DOCUMENTS")
        canvas.setStrokeColor(ACCENT)
        canvas.setLineWidth(1.2)
        canvas.line(MARGIN, HEIGHT - 42, WIDTH - MARGIN, HEIGHT - 42)
        canvas.setStrokeColor(RULE)
        canvas.setLineWidth(0.5)
        canvas.line(MARGIN, 38, WIDTH - MARGIN, 38)
        canvas.setFont(REGULAR, 7)
        canvas.setFillColor(MUTED)
        canvas.drawString(MARGIN, 25, "V2 TEMPLATE  /  26 AUG 2026  /  COMPLETE BEFORE USE")
        if doc.page <= count:
            _, local, total = page_meta[doc.page - 1]
            canvas.drawRightString(WIDTH - MARGIN, 25,
                                   f"Document {local}/{total}  |  Pack {doc.page:02d}/{count}")
        canvas.restoreState()

    pdf_path = OUTPUT / PDF_NAME
    doc = PackDoc(str(pdf_path), pagesize=A4, title="Matei Patric - US Client Document Pack v2",
                  author="Matei Patric", subject="Unfilled software-services templates; version 2",
                  leftMargin=MARGIN, rightMargin=MARGIN, topMargin=58, bottomMargin=49,
                  allowSplitting=1, pageCompression=1)
    frame = Frame(MARGIN, 49, CONTENT_WIDTH, HEIGHT - 58 - 49,
                  leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
    doc.addPageTemplates(PageTemplate(id="client", frames=frame, onPage=decorate))
    doc.build(story)
    reader = PdfReader(pdf_path)
    if len(reader.pages) != count:
        raise AssertionError(f"Layout overflow: expected {count}, got {len(reader.pages)} pages")
    texts = [page.extract_text() for page in reader.pages]
    for number, (content_page, meta) in enumerate(zip(texts, page_meta, strict=True), 1):
        source_page = (SOURCE / meta[0]).read_text(encoding="utf-8").split("<!-- page -->")[meta[1] - 1]
        expected_title = source_page.strip().splitlines()[0][2:]
        if expected_title not in content_page:
            raise AssertionError(f"Wrong page title at {number}: {expected_title}")
    full_text = "\n".join(texts)
    for forbidden in ["under 18", "under eighteen", "INTERNAL_README", "Ikira Company", "CVV", "VAT-registered provider"]:
        if forbidden.lower() in full_text.lower():
            raise AssertionError(f"Unexpected client-pack content: {forbidden}")
    if reader.get_fields():
        raise AssertionError("This library is intentionally a static PDF, not a fillable or signed artifact")
    manifest = {"output": PDF_NAME, "pages": count, "sources": sources,
                "pdf_sha256": hashlib.sha256(pdf_path.read_bytes()).hexdigest(),
                "unfilled_template": True, "original_v1_modified": False,
                "visual_inspection_required": True}
    (QA / "v2_build_manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")
    (QA / "v2_extracted_text.txt").write_text(full_text, encoding="utf-8")
    zip_path = OUTPUT / "Matei_Patric_Document_Pack_v2_EDITABLE_AND_INTERNAL.zip"
    with ZipFile(zip_path, "w", compression=ZIP_DEFLATED) as archive:
        archive.write(pdf_path, f"output/pdf/{PDF_NAME}")
        for name in FILES:
            archive.write(SOURCE / name, f"docs/client-documents-v2/{name}")
        for name in ["INTERNAL_README_RU.md", "payment_records_template.csv"]:
            archive.write(SOURCE / name, f"docs/client-documents-v2/{name}")
        archive.write(Path(__file__), "scripts/build_client_document_pack_v2.py")
    print(json.dumps({"pdf": str(pdf_path), "pages": count, "zip": str(zip_path)}, indent=2))


if __name__ == "__main__":
    main()
