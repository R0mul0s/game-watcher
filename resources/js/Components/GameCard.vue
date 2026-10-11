<!--
    Karta sledované hry na přehledu — banner, název, druh a datum vydání.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameBadges from '@/Components/GameBadges.vue';
import { formatDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    game: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
</script>

<template>
    <Link :href="game.url" class="game-card">
        <!-- Obrázek je vedle názvu dekorativní — alt prázdný -->
        <img v-if="game.bannerUrl" :src="game.bannerUrl" alt="" class="game-card__image" loading="lazy" />
        <span v-else class="game-card__image game-card__image--empty" aria-hidden="true"></span>
        <span class="game-card__body">
            <span class="game-card__title">{{ game.title }}</span>
            <span class="game-card__meta">
                <GameBadges :type="game.type" />
                <span v-if="game.releaseDate">{{ formatDate(game.releaseDate, page.props.locale) }}</span>
                <span v-else>{{ t('game.unreleased') }}</span>
            </span>
        </span>
    </Link>
</template>
