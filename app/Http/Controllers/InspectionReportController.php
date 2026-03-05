<?php

namespace App\Http\Controllers;

use App\Models\BoatInspection;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InspectionReportController extends Controller
{
    /**
     * GET /api/yachts/{id}/inspection-report
     *
     * Returns the inspection data as JSON for frontend display.
     */
    public function show($id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);

        $inspection = BoatInspection::where('boat_id', $id)
            ->with(['answers.question', 'user'])
            ->latest()
            ->first();

        if (!$inspection) {
            return response()->json([
                'has_report' => false,
                'message' => 'No inspection report available for this vessel.',
            ]);
        }

        // Build report data
        $reportItems = [];
        foreach ($inspection->answers as $answer) {
            if (!$answer->question) continue;

            $finalAnswer = $answer->human_answer ?? $answer->ai_answer;
            $isVerified = in_array($answer->review_status, ['accepted', 'verified']);

            $reportItems[] = [
                'question' => $answer->question->question_text,
                'type' => $answer->question->type,
                'weight' => $answer->question->weight,
                'answer' => $finalAnswer,
                'ai_answer' => $answer->ai_answer,
                'ai_confidence' => $answer->ai_confidence,
                'human_answer' => $answer->human_answer,
                'review_status' => $answer->review_status,
                'is_verified' => $isVerified,
                'evidence' => $answer->ai_evidence,
            ];
        }

        // Sort: high priority first
        $weightOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($reportItems, function ($a, $b) use ($weightOrder) {
            return ($weightOrder[$a['weight']] ?? 1) <=> ($weightOrder[$b['weight']] ?? 1);
        });

        return response()->json([
            'has_report' => true,
            'yacht' => [
                'id' => $yacht->id,
                'name' => $yacht->boat_name,
                'year' => $yacht->year,
                'type' => $yacht->boatType?->name ?? 'Unknown',
            ],
            'inspection' => [
                'id' => $inspection->id,
                'status' => $inspection->status,
                'inspector' => $inspection->user?->name ?? 'AI System',
                'date' => $inspection->updated_at?->format('Y-m-d'),
            ],
            'items' => $reportItems,
            'summary' => [
                'total' => count($reportItems),
                'verified' => count(array_filter($reportItems, fn($i) => $i['is_verified'])),
                'high_priority' => count(array_filter($reportItems, fn($i) => $i['weight'] === 'high')),
            ],
        ]);
    }

    /**
     * GET /api/yachts/{id}/inspection-report/pdf
     *
     * Generate and download a PDF inspection report.
     */
    public function downloadPdf($id): Response
    {
        $yacht = Yacht::findOrFail($id);

        $inspection = BoatInspection::where('boat_id', $id)
            ->with(['answers.question', 'user'])
            ->latest()
            ->first();

        if (!$inspection) {
            abort(404, 'No inspection report available');
        }

        // Build report items
        $items = [];
        foreach ($inspection->answers as $answer) {
            if (!$answer->question) continue;
            $items[] = [
                'question' => $answer->question->question_text,
                'weight' => $answer->question->weight,
                'type' => $answer->question->type,
                'answer' => $answer->human_answer ?? $answer->ai_answer ?? 'N/A',
                'confidence' => $answer->ai_confidence,
                'status' => $answer->review_status ?? 'pending',
                'is_ai' => is_null($answer->human_answer),
            ];
        }

        // Sort by priority
        $weightOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, fn($a, $b) => ($weightOrder[$a['weight']] ?? 1) <=> ($weightOrder[$b['weight']] ?? 1));

        // Generate HTML for PDF
        $html = $this->buildPdfHtml($yacht, $inspection, $items);

        // Try dompdf if installed, otherwise return HTML
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return $pdf->download("inspection-report-{$yacht->boat_name}.pdf");
        }

        // Fallback: return HTML as downloadable file
        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"inspection-report-{$yacht->boat_name}.html\"",
        ]);
    }

    /**
     * Build the HTML template for the PDF report.
     */
    private function buildPdfHtml(Yacht $yacht, BoatInspection $inspection, array $items): string
    {
        $date = $inspection->updated_at?->format('d M Y') ?? date('d M Y');
        $inspector = $inspection->user?->name ?? 'AI System';
        $total = count($items);
        $verified = count(array_filter($items, fn($i) => in_array($i['status'], ['accepted', 'verified'])));

        $weightColors = [
            'high' => '#dc2626',
            'medium' => '#d97706',
            'low' => '#16a34a',
        ];
        $statusLabels = [
            'accepted' => '✓ Accepted',
            'verified' => '✓✓ Verified',
            'overridden' => '✏ Overridden',
            'pending' => '⏳ Pending',
        ];

        $rows = '';
        foreach ($items as $idx => $item) {
            $wColor = $weightColors[$item['weight']] ?? '#6b7280';
            $sLabel = $statusLabels[$item['status']] ?? $item['status'];
            $conf = $item['confidence'] ? round($item['confidence'] * 100) . '%' : '-';
            $source = $item['is_ai'] ? 'AI' : 'Human';
            $bg = $idx % 2 === 0 ? '#ffffff' : '#f9fafb';

            $rows .= <<<ROW
            <tr style="background:{$bg}">
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#111">{$item['question']}</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center">
                    <span style="color:{$wColor};font-weight:bold;font-size:11px;text-transform:uppercase">{$item['weight']}</span>
                </td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:bold;color:#003566">{$item['answer']}</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;font-size:12px;color:#6b7280">{$conf}</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;font-size:11px">{$source}</td>
                <td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;font-size:11px">{$sLabel}</td>
            </tr>
            ROW;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Inspection Report - {$yacht->boat_name}</title>
            <style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; margin: 0; padding: 40px; color: #1f2937; }
                .header { border-bottom: 3px solid #003566; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #003566; font-size: 24px; margin: 0; }
                .header p { color: #6b7280; font-size: 13px; margin: 5px 0 0; }
                .meta { display: flex; gap: 40px; margin-bottom: 30px; }
                .meta-item label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; display: block; }
                .meta-item span { font-size: 14px; font-weight: bold; color: #111; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #003566; color: white; padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af; text-align: center; }
                .summary { background: #f0f9ff; border: 1px solid #bae6fd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
                .summary span { font-weight: bold; color: #003566; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🚢 Vessel Inspection Report</h1>
                <p>Control Report for {$yacht->boat_name}</p>
            </div>

            <div class="meta">
                <div class="meta-item">
                    <label>Vessel</label>
                    <span>{$yacht->boat_name}</span>
                </div>
                <div class="meta-item">
                    <label>Year</label>
                    <span>{$yacht->year}</span>
                </div>
                <div class="meta-item">
                    <label>Inspector</label>
                    <span>{$inspector}</span>
                </div>
                <div class="meta-item">
                    <label>Report Date</label>
                    <span>{$date}</span>
                </div>
            </div>

            <div class="summary">
                Total Questions: <span>{$total}</span> · Verified: <span>{$verified}/{$total}</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Question</th>
                        <th style="text-align:center">Priority</th>
                        <th>Answer</th>
                        <th style="text-align:center">AI Conf.</th>
                        <th style="text-align:center">Source</th>
                        <th style="text-align:center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>

            <div class="footer">
                Generated on {$date} · Powered by AI Vessel Inspection System
            </div>
        </body>
        </html>
        HTML;
    }
}
