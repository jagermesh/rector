<?php

declare (strict_types=1);
namespace Rector\Skipper\FileSystem;

use RectorPrefix202306\Nette\Utils\Strings;
use Rector\Skipper\Enum\AsteriskMatch;
/**
 * @see \Rector\Tests\Skipper\FileSystem\FnMatchPathNormalizerTest
 */
final class FnMatchPathNormalizer
{
    public function normalizeForFnmatch(string $path) : string
    {
        if (\substr_compare($path, '*', -\strlen('*')) === 0 || \strncmp($path, '*', \strlen('*')) === 0) {
            return '*' . \trim($path, '*') . '*';
        }
        if (\strpos($path, '..') !== \false) {
            $path = \realpath($path);
            if ($path === \false) {
                return '';
            }
        }
        return $path;
    }
}
