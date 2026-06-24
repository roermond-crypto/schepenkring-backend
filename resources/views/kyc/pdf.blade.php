<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KYC-dossier {{ $kycCase->case_number }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }
  .page { max-width: 900px; margin: 0 auto; padding: 32px 40px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #003566; padding-bottom: 16px; margin-bottom: 24px; }
  .header-left h1 { font-size: 22px; font-weight: 800; color: #003566; }
  .header-left p { font-size: 11px; color: #666; margin-top: 2px; }
  .header-right { text-align: right; }
  .case-number { font-size: 18px; font-weight: 700; font-family: monospace; color: #C8102E; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; }
  .badge-approved  { background: #dcfce7; color: #166534; }
  .badge-rejected  { background: #fee2e2; color: #991b1b; }
  .badge-high_risk { background: #fecaca; color: #7f1d1d; }
  .badge-draft     { background: #f1f5f9; color: #475569; }
  .badge-default   { background: #dbeafe; color: #1e40af; }
  .risk-badge { font-size: 11px; font-weight: 700; }
  .risk-low      { color: #16a34a; }
  .risk-medium   { color: #d97706; }
  .risk-high     { color: #dc2626; }
  .risk-critical { color: #7f1d1d; }
  .blocking-banner { background: #fee2e2; border: 2px solid #dc2626; border-radius: 6px; padding: 8px 14px; margin-bottom: 20px; color: #991b1b; font-weight: 700; font-size: 12px; }

  /* Sections */
  .section { margin-bottom: 24px; page-break-inside: avoid; }
  .section-title { font-size: 13px; font-weight: 700; color: #003566; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 16px; }
  .field { margin-bottom: 4px; }
  .field-label { font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
  .field-value { font-size: 12px; color: #1e293b; font-weight: 500; margin-top: 1px; }
  .field-value.empty { color: #94a3b8; font-style: italic; }

  /* Risk score */
  .risk-bar-wrap { background: #f1f5f9; border-radius: 4px; height: 8px; margin-top: 6px; overflow: hidden; }
  .risk-bar-fill { height: 8px; border-radius: 4px; }

  /* Questions table */
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  thead th { background: #f8fafc; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
  tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 11px; vertical-align: top; }
  tbody tr:last-child td { border-bottom: none; }
  .answer-yes { color: #dc2626; font-weight: 700; }
  .answer-no  { color: #16a34a; font-weight: 600; }
  .flag-blocking { color: #dc2626; font-size: 10px; font-weight: 700; }
  .flag-warning  { color: #d97706; font-size: 10px; font-weight: 700; }
  .points { font-size: 10px; color: #d97706; font-weight: 700; }

  /* Documents */
  .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f1f5f9; }
  .doc-item:last-child { border-bottom: none; }
  .doc-party { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 600; }
  .doc-type  { font-size: 11px; font-weight: 600; }
  .doc-verified   { color: #16a34a; font-size: 10px; font-weight: 700; }
  .doc-unverified { color: #d97706; font-size: 10px; }

  /* Timeline */
  .timeline-item { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f8fafc; }
  .timeline-item:last-child { border-bottom: none; }
  .timeline-dot { width: 8px; height: 8px; border-radius: 50%; background: #003566; margin-top: 4px; flex-shrink: 0; }
  .timeline-time { font-size: 10px; color: #64748b; white-space: nowrap; min-width: 90px; }
  .timeline-text { font-size: 11px; color: #1e293b; }
  .timeline-actor { font-size: 10px; color: #94a3b8; }

  /* Footer */
  .footer { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 12px; display: flex; justify-content: space-between; color: #94a3b8; font-size: 10px; }

  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page { padding: 16px 20px; }
    .section { page-break-inside: avoid; }
  }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <h1>Schepenkring KYC-Rapport</h1>
      <p>Compliance dossier — vertrouwelijk document</p>
      @if($kycCase->location)
        <p style="margin-top:4px; color:#003566; font-weight:600;">{{ $kycCase->location->name }}</p>
      @endif
    </div>
    <div class="header-right">
      <div class="case-number">{{ $kycCase->case_number }}</div>
      @php
        $statusMap = [
          'draft' => ['Concept','badge-draft'],
          'in_progress' => ['In behandeling','badge-default'],
          'waiting_documents' => ['Wacht op docs','badge-default'],
          'under_review' => ['Onder review','badge-default'],
          'approved' => ['Goedgekeurd','badge-approved'],
          'rejected' => ['Afgewezen','badge-rejected'],
          'high_risk' => ['Hoog risico','badge-high_risk'],
          'reported' => ['Gemeld bij FIU','badge-rejected'],
        ];
        [$statusLabel, $statusClass] = $statusMap[$kycCase->status] ?? ['Onbekend','badge-default'];
      @endphp
      <div><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></div>
      <div style="margin-top:6px; font-size:11px; color:#64748b;">Aangemaakt: {{ $kycCase->created_at->format('d-m-Y') }}</div>
      @if($kycCase->approved_at)
        <div style="font-size:11px; color:#16a34a;">Goedgekeurd: {{ $kycCase->approved_at->format('d-m-Y H:i') }}</div>
      @endif
    </div>
  </div>

  @if($kycCase->blocking)
  <div class="blocking-banner">
    ⛔ DIT DOSSIER BLOKKEERT DE TRANSACTIE — compliance actie vereist vóór ondertekening
  </div>
  @endif

  <!-- Risk score -->
  <div class="section">
    <div class="section-title">Risicobeoordeling</div>
    <div class="grid-3">
      <div class="field">
        <div class="field-label">Risicoscore</div>
        @php
          $riskClass = match($kycCase->risk_level) { 'low' => 'risk-low', 'medium' => 'risk-medium', 'high' => 'risk-high', 'critical' => 'risk-critical', default => 'risk-low' };
          $riskLabel = match($kycCase->risk_level) { 'low' => 'Laag', 'medium' => 'Gemiddeld', 'high' => 'Hoog', 'critical' => 'Kritiek', default => 'Laag' };
          $riskPct   = min(100, $kycCase->risk_score);
          $barColor  = match($kycCase->risk_level) { 'low' => '#22c55e', 'medium' => '#f59e0b', 'high' => '#ef4444', 'critical' => '#7f1d1d', default => '#22c55e' };
        @endphp
        <div class="field-value risk-badge {{ $riskClass }}">{{ $kycCase->risk_score }} punten — {{ $riskLabel }}</div>
        <div class="risk-bar-wrap">
          <div class="risk-bar-fill" style="width:{{ $riskPct }}%; background:{{ $barColor }};"></div>
        </div>
      </div>
      <div class="field">
        <div class="field-label">Risiconiveau</div>
        <div class="field-value {{ $riskClass }}">{{ $riskLabel }}</div>
      </div>
      <div class="field">
        <div class="field-label">Deal blokkerend</div>
        <div class="field-value" style="{{ $kycCase->blocking ? 'color:#dc2626;font-weight:700' : 'color:#16a34a' }}">
          {{ $kycCase->blocking ? 'Ja — blokkeert deal' : 'Nee' }}
        </div>
      </div>
    </div>
  </div>

  <!-- Parties -->
  <div class="section">
    <div class="section-title">Koper</div>
    <div class="grid-3">
      @foreach([['Naam', $kycCase->buyer_name], ['E-mail', $kycCase->buyer_email], ['Telefoon', $kycCase->buyer_phone], ['Adres', $kycCase->buyer_address], ['Stad', $kycCase->buyer_city], ['Land', $kycCase->buyer_country], ['IBAN', $kycCase->buyer_iban], ['Geboortedatum', $kycCase->buyer_dob]] as [$label, $value])
      <div class="field">
        <div class="field-label">{{ $label }}</div>
        <div class="field-value {{ $value ? '' : 'empty' }}">{{ $value ?: '—' }}</div>
      </div>
      @endforeach
    </div>
  </div>

  <div class="section">
    <div class="section-title">Verkoper</div>
    <div class="grid-3">
      @foreach([['Naam', $kycCase->seller_name], ['E-mail', $kycCase->seller_email], ['Telefoon', $kycCase->seller_phone], ['Adres', $kycCase->seller_address]] as [$label, $value])
      <div class="field">
        <div class="field-label">{{ $label }}</div>
        <div class="field-value {{ $value ? '' : 'empty' }}">{{ $value ?: '—' }}</div>
      </div>
      @endforeach
    </div>
  </div>

  <div class="section">
    <div class="section-title">Vaartuig & Transactie</div>
    <div class="grid-3">
      @foreach([
        ['Boot', $kycCase->boat_name],
        ['Type', $kycCase->boat_type],
        ['Waarde boot', $kycCase->boat_value ? '€ ' . number_format($kycCase->boat_value, 0, ',', '.') : null],
        ['Transactiewaarde', $kycCase->deal_value ? '€ ' . number_format($kycCase->deal_value, 0, ',', '.') : null],
        ['Betaalmethode', $kycCase->payment_method],
        ['Makelaar', $kycCase->broker?->name],
      ] as [$label, $value])
      <div class="field">
        <div class="field-label">{{ $label }}</div>
        <div class="field-value {{ $value ? '' : 'empty' }}">{{ $value ?: '—' }}</div>
      </div>
      @endforeach
    </div>
  </div>

  <!-- Questions & Answers -->
  @php
    $bySection = $kycCase->answers->groupBy(fn($a) => $a->questionTemplate?->section ?? 'Overig');
  @endphp
  @if($bySection->isNotEmpty())
  <div class="section">
    <div class="section-title">Vragenlijst & Antwoorden</div>
    @foreach($bySection as $section => $sectionAnswers)
      <div style="margin-bottom:12px;">
        <div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.04em;">{{ $section }}</div>
        <table>
          <thead>
            <tr>
              <th style="width:50%">Vraag</th>
              <th style="width:12%">Antwoord</th>
              <th style="width:8%">Punten</th>
              <th style="width:15%">Markering</th>
              <th style="width:15%">Door / Wanneer</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sectionAnswers as $answer)
            @php $tpl = $answer->questionTemplate; @endphp
            <tr>
              <td>
                {{ $tpl?->question ?? '—' }}
                @if($tpl?->action)
                  <div style="font-size:10px; color:#94a3b8; margin-top:2px;">→ {{ $tpl->action }}</div>
                @endif
                @if($answer->note)
                  <div style="font-size:10px; color:#475569; margin-top:2px; font-style:italic;">Toelichting: {{ $answer->note }}</div>
                @endif
              </td>
              <td>
                @php $ans = strtolower($answer->answer ?? ''); @endphp
                @if(in_array($ans, ['ja', 'yes', '1', 'true']))
                  <span class="answer-yes">JA</span>
                @elseif(in_array($ans, ['nee', 'no', '0', 'false']))
                  <span class="answer-no">Nee</span>
                @else
                  {{ $answer->answer }}
                @endif
              </td>
              <td>
                @if($answer->risk_points_awarded > 0)
                  <span class="points">+{{ $answer->risk_points_awarded }}</span>
                @else
                  <span style="color:#94a3b8">0</span>
                @endif
              </td>
              <td>
                @if($tpl?->risk_flag === 'blocking')
                  <span class="flag-blocking">⛔ Blokkerend</span>
                @elseif($tpl?->risk_flag === 'critical')
                  <span class="flag-blocking">🔴 Kritiek</span>
                @elseif($tpl?->risk_flag === 'warning')
                  <span class="flag-warning">⚠ Waarschuwing</span>
                @else
                  <span style="color:#94a3b8">—</span>
                @endif
              </td>
              <td>
                <div style="font-size:10px;">{{ $answer->answered_by_user?->name ?? '—' }}</div>
                @if($answer->answered_at)
                  <div style="font-size:10px; color:#94a3b8;">{{ $answer->answered_at->format('d-m-Y H:i') }}</div>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  </div>
  @endif

  <!-- Documents -->
  @if($kycCase->documents->isNotEmpty())
  <div class="section">
    <div class="section-title">Documenten</div>
    @foreach($kycCase->documents->groupBy('party') as $party => $docs)
      <div style="margin-bottom:10px;">
        <div style="font-size:10px; font-weight:700; color:#475569; margin-bottom:4px; text-transform:uppercase;">
          {{ match($party) { 'buyer' => 'Koper', 'seller' => 'Verkoper', 'boat' => 'Boot', default => $party } }}
        </div>
        @foreach($docs as $doc)
        <div class="doc-item">
          <div>
            <div class="doc-type">{{ $doc->original_name }}</div>
            <div class="doc-party">{{ $doc->document_type }} · {{ round($doc->size / 1024) }} KB</div>
          </div>
          <div>
            @if($doc->verified)
              <span class="doc-verified">✓ Geverifieerd</span>
              @if($doc->verified_at)
                <div style="font-size:10px; color:#64748b;">{{ $doc->verified_at->format('d-m-Y') }}</div>
              @endif
            @else
              <span class="doc-unverified">⏳ Nog niet geverifieerd</span>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    @endforeach
  </div>
  @endif

  <!-- Timeline -->
  @if($kycCase->timeline->isNotEmpty())
  <div class="section">
    <div class="section-title">Audittrail / Timeline</div>
    @foreach($kycCase->timeline->sortByDesc('created_at') as $entry)
    <div class="timeline-item">
      <div class="timeline-dot"></div>
      <div style="flex:1">
        <div style="display:flex; justify-content:space-between;">
          <div class="timeline-text">{{ $entry->description ?? $entry->event }}</div>
          <div class="timeline-time">{{ $entry->created_at->format('d-m-Y H:i') }}</div>
        </div>
        <div class="timeline-actor">
          {{ $entry->actor_name ?? 'Systeem' }}
          @if($entry->meta && isset($entry->meta['ip']))
            · IP: {{ $entry->meta['ip'] }}
          @endif
          @if($entry->meta && isset($entry->meta['old_value']))
            · Was: {{ $entry->meta['old_value'] }} → Nu: {{ $entry->meta['new_value'] ?? '—' }}
          @endif
        </div>
      </div>
    </div>
    @endforeach
  </div>
  @endif

  <!-- Approval -->
  @if($kycCase->isApproved() && $kycCase->approvedBy)
  <div class="section" style="border:2px solid #16a34a; border-radius:8px; padding:14px;">
    <div style="color:#166534; font-weight:700; font-size:13px; margin-bottom:8px;">✓ Goedgekeurd door compliance</div>
    <div class="grid-2">
      <div class="field">
        <div class="field-label">Goedgekeurd door</div>
        <div class="field-value">{{ $kycCase->approvedBy->name }}</div>
      </div>
      <div class="field">
        <div class="field-label">Datum goedkeuring</div>
        <div class="field-value">{{ $kycCase->approved_at?->format('d-m-Y H:i') }}</div>
      </div>
    </div>
  </div>
  @elseif($kycCase->status === 'rejected')
  <div class="section" style="border:2px solid #dc2626; border-radius:8px; padding:14px;">
    <div style="color:#991b1b; font-weight:700; font-size:13px; margin-bottom:8px;">✗ Afgewezen</div>
    <div class="field">
      <div class="field-label">Reden</div>
      <div class="field-value">{{ $kycCase->rejection_reason ?? '—' }}</div>
    </div>
  </div>
  @endif

  <!-- Notes -->
  @if($kycCase->notes)
  <div class="section">
    <div class="section-title">Interne notities</div>
    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:10px; font-size:11px; color:#92400e;">
      {{ $kycCase->notes }}
    </div>
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    <div>Schepenkring · KYC Compliance Rapport · {{ $kycCase->case_number }}</div>
    <div>Gegenereerd op {{ now()->format('d-m-Y H:i') }} · Vertrouwelijk document</div>
  </div>

</div>

<script>
  // Auto-trigger print dialog when opened in browser
  window.onload = function() {
    if (window.location.search.includes('print=1')) {
      window.print();
    }
  };
</script>
</body>
</html>
