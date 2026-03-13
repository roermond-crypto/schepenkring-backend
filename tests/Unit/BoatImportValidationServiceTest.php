<?php

use App\Services\BoatImportValidationService;

it('accepts a plausible imported boat payload', function () {
    $validator = new BoatImportValidationService();

    $result = $validator->validate([
        'manufacturer' => 'Bayliner',
        'model' => '285 SB',
        'boat_name' => 'Bayliner 285 SB',
        'year' => 2004,
        'loa' => 8.76,
        'beam' => 2.95,
        'draft' => 0.85,
        'location' => 'Roermond',
        'description' => 'Well maintained cruiser with recent engine service and clean upholstery.',
        'cabins' => 2,
        'berths' => 4,
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['issues'])->toBe([]);
});

it('rejects placeholder and counter-based payloads', function () {
    $validator = new BoatImportValidationService();

    $result = $validator->validate([
        'manufacturer' => '1',
        'model' => '2',
        'title' => '1 2',
        'year' => 7,
        'loa' => 0.09,
        'beam' => 0.10,
        'draft' => 0.11,
        'location' => '6',
        'description' => 'Testtekst',
        'berths' => '501 vast 502 extra 503 personeel',
    ]);

    expect($result['valid'])->toBeFalse()
        ->and($result['issues'])->toContain('description contains placeholder text')
        ->and($result['issues'])->toContain('year is outside plausible bounds: 7');
});

it('allows numeric model names when the rest of the identity is valid', function () {
    $validator = new BoatImportValidationService();

    $result = $validator->validate([
        'manufacturer' => 'Princess',
        'model' => '360',
        'boat_name' => 'Princess 360',
        'year' => 1994,
        'loa' => 11.2,
        'beam' => 3.65,
        'draft' => 0.92,
        'location' => 'Leeuwarden',
        'description' => 'Classic flybridge cruiser in honest condition.',
        'berths' => 6,
    ]);

    expect($result['valid'])->toBeTrue();
});

it('allows missing boat name markers and normal berth strings when make and model are valid', function () {
    $validator = new BoatImportValidationService();

    $result = $validator->validate([
        'manufacturer' => 'Fox',
        'model' => '22',
        'boat_name' => '-',
        'year' => 2007,
        'loa' => 6.5,
        'beam' => 2.5,
        'draft' => 0.9,
        'location' => 'Heeg',
        'description' => 'Compact zeilboot met nette kajuit.',
        'berths' => '4 vast 2 extra',
    ]);

    expect($result['valid'])->toBeTrue();
});
