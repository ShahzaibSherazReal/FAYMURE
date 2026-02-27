<?php
/**
 * Minimal PDF generator (no external dependencies).
 * Supports AddPage(), SetFont(), Cell(), MultiCell(), Ln(), Output().
 */
class SimplePdf {
    private $buffer = '';
    private $pageBuffers = [];
    private $font = 'Helvetica';
    private $fontSize = 10;
    private $fontStyle = '';
    private $x = 20;
    private $y = 20;
    private $w = 210 - 40;
    private $h = 297 - 40;
    private $lineHeight = 6;
    private $margin = 20;

    public function AddPage($orientation = 'P') {
        if ($this->buffer !== '') {
            $this->pageBuffers[] = $this->buffer;
            $this->buffer = '';
        }
        $this->x = $this->margin;
        $this->y = $this->margin;
    }

    public function SetFont($family = 'Helvetica', $style = '', $size = 10) {
        $this->font = $family;
        $this->fontStyle = $style;
        $this->fontSize = $size;
        $this->lineHeight = max(5, $size * 0.5);
    }

    public function Cell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L') {
        $txt = $this->escape($txt);
        $this->emitText($txt, $this->x, $this->y, $this->fontSize);
        $this->x += $w;
        if ($ln > 0) {
            $this->x = $this->margin;
            $this->y += $h;
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false) {
        $lines = explode("\n", str_replace("\r", '', $txt));
        foreach ($lines as $line) {
            $this->emitText($this->escape($line), $this->x, $this->y, $this->fontSize);
            $this->y += $this->lineHeight;
        }
        $this->x = $this->margin;
    }

    public function Ln($h = null) {
        $this->y += $h !== null ? $h : $this->lineHeight;
        $this->x = $this->margin;
    }

    public function SetXY($x, $y) {
        $this->x = $x;
        $this->y = $y;
    }

    public function GetY() { return $this->y; }
    public function SetY($y) { $this->y = $y; }
    public function GetX() { return $this->x; }
    public function SetX($x) { $this->x = $x; }

    private function escape($s) {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function emitText($text, $x, $y, $size) {
        $pdfY = 842 - $y; // A4 height in points (842)
        $this->buffer .= "BT /F1 " . $size . " Tf " . $x . " " . $pdfY . " Td (" . $text . ") Tj ET\n";
    }

    public function Output($dest = 'I', $name = 'document.pdf') {
        if ($this->buffer !== '') {
            $this->pageBuffers[] = $this->buffer;
            $this->buffer = '';
        }
        if (empty($this->pageBuffers)) {
            $this->pageBuffers[] = " ";
        }
        $n = count($this->pageBuffers);
        $out = "%PDF-1.4\n";
        $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (3 + $i * 2) . " 0 R";
        }
        $out .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . $n . " >>\nendobj\n";
        $fontObj = 3 + $n * 2;
        for ($i = 0; $i < $n; $i++) {
            $pageObj = 3 + $i * 2;
            $contentObj = 4 + $i * 2;
            $stream = $this->pageBuffers[$i];
            $out .= $contentObj . " 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
            $out .= $pageObj . " 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 " . $fontObj . " 0 R >> >> /MediaBox [0 0 595 842] /Contents " . $contentObj . " 0 R >>\nendobj\n";
        }
        $out .= $fontObj . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $out .= "trailer\n<< /Size " . ($fontObj + 1) . " /Root 1 0 R >>\nstartxref\n" . strlen($out) . "\n%%EOF\n";
        if ($dest === 'D' || $dest === 'I') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($dest === 'D' ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $out;
        }
        return $out;
    }

    /** Call this to build content before Output(); use SetXY, Cell, MultiCell, Ln. */
    public function ContentBuffer() {
        return $this->buffer;
    }
}
