<!--
    Logo aplikace — neonový šestiúhelník s trojúhelníkem „play" (R6).
    Stejná kresba je v resources/images/logo.svg (zdroj ikon), barvy tady
    ale berou tokeny, aby logo šlo zvýraznit stejně jako ostatní prvky.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<template>
    <svg class="brand-logo" viewBox="0 0 48 48" aria-hidden="true">
        <polygon class="brand-logo__frame" points="24,3 42,13.5 42,34.5 24,45 6,34.5 6,13.5" />
        <polygon class="brand-logo__play" points="19.5,16 33,24 19.5,32" />
        <path class="brand-logo__ticks" d="M2 24h5M41 24h5" />
    </svg>
</template>
