<?php

namespace App\Service;

use App\Entity\CurriculumVitae;
use App\Entity\Position;
use App\Repository\CurriculumVitaeRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class CvExportService
{
    public function __construct(
        private readonly CurriculumVitaeRepository $cvs,
        private readonly CvAssembler $assembler,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function spreadsheetForPosition(Position $position): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CVs');

        $headers = ['Candidate', 'Email', 'Location', 'Likes', 'Updated'];
        foreach ($position->getTemplateAttributes() as $slot) {
            $headers[] = $slot->getAttribute()->getName();
        }
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($this->cvs->findPublishedForPosition($position) as $cv) {
            $assembled = $this->assembler->assemble($cv);
            $values = [];
            foreach ($assembled['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $values[$item['definition']->getId()] = $item['display'];
                }
            }

            $line = [
                $cv->getOwner()->getDisplayName(),
                $cv->getOwner()->getEmail(),
                $cv->getOwner()->getLocation(),
                $cv->getLikeCount(),
                $cv->getUpdatedAt()->format('Y-m-d H:i'),
            ];
            foreach ($position->getTemplateAttributes() as $slot) {
                $line[] = $values[$slot->getAttribute()->getId()] ?? '';
            }
            $sheet->fromArray($line, null, 'A'.$row);
            ++$row;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cvs').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);

        return $tmp;
    }

    public function printableHtml(CurriculumVitae $cv): string
    {
        $url = $this->urls->generate('cv_show', ['id' => $cv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $qr = (new Builder(writer: new PngWriter(), data: $url, size: 180))->build();

        return $this->twig->render('cv/print.html.twig', [
            'cv' => $cv,
            'assembled' => $this->assembler->assemble($cv),
            'qr' => $qr->getDataUri(),
            'url' => $url,
        ]);
    }

    public function pdf(CurriculumVitae $cv): string
    {
        $url = $this->urls->generate('cv_show', ['id' => $cv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $qr = (new Builder(writer: new PngWriter(), data: $url, size: 180))->build();
        $html = $this->twig->render('cv/pdf.html.twig', [
            'cv' => $cv,
            'assembled' => $this->assembler->assemble($cv),
            'qr' => $qr->getDataUri(),
            'url' => $url,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
