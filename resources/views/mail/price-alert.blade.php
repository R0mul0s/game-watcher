{{--
    E-mail s upozorněním na ceny sledovaných her (etapa 6, R26) — Markdown
    komponenty Laravelu. Řádky chystá AlertPresenter ze záznamů centra upozornění
    (R53), texty jsou v lang/cs/app.php.

    @author Roman Hlaváček
    @created 2026-10-12
--}}
<x-mail::message>
# {{ __('app.alerts.mail_heading') }}

@foreach ($lines as $line)
**[{{ $line['title'] }}]({{ $line['detailUrl'] }})**@if ($line['price'] !== '') — **{{ $line['price'] }}**@endif {{ $line['shop'] }}<br>
{{ $line['reason'] }}@if ($line['shopUrl']) · [{{ __('app.alerts.open_shop') }}]({{ $line['shopUrl'] }})@endif


@endforeach
<x-mail::button :url="$centerUrl">
{{ __('app.alerts.open_center') }}
</x-mail::button>

{{ __('app.alerts.mail_footer') }} [{{ __('app.alerts.settings') }}]({{ $settingsUrl }})
</x-mail::message>
