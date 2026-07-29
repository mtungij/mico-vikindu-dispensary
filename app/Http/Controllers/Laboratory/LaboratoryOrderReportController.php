<?php

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryOrder;
use App\Services\LaboratoryReportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LaboratoryOrderReportController extends Controller
{
    public function view(LaboratoryOrder $laboratoryOrder, LaboratoryReportService $reports): View
    {
        Gate::authorize('viewReport', $laboratoryOrder);
        $order = $this->prepare($laboratoryOrder, $reports);
        $reports->recordAccess($order, auth()->user(), 'viewed');

        return view('laboratory.order-report', $this->viewData($order, $reports));
    }

    public function download(LaboratoryOrder $laboratoryOrder, LaboratoryReportService $reports): Response
    {
        Gate::authorize('downloadReport', $laboratoryOrder);
        $order = $this->prepare($laboratoryOrder, $reports);
        $reports->recordAccess($order, auth()->user(), 'downloaded');

        return $this->pdf($order, $reports, true);
    }

    public function print(LaboratoryOrder $laboratoryOrder, LaboratoryReportService $reports): Response
    {
        Gate::authorize('printReport', $laboratoryOrder);
        $order = $this->prepare($laboratoryOrder, $reports);
        $reports->recordAccess($order, auth()->user(), 'printed');

        return $this->pdf($order, $reports, false);
    }

    private function prepare(LaboratoryOrder $order, LaboratoryReportService $reports): LaboratoryOrder
    {
        try {
            return $reports->prepare($order);
        } catch (ValidationException $exception) {
            abort(422, $exception->errors()['report'][0] ?? LaboratoryReportService::INCOMPLETE_MESSAGE);
        }
    }

    private function pdf(LaboratoryOrder $order, LaboratoryReportService $reports, bool $download): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(view('laboratory.order-report', [
            ...$this->viewData($order, $reports),
            'pdf' => true,
        ])->render());
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
        $dompdf->getCanvas()->page_text(
            500,
            806,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0.39, 0.45, 0.55],
        );

        $filename = "{$order->report_number}-R{$order->report_revision}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function viewData(LaboratoryOrder $order, LaboratoryReportService $reports): array
    {
        return [
            'order' => $order,
            'results' => $reports->orderReleasedResults($order),
            'facility' => currentFacility(),
            'logoDataUri' => $reports->facilityLogoDataUri(currentFacility()),
            'signatureDataUris' => $reports->signatureDataUris($order),
            'pdf' => false,
        ];
    }
}
