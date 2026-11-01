<?php

namespace WpAddon\Services;

/**
 * Content heuristics for comment spam.
 *
 * Every rule returns a weight. The caller blocks a comment when the total
 * reaches the configured threshold and sends it to moderation when the total
 * reaches the lower hold score, so single weak signals stay harmless while a
 * combination of weak signals still catches bots.
 */
class CommentAntispamScoringService
{
    public const REASON_TEXT_TOKEN = 'text_token';

    public const REASON_AUTHOR_TOKEN = 'author_token';

    public const REASON_NO_CYRILLIC = 'no_cyrillic';

    public const REASON_EMAIL_DOTS = 'email_dots';

    public const REASON_FIRST_TIME = 'first_time';

    public const REASON_LINKS = 'links';

    public const REASON_BRAND = 'brand_match';

    public const REASON_KEYWORDS = 'spam_keywords';

    public const REASON_TLD = 'suspicious_tld';

    public const REASON_HTML_ANCHOR = 'html_anchor';

    public const REASON_LATIN_LINK = 'latin_link';

    public const WEIGHT_TEXT_TOKEN = 3;

    public const WEIGHT_AUTHOR_TOKEN = 2;

    public const WEIGHT_NO_CYRILLIC = 1;

    public const WEIGHT_EMAIL_DOTS = 1;

    public const WEIGHT_FIRST_TIME = 1;

    public const WEIGHT_LINK_STEP = 1;

    public const WEIGHT_BRAND = 2;

    public const WEIGHT_KEYWORD_ONE = 1;

    public const WEIGHT_KEYWORD_MANY = 2;

    public const WEIGHT_TLD = 1;

    public const WEIGHT_HTML_ANCHOR = 2;

    public const WEIGHT_LATIN_LINK = 3;

    /**
     * Bots emit random letter-only tokens of 16+ chars. The bound is high
     * enough to keep real words like "PrivetKakDela" out of the rule.
     */
    public const MIN_TOKEN_LENGTH = 16;

    /**
     * A reader may drop a link to a related article for free. Every link
     * beyond the first adds a weight step, marketing spam carries several.
     */
    public const FREE_LINKS = 1;

    public const MAX_LINK_WEIGHT = 3;

    /**
     * Well read top level domains; anything else on a linked or email domain
     * is a mark of a bulk registered domain.
     */
    private const TLD_ALTERNATION = 'ru|su|com|net|org|io|me|info|biz|ua|kz|by|xyz|top|online|site|fun|click|club|vip|icu|buzz|loan|loans|ltd|shop|link';

    /**
     * Top level domains that SEO spam farms register in bulk.
     */
    private const SUSPICIOUS_TLDS = [
        'fun', 'xyz', 'top', 'online', 'site', 'click', 'club', 'vip', 'icu',
        'buzz', 'loan', 'loans', 'ltd', 'shop', 'link',
    ];

    /**
     * Stem-like patterns of the niches bulk comment spam advertises. Matched
     * with a left word boundary only, so Russian word forms still hit.
     */
    private const SPAM_KEYWORDS = [
        'займ', 'микрозайм', 'мфо', 'кредит', 'микрокредит',
        'казино', 'casino', 'джекпот', 'букмекер', 'ставки на спорт',
        'порно', 'porn', 'xxx', 'viagra', 'cialis',
        'аптека', 'генерик', 'без рецепта',
    ];

    /**
     * An author name shorter than this proves nothing when it meets a domain.
     */
    private const MIN_BRAND_LENGTH = 5;

    private const EMAIL_DOTS_LIMIT = 3;

    /**
     * @return array{score: int, reasons: string[]}
     */
    public static function score(string $content, string $author, string $email, bool $isFirstVisit, string $authorUrl = ''): array
    {
        $reasons = [];
        $score = 0;
        $text = self::plainText($content);
        $urls = self::extractUrls($content, $authorUrl);

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

        if (self::linkWeight(count($urls)) > 0) {
            $score += self::linkWeight(count($urls));
            $reasons[] = self::REASON_LINKS;
        }

        $hosts = array_values(array_filter(array_map(self::hostOf(...), $urls)));

        if (self::isBrandMatch($author, trim($email), $hosts)) {
            $score += self::WEIGHT_BRAND;
            $reasons[] = self::REASON_BRAND;
        }

        $keywords = self::matchedSpamKeywords($text);

        if ($keywords > 0) {
            $weight = $keywords >= 2 ? self::WEIGHT_KEYWORD_MANY : self::WEIGHT_KEYWORD_ONE;
            $score += $weight;
            $reasons[] = self::REASON_KEYWORDS;
        }

        if (self::hasSuspiciousTld($email, $hosts)) {
            $score += self::WEIGHT_TLD;
            $reasons[] = self::REASON_TLD;
        }

        // HTML anchor links (<a href="...">) in comments from readers are typical for SEO spam
        if (self::hasHtmlAnchor($content)) {
            $score += self::WEIGHT_HTML_ANCHOR;
            $reasons[] = self::REASON_HTML_ANCHOR;
        }

        // If the comment has links and no Cyrillic text, check if it's translit or foreign link spam
        if (! empty($urls) && ! self::hasCyrillic($text)) {
            if (! self::looksLikeRussianTranslit($text)) {
                $score += self::WEIGHT_LATIN_LINK;
                $reasons[] = self::REASON_LATIN_LINK;
            }
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

    /**
     * Checks if HTML contains an anchor tag <a ... href=...>.
     */
    public static function hasHtmlAnchor(string $content): bool
    {
        return preg_match('/<a\s+[^>]*href=["\'][^"\']+["\'][^>]*>/i', $content) === 1;
    }

    /**
     * Checks if Latin text looks like transliterated Russian text.
     * Looks for common transliteration character combinations and frequent Russian translit words.
     */
    public static function looksLikeRussianTranslit(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        // Common translit letter combinations (shch, ch, sh, zh, th, ya, yu, etc.)
        $translitPatterns = [
            '/(?:shch|sch|ch|sh|zh|kh|ts|yu|ya|yo|je|ja|ju|iy|yy)/i',
        ];

        // Common Russian words in translit (privet, spasibo, krasivo, rabotaet, pochemu, etc.)
        $commonTranslitWords = [
            'privet', 'spasibo', 'pozhaluysta', 'pozhalujsta', 'pomoglo', 'rabotaet', 'ne', 'kak',
            'chto', 'zachem', 'pochemu', 'kogda', 'gde', 'kuda', 'otkuda', 'vse', 'ochen', 'horosho',
            'statya', 'statia', 'avtor', 'avtoru', 'klass', 'super', 'kruto', 'dlya', 'menya', 'tebya',
            'esli', 'tolko', 'prosto', 'mozhno', 'nado', 'nuzhno', 'est', 'net', 'budet', 'byl',
        ];

        $wordPattern = '/\b(?:'.implode('|', $commonTranslitWords).')\b/i';
        if (preg_match($wordPattern, $normalized) === 1) {
            return true;
        }

        $translitHits = 0;
        foreach ($translitPatterns as $pattern) {
            if (preg_match_all($pattern, $normalized, $matches)) {
                $translitHits += count($matches[0]);
            }
        }

        $words = preg_split('/\s+/u', $normalized);
        $wordCount = is_array($words) ? count($words) : 0;

        // If at least 2 distinct translit combinations appear in a short comment, or proportional to length
        return $translitHits >= 2 || ($wordCount <= 3 && $translitHits >= 1);
    }

    /**
     * Every distinct URL in the comment: explicit links, www links, and bare
     * domains of well known or bulk registered top level domains, plus the
     * author website field.
     *
     * Runs on the raw content because strip_tags() drops href attributes.
     *
     * @return string[]
     */
    public static function extractUrls(string $rawContent, string $authorUrl = ''): array
    {
        $urls = [];

        if (preg_match_all('/(?:https?:\/\/|www\.)[^\s<>"\']+/iu', $rawContent, $matches)) {
            $urls = array_map(fn ($url) => rtrim((string) $url, '),.;]'), $matches[0]);
        }

        // Remove matched URLs before the bare domain scan, otherwise bare
        // domains inside full URLs would be counted twice.
        $rest = preg_replace('/(?:https?:\/\/|www\.)[^\s<>"\']+/iu', ' ', $rawContent);

        if (is_string($rest) && preg_match_all(
            '/(?<![\p{L}\p{N}@.\-])[a-z0-9][a-z0-9\-]*\.(?:'.self::TLD_ALTERNATION.')(?![\p{L}\p{N}@])/i',
            $rest,
            $bare
        )) {
            $urls = array_merge($urls, $bare[0]);
        }

        if (trim($authorUrl) !== '') {
            $urls[] = $authorUrl;
        }

        return array_values(array_unique($urls));
    }

    /**
     * A comment may hold the first link for free. Extra links add steps.
     */
    public static function linkWeight(int $linkCount): int
    {
        return min(max(0, $linkCount - self::FREE_LINKS), self::MAX_LINK_WEIGHT) * self::WEIGHT_LINK_STEP;
    }

    public static function hostOf(string $url): string
    {
        $host = preg_replace('#^\w+://#i', '', trim($url, '\'"<>')) ?? $url;
        $host = preg_split('%[/?#<>"\']%', (string) $host)[0] ?? '';

        $host = strtolower(rtrim((string) $host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public static function emailDomain(string $email): string
    {
        $email = trim($email);
        $position = strrpos($email, '@');

        return $position === false ? '' : strtolower(substr($email, $position + 1));
    }

    /**
     * The name part of a host: the host without its top level domain and
     * separators. "rus-finer.ru" becomes "rusfiner".
     */
    public static function domainCore(string $host): string
    {
        if ($host === '' || ! str_contains($host, '.')) {
            return '';
        }

        $withoutTld = substr($host, 0, (int) strrpos($host, '.'));

        return self::alnumLetters($withoutTld);
    }

    /**
     * Author name, email local part and linked domain names that share a word
     * reveal the classic SEO spam comment: the author IS the promoted brand.
     */
    public static function isBrandMatch(string $author, string $email, array $hosts): bool
    {
        $authorNorm = self::alnumLetters($author);
        $emailNorm = self::alnumLetters(preg_replace('/[\d_.-]+$/', '', self::localPart($email)));

        foreach ($hosts as $host) {
            $core = self::domainCore($host);

            if (mb_strlen($core) < self::MIN_BRAND_LENGTH) {
                continue;
            }

            foreach ([$authorNorm, $emailNorm] as $word) {
                if (mb_strlen($word) < self::MIN_BRAND_LENGTH) {
                    continue;
                }

                if (str_starts_with($word, $core) || str_starts_with($core, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return int number of distinct spam keywords found in the text
     */
    public static function matchedSpamKeywords(string $text): int
    {
        $text = mb_strtolower($text);
        $found = 0;

        foreach (self::SPAM_KEYWORDS as $keyword) {
            $pattern = '/(?<!\p{L})'.preg_quote($keyword, '/').'/iu';

            if (preg_match($pattern, $text) === 1) {
                $found++;
            }
        }

        return $found;
    }

    public static function isSuspiciousTld(string $tld): bool
    {
        return in_array(strtolower($tld), self::SUSPICIOUS_TLDS, true);
    }

    public static function hasSuspiciousTld(string $email, array $hosts): bool
    {
        $emailTld = self::tldOf(self::emailDomain($email));

        if (self::isSuspiciousTld($emailTld)) {
            return true;
        }

        foreach ($hosts as $host) {
            if (self::isSuspiciousTld(self::tldOf($host))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercase letters and digits of any script, separators dropped.
     */
    private static function alnumLetters(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($value)) ?? '';
    }

    private static function tldOf(string $host): string
    {
        $position = strrpos($host, '.');

        return $position === false ? '' : substr($host, $position + 1);
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
