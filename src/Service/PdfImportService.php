<?php

namespace Planer\PlanerBundle\Service;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;

class PdfImportService
{
    // ========== PDF import (pdftohtml CLI) ==========

    public function convertPdfToHtml(string $pdfContent): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdfimport_');
        file_put_contents($tmpFile, $pdfContent);

        try {
            $cmd = sprintf(
                'pdftohtml -zoom 1.0 -s -i -noframes -enc UTF-8 -stdout %s 2>/dev/null',
                escapeshellarg($tmpFile)
            );
            $output = shell_exec($cmd);

            if ($output === null || trim($output) === '') {
                throw new \RuntimeException('pdftohtml nie zwrócił żadnych danych.');
            }
        } finally {
            @unlink($tmpFile);
        }

        return $this->transformPdfHtml($output);
    }

    private function transformPdfHtml(string $html): string
    {
        $html = preg_replace_callback(
            '/font-family:([^;}"]+)/',
            function ($m) {
                $orig = strtolower($m[1]);
                if (str_contains($orig, 'times') || str_contains($orig, 'roman') || str_contains($orig, 'serif')) {
                    return "font-family:'DejaVu Serif', serif";
                }
                return "font-family:'DejaVu Sans', sans-serif";
            },
            $html
        );

        $html = preg_replace_callback(
            '/(\d+(?:\.\d+)?)px/',
            fn($m) => $m[1] . 'pt',
            $html
        );

        $html = preg_replace(
            '/<style type="text\/css">/',
            "<style type=\"text/css\">\n@page { size: A4; margin: 0; }\n",
            $html,
            1
        );

        $html = preg_replace('/\s*bgcolor="[^"]*"/', '', $html);
        $html = preg_replace('/\s*vlink="[^"]*"/', '', $html);
        $html = preg_replace('/\s*link="[^"]*"/', '', $html);
        $html = preg_replace('/\.(x|y|xy)flip\s*\{[^}]+\}/', '', $html);
        $html = preg_replace('/<meta name="(generator|date|author)"[^>]*\/>/', '', $html);
        $html = preg_replace('/<a name="\d+">\s*<\/a>/', '', $html);
        $html = preg_replace('/<!--.*?-->/', '', $html);

        $html = preg_replace_callback(
            '/<div id="page(\d+)-div" style="/',
            function ($m) {
                if ((int) $m[1] > 1) {
                    return '<div id="page' . $m[1] . '-div" style="page-break-before:always;';
                }
                return $m[0];
            },
            $html
        );

        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return trim($html);
    }

    // ========== DOCX import (PHPWord) ==========

    public function convertDocxToHtml(string $docxContent): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'docximport_') . '.docx';
        file_put_contents($tmpFile, $docxContent);

        try {
            $phpWord = IOFactory::load($tmpFile, 'Word2007');
        } finally {
            @unlink($tmpFile);
        }

        return $this->buildDocxHtml($phpWord);
    }

    private function buildDocxHtml(\PhpOffice\PhpWord\PhpWord $phpWord): string
    {
        $bodyHtml = '';
        $pageMargins = null;
        $defaultFontName = $phpWord->getDefaultFontName() ?: 'Times New Roman';

        foreach ($phpWord->getSections() as $sIndex => $section) {
            if ($sIndex > 0) {
                $bodyHtml .= "\n" . '<div style="page-break-before:always;"></div>' . "\n\n";
            }

            $style = $section->getStyle();
            if (!$pageMargins && $style) {
                $pageMargins = [
                    'top' => round($style->getMarginTop() / 567, 2),
                    'right' => round($style->getMarginRight() / 567, 2),
                    'bottom' => round($style->getMarginBottom() / 567, 2),
                    'left' => round($style->getMarginLeft() / 567, 2),
                ];
            }

            $inList = false;
            foreach ($section->getElements() as $element) {
                $isList = ($element instanceof ListItem || $element instanceof ListItemRun);

                if ($inList && !$isList) {
                    $bodyHtml .= "</ul>\n";
                    $inList = false;
                }
                if ($isList && !$inList) {
                    $bodyHtml .= "<ul>\n";
                    $inList = true;
                }

                $bodyHtml .= $this->renderDocxElement($element);
            }

            if ($inList) {
                $bodyHtml .= "</ul>\n";
            }
        }

        $margins = $pageMargins ?? ['top' => 1.5, 'right' => 2.0, 'bottom' => 1.5, 'left' => 2.0];
        $fontFamily = $this->mapFontForDompdf($defaultFontName);

        return $this->wrapDocxHtml($bodyHtml, $margins, $fontFamily);
    }

    private function renderDocxElement(AbstractElement $element): string
    {
        if ($element instanceof TextRun) {
            return $this->renderTextRun($element);
        }
        if ($element instanceof Text) {
            return $this->renderSimpleText($element);
        }
        if ($element instanceof TextBreak) {
            return "<p>&nbsp;</p>\n";
        }
        if ($element instanceof Table) {
            return $this->renderTable($element);
        }
        if ($element instanceof ListItemRun) {
            return $this->renderListItemRun($element);
        }
        if ($element instanceof ListItem) {
            return $this->renderListItem($element);
        }
        if ($element instanceof Title) {
            return $this->renderTitle($element);
        }

        return '';
    }

    private function renderTextRun(TextRun $textRun): string
    {
        $paraStyle = $textRun->getParagraphStyle();
        $pStyle = $this->buildParagraphCss($paraStyle);

        $innerHtml = '';
        foreach ($textRun->getElements() as $child) {
            if ($child instanceof Text) {
                $innerHtml .= $this->renderInlineText($child);
            } elseif ($child instanceof TextBreak) {
                $innerHtml .= "<br/>";
            }
        }

        $stripped = trim(strip_tags($innerHtml));
        if ($stripped === '' || $stripped === '&nbsp;') {
            return "<p>&nbsp;</p>\n";
        }

        $attr = $pStyle ? ' style="' . $pStyle . '"' : '';
        return "<p{$attr}>{$innerHtml}</p>\n";
    }

    private function renderSimpleText(Text $text): string
    {
        $paraStyle = $text->getParagraphStyle();
        $pStyle = $this->buildParagraphCss($paraStyle);
        $inner = $this->renderInlineText($text);

        $attr = $pStyle ? ' style="' . $pStyle . '"' : '';
        return "<p{$attr}>{$inner}</p>\n";
    }

    private function renderInlineText(Text $text): string
    {
        $content = htmlspecialchars($text->getText(), ENT_QUOTES, 'UTF-8');
        $font = $text->getFontStyle();

        if (!$font || !($font instanceof Font)) {
            return $content;
        }

        if ($font->isBold()) {
            $content = '<b>' . $content . '</b>';
        }
        if ($font->isItalic()) {
            $content = '<i>' . $content . '</i>';
        }
        $underline = $font->getUnderline();
        if ($underline && $underline !== 'none' && $underline !== Font::UNDERLINE_NONE) {
            $content = '<u>' . $content . '</u>';
        }
        if ($font->isStrikethrough()) {
            $content = '<s>' . $content . '</s>';
        }
        if ($font->isSuperScript()) {
            $content = '<sup>' . $content . '</sup>';
        }
        if ($font->isSubScript()) {
            $content = '<sub>' . $content . '</sub>';
        }

        $css = $this->buildFontCss($font);
        if ($css) {
            return '<span style="' . $css . '">' . $content . '</span>';
        }

        return $content;
    }

    private function buildFontCss(Font $font): string
    {
        $parts = [];

        $size = $font->getSize();
        if ($size && $size != 11) {
            $parts[] = 'font-size:' . $size . 'pt';
        }

        $name = $font->getName();
        if ($name) {
            $mapped = $this->mapFontForDompdf($name);
            $parts[] = "font-family:'" . $mapped . "'";
        }

        $color = $font->getColor();
        if ($color) {
            $rgb = method_exists($color, 'getRgb') ? $color->getRgb() : null;
            if ($rgb && $rgb !== '000000') {
                $parts[] = 'color:#' . $rgb;
            }
        }

        return implode('; ', $parts);
    }

    private function buildParagraphCss($paraStyle): string
    {
        if (!$paraStyle || !($paraStyle instanceof Paragraph)) {
            return '';
        }

        $parts = [];

        $align = $paraStyle->getAlignment();
        if ($align) {
            $cssAlign = match ($align) {
                'both', 'justify' => 'justify',
                'center' => 'center',
                'right', 'end' => 'right',
                default => '',
            };
            if ($cssAlign) {
                $parts[] = 'text-align:' . $cssAlign;
            }
        }

        $spaceBefore = $paraStyle->getSpaceBefore();
        if ($spaceBefore && $spaceBefore > 0) {
            $parts[] = 'margin-top:' . round($spaceBefore / 20) . 'pt';
        }

        $spaceAfter = $paraStyle->getSpaceAfter();
        if ($spaceAfter && $spaceAfter > 0) {
            $parts[] = 'margin-bottom:' . round($spaceAfter / 20) . 'pt';
        }

        $indent = $paraStyle->getIndentation();
        if ($indent) {
            $left = $indent->getLeft();
            if ($left && $left > 0) {
                $parts[] = 'margin-left:' . round($left / 20) . 'pt';
            }
            $firstLine = $indent->getFirstLine();
            if ($firstLine && $firstLine > 0) {
                $parts[] = 'text-indent:' . round($firstLine / 20) . 'pt';
            }
        }

        $lineHeight = $paraStyle->getLineHeight();
        if ($lineHeight && $lineHeight > 0 && $lineHeight != 1.0) {
            $parts[] = 'line-height:' . $lineHeight;
        }

        return implode('; ', $parts);
    }

    private function renderTable(Table $table): string
    {
        $html = '<table style="width:100%; border-collapse:collapse;">' . "\n";

        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $cellCss = 'vertical-align:top; padding:2pt 4pt;';

                $width = $cell->getWidth();
                if ($width && $width > 0) {
                    $cellCss .= ' width:' . round($width / 20) . 'pt;';
                }

                $cellStyle = $cell->getStyle();
                if ($cellStyle) {
                    $borders = '';
                    $bTop = $cellStyle->getBorderTopSize();
                    $bRight = $cellStyle->getBorderRightSize();
                    $bBottom = $cellStyle->getBorderBottomSize();
                    $bLeft = $cellStyle->getBorderLeftSize();

                    if ($bTop || $bRight || $bBottom || $bLeft) {
                        if ($bTop) $borders .= ' border-top:' . round($bTop / 8) . 'pt solid #000;';
                        if ($bRight) $borders .= ' border-right:' . round($bRight / 8) . 'pt solid #000;';
                        if ($bBottom) $borders .= ' border-bottom:' . round($bBottom / 8) . 'pt solid #000;';
                        if ($bLeft) $borders .= ' border-left:' . round($bLeft / 8) . 'pt solid #000;';
                        $cellCss .= $borders;
                    }
                }

                $html .= '<td style="' . $cellCss . '">';
                foreach ($cell->getElements() as $elem) {
                    $html .= $this->renderDocxElement($elem);
                }
                $html .= '</td>';
            }
            $html .= "</tr>\n";
        }

        $html .= "</table>\n";
        return $html;
    }

    private function renderListItem(ListItem $item): string
    {
        $textObj = $item->getTextObject();
        $inner = $textObj ? $this->renderInlineText($textObj) : htmlspecialchars((string) $item->getText());
        return '<li>' . $inner . "</li>\n";
    }

    private function renderListItemRun(ListItemRun $item): string
    {
        $innerHtml = '';
        foreach ($item->getElements() as $child) {
            if ($child instanceof Text) {
                $innerHtml .= $this->renderInlineText($child);
            } elseif ($child instanceof TextBreak) {
                $innerHtml .= '<br/>';
            }
        }
        return '<li>' . $innerHtml . "</li>\n";
    }

    private function renderTitle(Title $title): string
    {
        $depth = $title->getDepth() ?: 1;
        $tag = 'h' . min($depth, 6);
        $text = htmlspecialchars($title->getText(), ENT_QUOTES, 'UTF-8');
        return "<{$tag}>{$text}</{$tag}>\n";
    }

    private function mapFontForDompdf(string $fontName): string
    {
        $lower = strtolower($fontName);
        if (str_contains($lower, 'times') || str_contains($lower, 'roman') || str_contains($lower, 'serif') || str_contains($lower, 'georgia')) {
            return 'DejaVu Serif';
        }
        if (str_contains($lower, 'courier') || str_contains($lower, 'mono') || str_contains($lower, 'consol')) {
            return 'DejaVu Sans Mono';
        }
        return 'DejaVu Sans';
    }

    private function wrapDocxHtml(string $body, array $margins, string $fontFamily): string
    {
        $m = sprintf(
            '%scm %scm %scm %scm',
            $margins['top'], $margins['right'], $margins['bottom'], $margins['left']
        );

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: {$m};
        }
        body {
            font-family: '{$fontFamily}', serif;
            font-size: 11pt;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }
        p {
            margin: 0 0 2pt 0;
        }
        h1 { font-size: 16pt; margin: 12pt 0 6pt 0; }
        h2 { font-size: 14pt; margin: 10pt 0 5pt 0; }
        h3 { font-size: 12pt; margin: 8pt 0 4pt 0; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            vertical-align: top;
            padding: 2pt 4pt;
        }
        ul, ol {
            margin: 4pt 0;
            padding-left: 20pt;
        }
        li {
            margin-bottom: 2pt;
        }
    </style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }
}
