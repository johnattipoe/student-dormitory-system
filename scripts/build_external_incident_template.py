from pathlib import Path
import zipfile
from xml.sax.saxutils import escape

output_path = Path(r"C:\Users\ATTIPOE JOHN WILSON\Desktop\student-dormitory-system\public\templates\external-incident-template.docx")
output_path.parent.mkdir(parents=True, exist_ok=True)


def paragraph(text: str, *, bold: bool = False, color: str | None = None, size: int | None = None, style: str | None = None) -> str:
    text = escape(text)
    runs = []
    run_props = []
    if bold:
        run_props.append("<w:b/>")
    if color:
        run_props.append(f"<w:color w:val=\"{color}\"/>")
    if size:
        run_props.append(f"<w:sz w:val=\"{size}\"/>")
    if run_props:
        runs.append(f"<w:r><w:rPr>{''.join(run_props)}</w:rPr><w:t xml:space=\"preserve\">{text}</w:t></w:r>")
    else:
        runs.append(f"<w:r><w:t xml:space=\"preserve\">{text}</w:t></w:r>")

    if style:
        return f'<w:p><w:pPr><w:pStyle w:val="{style}"/></w:pPr>{"".join(runs)}</w:p>'
    return f'<w:p>{"".join(runs)}</w:p>'


def title_block() -> str:
    return (
        '<w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr>'
        '<w:r><w:rPr><w:b/><w:color w:val="1B365C"/><w:sz w:val="28"/></w:rPr><w:t>MAWULI</w:t></w:r>'
        '<w:r><w:rPr><w:color w:val="1B365C"/><w:sz w:val="28"/></w:rPr><w:t xml:space="preserve"> SCHOOL INCIDENT REPORT FORM</w:t></w:r>'
        '</w:p>'
        '<w:p><w:r><w:rPr><w:b/><w:color w:val="B42828"/><w:sz w:val="20"/></w:rPr><w:t xml:space="preserve">OFFICIAL DISCIPLINARY &amp; BEHAVIORAL REPORT</w:t></w:r></w:p>'
        '<w:p><w:r><w:t xml:space="preserve"></w:t></w:r></w:p>'
    )


def section_header(text: str) -> str:
    return paragraph(text, bold=True, color='000000', size=18, style='Heading1')


def blank_line() -> str:
    return '<w:p><w:r><w:t xml:space="preserve"></w:t></w:r></w:p>'


lines = [
    "MAWULI SCHOOL INCIDENT REPORT FORM",
    "OFFICIAL DISCIPLINARY & BEHAVIORAL REPORT",
    "",
    "1. Student Information",
    "Student ID: ______________________________",
    "Student Name: ____________________________",
    "Class: _________________________________",
    "House / Dormitory: ______________________",
    "Room / Bed: _____________________________",
    "Incident Number: ______________________",
    "Date: _________________________________",
    "Time: _________________________________",
    "Location: _____________________________",
    "Incident Type: ________________________",
    "Severity: _____________________________",
    "Reported By: _________________________",
    "Other Students Involved: _____________",
    "Witnesses: ___________________________",
    "",
    "2. Incident Details",
    "Brief description of the incident:",
    "________________________________________________________________________",
    "________________________________________________________________________",
    "",
    "3. Student Statement",
    "Student explanation:",
    "________________________________________________________________________",
    "________________________________________________________________________",
    "Date of statement: ____________________",
    "Student signature: _____________________",
    "Witness / Officer signature: __________",
    "",
    "4. Bond / Undertaking",
    "Type of bond: __________________________",
    "Reason: _______________________________",
    "Terms / Conditions:",
    "________________________________________________________________________",
    "Student acknowledgement: ______________",
    "Parent / Guardian acknowledgement: ______",
    "Date signed: ___________________________",
    "Signature: _____________________________",
    "",
    "5. Disciplinary Action",
    "Warning   Counseling   Parent contact   Housemaster action   Committee referral   Other: ______",
    "",
    "6. Follow-up",
    "Follow-up date: _______________________",
    "Officer responsible: ___________________",
    "Follow-up notes:",
    "________________________________________________________________________",
    "Status: Open / Under Review / Resolved / Closed",
]

content_types = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
'''

rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
'''

core = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>External Incident Report Template</dc:title>
  <dc:creator>Student Dormitory System</dc:creator>
  <cp:lastModifiedBy>Student Dormitory System</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">2026-09-13T00:00:00Z</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">2026-09-13T00:00:00Z</dcterms:modified>
</cp:coreProperties>
'''

app = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Student Dormitory System</Application>
</Properties>
'''

styles = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:rPr><w:b/><w:sz w:val="32"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Heading 1"/>
    <w:rPr><w:b/><w:sz w:val="18"/></w:rPr>
  </w:style>
</w:styles>
'''

document_xml = (
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    '<w:body>'
    + title_block()
    + ''.join(
        paragraph(line, bold=(line.startswith(('1.', '2.', '3.', '4.', '5.', '6.'))), color=('B42828' if line.startswith(('1.', '2.', '3.', '4.', '5.', '6.')) else None), size=(18 if line.startswith(('1.', '2.', '3.', '4.', '5.', '6.')) else None), style=('Heading1' if line.startswith(('1.', '2.', '3.', '4.', '5.', '6.')) else None))
        if line != '' else blank_line()
        for line in lines[2:]
    )
    + '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
    + '</w:body></w:document>'
)

with zipfile.ZipFile(output_path, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', content_types)
    z.writestr('_rels/.rels', rels)
    z.writestr('docProps/core.xml', core)
    z.writestr('docProps/app.xml', app)
    z.writestr('word/document.xml', document_xml)
    z.writestr('word/styles.xml', styles)

print(f"Created {output_path}")
print(f"Size: {output_path.stat().st_size} bytes")
print(sorted(zipfile.ZipFile(output_path).namelist()))
