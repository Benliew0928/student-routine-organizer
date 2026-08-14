from __future__ import annotations

import sys
import zipfile
from pathlib import Path

from lxml import etree


W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
NS = {"w": W}


def finalize(path: Path) -> None:
    temporary = path.with_suffix(".finalizing.docx")
    with zipfile.ZipFile(path, "r") as source, zipfile.ZipFile(
        temporary, "w", zipfile.ZIP_DEFLATED
    ) as target:
        for item in source.infolist():
            data = source.read(item.filename)
            if item.filename == "word/settings.xml":
                settings = etree.fromstring(data)
                update_fields = settings.find(f"{{{W}}}updateFields")
                if update_fields is None:
                    update_fields = etree.Element(f"{{{W}}}updateFields")
                    settings.append(update_fields)
                update_fields.set(f"{{{W}}}val", "true")
                data = etree.tostring(
                    settings,
                    xml_declaration=True,
                    encoding="UTF-8",
                    standalone=True,
                )
            target.writestr(item, data)
    temporary.replace(path)


if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: finalize_docx_xml.py DOCUMENT.docx")
    finalize(Path(sys.argv[1]))
