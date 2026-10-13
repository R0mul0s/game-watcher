{{--
    Týdenní souhrn slev sledovaných her (R36) — Markdown komponenty Laravelu.
    Řádky chystá SendWeeklyDigests, texty jsou v lang/cs/app.php.

    @author Roman Hlaváček
    @created 2026-10-13
--}}
<x-mail::message>
# {{ __('app.alerts.weekly_heading') }}

@foreach ($lines as $line)
**[{{ $line['title'] }}]({{ $line['detailUrl'] }})** — **{{ $line['price'] }}**<br>
{{ __('app.alerts.weekly_line', ['previous' => $line['previous'], 'drop' => $line['drop']]) }}


@endforeach
<x-mail::button :url="$dashboardUrl">
{{ __('app.alerts.open_app') }}
</x-mail::button>

{{ __('app.alerts.weekly_footer') }} [{{ __('app.alerts.settings') }}]({{ $settingsUrl }})
</x-mail::message>
