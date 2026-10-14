<?php

declare(strict_types=1);

namespace Orchestra\Library\Part;

/**
 * Rules for part names: required, at most 191 characters, unique within a piece (ignoring case and
 * surrounding whitespace, as the database does).
 */
final class PartNames
{
    public const MAX_LENGTH = 191;

    public static function normalize(string $name): string
    {
        return \trim((string) \preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * @param list<string> $names All part names a piece would have, already normalized
     * @return list<string> Error messages; empty if the names are valid
     */
    public static function validate(array $names): array
    {
        $errors = [];
        $seen = [];

        foreach ($names as $name) {
            if ($name === '') {
                $errors[] = \__('Part names must not be empty.', 'orchestra-library');
                continue;
            }
            if (\mb_strlen($name) > self::MAX_LENGTH) {
                /* translators: 1: part name, 2: maximum number of characters */
                $message = \__('The part name "%1$s" is longer than %2$d characters.', 'orchestra-library');
                $errors[] = \sprintf($message, $name, self::MAX_LENGTH);
                continue;
            }

            $key = \mb_strtolower($name);
            if (isset($seen[$key])) {
                /* translators: %s: part name */
                $errors[] = \sprintf(\__('The part name "%s" is used more than once.', 'orchestra-library'), $name);
            }
            $seen[$key] = true;
        }

        return \array_values(\array_unique($errors));
    }
}
