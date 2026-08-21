<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Patient;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PatientCardController extends Controller
{
    public function show(Patient $patient): View
    {
        return view('patients.card', $this->cardData($patient));
    }

    public function print(Patient $patient): View
    {
        return view('patients.card-print', $this->cardData($patient));
    }

    /** @return array<string, mixed> */
    private function cardData(Patient $patient): array
    {
        $facility = currentFacility();

        abort_unless($facility && $patient->facility_id === $facility->id, 404);
        Gate::authorize('printCard', $patient);

        $patient->loadMissing('primaryPayerProfile:id,patient_id,payer_type,is_primary');
        $qrPayload = route('patients.card', $patient, true);

        return [
            'patient' => $patient,
            'facility' => $facility,
            'facilitySlogan' => $this->facilitySlogan($facility),
            'logoDataUri' => $this->publicImageDataUri($facility->logo_path),
            'photoDataUri' => $this->publicImageDataUri($patient->passport_photo_path),
            'qrPayload' => $qrPayload,
            'qrDataUri' => $this->qrDataUri($qrPayload),
            'issueDate' => ($patient->registered_at ?? $patient->created_at)->format('d M Y'),
        ];
    }

    private function qrDataUri(string $payload): string
    {
        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 320,
            margin: 18,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }

    private function facilitySlogan(Facility $facility): ?string
    {
        if (blank($facility->receipt_header)) {
            return null;
        }

        $slogan = Str::squish(strip_tags($facility->receipt_header));

        return strcasecmp($slogan, $facility->name) === 0 ? null : Str::limit($slogan, 80);
    }

    private function publicImageDataUri(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
