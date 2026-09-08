import os
import re
from docx import Document
from docx.shared import Pt, RGBColor, Inches
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

def set_cell_background(cell, fill, color=None, val=None):
    """
    Set cell background color
    """
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:fill'), fill)
    if color:
        shd.set(qn('w:color'), color)
    if val:
        shd.set(qn('w:val'), val)
    tcPr.append(shd)

def create_premium_docx(md_path, docx_path):
    doc = Document()
    
    # Define some institutional colors
    BRAND_BLUE = RGBColor(41, 128, 185)
    DARK_BLUE = RGBColor(44, 62, 80)
    GRAY_TEXT = RGBColor(84, 110, 122)
    CRITICAL_RED = RGBColor(192, 57, 43)
    HIGH_ORANGE = RGBColor(211, 84, 0)
    MEDIUM_YELLOW = RGBColor(241, 196, 15)
    
    # Setup styles
    style = doc.styles['Normal']
    style.font.name = 'Segoe UI'
    style.font.size = Pt(11)
    
    h1 = doc.styles['Heading 1']
    h1.font.name = 'Segoe UI Semibold'
    h1.font.size = Pt(20)
    h1.font.color.rgb = DARK_BLUE
    
    h2 = doc.styles['Heading 2']
    h2.font.name = 'Segoe UI Semibold'
    h2.font.size = Pt(16)
    h2.font.color.rgb = BRAND_BLUE
    
    h3 = doc.styles['Heading 3']
    h3.font.name = 'Segoe UI Semibold'
    h3.font.size = Pt(13)
    h3.font.color.rgb = DARK_BLUE

    # --- COVER PAGE ---
    # Add a title at the center
    doc.add_paragraph('\n' * 5)
    title_p = doc.add_paragraph()
    title_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title_run = title_p.add_run("Security Audit & Deployment Pricing")
    title_run.font.size = Pt(32)
    title_run.font.bold = True
    title_run.font.color.rgb = DARK_BLUE
    
    subtitle_p = doc.add_paragraph()
    subtitle_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    subtitle_run = subtitle_p.add_run("eSchool SaaS v1.8.0")
    subtitle_run.font.size = Pt(18)
    subtitle_run.font.color.rgb = BRAND_BLUE
    
    doc.add_paragraph('\n' * 10)
    
    info_p = doc.add_paragraph()
    info_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    info_run = info_p.add_run("Audit Date: 10 April 2026\nPrepared by: Antigravity Security Analysis Engine\nClassification: CONFIDENTIAL")
    info_run.font.size = Pt(12)
    info_run.font.color.rgb = GRAY_TEXT
    
    doc.add_page_break()
    
    # --- END COVER PAGE ---

    with open(md_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    current_table = []
    in_table = False

    def handle_style_text(paragraph, text):
        """Basic markdown bold/italic parsing"""
        remaining = text
        while '**' in remaining or '*' in remaining:
            # Matches bold **text** or *italic* text
            match = re.search(r'(\*\*(.*?)\*\*)|(\*(.*?)\*)', remaining)
            if not match:
                paragraph.add_run(remaining)
                return
            
            # Text before the match
            paragraph.add_run(remaining[:match.start()])
            
            # Bold
            if match.group(1):
                run = paragraph.add_run(match.group(2))
                run.bold = True
            # Italic
            elif match.group(3):
                run = paragraph.add_run(match.group(4))
                run.italic = True
            
            remaining = remaining[match.end():]
        paragraph.add_run(remaining)

    def finish_table(table_data):
        if not table_data: return
        rows_content = []
        for r in table_data:
            if re.match(r'^[\s|:-]+$', r.strip()): continue
            cells = [c.strip() for c in r.split('|')]
            # Handle leading/trailing empty cells from split
            if cells[0] == '': cells = cells[1:]
            if cells and cells[-1] == '': cells = cells[:-1]
            if cells: rows_content.append(cells)
        
        if not rows_content: return
        
        table = doc.add_table(rows=len(rows_content), cols=len(rows_content[0]))
        table.style = 'Table Grid'
        table.autofit = True
        
        for i, row_data in enumerate(rows_content):
            row = table.rows[i]
            for j, cell_text in enumerate(row_data):
                if j < len(table.columns):
                    cell = table.cell(i, j)
                    # Clear cell text
                    cell.text = ""
                    p = cell.paragraphs[0]
                    # Format text with icons
                    clean_text = cell_text
                    
                    # Style based on severity
                    color = None
                    if "CRITICAL" in clean_text.upper() or "🔴" in clean_text:
                        color = CRITICAL_RED
                    elif "HIGH" in clean_text.upper() or "🟠" in clean_text:
                        color = HIGH_ORANGE
                    elif "MEDIUM" in clean_text.upper() or "🟡" in clean_text:
                        color = DARK_BLUE # Using dark blue for medium instead of yellow for readability
                    
                    # Handle Bold
                    handle_style_text(p, clean_text)
                    
                    if i == 0: # Header
                        p.runs[0].bold = True
                        set_cell_background(cell, 'ECEFF1') # Light Gray-Blue
                    
                    # Zebra striping
                    elif i % 2 == 0:
                        set_cell_background(cell, 'F9F9F9')

    for line in lines:
        line = line.strip()
        
        if line.startswith('|'):
            in_table = True
            current_table.append(line)
            continue
        elif in_table:
            finish_table(current_table)
            current_table = []
            in_table = False
        
        if not line:
            doc.add_paragraph()
            continue
            
        if line.startswith('# '):
            doc.add_heading(line[2:], level=1)
        elif line.startswith('## '):
            doc.add_heading(line[3:], level=2)
        elif line.startswith('### '):
            doc.add_heading(line[4:], level=3)
        elif line.startswith('#### '):
            doc.add_heading(line[5:], level=4)
        elif line.startswith('- '):
            p = doc.add_paragraph(style='List Bullet')
            handle_style_text(p, line[2:])
        elif line.startswith('> '):
            text = line[2:]
            type_map = {
                '[!IMPORTANT]': ('IMPORTANT', RGBColor(25, 118, 210)),
                '[!WARNING]': ('WARNING', RGBColor(255, 152, 0)),
                '[!CAUTION]': ('CAUTION', RGBColor(211, 47, 47)),
                '[!NOTE]': ('NOTE', RGBColor(69, 90, 100)),
                '[!TIP]': ('TIP', RGBColor(56, 142, 60))
            }
            
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Pt(12)
            
            found_type = False
            for key, (label, color) in type_map.items():
                if key in text:
                    run = p.add_run(f"{label}: ")
                    run.bold = True
                    run.font.color.rgb = color
                    text = text.replace(key, "").strip()
                    found_type = True
                    break
            
            if not found_type:
                p.add_run().italic = True
            
            run = p.add_run(text)
            run.italic = True
            run.font.color.rgb = GRAY_TEXT
            
        elif line == '---':
            doc.add_paragraph().add_run().add_break(WD_BREAK.LINE)
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            run = p.add_run('━' * 40)
            run.font.color.rgb = RGBColor(224, 224, 224)
        else:
            p = doc.add_paragraph()
            handle_style_text(p, line)

    doc.save(docx_path)
    print(f"Premium DOCX saved to {docx_path}")

if __name__ == "__main__":
    input_file = r"d:\PROJECTS\WEBSITES\eSchool SaaS v1.8.0\eschool_saas_security_audit_and_pricing.md.resolved"
    output_file = r"d:\PROJECTS\WEBSITES\eSchool SaaS v1.8.0\eSchool_SaaS_Audit_Report_2026.docx"
    create_premium_docx(input_file, output_file)
