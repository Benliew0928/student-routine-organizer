param(
    [Parameter(Mandatory = $true)]
    [string]$DocumentPath,

    [Parameter(Mandatory = $true)]
    [string]$PdfPath
)

$word = $null
$document = $null

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $document = $word.Documents.Open($DocumentPath)

    foreach ($toc in $document.TablesOfContents) {
        $toc.Update()
    }
    foreach ($tof in $document.TablesOfFigures) {
        $tof.Update()
    }
    $document.Fields.Update() | Out-Null

    foreach ($section in $document.Sections) {
        foreach ($header in $section.Headers) {
            if ($header.Exists) {
                $header.Range.Fields.Update() | Out-Null
            }
        }
        foreach ($footer in $section.Footers) {
            if ($footer.Exists) {
                $footer.Range.Fields.Update() | Out-Null
            }
        }
    }

    # Word can reintroduce inherited body spacing when it refreshes a TOC.
    # Apply the approved 12 pt Times New Roman, single-spaced TOC formatting
    # after updating the field so the complete TOC remains on one page.
    foreach ($toc in $document.TablesOfContents) {
        $range = $toc.Range
        $range.Font.Name = "Times New Roman"
        $range.Font.Size = 12
        $range.ParagraphFormat.LineSpacingRule = 0
        $range.ParagraphFormat.SpaceBefore = 0
        $range.ParagraphFormat.SpaceAfter = 0
        $range.ParagraphFormat.KeepTogether = 0
        $range.ParagraphFormat.KeepWithNext = 0
        $range.ParagraphFormat.WidowControl = 0
    }

    foreach ($tof in $document.TablesOfFigures) {
        $range = $tof.Range
        $range.Font.Name = "Times New Roman"
        $range.Font.Size = 12
        $range.ParagraphFormat.LineSpacingRule = 0
        $range.ParagraphFormat.SpaceBefore = 0
        $range.ParagraphFormat.SpaceAfter = 0
    }

    $document.Repaginate()
    $document.Save()
    $pages = $document.ComputeStatistics(2)

    $pdfDirectory = Split-Path -Parent $PdfPath
    if (-not (Test-Path -LiteralPath $pdfDirectory)) {
        New-Item -ItemType Directory -Path $pdfDirectory | Out-Null
    }
    $document.ExportAsFixedFormat($PdfPath, 17)
    Write-Output "Word pages: $pages"
}
finally {
    if ($null -ne $document) {
        $document.Close(0)
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($document)
    }
    if ($null -ne $word) {
        $word.Quit()
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word)
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
