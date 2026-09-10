<?php

declare(strict_types=1);

final class RecruitmentContentPolicy
{
    public static function requiredText(mixed $value, string $label, int $minimum, int $maximum): string
    {
        $text = trim((string)$value);
        $length = mb_strlen($text, 'UTF-8');
        if ($length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException("{$label} must contain {$minimum} to {$maximum} characters.");
        }
        return $text;
    }

    public static function optionalText(mixed $value, string $label, int $maximum): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $maximum) {
            throw new InvalidArgumentException("{$label} must not exceed {$maximum} characters.");
        }
        return $text;
    }

    public static function oneOf(mixed $value, string $label, array $allowed): string
    {
        $selected = trim((string)$value);
        if (!in_array($selected, $allowed, true)) {
            throw new InvalidArgumentException("Select a supported {$label}.");
        }
        return $selected;
    }

    public static function positiveInteger(mixed $value, string $label, int $maximum): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("{$label} must be a whole number.");
        }
        $number = (int)$value;
        if ($number < 1 || $number > $maximum) {
            throw new InvalidArgumentException("{$label} must be between 1 and {$maximum}.");
        }
        return $number;
    }

    public static function date(mixed $value, string $label, bool $required = false): ?string
    {
        $text = trim((string)$value);
        if ($text === '' && !$required) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException("Enter a valid {$label}.");
        }
        return $text;
    }

    public static function utcDateTime(mixed $value, string $label): string
    {
        $text = trim((string)$value);
        try {
            $date = new DateTimeImmutable($text, new DateTimeZone('UTC'));
        } catch (Throwable) {
            throw new InvalidArgumentException("Enter a valid {$label}.");
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function plainTextHtml(mixed $value, string $label, int $minimum, int $maximum): string
    {
        $text = self::requiredText($value, $label, $minimum, $maximum);
        $paragraphs = preg_split('/\R{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $html[] = '<p>' . nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
        }
        return implode('', $html);
    }

    public static function publicSlug(mixed $value): string
    {
        $slug = strtolower(trim((string)$value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '' || strlen($slug) > 190) {
            throw new InvalidArgumentException('Enter a valid public job slug.');
        }
        return $slug;
    }

    public static function canonicalJson(array $value): string
    {
        self::sortRecursively($value);
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function sortRecursively(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursively($item);
            }
        }
        unset($item);
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
    }
}
