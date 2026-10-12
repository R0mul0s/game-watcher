<!--
    Kulisa stránky ve stylu Tronu (R6) — noční obloha, neonový obzor,
    ubíhající perspektivní podlaha a světelné stopy po ní. Na přihlášení
    naplno, na stránkách aplikace tlumeně (variant "subtle"), aby nerušila obsah.
    Jen dekorace — pohyb jen bez omezeného pohybu v systému.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
defineProps({
    /** full = přihlášení, subtle = stránky aplikace */
    variant: { type: String, default: 'full', validator: (value) => ['full', 'subtle'].includes(value) },
});
</script>

<template>
    <div class="backdrop" :class="`backdrop--${variant}`" aria-hidden="true">
        <div class="backdrop__horizon"></div>
        <div class="backdrop__floor"></div>
        <div class="backdrop__trail backdrop__trail--cyan"></div>
        <div class="backdrop__trail backdrop__trail--magenta"></div>
    </div>
</template>
