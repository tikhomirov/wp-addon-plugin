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

    /**
     * Second real spam sample from rwsite.ru: a coherent Russian promo text
     * by "RusFiner" that the old scoring passed because every weak rule
     * individually stayed silent.
     */
    it('scores the coherent promo spam above the default threshold', function () {
        $content = 'Онлайн-сервис РУС-ФИНЕР помогает подобрать займ с дистанционным оформлением.'
            .' Через https://rus-finer.ru/zaimy-pensioneram-na-kartu/ легко сравнить условия МФО.'
            .' Используя https://rus-finer.ru/zaim-45000-rubley-na-kartu/ можно подать заявку.';

        $verdict = CommentAntispamScoringService::score($content, 'RusFiner', 'rusfiner2@pil-casi.fun', true);

        expect($verdict['score'])->toBeGreaterThanOrEqual(3);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_LINKS);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_BRAND);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_KEYWORDS);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_TLD);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_FIRST_TIME);
    });

    it('gives the first link in a comment no weight at all', function () {
        expect(CommentAntispamScoringService::linkWeight(0))->toBe(0);
        expect(CommentAntispamScoringService::linkWeight(1))->toBe(0);

        $verdict = CommentAntispamScoringService::score(
            'Сообщение без ссылок и промо-акций никак не оценивается.',
            'Игорь',
            'igor@example.org',
            false
        );

        expect($verdict['score'])->toBe(0);
    });

    it('detects links in html attributes, markdown and bare domains', function () {
        $urls = CommentAntispamScoringService::extractUrls(
            '<a href="https://a.ru/">А</a> www.b.com/x [текст](https://d.fun/y) и сайт e.io, ещё wp-kama.ru'
        );

        expect($urls)->toContain('https://a.ru/');
        expect($urls)->toContain('www.b.com/x');
        expect($urls)->toContain('https://d.fun/y');
        expect($urls)->toContain('e.io');
        expect(CommentAntispamScoringService::hostOf($urls[0]))->toBe('a.ru');

        expect(CommentAntispamScoringService::extractUrls('Без ссылок вообще'))->toBe([]);
    });

    it('flags an author whose name repeats the linked domain', function () {
        expect(CommentAntispamScoringService::isBrandMatch('RusFiner', '', ['rus-finer.ru']))->toBeTrue();
        expect(CommentAntispamScoringService::isBrandMatch('', 'rusfiner2@pil-casi.fun', ['rus-finer.ru']))->toBeTrue();
        expect(CommentAntispamScoringService::isBrandMatch('Иван', '', ['rus-finer.ru']))->toBeFalse();
        expect(CommentAntispamScoringService::isBrandMatch('', '', ['example.com']))->toBeFalse();

        $verdict = CommentAntispamScoringService::score(
            'Отличная статья, обязательно посмотрите.',
            'RusFiner',
            'test@mail.ru',
            false,
            'https://rus-finer.ru/'
        );

        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_BRAND);
    });

    it('weights loan, casino and pharmacy vocabulary', function () {
        $comment = CommentAntispamScoringService::score(
            'Обзор казино и лучшие слоты, как выиграть.',
            'Иван',
            'ivan@mail.ru',
            false
        );

        expect($comment['reasons'])->toContain(CommentAntispamScoringService::REASON_KEYWORDS);
        expect($comment['score'])->toBeLessThan(3, 'keywords alone stay a weak signal');

        expect(CommentAntispamScoringService::matchedSpamKeywords('Спасибо за статью про Docker'))->toBe(0);
        expect(CommentAntispamScoringService::matchedSpamKeywords('Банк не даёт кредит'))->toBe(1);
    });

    it('treats bulk registered tlds as a weak spam signal', function () {
        expect(CommentAntispamScoringService::isSuspiciousTld('fun'))->toBeTrue();
        expect(CommentAntispamScoringService::isSuspiciousTld('ru'))->toBeFalse();

        $verdict = CommentAntispamScoringService::score(
            'Спасибо за разбор!',
            'Олег',
            'oleg@abc123.fun',
            false
        );

        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_TLD);
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

    it('blocks English spam with links and html anchors', function () {
        $spam = 'This treatment of risk management across modular dependencies avoids reducing the subject to a feature list. <a href="http://www2u.biglobe.ne.jp/~Dolls/houkoku/jawanote.cgi?page=0" rel="nofollow ugc">risk management</a>';
        $verdict = CommentAntispamScoringService::score(
            $spam,
            'Sue',
            'suemassey689@hotmail.com',
            true
        );

        // 1 (first_time) + 2 (html_anchor) + 3 (latin_link) = 6 >= threshold 3
        expect($verdict['score'])->toBeGreaterThanOrEqual(3);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_HTML_ANCHOR);
        expect($verdict['reasons'])->toContain(CommentAntispamScoringService::REASON_LATIN_LINK);
    });

    it('allows transliterated Russian comments without blocking them', function () {
        $translit = 'Spasibo bolshoe za statyu, ochen pomoglo! Vse rabotaet klassno.';
        $verdict = CommentAntispamScoringService::score(
            $translit,
            'Alex',
            'alex@gmail.com',
            false
        );

        expect($verdict['score'])->toBe(0);
        expect(CommentAntispamScoringService::looksLikeRussianTranslit($translit))->toBeTrue();
    });

    it('does not penalize transliterated comments with legitimate single link', function () {
        $translit = 'Privet, vot tut tozhe horosho napisano: https://example.com/info';
        $verdict = CommentAntispamScoringService::score(
            $translit,
            'Dmitriy',
            'dmitriy@gmail.com',
            false
        );

        expect($verdict['reasons'])->not->toContain(CommentAntispamScoringService::REASON_LATIN_LINK);
    });
});
