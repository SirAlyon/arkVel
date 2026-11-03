<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ __('adoptions.contract.title') }}</title>
    <style>
        @page { margin: 120px 50px 90px 50px; }

        * { font-family: DejaVu Sans, sans-serif; }

        body {
            color: #1f2937;
            font-size: 12px;
            line-height: 1.5;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
            color: #111827;
        }

        h2 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin: 22px 0 8px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 18px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data td {
            padding: 3px 0;
            vertical-align: top;
        }

        table.data td.label {
            width: 40%;
            color: #6b7280;
        }

        table.data td.value {
            width: 60%;
            color: #111827;
        }

        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .parties td {
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }

        .party-name {
            font-weight: bold;
            color: #111827;
        }

        ol.clauses {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        ol.clauses li {
            margin-bottom: 6px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 48px;
        }

        .signatures td {
            width: 50%;
            padding-top: 40px;
            border-top: 1px solid #9ca3af;
            text-align: center;
            color: #6b7280;
            font-size: 11px;
        }

        .signatures .spacer { border: none; width: 8%; }

        .fee {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>{{ __('adoptions.contract.title') }}</h1>
    <p class="subtitle">
        {{ __('adoptions.contract.reference', ['id' => $adoption->getKey()]) }}
        &middot;
        {{ __('adoptions.contract.dated', ['date' => $adoptedAt]) }}
    </p>

    <p>{{ __('adoptions.contract.preamble') }}</p>

    <h2>{{ __('adoptions.contract.parties') }}</h2>
    <table class="parties">
        <tr>
            <td>
                <div class="party-name">{{ __('adoptions.contract.party_organization') }}</div>
                <div>{{ $organizationName }}</div>
                @if ($organizationLegalName !== null)
                    <div>{{ $organizationLegalName }}</div>
                @endif
                @if ($organizationAddress !== null)
                    <div>{{ $organizationAddress }}</div>
                @endif
                @if ($organizationEmail !== null)
                    <div>{{ $organizationEmail }}</div>
                @endif
            </td>
            <td>
                <div class="party-name">{{ __('adoptions.contract.party_adopter') }}</div>
                <div>{{ $adopterName }}</div>
                <div>{{ $adopterEmail }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ __('adoptions.contract.animal') }}</h2>
    <table class="data">
        <tr>
            <td class="label">{{ __('adoptions.contract.animal_name') }}</td>
            <td class="value">{{ $animalName }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('adoptions.contract.species') }}</td>
            <td class="value">{{ $species }}</td>
        </tr>
        @if ($breed !== null)
            <tr>
                <td class="label">{{ __('adoptions.contract.breed') }}</td>
                <td class="value">{{ $breed }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ __('adoptions.contract.sex') }}</td>
            <td class="value">{{ $sex }}</td>
        </tr>
        @if ($microchip !== null)
            <tr>
                <td class="label">{{ __('adoptions.contract.microchip') }}</td>
                <td class="value">{{ $microchip }}</td>
            </tr>
        @endif
    </table>

    <h2>{{ __('adoptions.contract.terms') }}</h2>
    <table class="data">
        <tr>
            <td class="label">{{ __('adoptions.contract.adoption_date') }}</td>
            <td class="value">{{ $adoptedAt }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('adoptions.contract.fee') }}</td>
            <td class="value fee">{{ $feeLabel }}</td>
        </tr>
    </table>

    <h2>{{ __('adoptions.contract.clauses') }}</h2>
    <ol class="clauses">
        <li>{{ __('adoptions.contract.clause_care') }}</li>
        <li>{{ __('adoptions.contract.clause_health') }}</li>
        <li>{{ __('adoptions.contract.clause_no_transfer') }}</li>
        <li>{{ __('adoptions.contract.clause_follow_up') }}</li>
        <li>{{ __('adoptions.contract.clause_return') }}</li>
        <li>{{ __('adoptions.contract.clause_gdpr') }}</li>
    </ol>

    <table class="signatures">
        <tr>
            <td>{{ __('adoptions.contract.signature_organization') }}</td>
            <td class="spacer"></td>
            <td>{{ __('adoptions.contract.signature_adopter') }}</td>
        </tr>
    </table>
</body>
</html>
