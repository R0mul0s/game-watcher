<!--
    Štítky hry — druh (jen když nejde o běžnou hru), obsah pro dospělé
    a čeština (titulky nebo dabing, R47).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    mature: { type: Boolean, default: false },
    // Čeština ve hře (enum CzechSupport); null = neznámá
    czech: { type: String, default: null },
});

/** Druh, který se jako štítek neukazuje — běžná hra. */
const DEFAULT_TYPE = 'game';
/** Čeština, která má štítek — bez češtiny ani neznámá ho nemají. */
const CZECH_BADGES = ['text', 'voice'];

const t = useTranslations();

/** Název druhu; neznámý druh z ITAD tak, jak přišel. */
const typeLabel = computed(() => {
    const key = `game.types.${props.type}`;
    const label = t(key);

    return label === key ? props.type : label;
});

const hasCzech = computed(() => CZECH_BADGES.includes(props.czech));
</script>

<template>
    <span v-if="type !== DEFAULT_TYPE || mature || hasCzech" class="badges">
        <span v-if="type !== DEFAULT_TYPE" class="badge">{{ typeLabel }}</span>
        <span v-if="mature" class="badge badge--warning">{{ t('game.mature') }}</span>
        <span v-if="hasCzech" class="badge badge--language" :title="t(`game.czech_titles.${czech}`)">{{ t(`game.czech_badges.${czech}`) }}</span>
    </span>
</template>
