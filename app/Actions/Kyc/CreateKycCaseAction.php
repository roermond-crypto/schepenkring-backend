<?php

namespace App\Actions\Kyc;

use App\Models\Deal;
use App\Models\Offer;
use App\Models\Contract;
use App\Models\KycCase;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Str;

class CreateKycCaseAction
{
    public function fromOffer(Offer $offer): KycCase
    {
        $existing = KycCase::where('offer_id', $offer->id)->first();
        if ($existing) return $existing;

        $buyer  = $offer->buyer_id ? User::find($offer->buyer_id) : null;
        $seller = $offer->seller_id ? User::find($offer->seller_id) : null;
        $yacht  = $offer->yacht_id ? Yacht::find($offer->yacht_id) : null;

        return $this->create([
            'offer_id'       => $offer->id,
            'buyer_id'       => $offer->buyer_id,
            'seller_id'      => $offer->seller_id,
            'yacht_id'       => $offer->yacht_id,
            'location_id'    => $offer->location_id,
            'deal_value'     => $offer->amount,
            'buyer_name'     => $offer->buyer_name ?? $buyer?->name,
            'buyer_email'    => $offer->buyer_email ?? $buyer?->email,
            'buyer_phone'    => $offer->buyer_phone ?? $buyer?->phone,
            'seller_name'    => $seller?->name,
            'seller_email'   => $seller?->email,
            'boat_name'      => $yacht?->boat_name,
            'boat_type'      => $yacht?->boat_type,
            'boat_value'     => $yacht?->price,
        ]);
    }

    public function fromDeal(Deal $deal): KycCase
    {
        $existing = KycCase::where('deal_id', $deal->id)->first();
        if ($existing) return $existing;

        $buyer  = $deal->buyer;
        $seller = $deal->seller;
        $yacht  = $deal->yacht;

        return $this->create([
            'deal_id'        => $deal->id,
            'buyer_id'       => $deal->buyer_id,
            'seller_id'      => $deal->seller_id,
            'yacht_id'       => $deal->yacht_id,
            'location_id'    => $yacht?->location_id,
            'deal_value'     => $deal->agreed_amount,
            'buyer_name'     => $buyer?->name,
            'buyer_email'    => $buyer?->email,
            'buyer_phone'    => $buyer?->phone,
            'seller_name'    => $seller?->name,
            'seller_email'   => $seller?->email,
            'boat_name'      => $yacht?->boat_name,
            'boat_type'      => $yacht?->boat_type,
            'boat_value'     => $yacht?->price,
        ]);
    }

    public function fromContract(Contract $contract): KycCase
    {
        $existing = KycCase::where('contract_id', $contract->id)->first();
        if ($existing) return $existing;

        $buyer  = $contract->buyer;
        $seller = $contract->seller;
        $yacht  = $contract->boat_id ? Yacht::find($contract->boat_id) : null;

        return $this->create([
            'contract_id'    => $contract->id,
            'buyer_id'       => $contract->buyer_user_id,
            'seller_id'      => $contract->seller_user_id,
            'yacht_id'       => $contract->boat_id,
            'location_id'    => $yacht?->location_id,
            'buyer_name'     => $buyer?->name,
            'buyer_email'    => $buyer?->email,
            'buyer_phone'    => $buyer?->phone,
            'seller_name'    => $seller?->name,
            'seller_email'   => $seller?->email,
            'boat_name'      => $yacht?->boat_name,
            'boat_type'      => $yacht?->boat_type,
            'boat_value'     => $yacht?->price,
        ]);
    }

    public function fromYacht(Yacht $yacht): KycCase
    {
        $existing = KycCase::where('yacht_id', $yacht->id)
            ->whereNull('deal_id')
            ->whereNull('offer_id')
            ->whereNull('contract_id')
            ->first();
        if ($existing) return $existing;

        return $this->create([
            'yacht_id'    => $yacht->id,
            'location_id' => $yacht->location_id,
            'boat_name'   => $yacht->boat_name,
            'boat_type'   => $yacht->boat_type,
            'boat_value'  => $yacht->price,
        ]);
    }

    public function manual(array $overrides = []): KycCase
    {
        return $this->create($overrides);
    }

    private function create(array $data): KycCase
    {
        $caseNumber = $this->generateCaseNumber();

        $kycCase = KycCase::create(array_merge($data, [
            'case_number'  => $caseNumber,
            'status'       => 'draft',
            'risk_score'   => 0,
            'risk_level'   => 'low',
            'auto_created' => true,
        ]));

        $kycCase->addTimeline(
            'kyc_created',
            "KYC-dossier {$caseNumber} aangemaakt",
            null,
            ['trigger' => array_key_first($data)]
        );

        return $kycCase;
    }

    private function generateCaseNumber(): string
    {
        $year  = now()->format('Y');
        $seq   = KycCase::whereYear('created_at', $year)->count() + 1;
        return "KYC-{$year}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
