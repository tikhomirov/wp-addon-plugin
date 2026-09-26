<?php

namespace WpAddon\Services;

/**
 * Content heuristics for comment spam.
 *
 * Every rule returns a weight. The caller blocks a comment when the total
 * reaches the configured threshold, so single weak signals stay harmless
 * while a combination of weak signals still catches bots.
 */
class CommentAntispamScoringService
{
    public const REASON_TEXT_TOKEN = 'text_token';

    public const REASON_AUTHOR_TOKEN = 'author_token';

    public const REASON_NO_CYRILLIC = 'no_cyrillic';

    public const REASON_EMAIL_DOTS = 'email_dots';

    public const REASON_FIRST_TIME = 'first_time';

    public const WEIGHT_TEXT_TOKEN = 3;

    public const WEIGHT_AUTHOR_TOKEN = 2;

    public const WEIGHT_NO_CYRILLIC = 1;

    public const WEIGHT_EMAIL_DOTS = 1;

    public const WEIGHT_FIRST_TIME = 1;

    /**
     * Bots emit random letter-only tokens of 16+ chars. The bound is high
     * enough to keep real words like "PrivetKakDela" out of the rule.
     */
    public const MIN_TOKEN_LENGTH = 16;

    private const EMAIL_DOTS_LIMIT = 3;

    /**
     * @return array{score: int, reasons: string[]}
     */
    public static function score(string $content, string $author, string $email, bool $isFirstVisit): array
    {
        $reasons = [];
        $score = 0;
        $text = self::plainText($content);

        if (self::isRandomToken($text)) {
            $score += self::WEIGHT_TEXT_TOKEN;
            $reasons[] = self::REASON_TEXT_TOKEN;
        }

        if (self::isRandomToken(trim($author))) {
            $score += self::WEIGHT_AUTHOR_TOKEN;
            $reasons[] = self::REASON_AUTHOR_TOKEN;
        }

        if (self::looksMachineWritten($text)) {
            $score += self::WEIGHT_NO_CYRILLIC;
            $reasons[] = self::REASON_NO_CYRILLIC;
        }

        if (substr_count(self::localPart($email), '.') >= self::EMAIL_DOTS_LIMIT) {
            $score += self::WEIGHT_EMAIL_DOTS;
            $reasons[] = self::REASON_EMAIL_DOTS;
        }

        if ($isFirstVisit) {
            $score += self::WEIGHT_FIRST_TIME;
            $reasons[] = self::REASON_FIRST_TIME;
        }

        return [
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    /**
     * A single word made of ASCII letters only, long enough and mixed case.
     */
    public static function isRandomToken(string $value): bool
    {
        if (preg_match('/^[a-z]+$/i', $value) !== 1) {
            return false;
        }

        if (mb_strlen($value) < self::MIN_TOKEN_LENGTH) {
            return false;
        }

        return preg_match('/[a-z]/', $value) === 1 && preg_match('/[A-Z]/', $value) === 1;
    }

    /**
     * No Cyrillic and no whitespace: a single word that does not belong to
     * the site language.
     */
    public static function looksMachineWritten(string $text): bool
    {
        if ($text === '' || preg_match('/\s/u', $text) === 1) {
            return false;
        }

        return self::hasCyrillic($text) === false;
    }

    public static function hasCyrillic(string $text): bool
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        return preg_match('/[\x{0400}-\x{04FF}]/u', $text) === 1;
    }

    public static function localPart(string $email): string
    {
        $email = trim($email);
        $position = strrpos($email, '@');

        return $position === false ? $email : substr($email, 0, $position);
    }

    public static function plainText(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim(is_string($text) ? $text : '');
    }
}
