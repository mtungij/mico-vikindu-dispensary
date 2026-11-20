<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PrescriptionItem;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceResponsiveRenderingTest extends TestCase
{
    #[DataProvider('invoiceItems')]
    public function test_mobile_cards_and_desktop_table_preserve_financial_values_and_directions(bool $medicine, int $paid, string $status): void
    {
        $item = (new InvoiceItem)->forceFill([
            'id' => 1,
            'description' => 'Historical description',
            'description_snapshot' => 'Prescribed medicine or service',
            'quantity' => 10,
            'unit_price' => 100,
            'gross_amount' => 1000,
            'total_amount' => 1000,
            'patient_amount' => 800,
            'insurance_amount' => 200,
            'paid_amount' => $paid,
            'status' => $status,
        ]);
        $prescription = (new PrescriptionItem)->forceFill([
            'medication_name' => 'Doctor prescribed medicine',
            'strength' => '500 mg',
            'dose' => '1 tablet',
            'route' => 'PO',
            'frequency' => 'BD',
            'duration_value' => 5,
            'duration_unit' => 'days',
            'quantity' => 10,
            'dispensed_quantity' => 2,
            'instructions' => "Take after food.\n<script>alert('unsafe')</script>",
            'indication' => 'Pain relief',
        ]);
        $prescription->setRelation('invoiceItem', $item)->setRelation('medicine', null);
        $item->setRelation('prescriptionItem', $medicine ? $prescription : null);
        $invoice = (new Invoice)->forceFill(['id' => 1, 'patient_amount' => 800, 'paid_amount' => $paid, 'balance_amount' => max(0, 800 - $paid)]);
        $invoice->setRelation('patient', null)->setRelation('items', collect([$item]))->setRelation('payments', collect());

        $html = view('livewire.billing.invoices.show', [
            'invoice' => $invoice,
            'showPaymentModal' => false,
            'showCashierSessionModal' => false,
        ])->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $mobile = $xpath->query('//*[@data-invoice-item-cards]')->item(0);
        $desktop = $xpath->query('//*[@data-invoice-item-table]')->item(0);

        $this->assertStringContainsString('lg:hidden', $mobile->getAttribute('class'));
        $this->assertStringContainsString('hidden', $desktop->getAttribute('class'));
        $this->assertStringContainsString('lg:block', $desktop->getAttribute('class'));
        $this->assertCount(1, $xpath->query('.//article', $mobile));
        $this->assertCount(0, $xpath->query('.//table', $mobile));
        $this->assertCount(9, $xpath->query('.//thead/tr/th', $desktop));
        $expectedStatus = $medicine && $paid > 0 && $paid < 800 && $status === 'pending' ? 'Partially Paid' : $status;
        foreach ([$mobile, $desktop] as $layout) {
            foreach (['Prescribed medicine or service', '1,000.00', '800.00', '200.00', number_format($paid, 2), number_format(max(0, 800 - $paid), 2), $expectedStatus] as $value) {
                $this->assertStringContainsString($value, $layout->textContent);
            }
            $this->assertStringNotContainsString('Historical description', $layout->textContent);
            $this->assertCount(0, $xpath->query('.//script', $layout));
            if ($medicine) {
                foreach (['Strength:', '500 mg', 'Dose:', '1 tablet', 'Route:', 'Oral', 'Frequency:', 'Twice daily', 'Duration:', '5 days', 'Prescribed quantity:', 'Dispensed quantity:', 'Special / PRN instructions:', 'Take after food.', 'Indication / reason for PRN:', 'Pain relief'] as $direction) {
                    $this->assertStringContainsString($direction, $layout->textContent);
                }
                $this->assertCount(1, $xpath->query('.//p[contains(., "Doctor medication order:")]', $layout));
            } else {
                $this->assertStringNotContainsString('Doctor medication order:', $layout->textContent);
            }
        }
    }

    public static function invoiceItems(): array
    {
        return [
            'partially paid medicine' => [true, 400, 'pending'],
            'unpaid medicine' => [true, 0, 'pending'],
            'paid medicine' => [true, 800, 'paid'],
            'cancelled medicine' => [true, 400, 'cancelled'],
            'service without directions' => [false, 400, 'pending'],
        ];
    }
}
