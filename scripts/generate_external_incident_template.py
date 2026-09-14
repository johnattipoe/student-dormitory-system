from pathlib import Path
import zipfile

output_path = Path(r"C:\Users\ATTIPOE JOHN WILSON\Desktop\student-dormitory-system\public\templates\external-incident-template.docx")
output_path.parent.mkdir(parents=True, exist_ok=True)

lines = [
    "EXTERNAL INCIDENT REPORT TEMPLATE",
    "",
    "1. Student Information",
    "Student ID: ______________________________",
    "Student Name: ____________________________",
    "Class: _________________________________",
    "House/Dormitory: ______________________",
    "Room/Bed: _____________________________",
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
    "Date: _________________________________",
    "Time: _________________________________",
    "Location: _____________________________",
    "Incident Type: ________________________",
    "Severity: _____________________________",
    "Reported By: _________________________",
    "Other Students Involved: _____________",
    "Witnesses: ___________________________",
    "",
    "3. Incident Description",
    "Staff/Officer description: ________________________________________________",
    "Immediate action taken: ________________________________________________",
    "",
    "4. Student's Statement",
    "Student explanation: _________________________________________________",
    "Date of statement: ____________________",
    "Student signature: _____________________",
    "Witness/Staff signature: ______________",
    "",
    "5. Bond / Undertaking",
    "Type of bond: __________________________",
    "Reason: _______________________________",
    "Terms / Conditions: ____________________",
    "Student acknowledgement: ______________",
    "Parent/Guardian acknowledgement: ______",
    "Date signed: ___________________________",
    "Signature: _____________________________",
    "Uploaded copy of signed bond: _________",
    "",
    "6. Disciplinary Action",
    "Warning    Counselling    Parent/Guardian contacted    Housemaster action    Disciplinary committee referral    Other: ________",
    "",
    "7. Follow-up",
    "Follow-up date: _______________________",
    "Officer responsible: ___________________",
    "Follow-up notes: ____________________________________________________",
    "Status: Open / Under Review / Resolved / Closed",
]


def par(text: str) -> str:
    escaped = text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return f"<w:p><w:r><w:t>{escaped}</w:t></w:r></w:p>"

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
</w:styles>
'''

document = (
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    '<w:body>'
    + ''.join(par(line) for line in lines)
    + '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
    + '</w:body></w:document>'
)

with zipfile.ZipFile(output_path, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', content_types)
    z.writestr('_rels/.rels', rels)
    z.writestr('docProps/core.xml', core)
    z.writestr('docProps/app.xml', app)
    z.writestr('word/document.xml', document)
    z.writestr('word/styles.xml', styles)

print(f'Created {output_path} ({output_path.stat().st_size} bytes)')
print('Entries:', sorted(zipfile.ZipFile(output_path).namelist()))
