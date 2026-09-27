<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventStatusTest extends TestCase
{
    public static function statuses(): array
    {
        return [
            'espaces supprimés' => ['  en cours  ', 'en cours'],
            'ancien libellé normalisé' => ['annuler', 'annulé'],
            'statut inchangé' => ['terminé', 'terminé'],
        ];
    }

    #[DataProvider('statuses')]
    public function testNormalizeStatus(string $input, string $expected): void
    {
        self::assertSame($expected, Event::normalizeStatus($input));
    }

    public function testStatusReferenceContainsExpectedWorkflow(): void
    {
        self::assertSame(
            ['brouillon', 'planifié', 'accepté', 'en cours', 'terminé', 'annulé'],
            array_keys(Event::STATUS_LABELS)
        );
    }
}
