<?php

use WpAddon\Services\CommentAntispamScoringService;

/**
 * Real spam sample from rwsite.ru: author ztpyuOkqNTwQsQKwwkC,
 * email o.x.on.e.j.o.f.eza.96@gmail.com, body IjtRwXHmcvybfzgmAiM.
 */
describe('CommentAntispamScoringService', function () {
    it('scores the real spam sample above the default threshold', function () {
        $verdict = CommentAntispamScoringService::score(
            'IjtRwXHmcvybfzgmAiM',
            'ztpyuOkqNTwQsQKwwkC',
            'o.x.on.e.j.o.f.eza.96@gmail.com',
            true
        );

        expect($verdict['score'])->toBeGreaterThanOrEqual(3);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_TEXT_TOKEN);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_AUTHOR_TOKEN);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_NO_CYRILLIC);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_EMAIL_DOTS);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_FIRST_TIME);
    });

    it('gives real reader comments no weight at all', function () {
        $samples = [
            'Спасибо, помогло!',
            'Добавьте, пожалуйста, пример с Docker.',
            'Не работает на PHP 8.3, выдает ошибку.',
            'Проверил на Bitrix 24, все ок.',
        ];

        foreach ($samples as $sample) {
            $verdict = CommentAntispamScoringService::score($sample, 'Алексей', 'user.name@gmail.com', false);

            expect($verdict['score'])->toBe(0, $sample.' must not be scored');
            expect($verdict['reasons'])->toBe([], $sample.' must have no reasons');
        }
    });

    it('does not confuse device names and git hashes with random tokens', function () {
        expect(CommentAntispamScoringService::isRandomToken('iPhone15'))->toBeFalse();
        expect(CommentAntispamScoringService::isRandomToken('GalaxyS24Ultra'))->toBeFalse();
        expect(CommentAntispamScoringService::isRandomToken('2f8a1b9c3d4e5f6a7b'))->toBeFalse();
        expect(CommentAntispamScoringService::isRandomToken('PrivetKakDela'))->toBeFalse();
        expect(CommentAntispamScoringService::isRandomToken('congratulations'))->toBeFalse();
    });

    it('detects random tokens by length, letters only and mixed case', function () {
        expect(CommentAntispamScoringService::isRandomToken('IjtRwXHmcvybfzgmAiM'))->toBeTrue();
        expect(CommentAntispamScoringService::isRandomToken('ztpyuOkqNTwQsQKwwkC'))->toBeTrue();
        expect(CommentAntispamScoringService::isRandomToken('IjtRwXHmcvybfzg'))->toBeFalse();
        expect(CommentAntispamScoringService::isRandomToken('IjtRwXHmcvybfzgmAiMm'))->toBeTrue();
    });

    it('keeps a single English word below the threshold', function () {
        $verdict = CommentAntispamScoringService::score('Congrats', 'John', 'john@example.com', false);

        expect($verdict['score'])->toBe(1);
        expect($verdict['reasons'])->toBe([CommentAntispamScoringService::REASON_NO_CYRILLIC]);
    });

    it('ignores an English sentence', function () {
        $verdict = CommentAntispamScoringService::score(
            'Thanks for the article, very helpful!',
            'John',
            'john@example.com',
            false
        );

        expect($verdict['score'])->toBe(0);
    });

    it('counts dots in the email local part only', function () {
        expect(CommentAntispamScoringService::localPart('a.b.c.d.e@gmail.com'))->toBe('a.b.c.d.e');
        expect(CommentAntispamScoringService::localPart('user.name@gmail.com'))->toBe('user.name');
        expect(CommentAntispamScoringService::localPart('broken-without-at'))->toBe('broken-without-at');

        $dotted = CommentAntispamScoringService::score('Спасибо!', 'Иван', 'a.b.c.d.e@gmail.com', false);
        $normal = CommentAntispamScoringService::score('Спасибо!', 'Иван', 'user.name@gmail.com', false);

        expect($dotted['score'])->toBe(1);
        expect($normal['score'])->toBe(0);
    });

    it('strips html and collapses whitespace before scoring', function () {
        $verdict = CommentAntispamScoringService::score(
            '<p>Спасибо   за</p><p>статью</p>',
            'Алексей',
            'user@example.com',
            false
        );

        expect($verdict['score'])->toBe(0);

        expect(CommentAntispamScoringService::plainText('<p>IjtRwXHmcvybfzgmAiM</p>'))
            ->toBe('IjtRwXHmcvybfzgmAiM');
    });

    it('detects Cyrillic in a mixed language comment', function () {
        expect(CommentAntispamScoringService::hasCyrillic('Спасибо'))->toBeTrue();
        expect(CommentAntispamScoringService::hasCyrillic('Thanks'))->toBeFalse();
        expect(CommentAntispamScoringService::looksMachineWritten('Спасибо'))->toBeFalse();
        expect(CommentAntispamScoringService::looksMachineWritten('Thanks'))->toBeTrue();
        expect(CommentAntispamScoringService::looksMachineWritten('Thanks for this'))->toBeFalse();
        expect(CommentAntispamScoringService::looksMachineWritten(''))->toBeFalse();
    });
});
