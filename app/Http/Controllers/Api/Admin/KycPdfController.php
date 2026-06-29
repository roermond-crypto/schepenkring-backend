<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycCase;
use Illuminate\Http\Response;

class KycPdfController extends Controller
{
    public function show(KycCase $kycCase): Response
    {
        $kycCase->load(['answers.question', 'documents', 'timeline', 'buyer', 'seller', 'yacht', 'location', 'broker', 'approvedBy']);

        $html = view('kyc.pdf', compact('kycCase'))->render();

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'X-KYC-Case-Number'   => $kycCase->case_number,
        ]);
    }
}
