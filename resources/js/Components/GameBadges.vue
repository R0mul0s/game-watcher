<!--
    Štítky hry — druh (jen když nejde o běžnou hru) a obsah pro dospělé.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    mature: { type: Boolean, default: false },
});

/** Druh, který se jako štítek neukazuje — běžná hra. */
const DEFAULT_TYPE = 'game';

const t = useTranslations();

/** Název druhu; neznámý druh z ITAD tak, jak přišel. */
const typeLabel = computed(() => {
    const key = `game.types.${props.type}`;
    const label = t(key);

    return label === key ? props.type : label;
});
</script>

<template>
    <span v-if="type !== DEFAULT_TYPE || mature" class="badges">
        <span v-if="type !== DEFAULT_TYPE" class="badge">{{ typeLabel }}</span>
        <span v-if="mature" class="badge badge--warning">{{ t('game.mature') }}</span>
    </span>
</template>
