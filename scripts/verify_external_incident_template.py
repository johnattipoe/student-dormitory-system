from pathlib import Path
import zipfile

p = Path('C:/Users/ATTIPOE JOHN WILSON/Desktop/student-dormitory-system/public/templates/external-incident-template.docx')
print('exists=' + str(p.exists()))
print('size=' + str(p.stat().st_size if p.exists() else 0))
print('is_zip=' + str(zipfile.is_zipfile(p) if p.exists() else False))
if p.exists() and zipfile.is_zipfile(p):
    with zipfile.ZipFile(p) as z:
        names = z.namelist()
        print('entries=' + '; '.join(names))
        xml = z.read('word/document.xml').decode('utf-8', 'ignore')
        print('has_title=' + str('EXTERNAL INCIDENT REPORT TEMPLATE' in xml))
        print('has_student_info=' + str('1. Student Information' in xml))
        print('has_followup=' + str('6. Follow-up' in xml))
