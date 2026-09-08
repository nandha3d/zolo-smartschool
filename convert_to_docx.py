import os
import re
from docx import Document
from docx.shared import Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

def set_cell_border(cell, **kwargs):
    """
    Set cell borders
    Usage: set_cell_border(cell, top={"sz": 12, "val": "single", "color": "#FF0000", "space": "0"}, ...)
    """
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    for side in ["top", "left", "bottom", "right"]:
        edge = kwargs.get(side)
        if edge:
            tag = 'w:{}'.format(side)
            element = tcPr.find(qn(tag))
            if element is None:
                element = OxmlElement(tag)
                tcPr.append(element)
            for key, val in edge.items():
                element.set(qn('w:{}'.format(key)), str(val))

def convert_md_to_docx(md_path, docx_path):
    doc = Document()
    
    # Title
    style = doc.styles['Title']
    font = style.font
    font.name = 'Arial'
    font.size = Pt(24)
    font.bold = True
    
    with open(md_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    current_table = []
    in_table = False

    def finish_table(table_data):
        if not table_data:
            return
        # Clean rows (remove header separator like |---|---|)
        rows = []
        for r in table_data:
            if re.match(r'^[\s|:-]+$', r.strip()):
                continue
            # Split by | and strip
            cells = [c.strip() for c in r.split('|') if c.strip() or r.count('|') > 1]
            if cells:
                rows.append(cells)
        
        if not rows:
            return
        
        table = doc.add_table(rows=len(rows), cols=len(rows[0]))
        table.style = 'Table Grid'
        
        for i, row_data in enumerate(rows):
            for j, cell_text in enumerate(row_data):
                if j < len(table.columns):
                    cell = table.cell(i, j)
                    # Remove markdown bold/italic for simplicity or handle them
                    clean_text = re.sub(r'\*\*(.*?)\*\*', r'\1', cell_text)
                    clean_text = re.sub(r'✅|🔴|⚠️|🟠|🟡', lambda m: m.group(0) + " ", clean_text)
                    cell.text = clean_text
                    
                    # Style header row
                    if i == 0:
                        run = cell.paragraphs[0].runs[0]
                        run.bold = True
                        shading_elm_1 = OxmlElement('w:shd')
                        shading_elm_1.set(qn('w:fill'), 'D9D9D9')
                        cell._tc.get_or_add_tcPr().append(shading_elm_1)

    for line in lines:
        line = line.strip()
        
        # Handle Tables
        if line.startswith('|'):
            in_table = True
            current_table.append(line)
            continue
        elif in_table:
            finish_table(current_table)
            current_table = []
            in_table = False
        
        # Skip empty lines if they were just table separators
        if not line:
            doc.add_paragraph()
            continue
            
        # Headers
        if line.startswith('# '):
            p = doc.add_paragraph(line[2:], style='Heading 1')
        elif line.startswith('## '):
            p = doc.add_paragraph(line[3:], style='Heading 2')
        elif line.startswith('### '):
            p = doc.add_paragraph(line[4:], style='Heading 3')
        elif line.startswith('#### '):
            p = doc.add_paragraph(line[5:], style='Heading 4')
            
        # Bullet points
        elif line.startswith('- '):
            p = doc.add_paragraph(line[2:], style='List Bullet')
        
        # Blockquotes / Alerts
        elif line.startswith('> '):
            text = line[2:]
            if '[!IMPORTANT]' in text:
                text = text.replace('[!IMPORTANT]', 'IMPORTANT: ')
            elif '[!WARNING]' in text:
                text = text.replace('[!WARNING]', 'WARNING: ')
            elif '[!CAUTION]' in text:
                text = text.replace('[!CAUTION]', 'CAUTION: ')
            elif '[!NOTE]' in text:
                text = text.replace('[!NOTE]', 'NOTE: ')
            elif '[!TIP]' in text:
                text = text.replace('[!TIP]', 'TIP: ')
            
            p = doc.add_paragraph(text)
            p.paragraph_format.left_indent = Pt(18)
            for run in p.runs:
                run.italic = True
                run.font.color.rgb = RGBColor(80, 80, 80)
                
        # Horizontal Rule
        elif line == '---':
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            run = p.add_run('__________________________________________________')
            run.font.color.rgb = RGBColor(200, 200, 200)

        # Regular Text
        else:
            # Handle Bold/Italic in regular text
            p = doc.add_paragraph()
            # Basic parsing for bold
            parts = re.split(r'(\*\*.*?\*\*)', line)
            for part in parts:
                if part.startswith('**') and part.endswith('**'):
                    run = p.add_run(part[2:-2])
                    run.bold = True
                else:
                    p.add_run(part)

    # Save
    doc.save(docx_path)
    print(f"Document saved to {docx_path}")

if __name__ == "__main__":
    input_file = r"d:\PROJECTS\WEBSITES\eSchool SaaS v1.8.0\eschool_saas_security_audit_and_pricing.md.resolved"
    output_file = r"d:\PROJECTS\WEBSITES\eSchool SaaS v1.8.0\eschool_saas_security_audit_and_pricing.docx"
    convert_md_to_docx(input_file, output_file)
